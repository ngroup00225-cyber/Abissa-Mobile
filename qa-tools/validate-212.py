"""Syntax/structure checks; does not pretend to compile Swift on Windows."""
from pathlib import Path
import subprocess,json,plistlib,xml.etree.ElementTree as ET,sys
root=Path(__file__).resolve().parents[1];checks=[]
php=Path('C:/xampp/php/windowsXamppPhp/php.exe')
files=list((root/'deploy-cpanel/api').glob('*.php'))+list((root/'website-v2').rglob('*.php'))
for f in files:
    p=subprocess.run([str(php),'-l',str(f)],capture_output=True);assert p.returncode==0,(f.name,p.stderr.decode('utf-8','replace'))
checks.append(f'PHP syntax: {len(files)} API/site files')
js=list((root/'deploy-cpanel').glob('*.js'))
for f in js:
    p=subprocess.run(['node','--check',str(f)],capture_output=True);assert p.returncode==0,(f.name,p.stderr.decode('utf-8','replace'))
checks.append(f'JavaScript syntax: {len(js)} shared interface files')
for f in (root/'ios').rglob('*.plist'):plistlib.loads(f.read_bytes())
for f in (root/'ios').rglob('*.xcprivacy'):plistlib.loads(f.read_bytes())
info=plistlib.loads((root/'ios/AbissaMobile/Info.plist').read_bytes())
assert all(info.get(k) for k in ['NSCameraUsageDescription','NSPhotoLibraryUsageDescription','NSLocationWhenInUseUsageDescription'])
assert info['NSAppTransportSecurity']['NSAllowsArbitraryLoads'] is False
ent=plistlib.loads((root/'ios/AbissaMobile/AbissaMobile.entitlements').read_bytes())
assert ent['aps-environment']=='$(APNS_ENVIRONMENT)' and 'applinks:abissamobile.com' in ent['com.apple.developer.associated-domains']
for p in ['ios/AbissaMobile/LaunchScreen.storyboard','ios/AbissaMobile.xcodeproj/xcshareddata/xcschemes/AbissaMobile.xcscheme','app/src/main/AndroidManifest.xml']:ET.parse(root/p)
config=(root/'ios/Config/Shared.xcconfig').read_text();assert 'ci.abissa' in config and 'CURRENT_PROJECT_VERSION = 30' in config and 'IPHONEOS_DEPLOYMENT_TARGET = 15.0' in config
project=(root/'ios/AbissaMobile.xcodeproj/project.pbxproj').read_text();assert 'NativeCompanion.swift' in project and '212000000000000000000002' in project
assert not list((root/'ios/AbissaMobile/Web').rglob('*.php'))
checks.append('iOS plists, privacy, capabilities, version/build, Swift membership, launch screen and shared scheme validated; Xcode build pending')
manifest=(root/'app/src/main/AndroidManifest.xml').read_text();assert 'com.abissa.mobile' in manifest and 'READ_EXTERNAL_STORAGE' not in manifest and 'WRITE_EXTERNAL_STORAGE' not in manifest
assert (root/'app/libs/zxing-core-3.5.3.jar').is_file()
assert 'applicationId \"ci.abissa\"' in (root/'app/build.gradle').read_text()
checks.append('Android package/build, native scanner library and permission declarations validated')
for p in ['SIGNATURE_ANDROID_IMPORTANTE.md','GOOGLE_PLAY_SIGNATURE_SETUP.md','PRIVACY_STORE_DECLARATIONS.md','GITHUB_CODEMAGIC_IMPORT_212.md']:assert (root/p).is_file()
checks.append('Signature, store privacy and GitHub/Codemagic owner instructions present')
report={'checks':checks,'errors':[],'iosBuildVerified':False,'physicalDeviceVerified':False}
(root/'qa-tools/test-212-syntax-results.json').write_text(json.dumps(report,ensure_ascii=False,indent=2),encoding='utf-8')
print(json.dumps(report,ensure_ascii=True))
