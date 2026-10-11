"""Portable checks; Swift/WebKit/Xcode execution is explicitly reserved for macOS CI."""
from pathlib import Path
import ast
import hashlib
import importlib.util
import json
import plistlib
import re
import sys
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[1]
BASELINE = ROOT/'qa-tools/ios-startup-baseline-20261011'
sys.path.insert(0, str(ROOT/'qa-tools/apple-connect-deps'))
import yaml
import jsonschema


def load(name, path):
    spec = importlib.util.spec_from_file_location(name, ROOT/path)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


bundle = load('ios_web_bundle_validation', 'ios/scripts/validate_web_bundle.py')
prepare = load('ios_build_preparation', 'ios/scripts/prepare-app-store-build.py')
FIXTURE = '<div id="menuGrid"></div><div id="page-accueil"></div><script>window.AbissaNative212={};window.AbissaCommunity={};</script><img src="abissa-resource://app/image.png">'


class StartupChecks(unittest.TestCase):
    def test_actual_embedded_resources(self):
        result = bundle.validate_web_bundle(ROOT/'ios/AbissaMobile/Web')
        self.assertGreater(result['files'], 100)
        self.assertGreater(result['localReferences'], 40)

    def test_missing_entry_rejected(self):
        with tempfile.TemporaryDirectory(dir=ROOT/'qa-tools') as tmp:
            with self.assertRaises(FileNotFoundError): bundle.validate_web_bundle(tmp)

    def test_missing_resource_rejected(self):
        with tempfile.TemporaryDirectory(dir=ROOT/'qa-tools') as tmp:
            folder=Path(tmp); (folder/'abissa_mobile.html').write_text(FIXTURE,encoding='utf-8')
            with self.assertRaisesRegex(ValueError,'Missing or unsafe'): bundle.validate_web_bundle(folder)

    def test_encoded_traversal_rejected(self):
        with tempfile.TemporaryDirectory(dir=ROOT/'qa-tools') as tmp:
            folder=Path(tmp); web=folder/'Web'; web.mkdir(); (folder/'outside.png').write_bytes(b'private')
            (web/'abissa_mobile.html').write_text(FIXTURE.replace('image.png','%2e%2e/outside.png'),encoding='utf-8')
            with self.assertRaisesRegex(ValueError,'Missing or unsafe'): bundle.validate_web_bundle(web)

    def test_incomplete_ui_rejected(self):
        with tempfile.TemporaryDirectory(dir=ROOT/'qa-tools') as tmp:
            folder=Path(tmp); (folder/'abissa_mobile.html').write_text('<html></html>',encoding='utf-8')
            with self.assertRaisesRegex(ValueError,'incomplete'): bundle.validate_web_bundle(folder)

    def test_build_30_floor_and_collision(self):
        record={'attributes':{'version':'29','processingState':'VALID'}}
        self.assertEqual(prepare.next_build([record],30,'1')[0],30)
        record['attributes']['version']='30'
        self.assertEqual(prepare.next_build([record],30,'1')[0],31)
        config=(ROOT/'ios/Config/Shared.xcconfig').read_text(encoding='utf-8')
        self.assertIn('CURRENT_PROJECT_VERSION = 30',config)
        self.assertIn('PRODUCT_BUNDLE_IDENTIFIER = ci.abissa',config)
        self.assertIn('DEVELOPMENT_TEAM = XY97926U94',config)

    def test_codemagic_preserved_except_added_checks(self):
        before=yaml.safe_load((BASELINE/'codemagic.yaml').read_text(encoding='utf-8'))
        after=yaml.safe_load((ROOT/'codemagic.yaml').read_text(encoding='utf-8'))
        jsonschema.validate(after,json.loads((ROOT/'qa-tools/apple-connect-baseline/codemagic-schema.json').read_text()))
        for workflow in after['workflows'].values():
            added=[s for s in workflow['scripts'] if s['name']=='Verify embedded interface and iOS resource handler']
            self.assertEqual(len(added),1)
            workflow['scripts']=[s for s in workflow['scripts'] if s not in added]
            self.assertIn('qa-tools/test-ios-resource-handler-20261011-results.json',workflow['artifacts'])
            workflow['artifacts']=[a for a in workflow['artifacts'] if a!='qa-tools/test-ios-resource-handler-20261011-results.json']
        self.assertEqual(after,before)

    def test_xcode_references_handler_once(self):
        text=(ROOT/'ios/AbissaMobile.xcodeproj/project.pbxproj').read_text(encoding='utf-8')
        self.assertEqual(text.count('path = "BundleResourceHandler.swift"'),1)
        phase=re.search(r'isa = PBXSourcesBuildPhase;.*?files = \((.*?)\)',text).group(1)
        self.assertEqual(phase.count('213000000000000000000002'),1)
        definitions=re.findall(r'^([A-F0-9]{24}) = ',text,re.M)
        self.assertEqual(len(definitions),len(set(definitions)))

    def test_plists_and_preserved_security(self):
        for name in ['Info.plist','AbissaMobile.entitlements','PrivacyInfo.xcprivacy']:
            plistlib.loads((ROOT/'ios/AbissaMobile'/name).read_bytes())
            previous=BASELINE/'ios/AbissaMobile'/name
            self.assertEqual((ROOT/'ios/AbissaMobile'/name).read_bytes(),previous.read_bytes())
        controller=(ROOT/'ios/AbissaMobile/AbissaViewController.swift').read_text(encoding='utf-8')
        old=(BASELINE/'ios/AbissaMobile/AbissaViewController.swift').read_text(encoding='utf-8')
        # Bridge, native actions, location, sharing and all navigation/download policies are unchanged.
        start='    func openContent(_ url: URL) {'
        end='\nfinal class BundleResourceHandler:'
        self.assertEqual(controller.split(start,1)[1].strip(),old.split(start,1)[1].split(end,1)[0].strip())
        self.assertIn('ABISSA_STARTUP_READY',controller)
        self.assertIn('NSURLErrorCancelled',controller)
        self.assertIn('webViewWebContentProcessDidTerminate',controller)

    def test_android_release_configuration_preserved(self):
        self.assertEqual(hashlib.sha256((ROOT/'app/build.gradle').read_bytes()).hexdigest(),
                         '7171971296eaaeb12562f981173334617fc87887fc1098b18d4c44d7863def6d')
        aab=ROOT/'00 - ABISSA FINAL/Android-2.1.2-Release-Play-2026-10-11/Abissa-Mobile-v2.1.2-build25-release.aab'
        self.assertEqual(hashlib.sha256(aab.read_bytes()).hexdigest(),
                         'a5f66be3c2917b0e9a0ccaa2a90d0b4bb5d2a201aa7d0de8327c89082ba1feb5')

    def test_python_syntax(self):
        for name in ['ios/scripts/validate_web_bundle.py','ios/scripts/verify-app-store-ipa.py',
                     'qa-tools/test-ios-resource-handler-20261011.py','qa-tools/test-ios-simulator-212.py']:
            ast.parse((ROOT/name).read_text(encoding='utf-8'),filename=name)


if __name__=='__main__':
    result=unittest.TextTestRunner(verbosity=2).run(unittest.defaultTestLoader.loadTestsFromTestCase(StartupChecks))
    report={'testsRun':result.testsRun,'errors':len(result.errors),'failures':len(result.failures),
            'embeddedWeb':bundle.validate_web_bundle(ROOT/'ios/AbissaMobile/Web'),
            'swiftTestsExecutedLocally':False,'xcodeBuildExecutedLocally':False,
            'physicalIphoneVerified':False,'appleUploadConfirmed':False}
    (ROOT/'qa-tools/test-ios-startup-local-20261011-results.json').write_text(json.dumps(report,indent=2)+'\n',encoding='utf-8')
    sys.exit(not result.wasSuccessful())
