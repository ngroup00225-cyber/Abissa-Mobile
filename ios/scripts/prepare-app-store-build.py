"""Read complete Apple collections; only authenticated empty collections mean no builds."""
from pathlib import Path
from urllib import error, parse, request
import json
import os
import re
import shlex
import shutil
import sys

ROOT = Path(__file__).resolve().parents[2]
BUNDLE_ID = 'ci.abissa'
TEAM_ID = 'XY97926U94'
INITIAL_BUILD = 25
API_URL = 'https://api.appstoreconnect.apple.com/v1'


def decode_json(payload, context):
    try:
        return json.loads(payload)
    except (ValueError, UnicodeError):
        raise ValueError(f'{context}: response is not valid JSON; no build assigned.') from None


def valid_url(url, path):
    if not isinstance(url, str):
        raise ValueError('Apple pagination: next link must be a URL string.')
    parts = parse.urlsplit(url)
    if (parts.scheme != 'https' or parts.netloc != 'api.appstoreconnect.apple.com'
            or parts.path != path or parts.fragment):
        raise ValueError('Apple pagination: next link has an unexpected origin or collection path.')
    return url


def collect_pages(url, get_json, resource_type):
    """Follow raw API links.next; never accept a partial or malformed collection."""
    path = parse.urlsplit(url).path
    filters = {key: value for key, value in parse.parse_qs(parse.urlsplit(url).query).items()
               if key.startswith('filter[')}
    resources, seen_urls, seen_ids, totals = [], set(), set(), set()
    while url:
        valid_url(url, path)
        if url in seen_urls or len(seen_urls) >= 1000:
            raise ValueError(f'Apple {resource_type}: pagination cycle or page limit exceeded.')
        seen_urls.add(url)
        payload = get_json(url)
        context = f'Apple {resource_type}, page {len(seen_urls)}'
        if not isinstance(payload, dict) or 'errors' in payload:
            raise ValueError(f'{context}: error response or invalid collection object.')
        if not isinstance(payload.get('data'), list):
            raise ValueError(f'{context}: data must be an array, not missing/null/a scalar.')
        if not isinstance(payload.get('links'), dict):
            raise ValueError(f'{context}: links object missing; completeness cannot be verified.')
        if 'meta' in payload:
            meta = payload['meta']
            if not isinstance(meta, dict):
                raise ValueError(f'{context}: meta must be an object.')
            if 'paging' in meta:
                paging = meta['paging']
                if not isinstance(paging, dict):
                    raise ValueError(f'{context}: meta.paging must be an object.')
                if 'total' in paging:
                    total = paging['total']
                    if type(total) is not int or total < 0:
                        raise ValueError(f'{context}: meta.paging.total must be a nonnegative integer.')
                    totals.add(total)
        for resource in payload['data']:
            if (not isinstance(resource, dict) or resource.get('type') != resource_type
                    or not isinstance(resource.get('id'), str) or not resource['id']
                    or not isinstance(resource.get('attributes'), dict)):
                raise ValueError(f'{context}: malformed resource (type/id/attributes).')
            if resource['id'] in seen_ids:
                raise ValueError(f'{context}: duplicate resource across pages; retry after Apple settles.')
            seen_ids.add(resource['id'])
            resources.append(resource)
        url = payload['links'].get('next')
        if url is not None:
            valid_url(url, path)
            parts = parse.urlsplit(url)
            query = parse.parse_qs(parts.query, keep_blank_values=True)
            for key, value in filters.items():
                if key in query and query[key] != value:
                    raise ValueError(f'{context}: next page changes the original application/platform filter.')
                query[key] = value
            url = parse.urlunsplit(parts._replace(query=parse.urlencode(query, doseq=True)))
    if totals and (len(totals) != 1 or len(resources) != next(iter(totals))):
        raise ValueError(f'Apple {resource_type}: pagination total changed or collection is incomplete.')
    return resources, len(seen_urls)


class NoRedirect(request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def request_json(url, headers_factory, opener=None):
    """Read raw responses without recording auth headers or private API diagnostics."""
    valid_url(url, parse.urlsplit(url).path)
    context = f'Apple GET {parse.urlsplit(url).path}'
    try:
        headers = {**headers_factory(), 'Accept': 'application/json'}
    except Exception:
        raise RuntimeError('Abissa-Mobile-Apple: cannot authenticate with the existing integration key.') from None
    try:
        req = request.Request(url, headers=headers)
        with (opener or request.build_opener(NoRedirect())).open(req, timeout=45) as response:
            if response.status != 200:
                raise RuntimeError(f'{context}: unexpected HTTP {response.status}; no build assigned.')
            return decode_json(response.read(), context)
    except error.HTTPError as failure:
        hints = {401: 'authentication rejected', 403: 'application access denied',
                 404: 'application or endpoint not found', 429: 'Apple rate limit exceeded'}
        hint = hints.get(failure.code, 'Apple request failed')
        raise RuntimeError(f'{context}: HTTP {failure.code}, {hint}; no build assigned.') from None
    except (error.URLError, TimeoutError):
        raise RuntimeError(f'{context}: network/TLS error or 45-second timeout; no build assigned.') from None


def integration_value(name):
    value = os.environ.get(name, '')
    if value.startswith('@env:'):
        value = os.environ.get(value[5:], '')
    elif value.startswith('@file:'):
        try:
            value = Path(value[6:]).read_text(encoding='utf-8')
        except OSError:
            raise RuntimeError(f'Abissa-Mobile-Apple: cannot read the configured {name} file.') from None
    if not value.strip():
        raise RuntimeError(f'Abissa-Mobile-Apple: required integration variable {name} is missing.')
    return value.strip()


def apple_fetcher():
    # Existing Codemagic CLI tools provide the Apple SDK/JWT signer. Never create keys.
    try:
        from codemagic.apple import AppStoreConnectApiClient
    except ImportError:
        raise RuntimeError('The existing Codemagic CLI Python runtime is unavailable; no Apple query was made.') from None
    client = AppStoreConnectApiClient(
        key_identifier=integration_value('APP_STORE_CONNECT_KEY_IDENTIFIER'),
        issuer_id=integration_value('APP_STORE_CONNECT_ISSUER_ID'),
        private_key=integration_value('APP_STORE_CONNECT_PRIVATE_KEY'),
        log_requests=False, enable_jwt_cache=False,
    )
    return lambda url: request_json(url, client.generate_auth_headers)


def app_identifier(resources):
    if len(resources) != 1 or resources[0]['attributes'].get('bundleId') != BUNDLE_ID:
        raise ValueError('Apple apps: expected exactly one application with Bundle ID ci.abissa.')
    app_id = resources[0]['id']
    if not re.fullmatch(r'[0-9]+', app_id):
        raise ValueError('Apple apps: application ID must be a numeric string.')
    return app_id


def build_key(value, context='Apple build attributes.version'):
    # Apple attributes.version is the build string, not the marketing version.
    if not isinstance(value, str) or not re.fullmatch(r'[0-9]{1,4}(?:\.[0-9]{1,2}){0,2}', value):
        raise ValueError(f'{context}: expected a numeric build string with one to three components; received {type(value).__name__}.')
    parts = tuple(int(part) for part in value.split('.'))
    if parts[0] < 1:
        raise ValueError(f'{context}: first build component must be positive.')
    return parts + (0,) * (3 - len(parts))


def next_build(builds, minimum, ci_number):
    if type(minimum) is not int or minimum < INITIAL_BUILD:
        raise ValueError('The configured initial iOS build must be at least 25.')
    if not isinstance(ci_number, str) or not re.fullmatch(r'[0-9]+', ci_number):
        raise ValueError('Codemagic BUILD_NUMBER is missing or invalid; no build assigned.')
    latest = None
    states = {}
    for resource in builds:
        attributes = resource['attributes']
        value = attributes.get('version')
        key = build_key(value)
        state = attributes.get('processingState')
        if state not in ('PROCESSING', 'VALID', 'FAILED', 'INVALID'):
            raise ValueError('Apple build attributes.processingState is missing or unknown; no build ignored.')
        states[state] = states.get(state, 0) + 1
        if latest is None or key > build_key(latest):
            latest = value
    # Exceed every known build for this app, hence also those of this marketing version.
    number = max(minimum, INITIAL_BUILD + int(ci_number), build_key(latest)[0] + 1 if latest else minimum)
    if number > 9999:
        raise ValueError('No larger integer CFBundleVersion within 1..9999; review the versioning policy manually.')
    return number, latest, states


def prepare(root=ROOT, get_json=None):
    config = root / 'ios/Config/Shared.xcconfig'
    text = config.read_text(encoding='utf-8')
    settings = dict(re.findall(r'^([A-Z_]+)\s*=\s*(.+)$', text, re.M))
    if settings.get('PRODUCT_BUNDLE_IDENTIFIER') != BUNDLE_ID or settings.get('DEVELOPMENT_TEAM') != TEAM_ID:
        raise ValueError('The configured iOS bundle or Apple team differs from the authorized identity.')
    version = settings.get('MARKETING_VERSION')
    if not isinstance(version, str) or not re.fullmatch(r'[0-9]+(?:\.[0-9]+){0,2}', version):
        raise ValueError('The Xcode marketing version is missing or invalid.')
    minimum = int(settings['CURRENT_PROJECT_VERSION'])
    get_json = get_json or apple_fetcher()
    apps, app_pages = collect_pages(API_URL + '/apps?' + parse.urlencode({
        'filter[bundleId]': BUNDLE_ID, 'fields[apps]': 'bundleId', 'limit': 200}), get_json, 'apps')
    app_id = app_identifier(apps)
    # No version/platform/state/expiry filters: include orphan processing builds too.
    builds, build_pages = collect_pages(API_URL + '/builds?' + parse.urlencode({
        'filter[app]': app_id, 'fields[builds]': 'version,processingState', 'limit': 200}), get_json, 'builds')
    number, latest, states = next_build(builds, minimum, os.environ.get('BUILD_NUMBER', ''))
    records, version_pages = collect_pages(API_URL + f'/apps/{app_id}/appStoreVersions?' + parse.urlencode({
        'filter[platform]': 'IOS', 'fields[appStoreVersions]': 'versionString', 'limit': 200}),
        get_json, 'appStoreVersions')
    apple_versions = []
    for record in records:
        value = record['attributes'].get('versionString')
        if not isinstance(value, str) or not re.fullmatch(r'[0-9]+(?:\.[0-9]+){0,2}', value):
            raise ValueError('Apple appStoreVersions.attributes.versionString is missing or invalid.')
        apple_versions.append(value)
    updated, count = re.subn(r'^CURRENT_PROJECT_VERSION\s*=.*$', f'CURRENT_PROJECT_VERSION = {number}', text, flags=re.M)
    if count != 1:
        raise ValueError('Expected one CURRENT_PROJECT_VERSION setting.')
    metadata = {
        'bundleIdentifier': BUNDLE_ID, 'teamId': TEAM_ID, 'marketingVersion': version,
        'buildNumber': str(number), 'minimumBuildNumber': minimum,
        'latestAppleBuildNumber': latest, 'appleBuildCount': len(builds), 'appleBuildStates': states,
        'appleHistoryScope': 'all builds of this app; all versions/platforms/states including expired',
        'appleHistoryComplete': True, 'appleBuildPages': build_pages, 'appleAppPages': app_pages,
        'appStoreVersions': sorted(set(apple_versions)), 'appleVersionPages': version_pages,
        'publicVersionAligned': version in apple_versions, 'codemagicBuildNumber': os.environ['BUILD_NUMBER'],
        'appStoreAppleId': app_id, 'integration': 'Abissa-Mobile-Apple',
        'commit': os.environ.get('CM_COMMIT', ''), 'uploadConfirmed': False,
    }
    # No writes until all authenticated collections and their contents pass validation.
    config.write_text(updated, encoding='utf-8')
    (root / 'ios/ci-build-metadata.json').write_text(json.dumps(metadata, indent=2) + '\n', encoding='utf-8')
    print(f'Apple history verified: {len(builds)} builds, {build_pages} page(s), latest={latest!r}, states={states}.')
    if not builds:
        print(f'Authenticated complete build collection is empty; explicit initial floor={minimum}.')
    print(f'iOS {version} ({number}); Bundle ID {BUNDLE_ID}; Team {TEAM_ID}; Codemagic counter {os.environ["BUILD_NUMBER"]}.')
    if version not in apple_versions:
        print(f'Apple public version records {metadata["appStoreVersions"]} do not include {version}; align the public record before a future App Store submission. TestFlight upload remains enabled.')
    return metadata


def use_cli_python_if_needed():
    """Pipx CLI tools may use another Python; reuse their existing interpreter."""
    try:
        import codemagic.apple  # noqa: F401
        return
    except ImportError:
        binary = shutil.which('app-store-connect')
        if binary:
            with open(binary, 'rb') as stream:
                header = stream.readline(1024).decode('utf-8', 'replace').strip()
            if header.startswith('#!'):
                parts = shlex.split(header[2:])
                if (len(parts) == 1 and Path(parts[0]).is_absolute() and Path(parts[0]).is_file()
                        and Path(parts[0]).resolve() != Path(sys.executable).resolve()
                        and not os.environ.get('ABISSA_APPLE_RUNTIME_SELECTED')):
                    os.environ['ABISSA_APPLE_RUNTIME_SELECTED'] = '1'
                    os.execv(parts[0], [parts[0], *sys.argv])
        raise RuntimeError('Cannot use the existing Codemagic CLI Python/Apple SDK; no API call or configuration change was made.') from None


if __name__ == '__main__':
    try:
        use_cli_python_if_needed()
        prepare()
    except (ValueError, RuntimeError, KeyError, OSError) as failure:
        print(f'iOS build preparation stopped: {failure}', file=sys.stderr)
        sys.exit(1)
