"""Generate an APK from the delivered AAB and exercise it on the isolated QA emulator."""
from pathlib import Path
import hashlib
import importlib.util
import json
import re
import subprocess
import tempfile
import time
import xml.etree.ElementTree as ET
import zipfile

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('play_builder', ROOT / 'qa-tools/build-play-release-20261011.py')
builder = importlib.util.module_from_spec(spec)
spec.loader.exec_module(builder)
OUT = ROOT / 'qa-tools/play-ci-abissa-runtime-20261012'
ADB = Path('C:/Android/platform-tools/adb.exe')
SERIAL = 'emulator-5554'


def adb(*args, binary=False):
    result = subprocess.run([str(ADB), '-s', SERIAL, *map(str, args)], cwd=ROOT,
                            capture_output=True, timeout=60)
    if result.returncode:
        raise RuntimeError('ADB failed: ' + result.stderr.decode('utf-8', 'replace')[-1000:])
    return result.stdout if binary else result.stdout.decode('utf-8', 'replace')


def tree(name):
    for attempt in range(3):
        dumped = adb('shell', 'uiautomator', 'dump', '--compressed', '/sdcard/abissa-play-test.xml')
        if 'dumped to:' in dumped:
            adb('pull', '/sdcard/abissa-play-test.xml', OUT / (name + '.xml'))
            return ET.parse(OUT / (name + '.xml'))
        time.sleep(2)
    raise RuntimeError('UI hierarchy not available: ' + dumped)


def has(document, text):
    return any(text.casefold() in (node.get('text', '') + ' ' + node.get('content-desc', '')).casefold()
               for node in document.iter('node'))


def tap(label, document, maximum_y=1920):
    nodes = [node for node in document.iter('node') if label.casefold() in
             (node.get('text', '') + ' ' + node.get('content-desc', '')).casefold()]
    if not nodes:
        raise AssertionError('Missing control: ' + label)
    coords = list(map(int, re.findall(r'\d+', nodes[-1].get('bounds'))))
    if coords[1] == coords[3] or coords[3] > maximum_y:
        raise AssertionError('Control outside viewport: ' + label)
    adb('shell', 'input', 'tap', (coords[0] + coords[2]) // 2, (coords[1] + coords[3]) // 2)


def capture(name):
    (OUT / (name + '.png')).write_bytes(adb('exec-out', 'screencap', '-p', binary=True))


def main():
    OUT.mkdir(exist_ok=True)
    report = json.loads((ROOT / 'qa-tools/build-play-release-20261011-results.json').read_text())
    (OUT / 'results.json').write_text(json.dumps({
        'completed': False, 'aabSha256': report['sha256'], 'checks': [],
        'errors': ['Native verification has not completed.'], 'physicalDeviceTested': False
    }, indent=2), encoding='utf-8')
    aab = ROOT / report['aab']
    assert hashlib.sha256(aab.read_bytes()).hexdigest() == report['sha256']
    checks = []
    apk = builder.BUILD / 'universal-test.apk'
    with tempfile.TemporaryDirectory(prefix='play-qa-', dir=ROOT / '.release-signing') as secret_dir:
        password_file = Path(secret_dir) / 'password.txt'
        password_file.write_text(builder.unlock(), encoding='utf-8')
        builder.bundletool('build-apks', '--bundle=' + str(aab), '--mode=universal', '--overwrite',
                           '--aapt2=' + str(builder.BT / 'aapt2.exe'),
                           '--output=' + str(builder.BUILD / 'universal-test.apks'),
                           '--ks=' + str(builder.KEY), '--ks-key-alias=' + builder.ALIAS,
                           '--ks-pass=file:' + str(password_file), '--key-pass=file:' + str(password_file))
    with zipfile.ZipFile(builder.BUILD / 'universal-test.apks') as archive:
        assert archive.testzip() is None
        apk.write_bytes(archive.read('universal.apk'))
    signature = builder.run([builder.JAVA / 'java.exe', '-jar', builder.BT / 'lib/apksigner.jar',
                             'verify', '--verbose', '--print-certs', apk])
    assert builder.EXPECTED in signature.lower()
    badging = builder.run([builder.BT / 'aapt2.exe', 'dump', 'badging', apk])
    (OUT / 'apk-badging.txt').write_text(badging, encoding='utf-8')
    assert "package: name='ci.abissa'" in badging
    assert "versionCode='" + str(report['versionCode']) + "'" in badging
    assert "minSdkVersion:'24'" in badging and "targetSdkVersion:'36'" in badging
    assert "launchable-activity: name='com.abissa.mobile.MainActivity'" in badging
    with zipfile.ZipFile(apk) as archive:
        assert not any(name.endswith('.so') for name in archive.namelist())
        assert archive.testzip() is None
    checks.append('Universal APK generated from exact delivered AAB; signature, ci.abissa, code, SDK24/36 and launcher verified')
    print('PASS: ' + checks[-1], flush=True)

    # Never operate on a physical device or a production app installation.
    assert adb('shell', 'getprop', 'ro.kernel.qemu').strip() == '1'
    assert adb('shell', 'getprop', 'ro.build.version.sdk').strip() == '36'
    assert 'Success' in adb('install', '-r', apk)
    package = adb('shell', 'dumpsys', 'package', 'ci.abissa')
    (OUT / 'installed-package.txt').write_text(package, encoding='utf-8')
    assert 'versionCode=' + str(report['versionCode']) in package
    assert 'DEBUGGABLE' not in package
    adb('shell', 'am', 'force-stop', 'ci.abissa')
    started = adb('shell', 'am', 'start', '-W', '-n', 'ci.abissa/com.abissa.mobile.MainActivity')
    assert 'Status: ok' in started and 'Error' not in started
    time.sleep(3)
    for attempt in range(15):
        home = tree('home')
        if has(home, "System UI isn't responding"):
            (OUT / 'host-system-ui-interruption.txt').write_text(
                'The emulator System UI displayed an ANR dialog during cold boot; Wait selected. App verification continues and must still pass.', encoding='utf-8')
            tap('Wait', home)
            time.sleep(3)
            continue
        if has(home, 'Profil'):
            break
        if has(home, 'Passer l’écran de lancement'):
            tap('Passer l’écran de lancement', home)
        time.sleep(2)
    assert has(home, 'Profil') and has(home, 'Abissa Mobile')
    assert not has(home, 'Le chargement n’a pas abouti')
    tap('Accueil', home)
    home = tree('home')
    assert has(home, 'Programme') and has(home, 'Galerie')
    capture('home')
    checks.append('AAB-derived Release APK installs and starts ci.abissa on Android16 with bundled home screen')
    print('PASS: ' + checks[-1], flush=True)
    tap('Profil', home)
    profile = tree('profile')
    assert has(profile, 'Services de l’application')
    capture('profile')
    for attempt in range(8):
        try:
            tap('À propos', profile, maximum_y=1640)
            break
        except AssertionError:
            adb('shell', 'input', 'swipe', '540', '1450', '540', '650', '400')
            profile = tree('profile-scrolled')
    about = tree('about')
    assert has(about, '2.1.2 (' + str(report['versionCode']) + ')')
    capture('about')
    tap('Fermer', about)
    checks.append('Profile native bridge works and About reads the real installed version/build')
    print('PASS: ' + checks[-1], flush=True)
    # Launch the non-exported scanner through its actual in-app control.
    profile = tree('profile-before-scanner')
    for attempt in range(8):
        try:
            tap('Scanner un QR', profile, maximum_y=1640)
            break
        except AssertionError:
            adb('shell', 'input', 'swipe', '540', '700', '540', '1450', '400')
            profile = tree('profile-scanner-scrolled')
    scanner = tree('scanner-permission')
    for label in ('While using the app', 'Lorsque vous utilisez', 'Only this time', 'Autoriser'):
        if has(scanner, label):
            tap(label, scanner)
            scanner = tree('scanner')
            break
    scanner = tree('scanner')
    assert has(scanner, 'Cadrez un QR code') and has(scanner, 'Retour à Abissa')
    scanner_nodes = list(scanner.iter('node'))
    scanner_title = next(node for node in scanner_nodes if 'Cadrez un QR code' in node.get('text', ''))
    scanner_return = next(node for node in scanner_nodes if 'RETOUR À ABISSA' in node.get('text', '').upper())
    title_bounds = list(map(int, re.findall(r'\d+', scanner_title.get('bounds'))))
    return_bounds = list(map(int, re.findall(r'\d+', scanner_return.get('bounds'))))
    assert title_bounds[1] >= 60 and return_bounds[3] <= 1860, 'Scanner overlaps Android16 system bars'
    capture('scanner')
    tap('Retour à Abissa', scanner)
    checks.append('Non-exported scanner opens via Profile under ci.abissa, respects Android16 system bars and returns to the app')
    adb('shell', 'am', 'force-stop', 'ci.abissa')
    outcome = {'completed': True, 'checks': checks, 'errors': [], 'package': 'ci.abissa',
               'versionCode': report['versionCode'], 'aabSha256': report['sha256'],
               'apkSha256': hashlib.sha256(apk.read_bytes()).hexdigest(),
               'emulator': 'isolated Android16/API36', 'physicalDeviceTested': False,
               'playAcceptanceConfirmed': False, 'otherAndroidVersionsTested': False}
    (OUT / 'results.json').write_text(json.dumps(outcome, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    print(json.dumps(outcome, ensure_ascii=False))


if __name__ == '__main__':
    try:
        main()
    except Exception as error:
        OUT.mkdir(exist_ok=True)
        file = OUT / 'results.json'
        outcome = json.loads(file.read_text()) if file.exists() else {}
        outcome.update(completed=False, errors=[str(error)], physicalDeviceTested=False)
        file.write_text(json.dumps(outcome, indent=2), encoding='utf-8')
        raise
