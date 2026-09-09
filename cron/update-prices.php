<?php
declare(strict_types=1);

$base = dirname(__DIR__);

/* Empêche deux exécutions cron de crawler les mêmes sources en parallèle. */
$lockPath = __DIR__ . '/private/update-prices.lock';
$lockHandle = fopen($lockPath, 'c');
if ($lockHandle === false) {
    throw new RuntimeException('Unable to open cron lock: ' . $lockPath);
}
if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    echo "SKIP: update-prices already running\n";
    exit(0);
}
register_shutdown_function(static function () use ($lockHandle): void {
    @flock($lockHandle, LOCK_UN);
    @fclose($lockHandle);
});

$startedAt = microtime(true);
$crawlBudgetSeconds = 60;
$deadline = $startedAt + $crawlBudgetSeconds;
if (function_exists('set_time_limit')) {
    @set_time_limit(75);
}

require_once __DIR__ . '/bootstrap-public-data.php';
rgpd_ensure_public_data($base);

$observationsFile = __DIR__ . '/private/observations-private.json';
$rulesFile = __DIR__ . '/private/crawl-rules.json';
$lastRunFile = __DIR__ . '/last-run.json';

$observations = json_decode((string) file_get_contents($observationsFile), true, 512, JSON_THROW_ON_ERROR);
$rules = json_decode((string) file_get_contents($rulesFile), true, 512, JSON_THROW_ON_ERROR);

if (!isset($observations['observations']) || !is_array($observations['observations'])) {
    throw new RuntimeException('observations-private.json: observations missing or invalid');
}
if (!isset($rules['rules']) || !is_array($rules['rules'])) {
    throw new RuntimeException('crawl-rules.json: rules missing or invalid');
}

$userAgent = (string) ($rules['user_agent'] ?? 'RGPD-Tariff-Crawler/1.0');
$delayMs = max(0, min(1200, (int) ($rules['delay_ms'] ?? 0)));
$timeout = max(2, min(6, (int) ($rules['timeout_seconds'] ?? 6)));
$events = [];

function rgpd_get_url(string $url, string $userAgent, int $timeout): string|false
{
    $context = stream_context_create([
        'http' => [
            'timeout' => $timeout,
            'follow_location' => 1,
            'max_redirects' => 4,
            'header' => "User-Agent: {$userAgent}\r\nAccept: text/html\r\n",
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    return @file_get_contents($url, false, $context);
}

function rgpd_allowed(string $url, string $userAgent, int $timeout): bool
{
    $parts = parse_url($url);
    if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
        return false;
    }

    $robotsUrl = $parts['scheme'] . '://' . $parts['host'] . '/robots.txt';
    $robots = rgpd_get_url($robotsUrl, $userAgent, $timeout);
    if ($robots === false) {
        return true;
    }

    $path = strtolower((string) ($parts['path'] ?? '/'));
    $active = false;
    foreach (preg_split('/\R/', strtolower($robots)) ?: [] as $line) {
        $line = trim((string) preg_replace('/#.*/', '', $line));
        if (str_starts_with($line, 'user-agent:')) {
            $active = trim(substr($line, 11)) === '*';
            continue;
        }
        if ($active && str_starts_with($line, 'disallow:')) {
            $rule = trim(substr($line, 9));
            if ($rule !== '' && str_starts_with($path, $rule)) {
                return false;
            }
        }
    }
    return true;
}

function rgpd_text_only(string $html): string
{
    $html = preg_replace('#<(script|style|noscript)\b[^>]*>.*?</\1>#is', ' ', $html) ?? $html;
    return preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '';
}

function rgpd_number(string $value): ?float
{
    $value = preg_replace('/[\s\x{00A0}.]/u', '', $value) ?? $value;
    $value = str_replace(',', '.', $value);
    return is_numeric($value) ? (float) $value : null;
}

function rgpd_atomic_json_write(string $path, array $data): void
{
    $tmp = $path . '.tmp.' . getmypid();
    $json = json_encode(
        $data,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ) . "\n";

    if (file_put_contents($tmp, $json, LOCK_EX) === false || !rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException('Atomic JSON write failed: ' . $path);
    }
}

$index = [];
foreach ($observations['observations'] as $i => $observation) {
    if (isset($observation['id'])) {
        $index[(string) $observation['id']] = $i;
    }
}

foreach ($rules['rules'] as $rule) {
    if (microtime(true) >= $deadline) {
        $events[] = ['status' => 'budget_exhausted', 'budget_seconds' => $crawlBudgetSeconds];
        break;
    }

    $url = (string) ($rule['url'] ?? '');
    if ($url === '') {
        $events[] = ['status' => 'invalid_rule', 'reason' => 'missing_url'];
        continue;
    }

    if (!rgpd_allowed($url, $userAgent, $timeout)) {
        $events[] = ['url' => $url, 'status' => 'robots'];
        continue;
    }

    if (microtime(true) >= $deadline) {
        $events[] = ['url' => $url, 'status' => 'budget_exhausted', 'budget_seconds' => $crawlBudgetSeconds];
        break;
    }

    $html = rgpd_get_url($url, $userAgent, $timeout);
    if ($html === false) {
        $events[] = ['url' => $url, 'status' => 'fetch_failed'];
        continue;
    }

    $text = rgpd_text_only($html);
    $pattern = (string) ($rule['pattern'] ?? '');
    if ($pattern === '' || !preg_match('/' . $pattern . '/iu', $text, $matches)) {
        $events[] = ['url' => $url, 'status' => 'pattern_failed'];
        continue;
    }

    $updated = [];
    foreach (($rule['updates'] ?? []) as $update) {
        $id = (string) ($update['id'] ?? '');
        $capture = (int) ($update['capture'] ?? -1);
        if ($id === '' || !isset($index[$id], $matches[$capture])) {
            continue;
        }

        $candidate = rgpd_number((string) $matches[$capture]);
        $i = $index[$id];
        $old = (float) ($observations['observations'][$i]['value'] ?? 0);

        if (!$candidate || ($old > 0 && abs($candidate - $old) / $old > 0.70)) {
            $events[] = [
                'id' => $id,
                'status' => 'manual_review',
                'old' => $old,
                'candidate' => $candidate,
            ];
            continue;
        }

        $observations['observations'][$i]['value'] = $candidate;
        $observations['observations'][$i]['checked'] = date('Y-m-d');
        $updated[] = ['id' => $id, 'old' => $old, 'new' => $candidate];
    }

    $events[] = ['url' => $url, 'status' => 'ok', 'updated' => $updated];

    $remainingUs = (int) max(0, ($deadline - microtime(true)) * 1_000_000);
    if ($remainingUs > 0 && $delayMs > 0) {
        usleep(min($delayMs * 1000, $remainingUs));
    }
}

/*
 * Source de vérité : observations-private.json.
 * On ne publie plus jamais un sources.json intermédiaire avec l'ancien schéma.
 * lib-comparator.php reconstruit directement et atomiquement le schéma public
 * attendu par le front (views.france / grand-est / moselle).
 */
$observations['generated_at'] = date('Y-m-d');
rgpd_atomic_json_write($observationsFile, $observations);

rgpd_atomic_json_write($lastRunFile, [
    'finished_at' => date(DATE_ATOM),
    'duration_seconds' => round(microtime(true) - $startedAt, 3),
    'network_timeout_seconds' => $timeout,
    'observation_count' => count($observations['observations']),
    'events' => $events,
]);

require_once __DIR__ . '/lib-comparator.php';
$public = rgpd_rebuild_public($base);
$franceCount = (int) ($public['views']['france']['underlying_observation_count'] ?? 0);

echo "OK — {$franceCount} observations publiées\n";
