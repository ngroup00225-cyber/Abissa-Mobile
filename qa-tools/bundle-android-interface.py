"""Portable shared interface packaging; no compilation, signing or private data."""
from pathlib import Path
import subprocess,os,zipfile
root=Path(__file__).resolve().parents[1]
# Bundle all first-launch modules without a network dependency.
import base64,re
mobile=(root/'deploy-cpanel/mobile.html').read_text(encoding='utf-8')
mobile=re.sub(r'<(?:script|link)\b[^>]*\bdata-web-only="true"[^>]*>(?:</script>)?', '', mobile)
for script in ('request-forms.js','committee-data.js','committee-view.js','programme-data.js','programme-view.js','learning-data.js','learning-v8-data.js','learning-v8.js','learning-view.js','feedback.js','refonte-2026.js','experience-201.js','assets/leaflet/leaflet.js','evolution-203.js','refresh-204.js','release-208.js','release-209.js','badge-designer.js','community-mobile.js','release-211.js','release-212.js'):
    pattern=r'<script src="'+re.escape(script)+r'(?:\?[^"]*)?"></script>'
    mobile,count=re.subn(pattern,lambda m:'<script>'+(root/'deploy-cpanel'/script).read_text(encoding='utf-8')+'</script>',mobile)
    assert count==1,(script,count)
for sheet in ('committee-view.css','programme-view.css','learning-view.css','learning-v8.css','feedback.css','refonte-2026.css','experience-201.css','assets/leaflet/leaflet.css','evolution-203.css','palace-204.css','refresh-204.css','release-209.css','community.css','release-211.css','release-212.css'):
    pattern=r'<link rel="stylesheet" href="'+re.escape(sheet)+r'(?:\?[^"]*)?">'
    css=(root/'deploy-cpanel'/sheet).read_text(encoding='utf-8')
    # Inlined styles retain the URL base of their original stylesheet.
    def css_url(match):
        value=match.group(1).strip().strip('"\'')
        if value.startswith(('data:','http:','https:','/','#')):return match.group(0)
        from posixpath import normpath
        target=normpath(str(Path(sheet).parent.as_posix())+'/'+value)
        return 'url("'+target+'")'
    css=re.sub(r'url\(([^)]+)\)',css_url,css)
    mobile,count=re.subn(pattern,lambda m:'<style>'+css+'</style>',mobile)
    assert count==1,(sheet,count)
sfx={p.stem:'data:audio/wav;base64,'+base64.b64encode(p.read_bytes()).decode() for p in (root/'deploy-cpanel/assets/nzema-v8/sfx').glob('*.wav')}
import json
assert len(sfx)==5
mobile=mobile.replace('</head>','<script>window.ABISSA_V8_SFX='+json.dumps(sfx)+';</script></head>')
icons=(root/'deploy-cpanel/assets/icons/remixicon.css').read_text(encoding='utf-8')
font=base64.b64encode((root/'deploy-cpanel/assets/icons/remixicon.woff2').read_bytes()).decode()
icons=re.sub(r'@font-face\s*\{.*?\}', '@font-face{font-family:remixicon;src:url(data:font/woff2;base64,'+font+') format("woff2");font-display:swap}',icons,flags=re.S)
mobile=mobile.replace('<link rel="stylesheet" href="assets/icons/remixicon.css">','<style>'+icons+'</style>')
for key,name in [('CHART','organigramme-abissa-officiel-2026.png'),('PROGRAMME','programme-officiel-2026.png'),('LOGO','logo-evenement-2026.png'),('MOBILE_LOGO','logo-abissa-mobile.png'),('BADGE_LOGO','logo-badge-default-2026.png')]:
    data='data:image/png;base64,'+base64.b64encode((root/'deploy-cpanel/assets'/name).read_bytes()).decode()
    mobile=mobile.replace('</head>','<script>window.ABISSA_OFFLINE_'+key+'="'+data+'";</script></head>')
    if key=='LOGO':mobile=mobile.replace('id="homeLogo" src="assets/logo-site-2026.png"','id="homeLogo" src="'+data+'"')
assert not re.search(r'<script src="(?:committee-|programme-|learning-|feedback|request-forms)',mobile)
# Bundle raster assets once as Android assets. The trusted WebView origin stays unchanged.
import shutil,json
refonte=root/'app/src/main/assets/refonte-2026';refonte.mkdir(exist_ok=True)
asset_map={}
for image in (root/'deploy-cpanel/assets/refonte-2026').glob('*.png'):
    if image.stem=='splash':continue
    shutil.copy2(image,refonte/image.name)
    asset_map[image.stem]='https://abissamobile.com/appli/bundled-refonte/'+image.name
    mobile=mobile.replace('assets/refonte-2026/'+image.name,asset_map[image.stem])
mobile=mobile.replace('</head>','<script>window.ABISSA_REFONTE_ASSETS='+json.dumps(asset_map)+';</script></head>')
# Keep large media out of the HTML/Java string; WebView reads these assets as streams.
import hashlib
media_dir=root/'app/src/main/assets/bundled-media';media_dir.mkdir(exist_ok=True)
mime_extensions={'image/png':'png','image/webp':'webp','audio/wav':'wav','font/woff2':'woff2'}
media_manifest={}
def externalize_media(match):
    mime,encoded=match.groups()
    if mime not in mime_extensions:return match.group(0)
    blob=base64.b64decode(encoded,validate=True)
    digest=hashlib.sha256(blob).hexdigest();name=digest+'.'+mime_extensions[mime]
    (media_dir/name).write_bytes(blob)
    url='https://abissamobile.com/appli/bundled-media/'+name
    media_manifest[name]={'mime':mime,'bytes':len(blob),'sha256':digest,'url':url}
    return url
# Stream local colour icons and decorative assets on first launch.
new_assets={}
for asset in [*(root/'deploy-cpanel/assets/fluent-color').glob('*.svg'),root/'deploy-cpanel/assets/refonte-2026/coast.png']:
    blob=asset.read_bytes();mime='image/svg+xml' if asset.suffix=='.svg' else 'image/png';digest=hashlib.sha256(blob).hexdigest();name=digest+asset.suffix
    (media_dir/name).write_bytes(blob);url='https://abissamobile.com/appli/bundled-media/'+name
    new_assets[asset.relative_to(root/'deploy-cpanel/assets').as_posix()]=url
    media_manifest[name]={'mime':mime,'bytes':len(blob),'sha256':digest,'url':url}
mobile=mobile.replace('</head>','<script>window.ABISSA_211_ASSETS='+json.dumps(new_assets)+';</script></head>')
original_bytes=len(mobile.encode('utf-8'))
mobile=re.sub(r'data:([a-zA-Z0-9.+-]+/[a-zA-Z0-9.+-]+);base64,([A-Za-z0-9+/=]+)',externalize_media,mobile)
assert len(mobile.encode('utf-8'))<3*1024*1024
(root/'qa-tools/bundled-media-212.json').write_text(json.dumps({'originalHtmlBytes':original_bytes,'htmlBytes':len(mobile.encode('utf-8')),'assets':media_manifest},indent=2),encoding='utf-8')
print('Startup HTML:',original_bytes,'->',len(mobile.encode('utf-8')),'bytes;',len(media_manifest),'streamed media assets')
(root/'app/src/main/assets/abissa_mobile.html').write_text(mobile,encoding='utf-8')

shutil.copytree(root/'deploy-cpanel/assets',root/'app/src/main/assets/assets',dirs_exist_ok=True)
