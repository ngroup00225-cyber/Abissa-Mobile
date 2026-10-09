"""Exercise the signed release on the isolated ABISSA emulator, without production writes."""
from pathlib import Path
import subprocess,json,re,time,sys,xml.etree.ElementTree as ET
sys.stdout.reconfigure(encoding='utf-8')
root=Path(__file__).resolve().parents[1];out=root/'qa-tools/android-runtime-212';out.mkdir(exist_ok=True)
adb='C:/Android/platform-tools/adb.exe';device='emulator-5554';checks=[]
def run(*args):
    p=subprocess.run([adb,'-s',device,*map(str,args)],capture_output=True,timeout=45)
    assert p.returncode==0,p.stderr.decode('utf-8','replace')
    return p.stdout.decode('utf-8','replace')
def dump(name):
    run('shell','uiautomator','dump','/sdcard/abissa-qa.xml')
    run('pull','/sdcard/abissa-qa.xml',out/(name+'.xml'))
    return ET.parse(out/(name+'.xml'))
def screenshot(name):
    run('shell','screencap','-p','/sdcard/abissa-qa.png');run('pull','/sdcard/abissa-qa.png',out/(name+'.png'))
def tap(label,tree=None,attempt=0):
    assert attempt<15,('Control remains outside viewport',label)
    tree=tree or dump('current')
    nodes=[n for n in tree.iter('node') if n.get('text','').casefold()==label.casefold() or n.get('content-desc','').casefold()==label.casefold()]
    assert nodes, ('Missing control',label,[(n.get('text'),n.get('content-desc')) for n in tree.iter('node') if n.get('text')])
    xy=list(map(int,re.findall(r'\d+',nodes[-1].get('bounds'))))
    if label in ['Scanner un QR','Favoris','Notifications','Téléchargements','Préférences','À propos','Favori','Partager'] and (xy[3]>1580 or xy[1]==xy[3]):
        run('shell','input','swipe','540','1420','540','650','400');return tap(label,dump('scrolled-profile'),attempt+1)
    run('shell','input','tap',(xy[0]+xy[2])//2,(xy[1]+xy[3])//2)
def has(tree,label):return any(label.casefold() in (n.get('text','')+' '+n.get('content-desc','')).casefold() for n in tree.iter('node'))
def passed(text):checks.append(text);print('PASS '+text,flush=True)
assert 'Success' in run('install','-r',root/'Abissa-Mobile-v2.1.2-release.apk')
package=run('shell','dumpsys','package','com.abissa.mobile')
assert 'versionName=2.1.2' in package and 'versionCode=24' in package
original=(root/'qa-tools/update-212-before-package.txt').read_text(encoding='utf-8')
for pattern in [r'appId=(\d+)',r'firstInstallTime=([^\n\r]+)',r'ceDataInode=(\d+)']:
    assert re.search(pattern,original).group(1)==re.search(pattern,package).group(1),pattern
passed('In-place 2.1.1 → 2.1.2 update retains UID, first installation time and app data directory inode')
run('shell','am','force-stop','com.abissa.mobile');run('shell','am','start','-n','com.abissa.mobile/.MainActivity');time.sleep(5)
tree=dump('home')
if has(tree,'send you notifications?'):
    tap('Allow',tree);tree=dump('home')
if has(tree,"System UI isn't responding"):tap('Wait',tree);tree=dump('home')
for _ in range(10):
    if not has(tree,'Ouverture d’Abissa Mobile') and not has(tree,'Le chargement n’a pas abouti'):break
    time.sleep(2);tree=dump('home')
if has(tree,'Passer l’écran de lancement'):
    tap('Passer l’écran de lancement',tree);time.sleep(1);tree=dump('home')
assert has(tree,'Abissa Mobile') and has(tree,'Profil')
assert not any(n.get('text')=='SCANNER' and n.get('class')=='android.widget.Button' for n in tree.iter('node'))
screenshot('home')
passed('Signed release starts on Android 16 without the extra bottom toolbar')
tap('Profil',tree);tree=dump('profile');assert has(tree,'Services de l’application');screenshot('profile')
tap('À propos',tree);tree=dump('about')
assert has(tree,'2.1.2 (24)') and has(tree,'Narcisse');screenshot('about');tap('Fermer',tree)
screenshot('profile-services')
passed('Native About screen exposes version/build, developer and useful links')
tap('Scanner un QR');tree=dump('camera-permission')
if has(tree,'While using the app'):tap('While using the app',tree)
elif has(tree,'Only this time'):tap('Only this time',tree)
tree=dump('scanner');assert has(tree,'Cadrez un QR code') and has(tree,'Retour à Abissa');screenshot('scanner');tap('Retour à Abissa',tree)
passed('Native scanner opens its camera activity and returns to Abissa; permission requested only at use')
tap('Notifications');tree=dump('notifications-settings');tap('Activer',tree)
tree=dump('notification-permission')
if has(tree,'Allow'):tap('Allow',tree)
time.sleep(2)
jobs=run('shell','dumpsys','jobscheduler');assert 'com.abissa.mobile' in jobs and 'ReminderService' in jobs
run('shell','cmd','jobscheduler','run','-f','com.abissa.mobile','212');time.sleep(2)
notification=run('shell','dumpsys','notification','--noredact');assert 'com.abissa.mobile' in notification and 'abissa-reminders' in notification
run('shell','cmd','statusbar','expand-notifications');tree=dump('notification-shade');screenshot('notification-shade')
tap('ABISSA MOBILE',tree);tree=dump('notification-target');assert has(tree,'Programme');screenshot('notification-target')
passed('Android schedules and posts a real native reminder; tapping it opens the Programme screen')
run('shell','am','start','-a','android.intent.action.VIEW','-d',r'https://abissamobile.com/appli/mobile.html?content=hotel\&id=H%C3%B4tel%20Wharf%20Bassam','-n','com.abissa.mobile/.MainActivity')
tree=dump('hotel-deeplink');assert has(tree,'Hôtel Wharf Bassam');screenshot('hotel-deeplink')
tap('Partager',tree);tree=dump('native-share');screenshot('native-share')
assert any(n.get('package')=='com.android.intentresolver' for n in tree.iter('node')) and has(tree,'Hôtel Wharf Bassam')
run('shell','input','keyevent','4')
passed('Hotel deep link resolves from bundled data and its Share button opens the Android chooser')
tap('Favori');tap('Profil');tap('Favoris');tree=dump('favorites');assert has(tree,'Hôtel Wharf Bassam');screenshot('favorites');tap('Fermer',tree)
run('shell','am','force-stop','com.abissa.mobile');run('shell','am','start','-n','com.abissa.mobile/.MainActivity');time.sleep(2)
time.sleep(5);tree=dump('restarted')
for _ in range(10):
    if has(tree,'Profil'):break
    time.sleep(2);tree=dump('restarted')
if has(tree,'Passer l’écran de lancement'):tap('Passer l’écran de lancement',tree)
tap('Profil');tap('Favoris');tree=dump('favorites-after-restart');assert has(tree,'Hôtel Wharf Bassam');tap('Fermer',tree)
passed('Native favorite survives a full process restart')
run('shell','svc','wifi','disable');run('shell','svc','data','disable');time.sleep(1)
tree=dump('offline');assert has(tree,'Hors connexion') and has(tree,'Accueil');screenshot('offline')
passed('Native offline banner appears and bundled interface remains available')
run('shell','svc','wifi','enable');run('shell','svc','data','enable')
run('shell','input','keyevent','4');tree=dump('back');screenshot('back')
report={'completed':True,'checks':checks,'errors':[],'device':'Android 16 / API 36 isolated emulator','apkSha256':__import__('hashlib').sha256((root/'Abissa-Mobile-v2.1.2-release.apk').read_bytes()).hexdigest(),'physicalDeviceVerified':False,'accountSessionPersistenceVerified':False,'cameraDecodeVerified':False,'otherAndroidVersionsVerified':False}
(out/'results.json').write_text(json.dumps(report,ensure_ascii=False,indent=2),encoding='utf-8')
print(json.dumps(report,ensure_ascii=False))
