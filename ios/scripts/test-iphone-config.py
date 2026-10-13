"""Portable iOS configuration checks; this is not a simulator or device test."""
from pathlib import Path
import ast
import json
import plistlib
import re
import unittest
import xml.etree.ElementTree as ET

from validate_web_bundle import validate_web_bundle

ROOT = Path(__file__).resolve().parents[2]


class IPhoneConfiguration(unittest.TestCase):
    def setUp(self):
        self.settings = dict(re.findall(r'^([A-Z_]+)\s*=\s*(.+)$',
                                       (ROOT/'ios/Config/Shared.xcconfig').read_text(), re.M))

    def test_existing_apple_identity(self):
        self.assertEqual(self.settings['PRODUCT_BUNDLE_IDENTIFIER'], 'ci.abissa')
        self.assertEqual(self.settings['DEVELOPMENT_TEAM'], 'XY97926U94')
        self.assertEqual(self.settings['MARKETING_VERSION'], '2.1.2')
        self.assertGreaterEqual(int(self.settings['CURRENT_PROJECT_VERSION']), 30)

    def test_supported_minimum_ios(self):
        self.assertEqual(self.settings['IPHONEOS_DEPLOYMENT_TARGET'], '15.0')
        self.assertIn('1', self.settings['TARGETED_DEVICE_FAMILY'].split(','))

    def test_permissions_and_transport(self):
        info = plistlib.loads((ROOT/'ios/AbissaMobile/Info.plist').read_bytes())
        for key in ('NSCameraUsageDescription', 'NSPhotoLibraryUsageDescription',
                    'NSPhotoLibraryAddUsageDescription', 'NSLocationWhenInUseUsageDescription'):
            self.assertTrue(info.get(key), key)
        self.assertFalse(info['NSAppTransportSecurity']['NSAllowsArbitraryLoads'])
        self.assertEqual(info['CFBundleIdentifier'], '$(PRODUCT_BUNDLE_IDENTIFIER)')
        self.assertIn('abissa', info['CFBundleURLTypes'][0]['CFBundleURLSchemes'])

    def test_launch_storyboard_exists(self):
        info = plistlib.loads((ROOT/'ios/AbissaMobile/Info.plist').read_bytes())
        ET.parse(ROOT/'ios/AbissaMobile'/(info['UILaunchStoryboardName']+'.storyboard'))

    def test_apple_capabilities(self):
        entitlements = plistlib.loads((ROOT/'ios/AbissaMobile/AbissaMobile.entitlements').read_bytes())
        self.assertEqual(entitlements['aps-environment'], '$(APNS_ENVIRONMENT)')
        self.assertIn('applinks:abissamobile.com', entitlements['com.apple.developer.associated-domains'])
        self.assertFalse(plistlib.loads((ROOT/'ios/AbissaMobile/PrivacyInfo.xcprivacy').read_bytes())['NSPrivacyTracking'])

    def test_shared_scheme(self):
        scheme = ET.parse(ROOT/'ios/AbissaMobile.xcodeproj/xcshareddata/xcschemes/AbissaMobile.xcscheme')
        self.assertEqual(scheme.find('ArchiveAction').get('buildConfiguration'), 'Release')
        self.assertEqual(scheme.find('LaunchAction').get('buildConfiguration'), 'Debug')

    def test_offline_bundle(self):
        self.assertGreater(validate_web_bundle(ROOT/'ios/AbissaMobile/Web')['localReferences'], 56)

    def test_ios_python_syntax(self):
        for script in (ROOT/'ios/scripts').glob('*.py'):
            ast.parse(script.read_text(encoding='utf-8'), filename=script.name)


if __name__ == '__main__':
    suite = unittest.defaultTestLoader.loadTestsFromTestCase(IPhoneConfiguration)
    result = unittest.TextTestRunner(verbosity=2).run(suite)
    folder = ROOT/'ios/iphone-validation'
    folder.mkdir(parents=True, exist_ok=True)
    (folder/'configuration.json').write_text(json.dumps({
        'tests':result.testsRun, 'failures':len(result.failures), 'errors':len(result.errors),
        'simulatorExecuted':False, 'physicalDeviceExecuted':False
    }, indent=2)+'\n', encoding='utf-8')
    raise SystemExit(not result.wasSuccessful())
