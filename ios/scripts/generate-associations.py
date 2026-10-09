from pathlib import Path
import argparse,json,re
p=argparse.ArgumentParser(description='Generate domain associations using confirmed identifiers.');p.add_argument('--team-id',required=True);p.add_argument('--bundle-id',default='ci.abissa');p.add_argument('--android-sha256');p.add_argument('--output',required=True);a=p.parse_args()
if not re.fullmatch('[A-Z0-9]{10}',a.team_id):p.error('Team ID Apple attendu : 10 caractères majuscules/chiffres.')
if not re.fullmatch('[A-Za-z0-9.-]+',a.bundle_id):p.error('Bundle ID invalide.')
dest=Path(a.output);dest.mkdir(parents=True,exist_ok=True)
(dest/'apple-app-site-association').write_text(json.dumps({'applinks':{'apps':[],'details':[{'appID':a.team_id+'.'+a.bundle_id,'paths':['/appli/mobile.html']}]}},indent=2),encoding='utf-8')
if a.android_sha256:
 fingerprint=a.android_sha256.upper()
 if not re.fullmatch(r'(?:[A-F0-9]{2}:){31}[A-F0-9]{2}',fingerprint):p.error('Empreinte SHA-256 : 32 octets séparés par des deux-points.')
 (dest/'assetlinks.json').write_text(json.dumps([{'relation':['delegate_permission/common.handle_all_urls'],'target':{'namespace':'android_app','package_name':'com.abissa.mobile','sha256_cert_fingerprints':[fingerprint]}}],indent=2),encoding='utf-8')
print('Associations générées. Contrôler les identifiants et publier en HTTPS à /.well-known/.')
