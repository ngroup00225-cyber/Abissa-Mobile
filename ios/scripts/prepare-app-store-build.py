"""Select an iOS build using the existing Codemagic Apple integration; never create keys."""
from pathlib import Path
import json,os,re,subprocess,sys

ROOT=Path(__file__).resolve().parents[2]
BUNDLE_ID='ci.abissa'
TEAM_ID='XY97926U94'

def cli(*args):
    result=subprocess.run(['app-store-connect',*args],capture_output=True,text=True,timeout=180)
    if result.returncode:
        # Never copy authentication-related CLI diagnostics into artifacts or logs.
        raise RuntimeError('Apple query failed. Check the Abissa-Mobile-Apple integration and its app permissions; no fallback build was assigned.')
    return result.stdout.strip()

def app_identifier(payload):
    resources=json.loads(payload)
    if isinstance(resources,dict):resources=resources.get('data',[])
    if not isinstance(resources,list):raise ValueError('Unexpected Apple app list response.')
    matching=[r for r in resources if isinstance(r,dict) and r.get('attributes',{}).get('bundleId')==BUNDLE_ID]
    if len(matching)!=1:raise ValueError('Expected exactly one Apple application with Bundle ID ci.abissa.')
    app_id=str(matching[0].get('id',''))
    if not app_id.isdigit():raise ValueError('Apple application ID is missing or invalid.')
    return app_id

def next_build(latest,minimum,ci_number):
    if not re.fullmatch(r'\d+(?:\.\d+){0,2}',latest):raise ValueError('Unexpected latest Apple build number; refusing an unverified fallback.')
    if not re.fullmatch(r'\d+',ci_number):raise ValueError('Codemagic BUILD_NUMBER is missing or invalid.')
    # An integer greater than the major part also exceeds an old dotted build.
    return max(minimum,int(latest.split('.')[0])+1,25+int(ci_number))

def prepare(root=ROOT):
    config=root/'ios/Config/Shared.xcconfig'
    text=config.read_text(encoding='utf-8')
    settings=dict(re.findall(r'^([A-Z_]+)\s*=\s*(.+)$',text,re.M))
    if settings.get('PRODUCT_BUNDLE_IDENTIFIER')!=BUNDLE_ID or settings.get('DEVELOPMENT_TEAM')!=TEAM_ID:
        raise ValueError('The configured iOS bundle or Apple team differs from the authorized identity.')
    minimum=int(settings['CURRENT_PROJECT_VERSION'])
    app_id=app_identifier(cli('apps','list','--bundle-id-identifier',BUNDLE_ID,'--strict-match-identifier','--json'))
    latest=cli('get-latest-build-number',app_id,'--platform','IOS','--all-versions')
    number=next_build(latest,minimum,os.environ.get('BUILD_NUMBER',''))
    updated,count=re.subn(r'^CURRENT_PROJECT_VERSION\s*=.*$',f'CURRENT_PROJECT_VERSION = {number}',text,flags=re.M)
    if count!=1:raise ValueError('Expected one CURRENT_PROJECT_VERSION setting.')
    metadata={'bundleIdentifier':BUNDLE_ID,'teamId':TEAM_ID,'marketingVersion':settings['MARKETING_VERSION'],
              'buildNumber':str(number),'minimumBuildNumber':minimum,'latestAppleBuildNumber':latest,
              'codemagicBuildNumber':os.environ['BUILD_NUMBER'],'appStoreAppleId':app_id,
              'integration':'Abissa-Mobile-Apple','commit':os.environ.get('CM_COMMIT',''),
              'uploadConfirmed':False}
    config.write_text(updated,encoding='utf-8')
    (root/'ios/ci-build-metadata.json').write_text(json.dumps(metadata,indent=2)+'\n',encoding='utf-8')
    print(f'iOS {metadata["marketingVersion"]} ({number}); Apple latest build {latest}; Bundle ID {BUNDLE_ID}; Team {TEAM_ID}.')
    return metadata

if __name__=='__main__':
    try:prepare()
    except (ValueError,RuntimeError,KeyError,subprocess.TimeoutExpired) as error:
        print(f'iOS build preparation stopped: {error}',file=sys.stderr);sys.exit(1)
