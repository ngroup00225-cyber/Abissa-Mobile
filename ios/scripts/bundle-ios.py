"""Build the existing interface for iOS only. Never writes Android or shared source files."""
from pathlib import Path
from posixpath import normpath
import base64,hashlib,json,re,shutil

ROOT=Path(__file__).resolve().parents[2]
SOURCE=ROOT/'deploy-cpanel';DEST=ROOT/'ios/AbissaMobile/Web'

def bundle():
    DEST.mkdir(parents=True,exist_ok=True)
    html=(SOURCE/'mobile.html').read_text(encoding='utf-8')
    html=re.sub(r'<(?:script|link)\b[^>]*\bdata-web-only="true"[^>]*>(?:</script>)?','',html)
    scripts=('request-forms.js','committee-data.js','committee-view.js','programme-data.js','programme-view.js','learning-data.js','learning-v8-data.js','learning-v8.js','learning-view.js','feedback.js','refonte-2026.js','experience-201.js','assets/leaflet/leaflet.js','evolution-203.js','refresh-204.js','release-208.js','release-209.js','badge-designer.js','community-mobile.js','release-211.js','release-212.js')
    for name in scripts:
        html,count=re.subn(r'<script src="'+re.escape(name)+r'(?:\?[^"]*)?"></script>',lambda m:'<script>'+(SOURCE/name).read_text(encoding='utf-8')+'</script>',html)
        if count!=1:raise ValueError('Script absent ou dupliqué : '+name)
    sheets=('committee-view.css','programme-view.css','learning-view.css','learning-v8.css','feedback.css','refonte-2026.css','experience-201.css','assets/leaflet/leaflet.css','evolution-203.css','palace-204.css','refresh-204.css','release-209.css','community.css','release-211.css','release-212.css')
    for name in sheets:
        css=(SOURCE/name).read_text(encoding='utf-8')
        def css_url(match):
            value=match.group(1).strip().strip('"\'')
            if value.startswith(('data:','http:','https:','/','#')):return match.group(0)
            return 'url("'+normpath(Path(name).parent.as_posix()+'/'+value)+'")'
        css=re.sub(r'url\(([^)]+)\)',css_url,css)
        html,count=re.subn(r'<link rel="stylesheet" href="'+re.escape(name)+r'(?:\?[^"]*)?">',lambda m:'<style>'+css+'</style>',html)
        if count!=1:raise ValueError('Style absent ou dupliqué : '+name)
    shutil.copytree(SOURCE/'assets',DEST/'assets',dirs_exist_ok=True)
    refonte={p.stem:'abissa-resource://app/assets/refonte-2026/'+p.name for p in (SOURCE/'assets/refonte-2026').glob('*.png') if p.stem!='splash'}
    offline={key:'abissa-resource://app/assets/'+name for key,name in [('CHART','organigramme-abissa-officiel-2026.png'),('PROGRAMME','programme-officiel-2026.png'),('LOGO','logo-evenement-2026.png'),('MOBILE_LOGO','logo-abissa-mobile.png'),('BADGE_LOGO','logo-badge-default-2026.png')]}
    assets={p.relative_to(SOURCE/'assets').as_posix():'abissa-resource://app/assets/'+p.relative_to(SOURCE/'assets').as_posix() for p in [*(SOURCE/'assets/fluent-color').glob('*.svg'),SOURCE/'assets/refonte-2026/coast.png']}
    sfx={p.stem:'abissa-resource://app/assets/nzema-v8/sfx/'+p.name for p in (SOURCE/'assets/nzema-v8/sfx').glob('*.wav')}
    seeds='<script>window.ABISSA_REFONTE_ASSETS='+json.dumps(refonte)+';window.ABISSA_211_ASSETS='+json.dumps(assets)+';window.ABISSA_V8_SFX='+json.dumps(sfx)+';'+''.join('window.ABISSA_OFFLINE_'+k+'='+json.dumps(v)+';' for k,v in offline.items())+'</script>'
    html=html.replace('</head>',seeds+'</head>')
    # The original app embeds several data images; keep them as local streamed resources.
    media=DEST/'bundled-media';media.mkdir(exist_ok=True)
    def externalize(match):
        mime,encoded=match.groups();suffix={'image/png':'png','image/webp':'webp','audio/wav':'wav','font/woff2':'woff2'}.get(mime)
        if not suffix:return match.group(0)
        data=base64.b64decode(encoded,validate=True);name=hashlib.sha256(data).hexdigest()+'.'+suffix
        (media/name).write_bytes(data);return 'abissa-resource://app/bundled-media/'+name
    html=re.sub(r'data:([a-zA-Z0-9.+-]+/[a-zA-Z0-9.+-]+);base64,([A-Za-z0-9+/=]+)',externalize,html)
    # Legacy hidden map frames must not initiate third-party navigation on first launch.
    html=re.sub(r'(<iframe\b[^>]*\bid="mapFrame"[^>]*?)\bsrc="[^"]*"',r'\1src="about:blank"',html)
    html=html.replace('</body>','<script src="ios-review.js"></script></body>')
    (DEST/'abissa_mobile.html').write_text(html,encoding='utf-8')
    print('iOS interface bundled; Android and shared sources unchanged.')

if __name__=='__main__':bundle()
