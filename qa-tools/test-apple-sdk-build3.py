"""Smoke-test the real Codemagic signer using an ephemeral synthetic key, never Apple access."""
from pathlib import Path
from unittest.mock import patch,Mock
from types import SimpleNamespace
from contextlib import redirect_stdout
from io import StringIO
import importlib.metadata
import importlib.util
import json
import sys

ROOT=Path(__file__).resolve().parents[1]
local=ROOT/'qa-tools/apple-connect-deps/sdk'
if local.is_dir():sys.path.insert(0,str(local))
from cryptography.hazmat.primitives import serialization
from cryptography.hazmat.primitives.asymmetric import ec
import jwt
from codemagic.tools.app_store_connect.actions.latest_build_number_actions import GetLatestBuildNumberAction

spec=importlib.util.spec_from_file_location('prepare',ROOT/'ios/scripts/prepare-app-store-build.py')
prepare=importlib.util.module_from_spec(spec);spec.loader.exec_module(prepare)
key=ec.generate_private_key(ec.SECP256R1())
private=key.private_bytes(serialization.Encoding.PEM,serialization.PrivateFormat.PKCS8,serialization.NoEncryption()).decode()

# Reproduce the actual CLI action's successful "no build" return: no number on stdout.
action=SimpleNamespace(_get_app_store_latest_build_info=Mock(return_value=None),
                      _get_testflight_latest_build_info=Mock(return_value=None),logger=Mock())
stdout=StringIO()
with redirect_stdout(stdout):
    assert GetLatestBuildNumberAction.get_latest_build_number(action,'1234567890',all_versions=True) is None
assert stdout.getvalue()==''

def fake_request(url,headers_factory):
    headers=headers_factory()
    token=headers['Authorization'].removeprefix('Bearer ')
    payload=jwt.decode(token,key.public_key(),algorithms=['ES256'],audience='appstoreconnect-v1')
    assert payload['iss']=='synthetic-issuer' and jwt.get_unverified_header(token)['kid']=='synthetic-key-id'
    return {'data':[],'links':{}}

with patch.dict(prepare.os.environ,{'APP_STORE_CONNECT_PRIVATE_KEY':private,
        'APP_STORE_CONNECT_KEY_IDENTIFIER':'synthetic-key-id','APP_STORE_CONNECT_ISSUER_ID':'synthetic-issuer'}):
    prepare.use_cli_python_if_needed()
    with patch.object(prepare,'request_json',side_effect=fake_request):
        assert prepare.apple_fetcher()(prepare.API_URL+'/builds')['data']==[]

result={'codemagicSdkVersion':importlib.metadata.version('codemagic-cli-tools'),
        'emptyLatestBuildSummaryReproduced':True,
        'realSdkImportVerified':True,'syntheticEs256SignatureVerified':True,
        'realAppleRequestMade':False,'privateKeyWrittenToDisk':False,'appleUploadConfirmed':False}
(ROOT/'qa-tools/test-apple-sdk-build3-results.json').write_text(json.dumps(result,indent=2),encoding='utf-8')
print(json.dumps(result))
