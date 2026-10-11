"""Codemagic/macOS startup smoke tests. These do not validate camera, APNs or accounts."""
from pathlib import Path
import json, subprocess, sys, time

root=Path(__file__).resolve().parents[1]
out=root/'ios/simulator-qa-212';out.mkdir(parents=True,exist_ok=True)
app=root/'ios/build/Build/Products/Debug-iphonesimulator/AbissaMobile.app'
assert sys.platform=='darwin' and app.is_dir(), 'Run after the unsigned Xcode simulator build on macOS.'
def run(*args):
    return subprocess.check_output(['xcrun','simctl',*args],text=True,stderr=subprocess.STDOUT,timeout=180)
runtimes=json.loads(run('list','runtimes','-j'))['runtimes']
runtime=sorted((r for r in runtimes if r.get('isAvailable') and r['identifier'].startswith('com.apple.CoreSimulator.SimRuntime.iOS-')),key=lambda r:tuple(map(int,r['version'].split('.'))))[-1]
types=json.loads(run('list','devicetypes','-j'))['devicetypes']
parts=list(map(int,runtime['version'].split('.')))+[0,0]
runtime_code=(parts[0]<<16)|(parts[1]<<8)|parts[2]
supported=[d for d in types if d.get('minRuntimeVersion',0)<=runtime_code<=d.get('maxRuntimeVersion',0xffffffff)]
phones=[d for d in supported if d['name'].startswith('iPhone')];pads=[d for d in supported if d['name'].startswith('iPad')]
small=next((d for d in reversed(phones) if 'SE' in d['name'] or 'mini' in d['name']),phones[0])
recent=phones[-1];tablet=pads[-1]
checks=[];errors=[]
for label,device in [('petit-iphone',small),('iphone-recent',recent),('ipad',tablet)]:
    udid=None;startup_log=''
    try:
        udid=run('create','ABISSA 212 '+label,device['identifier'],runtime['identifier']).strip()
        run('boot',udid);run('bootstatus',udid,'-b');run('install',udid,str(app))
        result=run('launch',udid,'ci.abissa');assert 'ci.abissa:' in result,result
        # Require the WKWebView DOM/menu readiness marker; a surviving process
        # can otherwise be stuck behind the startup error alert indefinitely.
        startup_log = ''
        for attempt in range(20):
            startup_log = run('spawn',udid,'log','show','--last','2m','--style','compact',
                              '--predicate','subsystem == "ci.abissa" AND category == "Startup"')
            if 'ABISSA_STARTUP_FAILED' in startup_log or 'ABISSA_RESOURCE_FAILED' in startup_log:
                raise AssertionError('Embedded interface failed; see startup log artifact.')
            if 'ABISSA_STARTUP_READY' in startup_log:
                break
            time.sleep(2)
        (out/(label+'-startup.log')).write_text(startup_log,encoding='utf-8')
        assert 'ABISSA_STARTUP_READY' in startup_log, 'Native process started, but rendered menu was not confirmed.'
        time.sleep(7) # Let the existing splash finish before capturing the UI.
        # A surviving process distinguishes a launch from an immediate native crash.
        processes=run('spawn',udid,'launchctl','list');assert 'ci.abissa' in processes, processes
        run('io',udid,'screenshot',str(out/(label+'.png')))
        checks.append({'device':device['name'],'runtime':runtime['version'],'check':'Install, launch, rendered WKWebView menu confirmed, surviving process; screenshot captured'})
    except Exception as e:
        if 'startup_log' in locals(): (out/(label+'-startup.log')).write_text(startup_log,encoding='utf-8')
        if udid:
            try: run('io',udid,'screenshot',str(out/(label+'-failed.png')))
            except subprocess.CalledProcessError: pass
        errors.append({'device':device['name'],'error':str(e)})
    finally:
        if udid:
            try:run('shutdown',udid)
            except subprocess.CalledProcessError:pass
            run('delete',udid) # Only the temporary simulator created by this test.
report={'checks':checks,'errors':errors,'nativeStartupVerified':not errors and len(checks)==3,'interactiveFeaturesVerified':False,'physicalDevicesVerified':False}
(out/'results.json').write_text(json.dumps(report,ensure_ascii=False,indent=2),encoding='utf-8')
print(json.dumps(report,ensure_ascii=False));sys.exit(bool(errors))
