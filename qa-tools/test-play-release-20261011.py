"""Verify publication artifacts and execute the actual Gradle certificate guard with real keystores."""
from pathlib import Path
import ctypes
import hashlib
import importlib.util
import json
import os
import subprocess
import xml.etree.ElementTree as ET
import zipfile

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('release_builder', ROOT / 'qa-tools/build-play-release-20261011.py')
builder = importlib.util.module_from_spec(spec)
spec.loader.exec_module(builder)
spec2 = importlib.util.spec_from_file_location('historical', ROOT / 'qa-tools/sign-aab-historical-212.py')
historical = importlib.util.module_from_spec(spec2)
spec2.loader.exec_module(historical)


def main():
    results = []
    report = json.loads((ROOT / 'qa-tools/build-play-release-20261011-results.json').read_text())
    path = ROOT / report['aab']
    assert hashlib.sha256(path.read_bytes()).hexdigest() == report['sha256']
    with zipfile.ZipFile(path) as bundle:
        assert bundle.testzip() is None
        assert any(n.startswith('META-INF/') and n.endswith('.RSA') for n in bundle.namelist())
    results.append('Final signed AAB SHA256, CRC and signature-block presence verified')
    android = '{http://schemas.android.com/apk/res/android}'
    original = ET.parse(ROOT / 'app/src/main/AndroidManifest.xml').getroot()
    # Inspect the actual delivered AAB again, independently of the build report.
    built = ET.fromstring(builder.bundletool('dump', 'manifest', '--bundle=' + str(path)))
    assert 'package' not in original.attrib  # Modern AGP derives it from applicationId.
    assert built.attrib['package'] == report['package'] == 'ci.abissa'
    assert built.attrib[android + 'versionCode'] == str(report['versionCode'])
    assert built.find('uses-sdk').attrib[android + 'minSdkVersion'] == str(report['minSdk'])
    assert report['minSdk'] >= 24
    assert built.find('uses-sdk').attrib[android + 'targetSdkVersion'] == str(report['targetSdk'])
    assert built.find('application').attrib[android + 'debuggable'] == 'false'
    for tag in ['uses-permission', 'uses-feature']:
        for child in original.findall(tag):
            assert any(all(actual.attrib.get(k) == v for k, v in child.attrib.items()) for actual in built.findall(tag))
    for tag in ['activity', 'service']:
        assert {x.attrib[android + 'name'] for x in original.find('application').findall(tag)} == {x.attrib[android + 'name'] for x in built.find('application').findall(tag)}
    results.append('Delivered AAB manifest: correct package/version, minSdk >=24, targetSdk, non-debuggable, permissions, activities and services preserved')
    gradle_source = (ROOT / 'app/build.gradle').read_text(encoding='utf-8')
    config = builder.release_config(gradle_source, {})
    assert config == (str(report['versionCode']), report['minSdk'], report['targetSdk'], report['version'], report['package'])
    try:
        builder.release_config(gradle_source.replace('minSdk 24', 'minSdk 23'), {})
    except ValueError:
        pass
    else:
        raise AssertionError('Regression: minSdk 23 must fail before compilation')
    for invalid_code in ('25', '26', '0', '-1', 'bad', '2100000001'):
        try:
            builder.release_config(gradle_source, {'ABISSA_VERSION_CODE': invalid_code})
        except ValueError:
            pass
        else:
            raise AssertionError('Invalid version code accepted: ' + invalid_code)
    assert builder.release_config(gradle_source, {'ABISSA_VERSION_CODE': '28'})[0] == '28'
    for invalid_source in (gradle_source.replace('applicationId "ci.abissa"', 'applicationId "com.abissa.mobile"'), gradle_source.replace('targetSdk 36', 'targetSdk 35')):
        try:
            builder.release_config(invalid_source, {})
        except ValueError:
            pass
        else:
            raise AssertionError('Wrong Play package or obsolete target SDK accepted')
    with zipfile.ZipFile(path) as bundle:
        dex = bundle.read('base/dex/classes.dex')
        for component in built.find('application'):
            if component.tag in ('activity', 'service'):
                name = component.attrib[android + 'name']
                assert ('L' + name.replace('.', '/') + ';').encode() in dex, name
    results.append('SDK23, wrong Play package, targetSDK35 and six invalid/reused version codes rejected; all Android components present in DEX')
    java = builder.JAVA
    env = os.environ.copy()
    env['ABISSA_UPLOAD_STORE_PASSWORD'] = builder.unlock()
    env['ABISSA_HISTORICAL_PASSWORD'] = historical.password()
    try:
        # Use a Java argument file to preserve accented Windows paths.
        groovy = next((Path.home() / '.gradle/caches/modules-2/files-2.1/org.codehaus.groovy/groovy/3.0.10').rglob('groovy-3.0.10.jar'))
        args = builder.BUILD / 'groovy-validator.args'
        cp = str(groovy).replace('\\', '/')
        args.write_text('-cp\n"' + cp + '"\ngroovy.ui.GroovyMain\nqa-tools/test-play-release-gradle-20261011.groovy\n', encoding='cp1252')
        output = subprocess.run([str(java / 'java.exe'), '@' + str(args)], cwd=ROOT, env=env, capture_output=True, text=True, timeout=120)
        if output.returncode:
            raise RuntimeError('Groovy validation failed: ' + output.stderr[-1800:])
        assert 'ABISSA_GRADLE_GUARD_TESTS=15' in output.stdout
        guard = {'testCount': 15, 'tests': [line[6:] for line in output.stdout.splitlines() if line.startswith('PASS: ')],
                 'actualGradleAgpBuildExecuted': False, 'validatorExecutedWithRealKeystores': True,
                 'scope': 'Actual Gradle validator and dependency hooks executed in a minimal task harness; syntax parsed. This does not replace an AGP/Gradle build.'}
        assert guard['testCount'] == 15
        results.append('Actual Gradle guards: fifteen identity/SDK/signing/syntax tests with real keystores passed')
    finally:
        env.pop('ABISSA_UPLOAD_STORE_PASSWORD', None)
        env.pop('ABISSA_HISTORICAL_PASSWORD', None)
    # iOS has evolved since the October 7 delivery independently of this Android fix.
    # Record its current hashes; that old delivery is not a valid unchanged-source baseline.
    ios_files = ['ios/AbissaMobile/AbissaViewController.swift', 'ios/AbissaMobile/NativeCompanion.swift', 'ios/AbissaMobile/AppDelegate.swift', 'ios/AbissaMobile/Info.plist', 'ios/AbissaMobile.xcodeproj/project.pbxproj']
    ios_hashes = {name: hashlib.sha256((ROOT / name).read_bytes()).hexdigest() for name in ios_files}
    with zipfile.ZipFile(path) as bundle:
        assert not any(name.startswith('ios/') or name.endswith(('.swift', '.xcodeproj')) for name in bundle.namelist())
    results.append('Five current iOS source/configuration hashes recorded for traceability; iOS sources excluded from AAB')
    outcome = {'checks': results, 'errors': [], 'gradleGuard': guard,
               'aabSha256': report['sha256'], 'versionCode': report['versionCode'], 'minSdk': report['minSdk'], 'package': report['package'],
               'iosSourceSha256': ios_hashes,
               'actualGradleAgpBuildExecuted': False, 'googlePlayAccepted': False,
               'physicalDeviceUpdateTested': False}
    (ROOT / 'qa-tools/test-play-release-20261011-results.json').write_text(json.dumps(outcome, indent=2) + '\n')
    print(json.dumps(outcome))


if __name__ == '__main__':
    main()
