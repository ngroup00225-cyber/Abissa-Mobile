from pathlib import Path
import ast,base64,hashlib,json,re,subprocess,zipfile

root=Path(__file__).resolve().parents[1]
apk=root/'Abissa-Mobile-v2.1.2-release.apk'
bt=Path('C:/Android/build-tools/36.0.0')
java=Path('C:/Program Files/Java/jdk-17/bin/java.exe')
checks=[]
def sha(blob):return hashlib.sha256(blob).hexdigest()
def run(args):return subprocess.check_output([str(a) for a in args],text=True,encoding='utf-8',errors='replace')
def check(description):checks.append(description);print('PASS '+description)

badging=run([bt/'aapt2.exe','dump','badging',apk])
assert "name='com.abissa.mobile' versionCode='24' versionName='2.1.2'" in badging
assert re.search(r"(?m)^(?:minSdkVersion|sdkVersion):'23'$",badging) and "targetSdkVersion:'36'" in badging
assert "launchable-activity: name='com.abissa.mobile.MainActivity'" in badging
check('APK 2.1.2/code 24, package, minSdk 23/targetSdk 36 and launchable MainActivity verified with aapt2')
signatures=run([java,'-jar',bt/'lib/apksigner.jar','verify','--verbose','--print-certs',apk])
certificate=re.search(r'certificate SHA-256 digest: ([0-9a-f]{64})',signatures).group(1)
if (root/'Abissa-Mobile-v2.0.1.apk').exists():
    historical=run([java,'-jar',bt/'lib/apksigner.jar','verify','--print-certs',root/'Abissa-Mobile-v2.0.1.apk'])
    assert certificate==re.search(r'certificate SHA-256 digest: ([0-9a-f]{64})',historical).group(1)
assert certificate=='13f8e6d5d5ae51623d72d32e3d4b9c00b507e6ae5e61465679cc8740b6893293'
for scheme in ('v1','v2','v3'):assert re.search(r'Verified using '+scheme+r' scheme.*: true',signatures)
check('Historical signing certificate matches 2.0.1; v1/v2/v3 signatures verify')

manifest=json.loads((root/'qa-tools/bundled-media-212.json').read_text(encoding='utf-8'))
extensions={'image/png':'png','image/webp':'webp','audio/wav':'wav','font/woff2':'woff2'}
def streamed(match):
    mime,encoded=match.groups()
    if mime not in extensions:return match.group(0)
    blob=base64.b64decode(encoded,validate=True)
    name=sha(blob)+'.'+extensions[mime]
    assert name in manifest['assets'],name
    return 'https://abissamobile.com/appli/bundled-media/'+name
def transform(source):
    for image in (root/'deploy-cpanel/assets/refonte-2026').glob('*.png'):
        if image.stem!='splash':source=source.replace('assets/refonte-2026/'+image.name,'https://abissamobile.com/appli/bundled-refonte/'+image.name)
    return re.sub(r'data:([a-zA-Z0-9.+-]+/[a-zA-Z0-9.+-]+);base64,([A-Za-z0-9+/=]+)',streamed,source)

with zipfile.ZipFile(apk) as package:
    assert package.testzip() is None
    assert len(package.namelist())==len(set(package.namelist()))
    dex=package.read('classes.dex')
    assert dex==(root/'manual-build-v24/dex/classes.dex').read_bytes()
    check('APK ZIP CRCs, unique entries and exact compiled classes.dex verified')
    html_bytes=package.read('assets/abissa_mobile.html')
    assert html_bytes==(root/'app/src/main/assets/abissa_mobile.html').read_bytes()
    # Path.write_text uses CRLF on Windows; the builder measures its LF string.
    html=html_bytes.decode('utf-8').replace('\r\n','\n')
    assert len(html.encode('utf-8'))==manifest['htmlBytes'] and len(html_bytes)<3*1024*1024
    assert not re.search(r'<script\s+src=',html)
    assert not re.search(r'<link\s+rel="stylesheet"',html)
    check('Startup HTML matches generated source, stays below 3 MiB and has no external script/stylesheet dependency')
    # Get the explicit build input lists without executing the build.
    tree=ast.parse((root/'qa-tools/build-android-212.py').read_text(encoding='utf-8'))
    source_lists={node.target.id:ast.literal_eval(node.iter) for node in tree.body if isinstance(node,ast.For) and isinstance(node.target,ast.Name) and node.target.id in ('script','sheet')}
    for name in source_lists['script']:
        assert '<script>'+transform((root/'deploy-cpanel'/name).read_text(encoding='utf-8'))+'</script>' in html,name
    for name in source_lists['sheet']:
        assert '<style>'+transform((root/'deploy-cpanel'/name).read_text(encoding='utf-8'))+'</style>' in html,name
    check(f"All {len(source_lists['script'])} JavaScript and {len(source_lists['sheet'])} CSS sources match after media externalization")
    for name,meta in manifest['assets'].items():
        blob=package.read('assets/bundled-media/'+name)
        assert len(blob)==meta['bytes'] and sha(blob)==meta['sha256']==name.split('.')[0]
        assert blob==(root/'app/src/main/assets/bundled-media'/name).read_bytes()
        assert meta['url'] in html
    check(f"All {len(manifest['assets'])} streamed media entries match manifest sizes and binary hashes")
    asset_files=list((root/'app/src/main/assets').rglob('*'))
    asset_files=[p for p in asset_files if p.is_file()]
    for source in asset_files:
        relative=source.relative_to(root/'app/src/main/assets').as_posix()
        assert package.read('assets/'+relative)==source.read_bytes(),relative
    check(f'Every one of {len(asset_files)} Android asset files matches the signed APK')
    for name in ('organigramme-abissa-officiel-2026.png','programme-officiel-2026.png','logo-evenement-2026.png','logo-abissa-mobile.png'):
        digest=sha((root/'deploy-cpanel/assets'/name).read_bytes())
        assert digest+'.png' in manifest['assets'],name
    for sound in (root/'deploy-cpanel/assets/nzema-v8/sfx').glob('*.wav'):
        assert sha(sound.read_bytes())+'.wav' in manifest['assets'],sound.name
    font_digest=sha((root/'deploy-cpanel/assets/icons/remixicon.woff2').read_bytes())
    assert font_digest+'.woff2' in manifest['assets']
    for marker in ('ABISSA_OFFLINE_CHART','ABISSA_OFFLINE_PROGRAMME','ABISSA_OFFLINE_LOGO','ABISSA_OFFLINE_MOBILE_LOGO','ABISSA_V8_SFX','AbissaEvolution'):
        assert marker in html,marker
    check('Original event/mobile logos, programme, chart, five sounds, icon font and 2.1.2 interface module preserved')

try:adb_output=run([Path('C:/Android/platform-tools/adb.exe'),'devices','-l'])
except (OSError,subprocess.CalledProcessError):adb_output='List of devices attached\n'
devices=[line for line in adb_output.splitlines()[1:] if line.strip()]
result={'version':'2.1.2','versionCode':24,'package':'com.abissa.mobile','launchableActivity':'com.abissa.mobile.MainActivity','minSdk':23,'targetSdk':36,'checks':checks,'signingCertificateSha256':certificate,'signatures':{'v1':True,'v2':True,'v3':True},'apkBytes':apk.stat().st_size,'apkSha256':sha(apk.read_bytes()),'dexSha256':sha(dex),'htmlBytesBefore':manifest['originalHtmlBytes'],'htmlBytesAfter':len(html_bytes),'htmlBytesCanonical':manifest['htmlBytes'],'javascriptSources':list(source_lists['script']),'stylesheetSources':list(source_lists['sheet']),'streamedMediaAssets':len(manifest['assets']),'allAndroidAssetFiles':len(asset_files),'runtimeConfirmed':False,'adbDevices':devices,'runtimeLimitation':'Démarrage Android non vérifié : aucun appareil ni émulateur ADB disponible.' if not devices else 'Vérifications statiques du paquet uniquement ; aucun lancement Android effectué dans ce test.'}
(root/'qa-tools/test-apk-212-package-results.json').write_text(json.dumps(result,ensure_ascii=False,indent=2),encoding='utf-8')
print('PASS APK package validation; Android runtime remains unverified')
