"""Exercise deletion against disposable API storage; never use production data."""
from pathlib import Path
import json,shutil,socket,subprocess,tempfile,time
import qa_http as http

ROOT=Path(__file__).resolve().parents[1];QA=ROOT/'qa-tools'
PHP=Path('C:/xampp/php/windowsXamppPhp/php.exe');checks=[]
def write(path,value):path.write_text(json.dumps(value),encoding='utf-8')
def passed(value):checks.append(value);print('PASS',value,flush=True)

with tempfile.TemporaryDirectory(prefix='isolated-delete-208-',dir=QA) as temporary:
    site=Path(temporary).resolve();assert site.parent==QA.resolve()
    api=site/'appli/api';shutil.copytree(ROOT/'deploy-cpanel/api',api)
    shutil.copy2(QA/'qa-api-config.php',api/'config.php')
    data=site/'appli/data';uploads=site/'appli/uploads';data.mkdir();uploads.mkdir()
    backup=data/'backups/2026-10-03';backup.mkdir(parents=True)
    proofs=data/'proofs';proofs.mkdir()
    password='Disposable-deletion-208!'
    hashed=subprocess.check_output([str(PHP),'-r',"echo password_hash('"+password+"',PASSWORD_DEFAULT);"],text=True)
    photo='a'*32+'.jpg';shared='b'*32+'.png';proof='c'*32
    users=[{'id':u,'username':u,'name':u,'phone':'2250700000001' if u=='delete-208' else '2250700000002','passwordHash':hashed,'status':'active','photo':'/appli/uploads/'+(photo if u=='delete-208' else shared)} for u in ['delete-208','keep-208']]
    submissions=[{'id':'delete-doc','userId':'delete-208','type':'member','status':'validated','data':{'name':'Delete Me','photo':'/appli/uploads/'+shared}}, {'id':'keep-doc','userId':'keep-208','type':'member','status':'validated','data':{'name':'Keep Me','photo':'/appli/uploads/'+shared}}]
    state={'SETTINGS':{},'COMMENTS':[{'userId':'delete-208','text':'delete comment'},{'userId':'keep-208','text':'keep comment'}],'COMMITTEE':{'commissions':[{'members':[{'sourceId':'delete-doc','name':'Delete Me'},{'sourceId':'keep-doc','name':'Keep Me'}]}]}}
    records={'users':users,'state':state,'submissions':submissions,'user_sessions':{},'learning_progress':{'delete-208':{'score':1},'keep-208':{'score':2}},'member_registry':[{'sourceId':'delete-doc'},{'sourceId':'keep-doc'}], 'credential_prints':{'delete-doc':{'state':'printed'},'keep-doc':{'state':'printed'}},'qrcodes':[{'documentId':'delete-doc'},{'documentId':'keep-doc'}],'proof_files':{proof:{'ownerId':'delete-208','ext':'pdf'}},'migrations':{'2026-09-29-v3':{'appliedAt':'fixture'}},'admins':[]}
    for name,value in records.items():
        write(data/(name+'.json'),value)
        if name not in ['migrations','admins']:write(backup/(name+'.json'),value)
    for name in ['verification_codes','verification_tokens']:
        value={'owned':{'phone':'2250700000001','purpose':'reset'},'other':{'phone':'2250700000002','purpose':'reset'}}
        write(data/(name+'.json'),value);write(backup/(name+'.json'),value)
    for name in [photo,shared]:(uploads/name).write_bytes(b'fixture image')
    (proofs/(proof+'.pdf')).write_bytes(b'fixture proof')
    (data/'activity_log.jsonl').write_text(json.dumps({'userId':'delete-208','action':'delete'})+'\n'+json.dumps({'userId':'keep-208','action':'keep'})+'\n',encoding='utf-8')
    router=site/'router.php';router.write_text("<?php $_GET['route']=substr(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),strlen('/appli/api/'));require __DIR__.'/appli/api/index.php';",encoding='utf-8')
    with socket.socket() as sock:sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
    base=f'http://127.0.0.1:{port}/appli/api/'
    log=(site/'server.log').open('w')
    server=subprocess.Popen([str(PHP),'-d','extension_dir='+str(PHP.parent/'ext'),'-d','extension=mbstring','-d','extension=fileinfo','-S',f'127.0.0.1:{port}',str(router)],stdout=log,stderr=subprocess.STDOUT,creationflags=getattr(subprocess,'CREATE_NO_WINDOW',0))
    def call(session,route,method='GET',expected=200,body=None):
        response=session.request(method,base+route,json=body)
        assert response.status_code==expected,(route,response.status_code,response.text[:500])
        try:return response.json()
        except Exception:
            print('Response body:',repr(response.text[:1000]))
            print('PHP log:',(site/'server.log').read_text(errors='replace')[-2500:])
            raise
    try:
        anonymous=http.Session()
        for _ in range(100):
            try:call(anonymous,'health');break
            except OSError:time.sleep(.05)
        call(anonymous,'users/me','DELETE',401,{'password':password,'confirmation':'SUPPRIMER'})
        owner=http.Session();token=call(owner,'users/login','POST',body={'id':'delete-208','password':password})['token'];owner.headers['Authorization']='Bearer '+token
        before=(data/'users.json').read_bytes()
        call(owner,'users/me','DELETE',422,{'password':password,'confirmation':'no'})
        call(owner,'users/me','DELETE',403,{'password':'wrong','confirmation':'SUPPRIMER'})
        assert before==(data/'users.json').read_bytes()
        passed('Anonymous, missing confirmation and wrong password cannot delete an account')
        call(owner,'users/me','DELETE',body={'password':password,'confirmation':'SUPPRIMER','id':'keep-208'})
        for path in data.rglob('*.json'):
            text=path.read_text(encoding='utf-8');assert 'delete-208' not in text and 'delete-doc' not in text,path
        assert json.loads((data/'users.json').read_text())[0]['id']=='keep-208'
        assert json.loads((data/'learning_progress.json').read_text())['keep-208']['score']==2
        for name in ['verification_codes','verification_tokens']:
            for folder in [data,backup]:assert json.loads((folder/(name+'.json')).read_text())=={'other':{'phone':'2250700000002','purpose':'reset'}}
        assert len(json.loads((data/'state.json').read_text())['COMMENTS'])==1
        assert not (uploads/photo).exists() and (uploads/shared).exists()
        assert not (proofs/(proof+'.pdf')).exists()
        assert 'delete-208' not in (data/'activity_log.jsonl').read_text()
        passed('Deletion removes owned records, comments, progress, sessions, credentials, proof and private photo from active storage and managed backups')
        passed('Another account and its shared image survive; client-supplied account ID cannot select another owner')
        call(owner,'users/me',expected=401)
        call(owner,'users/login','POST',401,{'id':'delete-208','password':password})
        call(anonymous,'users/login','POST',body={'id':'keep-208','password':password})
        passed('Deleted session and login are rejected; other account can still log in')
    finally:server.terminate();server.wait(timeout=10);log.close()

(QA/'test-account-deletion-212-results.json').write_text(json.dumps({'checks':checks,'errors':[],'mode':'disposable synthetic accounts and storage'},indent=2),encoding='utf-8')
