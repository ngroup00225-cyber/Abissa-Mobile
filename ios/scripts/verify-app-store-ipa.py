"""Check the produced IPA before Codemagic publishing; this does not claim Apple acceptance."""
from pathlib import Path
import json,plistlib,re,subprocess,sys,tempfile,zipfile
from datetime import datetime, timezone
sys.path.insert(0, str(Path(__file__).resolve().parent))
from validate_web_bundle import validate_web_bundle

ROOT=Path(__file__).resolve().parents[2]

def verify_identity(info,entitlements,profile,metadata):
    if metadata.get('bundleIdentifier')!='ci.abissa' or metadata.get('teamId')!='XY97926U94':
        raise ValueError('Build metadata differs from the authorized Apple identity.')
    number=metadata.get('buildNumber')
    if not isinstance(number,str) or not re.fullmatch(r'[1-9][0-9]{0,3}',number):
        raise ValueError('Selected CFBundleVersion must be an integer string in 1..9999.')
    version=metadata.get('marketingVersion')
    if not isinstance(version,str) or not re.fullmatch(r'[0-9]+(?:\.[0-9]+){0,2}',version):
        raise ValueError('Selected iOS marketing version is missing or invalid.')
    expected=metadata['teamId']+'.'+metadata['bundleIdentifier']
    for key,value in [('CFBundleIdentifier',metadata['bundleIdentifier']),('CFBundleShortVersionString',metadata['marketingVersion']),('CFBundleVersion',metadata['buildNumber'])]:
        if info.get(key)!=value:raise ValueError(f'IPA {key} does not match the selected iOS build.')
    if entitlements.get('application-identifier')!=expected or entitlements.get('com.apple.developer.team-identifier')!=metadata['teamId']:
        raise ValueError('Signed IPA belongs to a different Apple team or Bundle ID.')
    if entitlements.get('aps-environment')!='production':raise ValueError('Signed IPA is missing production Push Notifications.')
    if 'applinks:abissamobile.com' not in entitlements.get('com.apple.developer.associated-domains',[]):
        raise ValueError('Signed IPA is missing the expected Associated Domains entitlement.')
    if entitlements.get('get-task-allow') is True:raise ValueError('IPA is signed for debugging rather than distribution.')
    authorized=profile.get('Entitlements',{})
    expires=profile.get('ExpirationDate')
    if not isinstance(expires,datetime) or expires.replace(tzinfo=timezone.utc)<=datetime.now(timezone.utc):
        raise ValueError('Embedded distribution profile has expired or has no valid expiration date.')
    if profile.get('TeamIdentifier')!=[metadata['teamId']] or authorized.get('application-identifier')!=expected:
        raise ValueError('Embedded distribution profile has the wrong team or application identity.')
    if profile.get('ProvisionedDevices') or profile.get('ProvisionsAllDevices') or authorized.get('get-task-allow') is True:
        raise ValueError('Embedded profile is not an App Store distribution profile.')
    # Apple's profile is an allowlist and may authorize this entitlement with '*'.
    # The signed app above must still claim the exact ABISSA domain.
    domains=authorized.get('com.apple.developer.associated-domains',[])
    if isinstance(domains,str):domains=[domains]
    domain_authorized=isinstance(domains,list) and any(value in ('*','applinks:abissamobile.com') for value in domains)
    if authorized.get('aps-environment')!='production' or not domain_authorized:
        raise ValueError('Distribution profile does not authorize production Push and Associated Domains.')

def checked(*args):
    result=subprocess.run(args,capture_output=True,timeout=120)
    if result.returncode:raise RuntimeError('IPA verification failed: '+args[0]+'; publishing must stop.')
    return result

def verify(root=ROOT):
    if sys.platform!='darwin':raise RuntimeError('IPA signature verification requires the Codemagic macOS builder.')
    metadata=json.loads((root/'ios/ci-build-metadata.json').read_text(encoding='utf-8'))
    ipas=list((root/'build/ios/ipa').glob('*.ipa'))
    if len(ipas)!=1:raise ValueError('Expected exactly one IPA at build/ios/ipa/*.ipa.')
    with zipfile.ZipFile(ipas[0]) as z:
        if z.testzip() is not None:raise ValueError('IPA ZIP integrity check failed.')
    with tempfile.TemporaryDirectory(prefix='abissa-ipa-check-') as temporary:
        checked('ditto','-x','-k',str(ipas[0]),temporary)
        apps=list((Path(temporary)/'Payload').glob('*.app'))
        if len(apps)!=1:raise ValueError('Expected one main app in the IPA Payload.')
        app=apps[0]
        checked('codesign','--verify','--deep','--strict',str(app))
        identity=checked('codesign','--display','--verbose=4',str(app)).stderr.decode('utf-8','replace')
        if 'Authority=Apple Distribution:' not in identity:raise ValueError('Expected the existing Apple Distribution signing identity.')
        entitlements=plistlib.loads(checked('codesign','--display','--entitlements',':-',str(app)).stdout)
        profile=plistlib.loads(checked('security','cms','-D','-i',str(app/'embedded.mobileprovision')).stdout)
        info=plistlib.loads((app/'Info.plist').read_bytes())
        verify_identity(info,entitlements,profile,metadata)
        bundled = validate_web_bundle(app/'Web')
        source = validate_web_bundle(root/'ios/AbissaMobile/Web')
        if bundled != source:
            raise ValueError('IPA embedded Web resources differ from the validated build sources.')
        metadata['embeddedWeb'] = bundled
    metadata['ipaValidated']=True
    metadata['ipaPath']=str(ipas[0].relative_to(root))
    (root/'ios/ci-build-metadata.json').write_text(json.dumps(metadata,indent=2)+'\n',encoding='utf-8')
    print(f'IPA validated: {metadata["bundleIdentifier"]}, {metadata["marketingVersion"]} ({metadata["buildNumber"]}), Apple Distribution, Push and Associated Domains. Apple upload is not yet confirmed.')

if __name__=='__main__':
    try:verify()
    except (ValueError,RuntimeError,KeyError,OSError,subprocess.TimeoutExpired) as error:
        print(str(error),file=sys.stderr);sys.exit(1)
