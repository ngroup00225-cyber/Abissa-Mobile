"""Build and sign a first-publication AAB from the local Android toolchain."""
from pathlib import Path
import ctypes,hashlib,json,os,re,subprocess,tempfile,zipfile,sys

ROOT=Path(__file__).resolve().parents[1];BUILD=ROOT/'manual-build-v24'
JAVA=Path('C:/Program Files/Java/jdk-17/bin');BT=Path('C:/Android/build-tools/36.0.0')
CACHE=Path.home()/'.gradle/caches/modules-2/files-2.1'
KEYS=ROOT/'.release-signing';KEYS.mkdir(exist_ok=True)
KEY=KEYS/'abissa-mobile-upload.jks';PASSWORD=KEYS/'upload-password.dpapi'
ENV=os.environ.copy();ENV['ABISSA_PASSWORD_FILE']=str(PASSWORD)
class Blob(ctypes.Structure):
    _fields_=[('size',ctypes.c_ulong),('data',ctypes.POINTER(ctypes.c_ubyte))]

def dpapi(data, protect):
    buffer=ctypes.create_string_buffer(data)
    source=Blob(len(data),ctypes.cast(buffer,ctypes.POINTER(ctypes.c_ubyte)));result=Blob()
    crypt=ctypes.WinDLL('crypt32',use_last_error=True)
    if protect:
        ok=crypt.CryptProtectData(ctypes.byref(source),'Abissa Mobile upload key',None,None,None,1,ctypes.byref(result))
    else:
        ok=crypt.CryptUnprotectData(ctypes.byref(source),None,None,None,None,1,ctypes.byref(result))
    if not ok:raise ctypes.WinError(ctypes.get_last_error())
    try:return ctypes.string_at(result.data,result.size)
    finally:ctypes.windll.kernel32.LocalFree(result.data)

def run(args,**kwargs):
    args=[str(x.relative_to(ROOT)) if isinstance(x,Path) and x.is_relative_to(ROOT) else str(x) for x in args]
    return subprocess.run(args,check=True,env=ENV,cwd=ROOT,**kwargs)

# A new upload key would break updates to the existing Play release.
assert KEY.is_file() and PASSWORD.is_file(),'Restore the existing upload key and its encrypted password; do not create a replacement.'
# Choose one version per cached dependency, with current bundletool first.
def version(path):return tuple(int(x) if x.isdigit() else x for x in re.findall(r'\d+|[A-Za-z]+',path.name))
jars=[]
for group in CACHE.iterdir():
    if not group.is_dir():continue
    for module in group.iterdir():
        if not module.is_dir():continue
        candidates=list(module.rglob('*.jar'))
        candidates=[p for p in candidates if not p.name.endswith(('-sources.jar','-javadoc.jar'))]
        if not candidates:continue
        versions=sorted({p.parent.parent for p in candidates},key=lambda p:tuple(int(x) for x in re.findall(r'\d+',p.name)),reverse=True)
        jars.extend(p for p in candidates if p.parent.parent==versions[0])
jars.sort(key=lambda p:(0 if p.name=='bundletool-1.18.1.jar' else 1 if 'aapt2-proto-8.13.1' in p.name else 2,str(p)))
classpath=os.pathsep.join(str(p).replace('\\','/') for p in jars)

def bundletool(*args,capture=False):
    # Java argument file avoids the Windows command length limit.
    argfile=BUILD/'bundletool-212.args'
    def quote(s):return '"'+str(s).replace('\\','/').replace('"','\\"')+'"'
    argfile.write_text('\n'.join([ '-cp',quote(classpath),'com.android.tools.build.bundletool.BundleToolMain',*(quote(a) for a in args)])+'\n',encoding='cp1252')
    return run([JAVA/'java.exe','@'+str(argfile)],capture_output=capture,text=capture)

run([BT/'aapt2.exe','link','--proto-format','-o',BUILD/'proto.apk','-I','C:/Android/platforms/android-36/android.jar','--manifest',ROOT/'app/src/main/AndroidManifest.xml','--min-sdk-version','23','--target-sdk-version','36','--version-code','24','--version-name','2.1.2','-A',ROOT/'app/src/main/assets',BUILD/'resources.zip'])
with zipfile.ZipFile(BUILD/'proto.apk') as source,zipfile.ZipFile(BUILD/'base.zip','w',zipfile.ZIP_DEFLATED) as module:
    for name in source.namelist():
        if name.startswith('META-INF/'):continue
        module.writestr('manifest/AndroidManifest.xml' if name=='AndroidManifest.xml' else name,source.read(name))
    module.write(BUILD/'dex/classes.dex','dex/classes.dex')
unsigned=BUILD/'Abissa-Mobile-v2.1.2-unsigned.aab';signed=ROOT/'Abissa-Mobile-v2.1.2.aab'
bundletool('build-bundle','--modules='+str(BUILD/'base.zip'),'--output='+str(unsigned),'--overwrite')
bundletool('validate','--bundle='+str(unsigned))
if '--unsigned-only' in sys.argv:
    result={'version':'2.1.2','versionCode':24,'unsignedAab':str(unsigned),'sha256':hashlib.sha256(unsigned.read_bytes()).hexdigest(),'signed':False,'storeReady':False,'reason':'Existing upload password must be restored; no replacement key generated.'}
    (ROOT/'qa-tools/test-unsigned-aab-212-results.json').write_text(json.dumps(result,indent=2),encoding='utf-8')
    print(json.dumps(result));sys.exit(0)
ENV['ABISSA_UPLOAD_PASSWORD']=ENV.get('ABISSA_UPLOAD_PASSWORD') or dpapi(PASSWORD.read_bytes(),False).decode('utf-8')
previous=json.loads((ROOT/'qa-tools/test-playstore-209-results.json').read_text(encoding='utf-8'))
with tempfile.TemporaryDirectory(dir=BUILD) as cert_dir:
    existing_cert=Path(cert_dir)/'upload.der'
    run([JAVA/'keytool.exe','-exportcert','-keystore',KEY,'-alias','abissa-upload','-storepass:env','ABISSA_UPLOAD_PASSWORD','-file',existing_cert],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
    assert hashlib.sha256(existing_cert.read_bytes()).hexdigest()==previous['uploadCertificateSha256'],'The upload certificate must match the 2.0.9 release.'
run([JAVA/'jarsigner.exe','-keystore',KEY,'-storepass:env','ABISSA_UPLOAD_PASSWORD','-keypass:env','ABISSA_UPLOAD_PASSWORD','-digestalg','SHA-256','-sigalg','SHA256withRSA','-signedjar',signed,unsigned,'abissa-upload'],stdout=subprocess.DEVNULL)
verified=run([JAVA/'jarsigner.exe','-verify','-verbose',signed],capture_output=True,text=True)
assert 'jar verified' in verified.stdout.lower(),verified.stdout[-1000:]
bundletool('validate','--bundle='+str(signed))
manifest=bundletool('dump','manifest','--bundle='+str(signed),capture=True).stdout
assert 'versionCode="24"' in manifest and 'versionName="2.1.2"' in manifest and 'targetSdkVersion="36"' in manifest and 'com.abissa.mobile' in manifest
certificate=ROOT/'store-assets/abissa-upload-certificate.der'
run([JAVA/'keytool.exe','-exportcert','-keystore',KEY,'-alias','abissa-upload','-storepass:env','ABISSA_UPLOAD_PASSWORD','-file',certificate],stdout=subprocess.DEVNULL)
with tempfile.NamedTemporaryFile(mode='w',encoding='utf-8',dir=KEYS,suffix='.private.tmp',delete=False) as secret_file:
    secret_file.write(ENV['ABISSA_UPLOAD_PASSWORD']+'\n');secret_path=Path(secret_file.name)
try:
    password_arg='file:'+secret_path.relative_to(ROOT).as_posix()
    bundletool('build-apks','--bundle='+str(signed),'--output='+str(BUILD/'playstore-test.apks'),'--mode=universal','--ks='+str(KEY),'--ks-key-alias=abissa-upload','--ks-pass='+password_arg,'--key-pass='+password_arg,'--aapt2='+str(BT/'aapt2.exe'),'--overwrite')
finally:secret_path.unlink(missing_ok=True)
with zipfile.ZipFile(BUILD/'playstore-test.apks') as apks: (BUILD/'playstore-universal.apk').write_bytes(apks.read('universal.apk'))
badging=run([BT/'aapt2.exe','dump','badging',BUILD/'playstore-universal.apk'],capture_output=True,text=True).stdout
assert "versionCode='24' versionName='2.1.2'" in badging and "targetSdkVersion:'36'" in badging
with zipfile.ZipFile(signed) as aab:
    assert aab.testzip() is None and not any(n.endswith('.so') for n in aab.namelist())
    assert aab.read('base/dex/classes.dex')==(BUILD/'dex/classes.dex').read_bytes()
    for file in (ROOT/'app/src/main/assets').rglob('*'):
        if file.is_file():assert aab.read('base/assets/'+file.relative_to(ROOT/'app/src/main/assets').as_posix())==file.read_bytes()
result={'version':'2.1.2','versionCode':24,'targetSdk':36,'package':'com.abissa.mobile','checks':['Signed release AAB validated by bundletool','Manifest/package/version/API36 verified','Existing RSA4096 upload certificate reused','JAR SHA256withRSA signature verifies','Universal test APK generated and its manifest verified','AAB CRC, exact DEX and every bundled asset match','No native libraries: no app-owned ELF alignment dependency'],'errors':[],'aab':str(signed),'aabBytes':signed.stat().st_size,'aabSha256':hashlib.sha256(signed.read_bytes()).hexdigest(),'uploadCertificateSha256':hashlib.sha256(certificate.read_bytes()).hexdigest(),'signingPasswordStorage':'Windows-user DPAPI, never included in public delivery','runtimeConfirmed':False}
(ROOT/'qa-tools/test-playstore-212-results.json').write_text(json.dumps(result,ensure_ascii=False,indent=2),encoding='utf-8')
print(json.dumps(result,ensure_ascii=False))
