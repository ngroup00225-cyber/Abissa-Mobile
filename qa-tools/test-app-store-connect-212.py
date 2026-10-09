"""Local configuration/logic checks. Apple queries and signed IPA remain CI-only."""
from pathlib import Path
import hashlib,importlib.util,json,plistlib,re,sys,tempfile,unittest,urllib.request
from unittest.mock import patch,Mock,MagicMock
from urllib.error import HTTPError,URLError

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
        self.assertEqual(prepare.app_identifier([resource]),'1234567890')
        for data in [[],[resource,resource],[{'id':'12','attributes':{'bundleId':'ci.other'}}]]:
            with self.assertRaises(ValueError):prepare.app_identifier(data)

    def test_older_versions_and_ci_retries_get_a_higher_build(self):
        self.assertEqual(prepare.next_build([],25,'0')[0],25)
        self.assertEqual(prepare.next_build([build('24')],25,'1')[0],26)
        self.assertEqual(prepare.next_build([build('96')],25,'2')[0],97)
        self.assertEqual(prepare.next_build([build('96.2.1')],25,'2')[0],97)
        self.assertEqual(prepare.next_build([],25,'100')[0],125)
        self.assertGreater(prepare.next_build([build('125')],25,'101')[0],prepare.next_build([],25,'100')[0])
        for value in ['', 'unknown', '24 trailing output']:
            with self.assertRaises(ValueError):prepare.next_build([build(value)],25,'1')
        with self.assertRaises(ValueError):prepare.next_build([build('24')],25,'')

    def test_failed_apple_query_does_not_modify_version(self):
        with tempfile.TemporaryDirectory(dir=ROOT/'qa-tools',prefix='apple-build-test-') as temporary:
            root=Path(temporary);config=root/'ios/Config/Shared.xcconfig';config.parent.mkdir(parents=True)
            original=(ROOT/'ios/Config/Shared.xcconfig').read_bytes();config.write_bytes(original)
            with self.assertRaises(RuntimeError):prepare.prepare(root,get_json=Mock(side_effect=RuntimeError('API unavailable')))
            self.assertEqual(config.read_bytes(),original);self.assertFalse((root/'ios/ci-build-metadata.json').exists())
            with patch.dict(prepare.os.environ,{'BUILD_NUMBER':'2'}):
                result=prepare.prepare(root,get_json=Mock(side_effect=[page([APP]),page([build('80')]),page([VERSION])]))
            self.assertEqual(result['buildNumber'],'81');self.assertEqual(result['marketingVersion'],'2.1.2');self.assertFalse(result['uploadConfirmed'])
            self.assertIn('CURRENT_PROJECT_VERSION = 81',config.read_text())

APP={'type':'apps','id':'1234567890','attributes':{'bundleId':'ci.abissa'}}
VERSION={'type':'appStoreVersions','id':'v1','attributes':{'versionString':'1.0'}}
URL=prepare.API_URL+'/builds?filter%5Bapp%5D=1234567890'

def build(number,state='VALID',identifier='build1',**attributes):
    return {'type':'builds','id':identifier,'attributes':{'version':number,'processingState':state,**attributes}}

def page(resources,next_url=None,total=None):
    result={'data':resources,'links':{'next':next_url}}
    if total is not None:result['meta']={'paging':{'total':total}}
    return result

class AppleResponseChecks(unittest.TestCase):
    def test_authenticated_empty_collection_has_explicit_initial_build(self):
        resources,count=prepare.collect_pages(URL,Mock(return_value=page([],total=0)),'builds')
        self.assertEqual(count,1);self.assertEqual(resources,[])
        self.assertEqual(prepare.next_build(resources,25,'3'),(28,None,{}))

    def test_empty_summary_that_failed_build3_is_not_an_api_empty_list(self):
        for text in ['', 'None', 'null', '0', '"24"', '24', 'build number 24', '<html>error</html>']:
            with self.subTest(text=text),self.assertRaises(ValueError):
                result=prepare.decode_json(text,'Apple response')
                prepare.collect_pages(URL,Mock(return_value=result),'builds')

    def test_all_processing_states_and_expired_builds_are_reserved(self):
        resources=[build('8','VALID','a'),build('99','PROCESSING','b'),
                   build('100','FAILED','c'),build('101','INVALID','d',expired=True)]
        number,latest,states=prepare.next_build(resources,25,'3')
        self.assertEqual((number,latest),(102,'101'));self.assertEqual(sum(states.values()),4)
        self.assertNotIn('relationships',resources[1])  # Not yet attached to a prerelease version.

    def test_numeric_comparison_ignores_order_and_handles_dotted_strings(self):
        resources=[build('9','VALID','a'),build('0096.02.01','VALID','b'),build('80','VALID','c')]
        number,latest,_=prepare.next_build(resources,25,'3')
        self.assertEqual((number,latest),(97,'0096.02.01'))
        self.assertGreater(prepare.build_key(str(number)),max(prepare.build_key(x['attributes']['version']) for x in resources))

    def test_malformed_collections_are_never_treated_as_empty(self):
        malformed=[{},[],None,{'data':None,'links':{}},{'data':'25','links':{}},
                   {'data':[]},{'data':[],'links':None},{'errors':[],'data':[],'links':{}},
                   page([],total='0'),page([],total=True),{'data':[],'links':{},'meta':None},
                   page([{'type':'builds','id':'a','attributes':None}]),
                   page([{'type':'apps','id':'a','attributes':{}}])]
        for response in malformed:
            with self.subTest(response=response),self.assertRaises(ValueError):
                prepare.collect_pages(URL,Mock(return_value=response),'builds')

    def test_null_missing_or_nonnumeric_build_numbers_stop(self):
        for value in [None,24,True,'','0','-1','1.2.3.4','1.123','10000','1e3',' 24 ','٢٤']:
            with self.subTest(value=value),self.assertRaises(ValueError):
                prepare.next_build([build(value)],25,'3')
        resource=build('24');del resource['attributes']['version']
        with self.assertRaisesRegex(ValueError,'attributes.version'):prepare.next_build([resource],25,'3')
        for state in [None,'NEW_STATE']:
            with self.assertRaisesRegex(ValueError,'processingState'):prepare.next_build([build('24',state)],25,'3')

    def test_pagination_uses_maximum_on_last_page_and_preserves_filter(self):
        next_url=prepare.API_URL+'/builds?cursor=second'
        get=Mock(side_effect=[page([build('24','VALID','a')],next_url,total=2),
                              page([build('101','PROCESSING','b')],total=2)])
        resources,count=prepare.collect_pages(URL,get,'builds')
        self.assertEqual(count,2);self.assertEqual(prepare.next_build(resources,25,'3')[0],102)
        query=prepare.parse.parse_qs(prepare.parse.urlsplit(get.call_args_list[1].args[0]).query)
        self.assertEqual(query['filter[app]'],['1234567890']);self.assertEqual(query['cursor'],['second'])

    def test_empty_first_page_with_next_is_not_absence(self):
        get=Mock(side_effect=[page([],prepare.API_URL+'/builds?cursor=second',total=1),page([build('70')],total=1)])
        resources,count=prepare.collect_pages(URL,get,'builds')
        self.assertEqual(count,2);self.assertEqual(prepare.next_build(resources,25,'3')[0],71)

    def test_pagination_rejects_partial_duplicate_cyclic_or_untrusted_pages(self):
        second=prepare.API_URL+'/builds?cursor=second'
        cases=[
            [page([],total=2)],
            [page([build('25')],second,total=2),page([build('26')],total=3)],
            [page([build('25')],second),page([build('26')])],  # Duplicate ID.
            [page([],URL)],
            [page([],'https://example.com/builds')],
            [page([],prepare.API_URL+'/apps?cursor=second')],
            [page([],prepare.API_URL+'/builds?filter%5Bapp%5D=999')],
            [page([],'')],
        ]
        for responses in cases:
            with self.subTest(responses=responses),self.assertRaises(ValueError):
                prepare.collect_pages(URL,Mock(side_effect=responses),'builds')

    def test_later_page_api_failure_aborts(self):
        get=Mock(side_effect=[page([build('24')],prepare.API_URL+'/builds?cursor=second'),RuntimeError('HTTP 403')])
        with self.assertRaisesRegex(RuntimeError,'403'):prepare.collect_pages(URL,get,'builds')

    def test_http_errors_and_network_errors_have_safe_precise_diagnostics(self):
        for status in [401,403,404,429,500]:
            opener=Mock();opener.open.side_effect=HTTPError(URL,status,'private diagnostic',{},None)
            with self.subTest(status=status),self.assertRaisesRegex(RuntimeError,'HTTP '+str(status)) as failure:
                prepare.request_json(URL,lambda:{'Authorization':'Bearer synthetic-secret'},opener)
            self.assertNotIn('private diagnostic',str(failure.exception));self.assertNotIn('synthetic-secret',str(failure.exception))
        for error in [URLError('private diagnostic'),TimeoutError()]:
            opener=Mock();opener.open.side_effect=error
            with self.assertRaisesRegex(RuntimeError,'network/TLS'):prepare.request_json(URL,lambda:{},opener)

    def test_http_200_malformed_json_is_rejected(self):
        response=MagicMock();response.__enter__.return_value=response;response.status=200
        response.read.return_value=b'{broken'
        opener=Mock();opener.open.return_value=response
        with self.assertRaisesRegex(ValueError,'valid JSON'):prepare.request_json(URL,lambda:{},opener)
        response.read.return_value=json.dumps(page([],total=0)).encode()
        self.assertEqual(prepare.request_json(URL,lambda:{},opener)['data'],[])

    def test_integration_missing_or_unusable_credentials_stop(self):
        with patch.dict(prepare.os.environ,{},clear=True):
            with self.assertRaisesRegex(RuntimeError,'APP_STORE_CONNECT_PRIVATE_KEY'):prepare.integration_value('APP_STORE_CONNECT_PRIVATE_KEY')
        with self.assertRaisesRegex(RuntimeError,'cannot authenticate'):
            prepare.request_json(URL,Mock(side_effect=ValueError('secret must not be printed')))

    def test_bad_initial_build_ci_counter_or_exhausted_integer_range_stop(self):
        for value in ['', '-1', '3x', '٣']:
            with self.assertRaises(ValueError):prepare.next_build([],25,value)
        with self.assertRaises(ValueError):prepare.next_build([],24,'3')
        with self.assertRaises(ValueError):prepare.next_build([build('9999.99.99')],25,'3')
        with self.assertRaises(ValueError):prepare.next_build([],25,'10000')

    def test_preparation_does_not_write_on_partial_or_invalid_history(self):
        responses=[
            [page([APP]),page([],total=1)],
            [page([APP]),page([build(None)])],
            [page([APP]),page([]),{'data':None,'links':{}}],
            [page([APP]),page([]),page([{'type':'appStoreVersions','id':'v1','attributes':{}}])],
        ]
        with tempfile.TemporaryDirectory(dir=ROOT/'qa-tools',prefix='apple-failure-test-') as temporary:
            root=Path(temporary);config=root/'ios/Config/Shared.xcconfig';config.parent.mkdir(parents=True)
            original=(ROOT/'ios/Config/Shared.xcconfig').read_bytes();config.write_bytes(original)
            for response in responses:
                with patch.dict(prepare.os.environ,{'BUILD_NUMBER':'3'}),self.assertRaises(ValueError):
                    prepare.prepare(root,get_json=Mock(side_effect=response))
                self.assertEqual(config.read_bytes(),original);self.assertFalse((root/'ios/ci-build-metadata.json').exists())

    def test_preparation_records_verified_empty_history_and_public_version_mismatch(self):
        with tempfile.TemporaryDirectory(dir=ROOT/'qa-tools',prefix='apple-initial-test-') as temporary:
            root=Path(temporary);config=root/'ios/Config/Shared.xcconfig';config.parent.mkdir(parents=True)
            config.write_bytes((ROOT/'ios/Config/Shared.xcconfig').read_bytes())
            with patch.dict(prepare.os.environ,{'BUILD_NUMBER':'3'}):
                result=prepare.prepare(root,get_json=Mock(side_effect=[page([APP]),page([],total=0),page([VERSION])]))
            self.assertEqual(result['buildNumber'],'28');self.assertIsNone(result['latestAppleBuildNumber'])
            self.assertTrue(result['appleHistoryComplete']);self.assertEqual(result['appleBuildCount'],0)
            self.assertFalse(result['publicVersionAligned']);self.assertEqual(result['appStoreVersions'],['1.0'])
            self.assertFalse(result['uploadConfirmed']);self.assertEqual(result['marketingVersion'],'2.1.2')

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
        for changed in [{'CFBundleVersion':81},{'CFBundleVersion':'81.0'},{'CFBundleVersion':None}]:
            with self.assertRaises(ValueError):verify.verify_identity({**info,**changed},ent,profile,metadata)
        for changed in [{'buildNumber':81},{'buildNumber':'0'},{'buildNumber':'81.0'},{'buildNumber':'10000'},
                        {'bundleIdentifier':'ci.other'},{'teamId':'OTHER'},{'marketingVersion':None}]:
            with self.assertRaises(ValueError):verify.verify_identity(info,ent,profile,{**metadata,**changed})

if __name__=='__main__':
    suite=unittest.defaultTestLoader.loadTestsFromModule(sys.modules[__name__])
    result=unittest.TextTestRunner(verbosity=2).run(suite)
    report={'testsRun':result.testsRun,'errors':len(result.errors),'failures':len(result.failures),
            'yamlSchema':'https://codemagic.io/codemagic-schema.json','marketingVersion':'2.1.2','minimumBuild':25,
            'historyFormat':'raw Apple JSON collections with validated pagination',
            'androidChanged':False,'realIpaVerified':False,'appleUploadConfirmed':False,'remoteCommitConfirmed':False}
    (ROOT/'qa-tools/test-app-store-connect-212-results.json').write_text(json.dumps(report,indent=2),encoding='utf-8')
    sys.exit(not result.wasSuccessful())
