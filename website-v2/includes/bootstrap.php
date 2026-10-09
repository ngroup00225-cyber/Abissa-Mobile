<?php
declare(strict_types=1);
$siteRoot = dirname(__DIR__);
$stateCandidates = [$siteRoot . '/appli/data/state.json', dirname($siteRoot) . '/deploy-cpanel/data/state.json'];
$state = [];
foreach ($stateCandidates as $candidate) {
    if (is_file($candidate)) {
        $decoded = json_decode((string)file_get_contents($candidate), true);
        if (is_array($decoded)) { $state = $decoded; break; }
    }
}
$committeeModel = is_file($siteRoot.'/appli/api/committee-model.php') ? $siteRoot.'/appli/api/committee-model.php' : dirname($siteRoot).'/deploy-cpanel/api/committee-model.php';
require_once $committeeModel;
$state = abissaEnrichState($state);
$website = array_merge([
    'siteName' => 'Abissa Mobile',
    'logo' => '/appli/assets/logo-site-2026.png',
    'heroTitle' => 'Abissa Mobile',
    'heroText' => 'La tradition N’zima vivante, au cœur de Grand-Bassam.',
    'aboutTitle' => 'Une culture royale, une communauté en mouvement',
    'aboutText' => 'Découvrez l’Abissa, ses temps forts, son histoire et tous les services utiles pour vivre pleinement la célébration.',
    'abissaTitle' => 'L’Abissa',
    'abissaIntro' => 'Une célébration de mémoire, de vérité, de cohésion et de renouveau pour le peuple N’zima.',
    'programmeTitle' => 'Programme de l’Abissa',
    'newsTitle' => 'Actualités',
    'bassamTitle' => 'Grand-Bassam',
    'partnersTitle' => 'Partenaires',
    'contactTitle' => 'Parlons de votre projet',
    'contactEmail' => 'contact@abissamobile.com',
    'contactPhone' => '+225 00 00 00 00 00',
    'seoTitle' => 'Abissa Mobile — Culture N’zima à Grand-Bassam',
    'seoDescription' => 'Découvrez l’Abissa, son programme, ses actualités, les hôtels, restaurants et partenaires à Grand-Bassam.'
], (array)($state['WEBSITE'] ?? []));
function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function items(string $key): array { global $state; return is_array($state[$key] ?? null) ? $state[$key] : []; }
function activeItems(string $key): array { return array_values(array_filter(items($key), fn($item) => is_array($item) && ($item['active'] ?? true) !== false && !($item['hidden'] ?? false))); }
function publicUrl(mixed $value, bool $media = false): string {
    if (!is_string($value)) return '';
    $value = trim($value);
    if ($value === '' || preg_match('/[\x00-\x20\x7f]/', $value)) return '';
    if ($media && preg_match('~^data:image/(?:png|jpe?g|webp);base64,[a-zA-Z0-9+/=]+$~', $value)) return $value;
    if (str_starts_with($value, 'assets/') || str_starts_with($value, 'uploads/')) $value = '/appli/'.$value;
    if ($value[0] === '/' && !str_starts_with($value, '//') && !str_contains($value, '..') && !str_contains($value, '\\')) return $value;
    $parts = parse_url($value);
    return is_array($parts) && in_array(strtolower($parts['scheme'] ?? ''), ['https', 'http'], true) && !empty($parts['host']) && empty($parts['user']) && empty($parts['pass']) ? $value : '';
}
function imageOf(array $item, string $fallback = '/assets/images/hero.png'): string {
    foreach (['img', 'image', 'photo', 'logo'] as $key) if ($url = publicUrl($item[$key] ?? '', true)) return $url;
    return $fallback;
}
function pageUrl(string $page): string { return $page === 'accueil' ? '/' : (in_array($page,['programme','actualites'],true) ? '/abissa/'.$page.'.php' : '/'.$page.'.php'); }
function moduleEnabled(string $page): bool {
    foreach (items('MENU_ITEMS') as $module) if (is_array($module) && ($module['page'] ?? '') === $page) return ($module['active'] ?? true) !== false && !($module['hidden'] ?? false);
    return true;
}
function pageBanner(string $page): array {
    global $state;
    foreach (items('PAGE_BANNERS') as $banner) if (is_array($banner) && ($banner['page'] ?? '') === $page && ($banner['active'] ?? true)) return $banner;
    $banners = (array)($state['SETTINGS']['appBanners'] ?? []);
    $aliases = ['membres'=>'comite', 'partenaires'=>'partners', 'grand-bassam'=>'sites'];
    foreach (array_unique([$page, $aliases[$page] ?? $page]) as $id) {
        if (is_array($banners[$id] ?? null) && ($banners[$id]['active'] ?? true)) return $banners[$id];
        foreach ($banners as $banner) if (is_array($banner) && ($banner['page'] ?? $banner['module'] ?? '') === $id && ($banner['active'] ?? true)) return $banner;
    }
    return [];
}
function pageHeading(string $fallback): string { global $pageBanner; return trim((string)($pageBanner['title'] ?? '')) ?: $fallback; }
function pageSubtitle(string $fallback): string { global $pageBanner; return trim((string)($pageBanner['subtitle'] ?? $pageBanner['description'] ?? '')) ?: $fallback; }
function bannerLink(): string {
    global $pageBanner;
    $url = publicUrl($pageBanner['link'] ?? '');
    return $url ? '<a class="button glass banner-link" href="'.e($url).'" target="_blank" rel="noopener noreferrer">En savoir plus ↗</a>' : '';
}
function phoneUrl(mixed $phone, bool $whatsapp = false): string {
    $number = preg_replace('/[^0-9+]/', '', is_string($phone) ? $phone : '');
    if (!preg_match('/^\+?[0-9]{8,15}$/', $number)) return '';
    return $whatsapp ? 'https://wa.me/'.ltrim($number, '+') : 'tel:'.$number;
}
function emailUrl(mixed $email): string { return is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) ? 'mailto:'.$email : ''; }
function coordinateTarget(array $place): string {
    $lat = $place['latitude'] ?? ''; $lon = $place['longitude'] ?? '';
    if (!is_numeric($lat) || !is_numeric($lon) || abs((float)$lat) > 90 || abs((float)$lon) > 180) return '';
    return (string)(float)$lat.','.(string)(float)$lon;
}
function activeSlides(): array {
    $slides = array_values(array_filter(items('SITE_SLIDES'), fn($slide) => is_array($slide) && (!isset($slide['active']) || $slide['active'])));
    usort($slides, fn($a, $b) => ((int)($a['order'] ?? 0)) <=> ((int)($b['order'] ?? 0)));
    return $slides;
}
function cssImage(string $url): string {
    return $url === '' ? 'none' : 'url("' . str_replace(['\\', '"', "\r", "\n", '<', '>'], ['%5C', '%22', '', '', '%3C', '%3E'], $url) . '")';
}
