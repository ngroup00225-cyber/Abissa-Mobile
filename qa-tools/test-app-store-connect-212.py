"""Local configuration/logic checks. Apple queries and signed IPA remain CI-only."""
from pathlib import Path
import hashlib,importlib.util,json,plistlib,re,sys,tempfile,unittest,urllib.request
from unittest.mock import patch

ROOT=Path(__file__).resolve().parents[1]
sys.path.insert(0,str(ROOT/'qa-tools/apple-connect-deps'))
import yaml,jsonschema

def load(name,path):
    spec=importlib.util.spec_from_file_location(name,ROOT/path);module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module);return module
prepare=load('prepare_apple_build','ios/scripts/prepare-app-store-build.py')
verify=load('verify_apple_ipa','ios/scripts/verify-app-store-ipa.py')
baseline=ROOT/'qa-tools/apple-connect-baseline'

def official_schema():
    path=baseline/'codemagic-schema.json'
    if not path.is_file():
        baseline.mkdir(parents=True,exist_ok=True)
        with urllib.request.urlopen('https://codemagic.io/codemagic-schema.json',timeout=30) as response:
            path.write_bytes(response.read())
    return json.loads(path.read_text(encoding='utf-8'))

class ConfigurationChecks(unittest.TestCase):
    def test_yaml_schema_and_preserved_validation(self):
        new=yaml.safe_load((ROOT/'codemagic.yaml').read_text(encoding='utf-8'))
        jsonschema.validate(new,official_schema())
        validation=json.dumps(new['workflows']['ios-212-validation'],sort_keys=True).encode()
        self.assertEqual(hashlib.sha256(validation).hexdigest(),'c2daa0187512948c014b3f3d5750517f7c6ee73f48f750e076bb3f1a2155feba')
        archive=new['workflows']['ios-212-archive']
        self.assertEqual(archive['integrations']['app_store_connect'],'Abissa-Mobile-Apple')
        self.assertEqual(archive['environment'],{'xcode':'latest','ios_signing':{'distribution_type':'app_store','bundle_identifier':'ci.abissa'}})
        self.assertEqual(archive['publishing']['app_store_connect'],{'auth':'integration','submit_to_testflight':False,'submit_to_app_store':False})
        for script in ['python3 qa-tools/bundle-mobile.py','xcode-project use-profiles','xcode-project build-ipa --project ios/AbissaMobile.xcodeproj --scheme AbissaMobile\n']:
            self.assertIn(script,[s['script'] for s in archive['scripts']])
        self.assertTrue(set(['build/ios/ipa/*.ipa','/tmp/xcodebuild_logs/*.log','~/Library/Developer/Xcode/DerivedData/**/Build/**/*.dSYM']).issubset(archive['artifacts']))
        self.assertNotIn('triggering',archive)

    def test_versions_entitlements_and_android_unchanged(self):
        text=(ROOT/'ios/Config/Shared.xcconfig').read_text(encoding='utf-8')
        self.assertIn('MARKETING_VERSION = 2.1.2',text);self.assertIn('CURRENT_PROJECT_VERSION = 25',text);self.assertIn('DEVELOPMENT_TEAM = XY97926U94',text)
        info=plistlib.loads((ROOT/'ios/AbissaMobile/Info.plist').read_bytes())
        self.assertEqual(info['CFBundleShortVersionString'],'$(MARKETING_VERSION)');self.assertEqual(info['CFBundleVersion'],'$(CURRENT_PROJECT_VERSION)')
        ent=plistlib.loads((ROOT/'ios/AbissaMobile/AbissaMobile.entitlements').read_bytes())
        self.assertEqual(ent['aps-environment'],'$(APNS_ENVIRONMENT)');self.assertIn('applinks:abissamobile.com',ent['com.apple.developer.associated-domains'])
        self.assertEqual(hashlib.sha256((ROOT/'app/build.gradle').read_bytes()).hexdigest(),'42a51347a04608ef8f6c6227f287ac6c00fc19fd1c22bda62f1b00b409659002')
        self.assertIn('*.p8',(ROOT/'.gitignore').read_text())

class BuildSelectionChecks(unittest.TestCase):
    def test_app_lookup_matches_exact_bundle(self):
        resource={'id':'1234567890','attributes':{'bundleId':'ci.abissa'}}
        self.assertEqual(prepare.app_identifier(json.dumps([resource])),'1234567890')
        self.assertEqual(prepare.app_identifier(json.dumps({'data':[resource]})),'1234567890')
        for data in [[],[resource,resource],[{'id':'12','attributes':{'bundleId':'ci.other'}}]]:
            with self.assertRaises(ValueError):prepare.app_identifier(json.dumps(data))

    def test_older_versions_and_ci_retries_get_a_higher_build(self):
        self.assertEqual(prepare.next_build('0',25,'0'),25)
        self.assertEqual(prepare.next_build('24',25,'1'),26)
        self.assertEqual(prepare.next_build('96',25,'2'),97)
        self.assertEqual(prepare.next_build('96.2.1',25,'2'),97)
        self.assertEqual(prepare.next_build('0',25,'100'),125)
        self.assertGreater(prepare.next_build('125',25,'101'),prepare.next_build('0',25,'100'))
        for value in ['', 'unknown', '24 trailing output']:
            with self.assertRaises(ValueError):prepare.next_build(value,25,'1')
        with self.assertRaises(ValueError):prepare.next_build('24',25,'')

    def test_failed_apple_query_does_not_modify_version(self):
        with tempfile.TemporaryDirectory(dir=ROOT/'qa-tools',prefix='apple-build-test-') as temporary:
            root=Path(temporary);config=root/'ios/Config/Shared.xcconfig';config.parent.mkdir(parents=True)
            original=(ROOT/'ios/Config/Shared.xcconfig').read_bytes();config.write_bytes(original)
            with patch.object(prepare,'cli',side_effect=RuntimeError('API unavailable')):
                with self.assertRaises(RuntimeError):prepare.prepare(root)
            self.assertEqual(config.read_bytes(),original);self.assertFalse((root/'ios/ci-build-metadata.json').exists())
            app=json.dumps([{'id':'1234567890','attributes':{'bundleId':'ci.abissa'}}])
            with patch.object(prepare,'cli',side_effect=[app,'80']),patch.dict(prepare.os.environ,{'BUILD_NUMBER':'2'}):
                result=prepare.prepare(root)
            self.assertEqual(result['buildNumber'],'81');self.assertEqual(result['marketingVersion'],'2.1.2');self.assertFalse(result['uploadConfirmed'])
            self.assertIn('CURRENT_PROJECT_VERSION = 81',config.read_text())

class IPAIdentityChecks(unittest.TestCase):
    def test_rejects_wrong_build_signing_or_missing_capabilities(self):
        metadata={'teamId':'XY97926U94','bundleIdentifier':'ci.abissa','marketingVersion':'2.1.2','buildNumber':'81'}
        info={'CFBundleIdentifier':'ci.abissa','CFBundleShortVersionString':'2.1.2','CFBundleVersion':'81'}
        ent={'application-identifier':'XY97926U94.ci.abissa','com.apple.developer.team-identifier':'XY97926U94','aps-environment':'production','com.apple.developer.associated-domains':['applinks:abissamobile.com'],'get-task-allow':False}
        profile={'TeamIdentifier':['XY97926U94'],'Entitlements':{'application-identifier':'XY97926U94.ci.abissa','get-task-allow':False}}
        verify.verify_identity(info,ent,profile,metadata)
        for changed in [{'CFBundleVersion':'24'},{'CFBundleIdentifier':'ci.other'},{'CFBundleShortVersionString':'1.0'}]:
            with self.assertRaises(ValueError):verify.verify_identity({**info,**changed},ent,profile,metadata)
        for changed in [{'aps-environment':'development'},{'com.apple.developer.associated-domains':[]},{'get-task-allow':True},{'application-identifier':'OTHER.ci.abissa'}]:
            with self.assertRaises(ValueError):verify.verify_identity(info,{**ent,**changed},profile,metadata)
        with self.assertRaises(ValueError):verify.verify_identity(info,ent,{**profile,'ProvisionedDevices':['test']},metadata)

if __name__=='__main__':
    suite=unittest.defaultTestLoader.loadTestsFromModule(sys.modules[__name__])
    result=unittest.TextTestRunner(verbosity=2).run(suite)
    report={'testsRun':result.testsRun,'errors':len(result.errors),'failures':len(result.failures),
            'yamlSchema':'https://codemagic.io/codemagic-schema.json','marketingVersion':'2.1.2','minimumBuild':25,
            'androidChanged':False,'realIpaVerified':False,'appleUploadConfirmed':False,'remoteCommitConfirmed':False}
    (ROOT/'qa-tools/test-app-store-connect-212-results.json').write_text(json.dumps(report,indent=2),encoding='utf-8')
    sys.exit(not result.wasSuccessful())
