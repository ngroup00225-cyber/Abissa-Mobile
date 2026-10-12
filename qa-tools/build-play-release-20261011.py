"""Compile a non-debug Android Release bundle using the separately authorized Play upload key."""
from pathlib import Path
import ctypes
import hashlib
import json
import os
import re
import subprocess
import sys
import tempfile
import xml.etree.ElementTree as ET
import zipfile

ROOT = Path(__file__).resolve().parents[1]
BUILD = ROOT / 'manual-build-play-ci-abissa-20261012'
DELIVERY = ROOT / os.environ.get('ABISSA_RELEASE_DIR', '00 - ABISSA FINAL/Android-2.1.2-ci.abissa-Play-2026-10-12')
JAVA = Path('C:/Program Files/Java/jdk-17/bin')
BT = Path('C:/Android/build-tools/36.0.0')
PLATFORM = Path('C:/Android/platforms/android-36/android.jar')
KEY = ROOT / '.release-signing/abissa-mobile-play-upload-20261011.jks'
ALIAS = 'abissa-play-upload-20261011'
EXPECTED = '704da9712d6a5a9d992997f653ef9ce0896ce5ea252827335ea8c083134f3021'
ANDROID = '{http://schemas.android.com/apk/res/android}'
PLAY_PACKAGE = 'ci.abissa'  # Required by the user's existing Play Console application.


class Blob(ctypes.Structure):
    _fields_ = [('size', ctypes.c_ulong), ('data', ctypes.POINTER(ctypes.c_ubyte))]


def unlock():
    data = (ROOT / '.release-signing/play-upload-20261011-password.dpapi').read_bytes()
    buffer = ctypes.create_string_buffer(data)
    source = Blob(len(data), ctypes.cast(buffer, ctypes.POINTER(ctypes.c_ubyte)))
    result = Blob()
    if not ctypes.WinDLL('crypt32', use_last_error=True).CryptUnprotectData(ctypes.byref(source), None, None, None, None, 1, ctypes.byref(result)):
        raise RuntimeError('Unlock the existing Play upload password in its Windows account; do not regenerate the key.')
    try:
        return ctypes.string_at(result.data, result.size).decode('utf-8')
    finally:
        ctypes.windll.kernel32.LocalFree(result.data)


def run(args, env=None):
    converted = [str(a.relative_to(ROOT)) if isinstance(a, Path) and a.is_relative_to(ROOT) else str(a) for a in args]
    completed = subprocess.run(converted, cwd=ROOT, env=env, capture_output=True, text=True, errors='replace', timeout=240)
    if completed.returncode:
        # These commands use env password references, never plaintext arguments.
        raise RuntimeError(Path(converted[0]).name + f' failed (exit {completed.returncode}): ' + (completed.stderr + completed.stdout)[-1800:])
    return completed.stdout


def bundletool(*args):
    prefix = (ROOT / 'manual-build-v24/bundletool-212.args').read_text(encoding='cp1252').splitlines()[:3]
    assert prefix[0] == '-cp' and prefix[2] == 'com.android.tools.build.bundletool.BundleToolMain'
    quote = lambda value: '"' + str(value).replace('\\', '/').replace('"', '\\"') + '"'
    path = BUILD / 'bundletool.args'
    path.write_text('\n'.join(prefix + [quote(a) for a in args]) + '\n', encoding='cp1252')
    return run([JAVA / 'java.exe', '@' + str(path)])


def release_config(gradle, environment=None):
    environment = os.environ if environment is None else environment
    default_code = re.search(r'def releaseCodeText = .*?\?: "([0-9]+)"', gradle).group(1)
    code = environment.get('ABISSA_VERSION_CODE', default_code)
    if not re.fullmatch(r'[1-9][0-9]*', code) or not 26 < int(code) <= 2100000000:
        raise ValueError('ABISSA_VERSION_CODE must exceed 26 and be within the Android limit; also check Play history.')
    minimum = int(re.search(r'^\s*minSdk\s+(\d+)\s*$', gradle, re.M).group(1))
    target = int(re.search(r'^\s*targetSdk\s+(\d+)\s*$', gradle, re.M).group(1))
    version = re.search(r'^\s*versionName\s+"([^"]+)"\s*$', gradle, re.M).group(1)
    package = re.search(r'^\s*applicationId\s+"([^"]+)"\s*$', gradle, re.M).group(1)
    if minimum < 24 or target < max(minimum, 36):
        raise ValueError('Play requires minSdk >= 24 and targetSdk >= 36 and >= minSdk.')
    if package != PLAY_PACKAGE:
        raise ValueError('The existing Play Console application requires package ' + PLAY_PACKAGE)
    return code, minimum, target, version, package


def main():
    gradle = (ROOT / 'app/build.gradle').read_text(encoding='utf-8')
    code, minimum, target, version, package = release_config(gradle)
    assert 'signingConfig signingConfigs.playRelease' in gradle and 'signingConfigs.debug' not in gradle
    assert EXPECTED in gradle
    BUILD.mkdir(exist_ok=True)
    DELIVERY.mkdir(parents=True, exist_ok=True)
    env = os.environ.copy()
    env['ABISSA_UPLOAD_STORE_PASSWORD'] = unlock()
    env['ABISSA_UPLOAD_KEY_PASSWORD'] = env['ABISSA_UPLOAD_STORE_PASSWORD']
    try:
        certificate = BUILD / 'upload.der'
        run([JAVA / 'keytool.exe', '-exportcert', '-keystore', KEY, '-alias', ALIAS, '-storepass:env', 'ABISSA_UPLOAD_STORE_PASSWORD', '-file', certificate], env)
        assert hashlib.sha256(certificate.read_bytes()).hexdigest() == EXPECTED
        description = run([JAVA / 'keytool.exe', '-printcert', '-file', certificate])
        assert 'Android Debug' not in description and '4096-bit RSA' in description
        # Every build recompiles the actual Android Java and resources; iOS is never bundled/modified.
        # Compile individually: aapt2 --dir crashes on this Windows host.
        # A fresh directory prevents stale resources from entering the bundle.
        with tempfile.TemporaryDirectory(prefix='compiled-res-', dir=BUILD) as compiled:
            compiled_dir = Path(compiled)
            for resource in sorted((ROOT / 'app/src/main/res').rglob('*')):
                if resource.is_file():
                    run([BT / 'aapt2.exe', 'compile', resource, '-o', compiled_dir])
            with zipfile.ZipFile(BUILD / 'resources.zip', 'w', zipfile.ZIP_DEFLATED) as archive:
                for resource in sorted(compiled_dir.glob('*.flat')):
                    archive.write(resource, resource.name)
        (BUILD / 'dex').mkdir(exist_ok=True)
        with tempfile.TemporaryDirectory(prefix='classes-', dir=BUILD) as compiled:
            compiled_dir = Path(compiled)
            run([JAVA / 'javac.exe', '-encoding', 'UTF-8', '-source', '8', '-target', '8', '-classpath', str(PLATFORM) + os.pathsep + str(ROOT / 'app/libs/zxing-core-3.5.3.jar'), '-d', compiled_dir, *sorted((ROOT / 'app/src/main/java').rglob('*.java'))])
            # A JAR is a ZIP; avoid jar.exe's cross-directory temporary rename on Windows.
            with zipfile.ZipFile(BUILD / 'classes.jar', 'w', zipfile.ZIP_DEFLATED) as archive:
                for entry in sorted(compiled_dir.rglob('*.class')):
                    archive.write(entry, entry.relative_to(compiled_dir).as_posix())
        run([JAVA / 'java.exe', '-cp', BT / 'lib/d8.jar', 'com.android.tools.r8.D8', '--release', '--lib', PLATFORM, '--min-api', str(minimum), '--output', BUILD / 'dex', BUILD / 'classes.jar', ROOT / 'app/libs/zxing-core-3.5.3.jar'])
        ET.register_namespace('android', 'http://schemas.android.com/apk/res/android')
        manifest = ET.parse(ROOT / 'app/src/main/AndroidManifest.xml')
        # Gradle uses applicationId; the manual linker needs it explicitly in its generated manifest.
        manifest.getroot().set('package', package)
        for component in manifest.getroot().find('application'):
            if component.tag in ('activity', 'service', 'receiver', 'provider'):
                assert component.attrib[ANDROID + 'name'].startswith('com.abissa.mobile.')
        manifest.getroot().find('application').set(ANDROID + 'debuggable', 'false')
        manifest_path = BUILD / 'AndroidManifest.xml'
        manifest.write(manifest_path, encoding='utf-8', xml_declaration=True)
        link_args = ['-I', PLATFORM, '--manifest', manifest_path, '--min-sdk-version', str(minimum), '--target-sdk-version', str(target), '--version-code', code, '--version-name', version, '-A', ROOT / 'app/src/main/assets', BUILD / 'resources.zip']
        run([BT / 'aapt2.exe', 'link', '--proto-format', '-o', BUILD / 'proto.apk', *link_args])
        with zipfile.ZipFile(BUILD / 'proto.apk') as source, zipfile.ZipFile(BUILD / 'base.zip', 'w', zipfile.ZIP_DEFLATED) as module:
            for name in source.namelist():
                if not name.startswith('META-INF/'):
                    module.writestr('manifest/AndroidManifest.xml' if name == 'AndroidManifest.xml' else name, source.read(name))
            module.write(BUILD / 'dex/classes.dex', 'dex/classes.dex')
        unsigned = BUILD / 'unsigned.aab'
        signed = DELIVERY / f'Abissa-Mobile-ci.abissa-v{version}-build{code}-release.aab'
        bundletool('build-bundle', '--modules=' + str(BUILD / 'base.zip'), '--output=' + str(unsigned), '--overwrite')
        run([JAVA / 'jarsigner.exe', '-keystore', KEY, '-storepass:env', 'ABISSA_UPLOAD_STORE_PASSWORD', '-keypass:env', 'ABISSA_UPLOAD_KEY_PASSWORD', '-digestalg', 'SHA-256', '-sigalg', 'SHA256withRSA', '-signedjar', signed, unsigned, ALIAS], env)
        verification = run([JAVA / 'jarsigner.exe', '-verify', '-verbose', '-certs', signed])
        assert 'jar verified' in verification.lower()
        (BUILD / 'jarsigner-verification.txt').write_text(verification, encoding='utf-8')
        jar_certificate = run([JAVA / 'keytool.exe', '-printcert', '-jarfile', signed])
        assert ':'.join(EXPECTED[i:i+2] for i in range(0, 64, 2)).upper() in jar_certificate.upper()
        assert 'Android Debug' not in jar_certificate
        bundletool('validate', '--bundle=' + str(signed))
        dumped = bundletool('dump', 'manifest', '--bundle=' + str(signed))
        (BUILD / 'manifest.xml').write_text(dumped, encoding='utf-8')
        built_manifest = ET.fromstring(dumped)
        assert built_manifest.attrib['package'] == package == PLAY_PACKAGE
        assert built_manifest.attrib[ANDROID + 'versionCode'] == code
        assert built_manifest.attrib[ANDROID + 'versionName'] == version
        assert built_manifest.find('uses-sdk').attrib[ANDROID + 'minSdkVersion'] == str(minimum)
        assert built_manifest.find('uses-sdk').attrib[ANDROID + 'targetSdkVersion'] == str(target)
        assert built_manifest.find('application').attrib[ANDROID + 'debuggable'] == 'false'
        assets = [p for p in (ROOT / 'app/src/main/assets').rglob('*') if p.is_file()]
        with zipfile.ZipFile(signed) as bundle:
            assert bundle.testzip() is None and not any(n.endswith('.so') for n in bundle.namelist())
            assert bundle.read('base/dex/classes.dex') == (BUILD / 'dex/classes.dex').read_bytes()
            for path in assets:
                assert bundle.read('base/assets/' + path.relative_to(ROOT / 'app/src/main/assets').as_posix()) == path.read_bytes()
        report = {'version': version, 'versionCode': int(code), 'package': package, 'namespace': 'com.abissa.mobile', 'minSdk': minimum, 'targetSdk': target,
                  'packageMatchesPlayConsoleScreenshot': True, 'nativeLibraries': 0,
                  'automaticProtectionSdkCompatible': minimum >= 24, 'builtManifestSdkVerified': True,
                  'signed': True, 'debuggable': False, 'debugCertificate': False, 'certificateSha256': EXPECTED,
                  'signatureVerifiedWithCerts': True, 'bundletoolValidated': True, 'assetsVerified': len(assets),
                  'compiledJavaAndDex': True, 'releaseDexCompilation': True, 'zipIntegrity': True,
                  'aab': str(signed.relative_to(ROOT)), 'bytes': signed.stat().st_size, 'sha256': hashlib.sha256(signed.read_bytes()).hexdigest(),
                  'playUploadKeyRegistrationConfirmed': False, 'playAppSigningCertificateKnown': False,
                  'highestPlayVersionCodeKnown': False, 'readyForLocalSignatureChecks': True, 'playAcceptanceConfirmed': False,
                  'historicalApkCertificateMatches': False, 'existingKeysPreserved': True, 'iosModified': False,
                  'buildEngine': 'installed Android SDK aapt2 + javac + D8 --release + bundletool; Gradle distribution is incomplete on this host'}
        (DELIVERY / 'VERIFICATIONS-RELEASE.json').write_text(json.dumps(report, indent=2) + '\n', encoding='utf-8')
        (ROOT / 'qa-tools/build-play-release-20261011-results.json').write_text(json.dumps(report, indent=2) + '\n', encoding='utf-8')
        print(json.dumps(report))
    finally:
        env.pop('ABISSA_UPLOAD_STORE_PASSWORD', None)
        env.pop('ABISSA_UPLOAD_KEY_PASSWORD', None)


if __name__ == '__main__':
    try:
        main()
    except (ValueError, RuntimeError, OSError) as error:
        print(str(error), file=sys.stderr)
        sys.exit(1)
