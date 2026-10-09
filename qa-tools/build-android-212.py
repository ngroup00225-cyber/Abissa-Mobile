from pathlib import Path
import subprocess,os,zipfile
root=Path(__file__).resolve().parents[1];build=root/'manual-build-v24';build.mkdir(exist_ok=True)
# Bundle all first-launch modules without a network dependency.
import base64,re
mobile=(root/'deploy-cpanel/mobile.html').read_text(encoding='utf-8')
for script in ('request-forms.js','committee-data.js','committee-view.js','programme-data.js','programme-view.js','learning-data.js','learning-v8-data.js','learning-v8.js','learning-view.js','feedback.js','refonte-2026.js','experience-201.js','assets/leaflet/leaflet.js','evolution-203.js','refresh-204.js','release-208.js','release-209.js','badge-designer.js','community-mobile.js','release-211.js','release-212.js'):
    pattern=r'<script src="'+re.escape(script)+r'(?:\?[^"]*)?"></script>'
    mobile,count=re.subn(pattern,lambda m:'<script>'+(root/'deploy-cpanel'/script).read_text(encoding='utf-8')+'</script>',mobile)
    assert count==1,(script,count)
for sheet in ('committee-view.css','programme-view.css','learning-view.css','learning-v8.css','feedback.css','refonte-2026.css','experience-201.css','assets/leaflet/leaflet.css','evolution-203.css','palace-204.css','refresh-204.css','release-209.css','community.css','release-211.css','release-212.css'):
    pattern=r'<link rel="stylesheet" href="'+re.escape(sheet)+r'(?:\?[^"]*)?">'
    mobile,count=re.subn(pattern,lambda m:'<style>'+(root/'deploy-cpanel'/sheet).read_text(encoding='utf-8')+'</style>',mobile)
    assert count==1,(sheet,count)
sfx={p.stem:'data:audio/wav;base64,'+base64.b64encode(p.read_bytes()).decode() for p in (root/'deploy-cpanel/assets/nzema-v8/sfx').glob('*.wav')}
import json
assert len(sfx)==5
mobile=mobile.replace('</head>','<script>window.ABISSA_V8_SFX='+json.dumps(sfx)+';</script></head>')
icons=(root/'deploy-cpanel/assets/icons/remixicon.css').read_text(encoding='utf-8')
font=base64.b64encode((root/'deploy-cpanel/assets/icons/remixicon.woff2').read_bytes()).decode()
icons=re.sub(r'@font-face\s*\{.*?\}', '@font-face{font-family:remixicon;src:url(data:font/woff2;base64,'+font+') format("woff2");font-display:swap}',icons,flags=re.S)
mobile=mobile.replace('<link rel="stylesheet" href="assets/icons/remixicon.css">','<style>'+icons+'</style>')
for key,name in [('CHART','organigramme-abissa-officiel-2026.png'),('PROGRAMME','programme-officiel-2026.png'),('LOGO','logo-evenement-2026.png'),('MOBILE_LOGO','logo-abissa-mobile.png'),('BADGE_LOGO','logo-badge-default-2026.png')]:
    data='data:image/png;base64,'+base64.b64encode((root/'deploy-cpanel/assets'/name).read_bytes()).decode()
    mobile=mobile.replace('</head>','<script>window.ABISSA_OFFLINE_'+key+'="'+data+'";</script></head>')
    if key=='LOGO':mobile=mobile.replace('id="homeLogo" src="assets/logo-site-2026.png"','id="homeLogo" src="'+data+'"')
assert not re.search(r'<script src="(?:committee-|programme-|learning-|feedback|request-forms)',mobile)
# Bundle raster assets once as Android assets. The trusted WebView origin stays unchanged.
import shutil,json
refonte=root/'app/src/main/assets/refonte-2026';refonte.mkdir(exist_ok=True)
asset_map={}
for image in (root/'deploy-cpanel/assets/refonte-2026').glob('*.png'):
    if image.stem=='splash':continue
    shutil.copy2(image,refonte/image.name)
    asset_map[image.stem]='https://abissamobile.com/appli/bundled-refonte/'+image.name
    mobile=mobile.replace('assets/refonte-2026/'+image.name,asset_map[image.stem])
mobile=mobile.replace('</head>','<script>window.ABISSA_REFONTE_ASSETS='+json.dumps(asset_map)+';</script></head>')
# Keep large media out of the HTML/Java string; WebView reads these assets as streams.
import hashlib
media_dir=root/'app/src/main/assets/bundled-media';media_dir.mkdir(exist_ok=True)
mime_extensions={'image/png':'png','image/webp':'webp','audio/wav':'wav','font/woff2':'woff2'}
media_manifest={}
def externalize_media(match):
    mime,encoded=match.groups()
    if mime not in mime_extensions:return match.group(0)
    blob=base64.b64decode(encoded,validate=True)
    digest=hashlib.sha256(blob).hexdigest();name=digest+'.'+mime_extensions[mime]
    (media_dir/name).write_bytes(blob)
    url='https://abissamobile.com/appli/bundled-media/'+name
    media_manifest[name]={'mime':mime,'bytes':len(blob),'sha256':digest,'url':url}
    return url
# Stream local colour icons and decorative assets on first launch.
new_assets={}
for asset in [*(root/'deploy-cpanel/assets/fluent-color').glob('*.svg'),root/'deploy-cpanel/assets/refonte-2026/coast.png']:
    blob=asset.read_bytes();mime='image/svg+xml' if asset.suffix=='.svg' else 'image/png';digest=hashlib.sha256(blob).hexdigest();name=digest+asset.suffix
    (media_dir/name).write_bytes(blob);url='https://abissamobile.com/appli/bundled-media/'+name
    new_assets[asset.relative_to(root/'deploy-cpanel/assets').as_posix()]=url
    media_manifest[name]={'mime':mime,'bytes':len(blob),'sha256':digest,'url':url}
mobile=mobile.replace('</head>','<script>window.ABISSA_211_ASSETS='+json.dumps(new_assets)+';</script></head>')
original_bytes=len(mobile.encode('utf-8'))
mobile=re.sub(r'data:([a-zA-Z0-9.+-]+/[a-zA-Z0-9.+-]+);base64,([A-Za-z0-9+/=]+)',externalize_media,mobile)
assert len(mobile.encode('utf-8'))<3*1024*1024
(root/'qa-tools/bundled-media-212.json').write_text(json.dumps({'originalHtmlBytes':original_bytes,'htmlBytes':len(mobile.encode('utf-8')),'assets':media_manifest},indent=2),encoding='utf-8')
print('Startup HTML:',original_bytes,'->',len(mobile.encode('utf-8')),'bytes;',len(media_manifest),'streamed media assets')
(root/'app/src/main/assets/abissa_mobile.html').write_text(mobile,encoding='utf-8')
java=Path('C:/Program Files/Java/jdk-17/bin');bt=Path('C:/Android/build-tools/36.0.0');platform=Path('C:/Android/platforms/android-36/android.jar')
def run(args):
    args=[str(a.relative_to(root)) if isinstance(a,Path) and a.is_relative_to(root) else str(a) for a in args]
    subprocess.run(args,check=True)
run([bt/'aapt2.exe','compile','--dir',root/'app/src/main/res','-o',build/'resources.zip'])
run([bt/'aapt2.exe','link','-o',build/'unsigned.apk','-I',platform,'--manifest',root/'app/src/main/AndroidManifest.xml','--min-sdk-version','23','--target-sdk-version','36','--version-code','24','--version-name','2.1.2','-A',root/'app/src/main/assets',build/'resources.zip'])
(build/'classes').mkdir(exist_ok=True);(build/'dex').mkdir(exist_ok=True)
run([java/'javac.exe','-encoding','UTF-8','-source','8','-target','8','-classpath',str(platform)+os.pathsep+str(root/'app/libs/zxing-core-3.5.3.jar'),'-d',build/'classes',*sorted((root/'app/src/main/java').rglob('*.java'))])
run([java/'jar.exe','cf',build/'classes.jar','-C',build/'classes','.'])
run([java/'java.exe','-cp',bt/'lib/d8.jar','com.android.tools.r8.D8','--lib',platform,'--min-api','23','--output',build/'dex',build/'classes.jar',root/'app/libs/zxing-core-3.5.3.jar'])
with zipfile.ZipFile(build/'unsigned.apk','a') as z:z.write(build/'dex/classes.dex','classes.dex')
run([bt/'zipalign.exe','-f','4',build/'unsigned.apk',build/'aligned.apk'])
import ctypes
class Blob(ctypes.Structure):
    _fields_=[('size',ctypes.c_ulong),('data',ctypes.POINTER(ctypes.c_ubyte))]
encrypted=(root/'.release-signing/historical-password.dpapi').read_bytes()
buffer=ctypes.create_string_buffer(encrypted);src=Blob(len(encrypted),ctypes.cast(buffer,ctypes.POINTER(ctypes.c_ubyte)));dest=Blob()
if not ctypes.windll.crypt32.CryptUnprotectData(ctypes.byref(src),None,None,None,None,1,ctypes.byref(dest)):raise ctypes.WinError()
try:os.environ['ABISSA_HISTORICAL_PASSWORD']=ctypes.string_at(dest.data,dest.size).decode('utf-8')
finally:ctypes.windll.kernel32.LocalFree(dest.data)
key=root/'.release-signing/backups/2026-10-07/historical-android.keystore'
run([java/'java.exe','-jar',bt/'lib/apksigner.jar','sign','--ks',key,'--ks-key-alias','androiddebugkey','--ks-pass','env:ABISSA_HISTORICAL_PASSWORD','--key-pass','env:ABISSA_HISTORICAL_PASSWORD','--out',root/'Abissa-Mobile-v2.1.2-release.apk',build/'aligned.apk'])
result=subprocess.run([str(java/'java.exe'),'-jar',str(bt/'lib/apksigner.jar'),'verify','--verbose','--print-certs',str(root/'Abissa-Mobile-v2.1.2-release.apk')],check=True,capture_output=True,text=True)
assert '13f8e6d5d5ae51623d72d32e3d4b9c00b507e6ae5e61465679cc8740b6893293' in result.stdout,result.stdout
print(result.stdout)
os.environ.pop('ABISSA_HISTORICAL_PASSWORD',None)
