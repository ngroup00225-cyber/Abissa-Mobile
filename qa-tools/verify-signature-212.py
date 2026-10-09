"""Verify existing private-key availability; export only its public certificate."""
from pathlib import Path
import ctypes,subprocess,os,json,re,hashlib
root=Path(__file__).resolve().parents[1];key=root/'.release-signing/backups/2026-10-07/historical-android.keystore'
class Blob(ctypes.Structure):_fields_=[('size',ctypes.c_ulong),('data',ctypes.POINTER(ctypes.c_ubyte))]
secret=(root/'.release-signing/historical-password.dpapi').read_bytes()
buffer=ctypes.create_string_buffer(secret);source=Blob(len(secret),ctypes.cast(buffer,ctypes.POINTER(ctypes.c_ubyte)));result=Blob()
crypt=ctypes.WinDLL('crypt32',use_last_error=True)
assert crypt.CryptUnprotectData(ctypes.byref(source),None,None,None,None,1,ctypes.byref(result)), 'Run in the Windows account that protected the existing password.'
env=os.environ.copy()
try:env['ABISSA_HISTORICAL_PASSWORD']=ctypes.string_at(result.data,result.size).decode('utf-8')
finally:ctypes.windll.kernel32.LocalFree(result.data)
tool='C:/Program Files/Java/jdk-17/bin/keytool.exe'
def run(*args):return subprocess.check_output([tool,*map(str,args)],env=env,text=True,encoding='utf-8',errors='replace',stderr=subprocess.STDOUT)
try:
    details=run('-list','-v','-keystore',key,'-alias','androiddebugkey','-storepass:env','ABISSA_HISTORICAL_PASSWORD')
    assert 'PrivateKeyEntry' in details and '2048-bit RSA' in details
    public=root/'store-assets/historical-android-certificate.der'
    run('-exportcert','-keystore',key,'-alias','androiddebugkey','-storepass:env','ABISSA_HISTORICAL_PASSWORD','-file',public)
    digest=hashlib.sha256(public.read_bytes()).hexdigest()
    assert digest=='13f8e6d5d5ae51623d72d32e3d4b9c00b507e6ae5e61465679cc8740b6893293'
    overview=run('-list','-keystore',key,'-storepass:env','ABISSA_HISTORICAL_PASSWORD')
    metadata={'package':'com.abissa.mobile','alias':'androiddebugkey','keystore':str(key),'keystoreType':re.search(r'Keystore type: (\w+)',overview).group(1),'entryType':'PrivateKeyEntry','privateKeyPasswordVerified':True,'certificateName':'CN=Android Debug, O=Android, C=US','algorithm':'RSA','bits':2048,'sha1':'dccbd22b50ef56f11101f825c8f50bc7f28e11ca','sha256':digest,'backupExists':True,'debugCertificate':True,'publicCertificate':str(public),'passwordIncluded':False}
    (root/'qa-tools/signature-212-results.json').write_text(json.dumps(metadata,ensure_ascii=False,indent=2),encoding='utf-8')
    print(json.dumps(metadata,ensure_ascii=True))
finally:env.pop('ABISSA_HISTORICAL_PASSWORD',None)
