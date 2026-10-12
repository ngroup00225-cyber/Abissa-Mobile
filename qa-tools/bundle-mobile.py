"""Bundle the current shared interface for Android and iOS without copying server data or secrets."""
from pathlib import Path
import re,shutil,json,base64,hashlib
root=Path(__file__).resolve().parents[1]
import runpy
runpy.run_path(str(root/'qa-tools/bundle-android-interface.py'))

destination=root/'ios/AbissaMobile/Web';destination.mkdir(parents=True,exist_ok=True)
mobile=(root/'app/src/main/assets/abissa_mobile.html').read_text(encoding='utf-8')
mobile=mobile.replace('https://abissamobile.com/appli/bundled-media/','abissa-resource://app/bundled-media/').replace('https://abissamobile.com/appli/bundled-refonte/','abissa-resource://app/refonte-2026/')
(destination/'abissa_mobile.html').write_text(mobile,encoding='utf-8')
for folder in ['bundled-media','refonte-2026']:
 shutil.copytree(root/'app/src/main/assets'/folder,destination/folder,dirs_exist_ok=True)
# Include local fonts, official assets and dictionaries for standalone first launch.
shutil.copytree(root/'deploy-cpanel/assets',destination/'assets',dirs_exist_ok=True)
print('Android and iOS interface bundled; private PHP/config/data excluded.')
