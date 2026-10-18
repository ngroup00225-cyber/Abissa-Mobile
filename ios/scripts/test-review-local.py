"""Portable checks for the Apple review correction; no production credentials needed."""
from pathlib import Path
import hashlib, importlib.util, json, os, plistlib, re, subprocess

ROOT=Path(__file__).resolve().parents[2]
env={**os.environ,'GIT_CONFIG_GLOBAL':'NUL' if os.name=='nt' else '/dev/null','GIT_CONFIG_NOSYSTEM':'1'}
def git(*args):return subprocess.check_output(['git','-C',str(ROOT),*args],env=env)
checks=[]
def check(name,condition):
    if not condition:raise AssertionError(name)
    checks.append(name)

privacy=plistlib.loads((ROOT/'ios/AbissaMobile/PrivacyInfo.xcprivacy').read_bytes())
types={v['NSPrivacyCollectedDataType']:v for v in privacy['NSPrivacyCollectedDataTypes']}
check('Tracking disabled for all categories',not privacy['NSPrivacyTracking'] and privacy['NSPrivacyTrackingDomains']==[] and all(not v['NSPrivacyCollectedDataTypeTracking'] for v in types.values()))
check('Account, messages, support, learning and location declared',all('NSPrivacyCollectedDataType'+s in types for s in ['Name','EmailAddress','PhoneNumber','EmailsOrTextMessages','CustomerSupport','PhotosorVideos','UserID','DeviceID','GameplayContent','ProductInteraction','OtherDataTypes','PreciseLocation','CoarseLocation']))
check('Location does not carry ABISSA account identity',not types['NSPrivacyCollectedDataTypePreciseLocation']['NSPrivacyCollectedDataTypeLinked'])
info=plistlib.loads((ROOT/'ios/AbissaMobile/Info.plist').read_bytes())
check('ATS retained; no unnecessary ATT prompt',not info['NSAppTransportSecurity']['NSAllowsArbitraryLoads'] and 'NSUserTrackingUsageDescription' not in info)
spec=importlib.util.spec_from_file_location('web_validation',ROOT/'ios/scripts/validate_web_bundle.py');module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
resources=module.validate_web_bundle(ROOT/'ios/AbissaMobile/Web')
check('All embedded resources exist',resources['localReferences']>50)
html=(ROOT/'ios/AbissaMobile/Web/abissa_mobile.html').read_text(encoding='utf-8')
check('iOS login overlay bundled once',html.count('<script src="ios-review.js"></script>')==1)
check('No remote executable scripts at initial load',not re.search(r'<script\b[^>]*src=["\']https?://',html))
swift=(ROOT/'ios/AbissaMobile/AbissaViewController.swift').read_text(encoding='utf-8')
check('Startup readiness and cartography retained','ABISSA_STARTUP_READY' in swift and 'url.host == "www.openstreetmap.org"' in swift and 'url.path == "/export/embed.html"' in swift)
check('Build minimum exceeds refused build 30','CURRENT_PROJECT_VERSION = 31' in (ROOT/'ios/Config/Shared.xcconfig').read_text())
changed=git('diff','--name-only').decode().splitlines()
check('All tracked changes stay in authorized scope',all(p.startswith(('ios/','qa-tools/test-ios-','website-v2/confidentialite.php')) or p=='codemagic.yaml' for p in changed))
check('Tracked Android sources unchanged (Git line-ending normalization)',not git('diff','HEAD','--name-only','--','app').strip())
check('Shared server API unchanged',not git('diff','--name-only','--','deploy-cpanel/api').strip())
workflow=(ROOT/'codemagic.yaml').read_text(encoding='utf-8')
check('iOS workflows use isolated bundler',workflow.count('python3 ios/scripts/bundle-ios.py')==2)
check('Signing and manual review preserved',all(s in workflow for s in ['Abissa-Mobile-Apple','distribution_type: app_store','bundle_identifier: ci.abissa','submit_to_testflight: false','submit_to_app_store: false','xcode-project use-profiles']))
before=hashlib.sha256((ROOT/'ios/AbissaMobile/Web/abissa_mobile.html').read_bytes()).hexdigest()
subprocess.run([os.sys.executable,str(ROOT/'ios/scripts/bundle-ios.py')],check=True)
check('iOS bundle generation is reproducible',before==hashlib.sha256((ROOT/'ios/AbissaMobile/Web/abissa_mobile.html').read_bytes()).hexdigest())
result={'checks':checks,'passed':len(checks),'resources':resources,'nativeCompilation':False,'physicalIOS':False}
(ROOT/'ios/review-evidence/local-tests.json').write_text(json.dumps(result,indent=2)+'\n',encoding='utf-8')
print(json.dumps({'passed':len(checks),'resources':resources}))
