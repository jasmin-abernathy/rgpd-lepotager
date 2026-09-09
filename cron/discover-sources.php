<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$base = dirname(__DIR__);
$cfgFile = __DIR__ . '/private/discovery-config.json';
$candFile = __DIR__ . '/private/candidates.json';
$obsFile = __DIR__ . '/private/observations-private.json';
$logFile = __DIR__ . '/discovery-last-run.json';
$lockFile = __DIR__ . '/private/discover-sources.lock';

@set_time_limit(105);
$startedAt = microtime(true);
$deadline = $startedAt + 90.0;

$lock = fopen($lockFile, 'c');
if ($lock === false) {
    throw new RuntimeException('Impossible d’ouvrir le verrou discovery.');
}
if (!flock($lock, LOCK_EX | LOCK_NB)) {
    echo "SKIP: discovery RGPD déjà en cours\n";
    fclose($lock);
    exit(0);
}

function read_json_file(string $path): array
{
    $raw = file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException('Fichier illisible : ' . $path);
    }
    $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($data)) {
        throw new RuntimeException('JSON invalide : ' . $path);
    }
    return $data;
}

function write_json_atomic(string $path, array $data): void
{
    $tmp = $path . '.tmp.' . getmypid();
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    if (file_put_contents($tmp, $json, LOCK_EX) === false || !rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException('Écriture atomique impossible : ' . $path);
    }
}

function discovery_budget_left(float $deadline): float
{
    return $deadline - microtime(true);
}

function canonical(string $url): string
{
    $parts = parse_url($url);
    if (!$parts) {
        return $url;
    }
    $path = rtrim($parts['path'] ?? '', '/');
    return strtolower(($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . $path);
}

function fetch_url(
    string $url,
    int $timeout,
    float $deadline,
    string $ua = 'RGPD-Tariff-Discovery/1.1 (+https://rgpd.lepotager.org/comparateur/)'
): string|false {
    $remaining = discovery_budget_left($deadline);
    if ($remaining <= 0.2) {
        return false;
    }
    $effectiveTimeout = max(1, min($timeout, (int)ceil($remaining)));
    $context = stream_context_create([
        'http' => [
            'timeout' => $effectiveTimeout,
            'follow_location' => 1,
            'max_redirects' => 3,
            'ignore_errors' => false,
            'header' => "User-Agent: {$ua}\r\nAccept: text/html,application/rss+xml,application/xml\r\n",
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    return @file_get_contents($url, false, $context);
}

function textonly(string $html): string
{
    $html = preg_replace('#<(script|style|noscript)\b[^>]*>.*?</\1>#is', ' ', $html) ?? $html;
    return preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '';
}

function prices(string $text): array
{
    preg_match_all('/(?<!\d)(\d{1,3}(?:[\s\x{00A0}.]\d{3})*(?:[,.]\d{1,2})?)\s*€\s*(?:HT|TTC)?(?:\s*\/\s*(?:jour|mois|an|traitement))?/iu', $text, $matches);
    return array_values(array_unique(array_slice($matches[0] ?? [], 0, 12)));
}

function contains_any(string $text, array $terms): bool
{
    $lower = mb_strtolower($text);
    foreach ($terms as $term) {
        if (str_contains($lower, mb_strtolower((string)$term))) {
            return true;
        }
    }
    return false;
}

function detect_region(string $text, array $cfg): array
{
    $lower = mb_strtolower($text);
    foreach ($cfg['regional_terms']['57'] ?? [] as $term) {
        if (str_contains($lower, mb_strtolower((string)$term))) {
            return ['region' => 'moselle', 'department' => '57'];
        }
    }
    foreach ($cfg['regional_terms']['grand-est'] ?? [] as $term) {
        if (str_contains($lower, mb_strtolower((string)$term))) {
            return ['region' => 'grand-est', 'department' => ''];
        }
    }
    return ['region' => 'unknown', 'department' => ''];
}

function classify(string $text, array $cfg): string
{
    if (contains_any($text, $cfg['reject_terms'] ?? [])) return 'reject';
    if (contains_any($text, $cfg['dpo_terms'] ?? [])) return 'dpo';
    if (contains_any($text, $cfg['legal_terms'] ?? [])) return 'legal';
    return contains_any($text, $cfg['service_terms'] ?? []) ? 'market' : 'reject';
}

function bing_rss(string $query, array $cfg, int $timeout, int $maxResults, float $deadline, int &$requestCount): array
{
    if (discovery_budget_left($deadline) <= 0.2) return [];
    $url = 'https://www.bing.com/search?format=rss&q=' . rawurlencode($query);
    $requestCount++;
    $xml = fetch_url($url, $timeout, $deadline);
    if ($xml === false) return [];
    $sx = @simplexml_load_string($xml);
    if (!$sx) return [];
    $out = [];
    foreach ($sx->channel->item ?? [] as $item) {
        if (count($out) >= $maxResults) break;
        $out[] = ['url' => (string)$item->link, 'title' => (string)$item->title, 'snippet' => (string)$item->description];
    }
    return $out;
}

function searxng(string $query, array $cfg, int $timeout, int $maxResults, float $deadline, int &$requestCount): array
{
    $base = rtrim((string)($cfg['searxng_url'] ?? ''), '/');
    if ($base === '' || discovery_budget_left($deadline) <= 0.2) return [];
    $requestCount++;
    $raw = fetch_url($base . '/search?q=' . rawurlencode($query) . '&format=json', $timeout, $deadline);
    if ($raw === false) return [];
    $json = json_decode($raw, true);
    if (!is_array($json)) return [];
    $out = [];
    foreach (array_slice($json['results'] ?? [], 0, $maxResults) as $row) {
        if (!is_array($row)) continue;
        $out[] = ['url' => (string)($row['url'] ?? ''), 'title' => (string)($row['title'] ?? ''), 'snippet' => (string)($row['content'] ?? '')];
    }
    return $out;
}

try {
    $cfg = read_json_file($cfgFile);
    if (empty($cfg['enabled'])) {
        echo "Discovery disabled\n";
        exit(0);
    }

    $requestTimeout = max(2, min(6, (int)($cfg['timeout_seconds'] ?? 6)));
    $maxResults = max(1, min(4, (int)($cfg['max_results_per_query'] ?? 4)));
    $delayMs = max(250, min(1200, (int)($cfg['request_delay_ms'] ?? 900)));

    $cands = read_json_file($candFile);
    $obs = read_json_file($obsFile);
    $cands['candidates'] = is_array($cands['candidates'] ?? null) ? $cands['candidates'] : [];
    $obs['observations'] = is_array($obs['observations'] ?? null) ? $obs['observations'] : [];

    $known = [];
    foreach ($obs['observations'] as $observation) {
        if (!is_array($observation) || empty($observation['source'])) continue;
        $known[canonical((string)$observation['source'])] = true;
    }
    $candIndex = [];
    foreach ($cands['candidates'] as $index => $candidate) {
        if (!is_array($candidate) || empty($candidate['url'])) continue;
        $candIndex[canonical((string)$candidate['url'])] = $index;
    }

    $events = [];
    $requestCount = 0;
    $budgetExhausted = false;
    $queries = [];
    foreach (['moselle', 'grand-est', 'france'] as $zone) {
        foreach ($cfg['queries'][$zone] ?? [] as $query) {
            $queries[] = [$zone, (string)$query];
        }
    }

    foreach ($queries as [$zone, $query]) {
        if (discovery_budget_left($deadline) <= 0.2) {
            $budgetExhausted = true;
            break;
        }
        $results = ($cfg['provider'] ?? '') === 'searxng'
            ? searxng($query, $cfg, $requestTimeout, $maxResults, $deadline, $requestCount)
            : bing_rss($query, $cfg, $requestTimeout, $maxResults, $deadline, $requestCount);

        foreach ($results as $result) {
            if (discovery_budget_left($deadline) <= 0.2) {
                $budgetExhausted = true;
                break 2;
            }
            $url = (string)($result['url'] ?? '');
            if (!filter_var($url, FILTER_VALIDATE_URL)) continue;
            $key = canonical($url);
            if (isset($known[$key]) || isset($candIndex[$key])) continue;

            $requestCount++;
            $page = fetch_url($url, $requestTimeout, $deadline);
            if ($page === false) continue;
            $text = textonly($page);
            if (!contains_any($text, $cfg['service_terms'] ?? [])) continue;

            $class = classify($text, $cfg);
            $region = detect_region($text . ' ' . (string)($result['snippet'] ?? ''), $cfg);
            $priceCandidates = prices($text);
            $status = $class === 'reject' ? 'rejected' : ($priceCandidates ? 'new' : 'monitored_no_price');
            $id = 'auto-' . substr(hash('sha256', $key), 0, 12);
            $entry = [
                'id' => $id,
                'url' => $url,
                'title' => trim((string)($result['title'] ?? '')) ?: (string)parse_url($url, PHP_URL_HOST),
                'status' => $status,
                'region' => $region['region'],
                'department' => $region['department'],
                'city' => '',
                'classification_hint' => $class,
                'price_candidates' => $priceCandidates,
                'reason' => $status === 'new'
                    ? 'Nouvelle source tarifaire détectée automatiquement — validation humaine obligatoire.'
                    : ($status === 'monitored_no_price'
                        ? 'Source RGPD détectée sans tarif exploitable — surveillance maintenue.'
                        : 'Hors périmètre selon les règles déterministes.'),
                'discovered_at' => date('Y-m-d'),
                'source' => 'search:' . $query,
                'search_zone' => $zone,
            ];
            $cands['candidates'][] = $entry;
            $candIndex[$key] = count($cands['candidates']) - 1;
            $events[] = ['status' => 'candidate', 'url' => $url, 'class' => $class, 'region' => $region['region'], 'prices' => $priceCandidates];

            $sleepUs = min($delayMs * 1000, max(0, (int)(discovery_budget_left($deadline) * 1_000_000)));
            if ($sleepUs > 0) usleep($sleepUs);
        }
    }

    $cands['updated_at'] = date(DATE_ATOM);
    write_json_atomic($candFile, $cands);
    write_json_atomic($logFile, [
        'finished_at' => date(DATE_ATOM),
        'duration_seconds' => round(microtime(true) - $startedAt, 3),
        'budget_seconds' => 90,
        'budget_exhausted' => $budgetExhausted,
        'request_timeout_seconds' => $requestTimeout,
        'max_results_per_query' => $maxResults,
        'requests_started' => $requestCount,
        'events' => $events,
    ]);

    echo 'OK — ' . count($events) . ' nouvelle(s) piste(s)';
    if ($budgetExhausted) echo ' — budget atteint, reprise au prochain cron';
    echo "\n";
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
