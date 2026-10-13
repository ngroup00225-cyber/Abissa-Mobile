"""Install and exercise the real iOS app. Requires Codemagic/macOS, never a browser substitute."""
from pathlib import Path
import json
import os
import plistlib
import re
import subprocess
import sys
import time

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / 'ios/iphone-validation'
APP = ROOT / 'ios/build/Build/Products/Debug-iphonesimulator/AbissaMobile.app'
BUNDLE = 'ci.abissa'


def run(*args, timeout=180):
    return subprocess.check_output(['xcrun', 'simctl', *args], text=True,
                                   stderr=subprocess.STDOUT, timeout=timeout)


def select_phones(runtimes, types):
    available = sorted((r for r in runtimes if r.get('isAvailable') and '.iOS-' in r['identifier']),
                       key=lambda r: tuple(map(int, r['version'].split('.'))))
    if not available:
        raise RuntimeError('No available iOS simulator runtime in this Xcode image.')
    runtime = available[-1]
    parts = list(map(int, runtime['version'].split('.'))) + [0, 0]
    code = (parts[0] << 16) | (parts[1] << 8) | parts[2]
    phones = [d for d in types if d['name'].startswith('iPhone') and
              d.get('minRuntimeVersion', 0) <= code <= d.get('maxRuntimeVersion', 0xffffffff)]
    if len(phones) < 2:
        raise RuntimeError('Two compatible iPhone simulator types are required.')
    small = next((d for d in reversed(phones) if any(n in d['name'] for n in ('SE', 'mini', '17e'))), phones[0])
    recent = max((d for d in phones if d != small),
                 key=lambda d: (tuple(map(int, re.findall(r'\d+', d['name']))), 'Max' in d['name'], 'Pro' in d['name']))
    return runtime, [('iphone-standard', small), ('iphone-recent', recent)]


def startup_log(udid, pid):
    return run('spawn', udid, 'log', 'show', '--last', '5m', '--style', 'compact',
               '--predicate', 'subsystem == "ci.abissa" AND category == "Startup" AND processIdentifier == '+pid)


def require_ready(udid, pid, label):
    for _ in range(25):
        log = startup_log(udid, pid)
        (OUT / (label+'.log')).write_text(log, encoding='utf-8')
        if any(marker in log for marker in ('ABISSA_STARTUP_FAILED', 'ABISSA_RESOURCE_FAILED', 'ABISSA_WEB_PROCESS_TERMINATED')):
            raise RuntimeError('Native/WebKit startup failure; inspect '+label+'.log')
        if 'ABISSA_STARTUP_READY' in log:
            return
        time.sleep(1)
    raise RuntimeError('No rendered WKWebView readiness marker for this process.')


def launch(udid, validation=False):
    args = ['launch', udid, BUNDLE]
    if validation:
        args.append('--abissa-iphone-validation')
    result = run(*args).strip()
    pid = result.rsplit(':', 1)[-1].strip()
    if not pid.isdigit():
        raise RuntimeError('iOS process was not launched: '+result)
    return pid


def wait_report(file, udid, label, target, seconds=150):
    deadline = time.monotonic() + seconds
    captured = set()
    while time.monotonic() < deadline:
        try:
            report = json.loads(file.read_text(encoding='utf-8'))
        except (FileNotFoundError, json.JSONDecodeError):
            time.sleep(.3)
            continue
        (OUT / (label+'-webview.json')).write_text(json.dumps(report, indent=2), encoding='utf-8')
        if report.get('stage') == 'failed' or report.get('passed') is False:
            raise RuntimeError('WKWebView validation failed: '+json.dumps(report.get('errors', [])))
        page = report.get('page')
        if report.get('stage') == 'page' and page not in captured and re.fullmatch('[a-z-]+', page or ''):
            run('io', udid, 'screenshot', str(OUT / (label+'-'+page+'.png')))
            captured.add(page)
        if report.get('stage') == target:
            if not report.get('passed'):
                raise RuntimeError('WKWebView reported an unsuccessful validation.')
            return report
        time.sleep(.3)
    raise RuntimeError('WKWebView did not complete '+target+' within '+str(seconds)+' seconds.')


def main():
    OUT.mkdir(parents=True, exist_ok=True)
    summary = {'commit': os.environ.get('CM_COMMIT'), 'checks': [], 'errors': [],
               'nativeStartupVerified': False, 'nativeNavigationVerified': False,
               'networkVerified': False, 'physicalDevicesVerified': False,
               'interactionMethod': 'DOM button clicks in actual iOS WKWebView; not physical touch/XCUITest'}
    try:
        if sys.platform != 'darwin' or not APP.is_dir():
            raise RuntimeError('Run on macOS after the unsigned iOS simulator build.')
        sys.path.insert(0, str(ROOT/'ios/scripts'))
        from validate_web_bundle import validate_web_bundle
        if validate_web_bundle(APP/'Web') != validate_web_bundle(ROOT/'ios/AbissaMobile/Web'):
            raise RuntimeError('Compiled iOS Web resources differ from validated sources.')
        info = plistlib.loads((APP/'Info.plist').read_bytes())
        if info['CFBundleIdentifier'] != BUNDLE:
            raise RuntimeError('Unexpected simulator app identity.')
        runtime, devices = select_phones(json.loads(run('list', 'runtimes', '-j'))['runtimes'],
                                         json.loads(run('list', 'devicetypes', '-j'))['devicetypes'])
        for label, device in devices:
            udid = None
            pid = None
            try:
                udid = run('create', 'ABISSA iPhone QA '+label, device['identifier'], runtime['identifier']).strip()
                run('boot', udid)
                run('bootstatus', udid, '-b', timeout=300)
                run('install', udid, str(APP))
                run('ui', udid, 'appearance', 'light')
                pid = launch(udid, validation=True)
                require_ready(udid, pid, label+'-startup')
                container = Path(run('get_app_container', udid, BUNDLE, 'data').strip())
                report_file = container/'Library/Caches/abissa-iphone-validation.json'
                report = wait_report(report_file, udid, label, 'complete')
                if set(report['pages']) != {'accueil','programme','histoire','sites','langue','profil'} or len(report['network']) != 2:
                    raise RuntimeError('Incomplete navigation/network evidence.')
                run('openurl', udid, 'abissa://open?page=programme')
                report = wait_report(report_file, udid, label, 'deeplink', seconds=15)
                if not report.get('deepLinkVerified'):
                    raise RuntimeError('Native custom-scheme deep link did not open Programme.')
                run('io', udid, 'screenshot', str(OUT/(label+'-deeplink.png')))
                require_ready(udid, pid, label+'-final')
                run('terminate', udid, BUNDLE)
                restarted = launch(udid)
                require_ready(udid, restarted, label+'-restart')
                time.sleep(7)
                run('io', udid, 'screenshot', str(OUT/(label+'-restart.png')))
                run('ui', udid, 'appearance', 'dark')
                run('io', udid, 'screenshot', str(OUT/(label+'-dark.png')))
                if BUNDLE not in run('spawn', udid, 'launchctl', 'list'):
                    raise RuntimeError('App process did not survive its cold restart.')
                summary['checks'].append({'device':device['name'], 'runtime':runtime['version'],
                                           'installed':True, 'renderedHome':True, 'pages':report['pages'],
                                           'network':report['network'], 'deepLink':True, 'coldRestart':True})
            except (OSError, ValueError, RuntimeError, subprocess.SubprocessError) as error:
                summary['errors'].append({'device':device['name'], 'error':str(error)})
                if udid:
                    try:
                        run('io', udid, 'screenshot', str(OUT/(label+'-failed.png')))
                        if pid:
                            (OUT/(label+'-failure.log')).write_text(startup_log(udid, pid), encoding='utf-8')
                    except subprocess.SubprocessError:
                        pass
            finally:
                if udid:
                    for command in ('shutdown', 'delete'):
                        try:
                            run(command, udid)
                        except subprocess.SubprocessError as error:
                            summary['errors'].append({'cleanup':command, 'error':str(error)})
        passed = not summary['errors'] and len(summary['checks']) == 2
        summary.update(nativeStartupVerified=passed, nativeNavigationVerified=passed, networkVerified=passed)
    except (OSError, ValueError, RuntimeError, subprocess.SubprocessError) as error:
        summary['errors'].append({'setup':str(error)})
    (OUT/'results.json').write_text(json.dumps(summary, indent=2)+'\n', encoding='utf-8')
    print(json.dumps(summary, indent=2))
    return 0 if summary['nativeStartupVerified'] else 1


if __name__ == '__main__':
    sys.exit(main())
