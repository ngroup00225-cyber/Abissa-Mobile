"""Deliver public sources and APK; keep signing material and production storage private."""
from pathlib import Path
import hashlib,json,re,shutil,subprocess,sys,tempfile,zipfile
sys.stdout.reconfigure(encoding='utf-8')
root=Path(__file__).resolve().parents[1]
delivery=root/'00 - ABISSA FINAL/Abissa-Mobile-v2.1.2-preparation-2026-10-07'
delivery.mkdir(parents=True,exist_ok=True)
def digest(p):return hashlib.sha256(p.read_bytes()).hexdigest()
def allowed(p):
    parts=p.relative_to(root).parts
    if any(x in {'.git','.gradle','.release-signing','node_modules','__pycache__','build','data','uploads','xcuserdata','simulator-qa-212'} for x in parts):return False
    if p.name in {'config.php','local.properties','Signing.local.xcconfig','ExportOptions.local.plist','.DS_Store'}:return False
    if p.suffix.lower() in {'.jks','.keystore','.dpapi','.p12','.mobileprovision','.apk','.aab','.ipa','.idsig','.log','.jsonl','.pem','.key'}:return False
    return True
files=set()
for folder in ['app/src','app/libs','ios','gradle','deploy-cpanel','website-v2','DOCUMENTATION_IOS']:
    files.update(p for p in (root/folder).rglob('*') if p.is_file() and allowed(p))
for name in ['.gitignore','build.gradle','settings.gradle','gradle.properties','gradlew','gradlew.bat','codemagic.yaml','app/build.gradle','app/proguard-rules.pro']:
    files.add(root/name)
files.update(root.glob('*.md'))
qa=['build-android-212.py','build-playstore-212.py','bundle-mobile.py','verify-signature-212.py','recover-upload-password.ps1','validate-212.py','test-account-deletion-212.py','test-native-212-ui.cjs','test-apk-212-package.py','test-android-runtime-212.py','test-ios-simulator-212.py','qa_http.py','qa-api-config.php','signature-212-results.json','test-212-syntax-results.json','test-account-deletion-212-results.json','test-native-212-ui-results.json','test-apk-212-package-results.json','test-unsigned-aab-212-results.json','test-playstore-209-results.json','test-community-regression-211-results.json','bundled-media-212.json','update-212-before-package.txt','package-212.py']
files.update(root/'qa-tools'/n for n in qa)
for folder in ['qa-tools/profile-212-captures','qa-tools/android-runtime-212','store-assets/well-known-212']:
    files.update(p for p in (root/folder).rglob('*') if p.is_file() and p.suffix in {'.png','.json'})
for name in ['historical-android-certificate.der','abissa-upload-certificate.der','abissa-mobile-playstore-512.png']:
    files.add(root/'store-assets'/name)
for p in files:
    assert p.is_file(),p
    assert allowed(p),p
    if p.suffix in {'.php','.js','.json','.py','.ps1','.md','.swift','.html','.yaml','.xcconfig','.plist'}:
        assert not re.search(rb'-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----|github_pat_[A-Za-z0-9_]{20,}|ghp_[A-Za-z0-9]{30,}|sk-proj-[A-Za-z0-9_-]{30,}',p.read_bytes()),p
source=delivery/'Abissa-Mobile-v2.1.2-SOURCES-GITHUB-CODEMAGIC.zip'
with zipfile.ZipFile(source,'w',zipfile.ZIP_DEFLATED,compresslevel=6) as z:
    for p in sorted(files):z.write(p,p.relative_to(root).as_posix())
with zipfile.ZipFile(source) as z:
    assert z.testzip() is None
    assert len(z.namelist())==len(set(z.namelist()))
    for name in ['app/libs/zxing-core-3.5.3.jar','ios/AbissaMobile.xcodeproj/project.pbxproj','deploy-cpanel/release-212.js','codemagic.yaml']:
        assert name in z.namelist(),name
    with tempfile.TemporaryDirectory(prefix='source-check-212-',dir=root/'qa-tools') as tmp:
        z.extractall(tmp)
        p=subprocess.run([sys.executable,str(Path(tmp)/'qa-tools/bundle-mobile.py')],capture_output=True,timeout=180)
        assert p.returncode==0,p.stderr.decode('utf-8','replace')
        for path in ['app/src/main/assets/abissa_mobile.html','ios/AbissaMobile/Web/abissa_mobile.html']:
            assert (Path(tmp)/path).read_bytes()==(root/path).read_bytes(),path
apk=root/'Abissa-Mobile-v2.1.2-release.apk'
shutil.copy2(apk,delivery/apk.name)
for target in [root/'Abissa-Mobile-v2.1.2.apk',root/'Abissa-Mobile.apk',root/'website-v2/Abissa-Mobile.apk']:
    shutil.copy2(apk,target)
    if target.with_suffix(target.suffix+'.idsig').exists():
        shutil.copy2(apk.with_suffix(apk.suffix+'.idsig'),target.with_suffix(target.suffix+'.idsig'))
for name in ['SIGNATURE_ANDROID_IMPORTANTE.md','GOOGLE_PLAY_SIGNATURE_SETUP.md','PRIVACY_STORE_DECLARATIONS.md','RAPPORT_PUBLICATION_212.md','GITHUB_CODEMAGIC_IMPORT_212.md']:
    shutil.copy2(root/name,delivery/name)
patch=delivery/'Abissa-Mobile-v2.1.2-SITE-A-HEBERGER.zip'
site=[p for p in files if p.relative_to(root).parts[0] in {'deploy-cpanel','website-v2'}]
with zipfile.ZipFile(patch,'w',zipfile.ZIP_DEFLATED,compresslevel=6) as z:
    z.write(apk,'SITE-A-HEBERGER/Abissa-Mobile.apk')
    for p in sorted(site):
        rel=p.relative_to(root);prefix='SITE-A-HEBERGER/appli' if rel.parts[0]=='deploy-cpanel' else 'SITE-A-HEBERGER'
        z.write(p,prefix+'/'+Path(*rel.parts[1:]).as_posix())
with zipfile.ZipFile(patch) as z:assert z.testzip() is None
report={'version':'2.1.2','build':24,'sourceFiles':len(files),'checks':['Public ZIP CRCs and unique paths verified','Android and iOS HTML reproduced byte-for-byte from extracted sources','Scanner dependency and Codemagic project included','Private signing files, server config and production storage excluded','APK copy has identical SHA-256','Site patch ZIP CRCs verified'],'errors':[],'publicationReady':False,'signedAabAvailable':False,'iosArchiveAvailable':False,'artifacts':[]}
for p in [source,delivery/apk.name,patch]:
    report['artifacts'].append({'name':p.name,'bytes':p.stat().st_size,'sha256':digest(p)})
assert digest(apk)==digest(delivery/apk.name)
(delivery/'MANIFESTE-212.json').write_text(json.dumps(report,ensure_ascii=False,indent=2),encoding='utf-8')
(root/'qa-tools/package-212-results.json').write_text(json.dumps(report,ensure_ascii=False,indent=2),encoding='utf-8')
print(json.dumps(report,ensure_ascii=False,indent=2))
