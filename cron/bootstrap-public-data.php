<?php
declare(strict_types=1);

/**
 * Ensure the generated public comparator JSON exists before a cron tries to read it.
 * The generated file stays runtime-only and remains ignored by Git.
 */
function rgpd_ensure_public_data(string $base): string
{
    $publicFile = $base . '/comparateur/data/sources.json';

    if (is_file($publicFile) && filesize($publicFile) > 0) {
        return $publicFile;
    }

    $seedFile = $base . '/comparateur/data/sources.example.json';
    if (!is_file($seedFile)) {
        throw new RuntimeException('Missing comparator seed: ' . $seedFile);
    }

    $dir = dirname($publicFile);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Unable to create comparator data directory: ' . $dir);
    }

    $seed = file_get_contents($seedFile);
    if ($seed === false || json_decode($seed, true) === null) {
        throw new RuntimeException('Invalid comparator seed JSON: ' . $seedFile);
    }

    $tmp = $publicFile . '.bootstrap.tmp';
    if (file_put_contents($tmp, $seed, LOCK_EX) === false || !rename($tmp, $publicFile)) {
        @unlink($tmp);
        throw new RuntimeException('Unable to bootstrap comparator public data: ' . $publicFile);
    }

    return $publicFile;
}
