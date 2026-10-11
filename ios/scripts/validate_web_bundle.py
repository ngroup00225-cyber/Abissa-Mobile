"""Reject missing or incomplete embedded UI before Xcode and again inside the IPA."""
from pathlib import Path
from html.parser import HTMLParser
from urllib.parse import urlsplit, unquote
import hashlib
import json
import re
import sys

ROOT = Path(__file__).resolve().parents[2]


class References(HTMLParser):
    def __init__(self):
        super().__init__()
        self.urls = set()

    def handle_starttag(self, tag, attrs):
        for name, value in attrs:
            if value and ((name == 'src') or (tag == 'link' and name == 'href')):
                self.urls.add(value)


def validate_web_bundle(folder):
    folder = Path(folder).resolve(strict=True)
    entry = folder / 'abissa_mobile.html'
    html = entry.read_text(encoding='utf-8')
    for marker in ['id="menuGrid"', 'id="page-accueil"', 'window.AbissaNative212', 'window.AbissaCommunity']:
        if marker not in html:
            raise ValueError('Embedded UI is incomplete: ' + marker)
    parser = References()
    parser.feed(html)
    parser.urls.update(re.findall(r'abissa-resource://app/[^\s\"\'<>\\)]+', html))
    references = set()
    for value in parser.urls:
        parts = urlsplit(value)
        if parts.scheme == 'abissa-resource':
            if parts.netloc != 'app':
                raise ValueError('Unexpected embedded resource origin.')
        elif parts.scheme or parts.netloc or value.startswith('#'):
            continue
        path = unquote(parts.path).lstrip('/')
        if not path:
            continue
        resolved = (folder / path).resolve()
        if not resolved.is_relative_to(folder) or not resolved.is_file():
            raise ValueError('Missing or unsafe embedded resource: ' + path)
        references.add(path)
    files = []
    for file in sorted(folder.rglob('*')):
        if file.is_file():
            if not file.resolve().is_relative_to(folder):
                raise ValueError('Embedded symlink escapes Web directory.')
            files.append({'path': file.relative_to(folder).as_posix(),
                          'sha256': hashlib.sha256(file.read_bytes()).hexdigest()})
    if not references:
        raise ValueError('No local resources found in embedded UI.')
    return {'files': len(files), 'localReferences': len(references), 'htmlBytes': entry.stat().st_size,
            'resourceManifestSha256': hashlib.sha256(json.dumps(files, sort_keys=True).encode()).hexdigest()}


if __name__ == '__main__':
    folder = Path(sys.argv[1]) if len(sys.argv) == 2 else ROOT / 'ios/AbissaMobile/Web'
    try:
        result = validate_web_bundle(folder)
    except (OSError, ValueError) as error:
        print('ABISSA Web bundle validation failed: ' + str(error), file=sys.stderr)
        sys.exit(1)
    print('ABISSA Web bundle validated: ' + json.dumps(result, sort_keys=True))
