<?php
declare(strict_types=1);

if (!defined('RGPD_PAYMENT_BOOTSTRAP')) {
    http_response_code(404);
    exit;
}

const RGPD_INVOICE_STORAGE = '/home/sc1leja3715/stancer-private/private/rgpd-invoices';
const RGPD_INVOICE_SHARE_CENTS = 7500;

function rgpd_invoice_private_config(): array
{
    if (!is_readable(RGPD_STANCER_CONFIG)) {
        throw new RuntimeException('Configuration privée indisponible pour la facturation.');
    }
    $config = require RGPD_STANCER_CONFIG;
    if (!is_array($config)) {
        throw new RuntimeException('Configuration privée invalide pour la facturation.');
    }
    return $config;
}

function rgpd_invoice_issuers(): array
{
    $private = rgpd_invoice_private_config();

    $issuers = [
        'potager' => [
            'prefix' => 'LPW-RGPD',
            'brand' => 'Le Potager du Web',
            'legal_name' => 'Juliane Lévêque',
            'legal_form' => 'Entrepreneur individuel',
            'siret' => '808 087 399 00032',
            'address' => '3 rue Brunehaut',
            'city' => '57000 Metz',
            'country' => 'France',
            'email' => 'contact@lepotager.org',
            'vat_mode' => strtolower(trim((string)($private['rgpd_invoice_potager_vat_mode'] ?? ''))),
        ],
        'prestadmin' => [
            'prefix' => 'PREST-RGPD',
            'brand' => 'Prestadmin',
            'legal_name' => 'Camille Poirel',
            'legal_form' => 'Entrepreneur individuel',
            'siret' => '944 326 917 00019',
            'address' => '3 rue Brunehaut',
            'city' => '57000 Metz',
            'country' => 'France',
            'email' => 'contact@prestadmin57.fr',
            'vat_mode' => strtolower(trim((string)($private['rgpd_invoice_prestadmin_vat_mode'] ?? ''))),
        ],
    ];

    foreach ($issuers as $key => &$issuer) {
        if (!in_array($issuer['vat_mode'], ['franchise', 'vat20'], true)) {
            throw new RuntimeException(
                'Régime TVA à configurer pour ' . $key
                . ' : rgpd_invoice_' . $key . '_vat_mode=franchise ou vat20.'
            );
        }
    }
    unset($issuer);

    return $issuers;
}

function rgpd_invoice_storage_ready(): void
{
    if (!is_dir(RGPD_INVOICE_STORAGE) && !mkdir(RGPD_INVOICE_STORAGE, 0700, true) && !is_dir(RGPD_INVOICE_STORAGE)) {
        throw new RuntimeException('Stockage privé des factures indisponible.');
    }
    @chmod(RGPD_INVOICE_STORAGE, 0700);
    if (!is_writable(RGPD_INVOICE_STORAGE)) {
        throw new RuntimeException('Stockage privé des factures non inscriptible.');
    }
}

function rgpd_invoice_money(int $cents): string
{
    return number_format($cents / 100, 2, ',', ' ') . ' €';
}

function rgpd_invoice_cp1252(string $text): string
{
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);
        if (is_string($converted)) {
            return $converted;
        }
    }
    return preg_replace('/[^\x20-\x7E]/', '?', $text) ?? '';
}

function rgpd_invoice_pdf_escape(string $text): string
{
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], rgpd_invoice_cp1252($text));
}

function rgpd_invoice_wrap(string $text, int $width = 82): array
{
    $encoded = rgpd_invoice_cp1252(trim($text));
    if ($encoded === '') return [''];
    return explode("\n", wordwrap($encoded, $width, "\n", true));
}

function rgpd_invoice_pdf(array $rows): string
{
    $stream = "BT\n50 790 Td\n";
    foreach ($rows as $row) {
        $text = (string)($row['text'] ?? '');
        $size = max(8, min(20, (int)($row['size'] ?? 10)));
        $gap = max(10, min(30, (int)($row['gap'] ?? 14)));
        $stream .= "/F1 {$size} Tf\n(" . str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text) . ") Tj\n0 -{$gap} Td\n";
    }
    $stream .= "ET\n";

    $objects = [
        1 => "<< /Type /Catalog /Pages 2 0 R >>",
        2 => "<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
        3 => "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>",
        4 => "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>",
        5 => "<< /Length " . strlen($stream) . " >>\nstream\n{$stream}endstream",
    ];

    $pdf = "%PDF-1.4\n";
    $offsets = [0 => 0];
    foreach ($objects as $id => $body) {
        $offsets[$id] = strlen($pdf);
        $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 6\n0000000000 65535 f \n";
    for ($i = 1; $i <= 5; $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    return $pdf;
}

function rgpd_invoice_amounts(string $vatMode): array
{
    if ($vatMode === 'franchise') {
        return [
            'net_cents' => RGPD_INVOICE_SHARE_CENTS,
            'vat_cents' => 0,
            'gross_cents' => RGPD_INVOICE_SHARE_CENTS,
            'vat_label' => 'TVA non applicable, art. 293 B du CGI.',
        ];
    }

    if ($vatMode === 'vat20') {
        $net = (int)round(RGPD_INVOICE_SHARE_CENTS / 1.20);
        return [
            'net_cents' => $net,
            'vat_cents' => RGPD_INVOICE_SHARE_CENTS - $net,
            'gross_cents' => RGPD_INVOICE_SHARE_CENTS,
            'vat_label' => 'TVA 20 %.',
        ];
    }

    throw new RuntimeException('Régime TVA non pris en charge.');
}

function rgpd_invoice_rows(array $issuer, string $number, string $issuerKey, string $orderId, array $record): array
{
    $paidAt = (int)($record['paid_at'] ?? time());
    $date = date('d/m/Y', $paidAt);
    $amounts = rgpd_invoice_amounts((string)$issuer['vat_mode']);

    $client = trim((string)($record['company'] ?? '')) !== ''
        ? (string)$record['company']
        : (string)($record['name'] ?? '');
    $rows = [
        ['text' => rgpd_invoice_cp1252('FACTURE ' . $number), 'size' => 18, 'gap' => 24],
        ['text' => rgpd_invoice_cp1252('Émise le ' . $date . ' · Prestation réalisée le ' . $date), 'size' => 9, 'gap' => 20],
        ['text' => rgpd_invoice_cp1252('PRESTATAIRE'), 'size' => 11, 'gap' => 14],
        ['text' => rgpd_invoice_cp1252($issuer['brand'] . ' — ' . $issuer['legal_name']), 'size' => 11, 'gap' => 14],
        ['text' => rgpd_invoice_cp1252($issuer['legal_form'] . ' · SIRET ' . $issuer['siret']), 'size' => 9, 'gap' => 13],
        ['text' => rgpd_invoice_cp1252($issuer['address'] . ' · ' . $issuer['city'] . ' · ' . $issuer['country']), 'size' => 9, 'gap' => 13],
        ['text' => rgpd_invoice_cp1252($issuer['email']), 'size' => 9, 'gap' => 20],
        ['text' => rgpd_invoice_cp1252('CLIENT'), 'size' => 11, 'gap' => 14],
        ['text' => rgpd_invoice_cp1252($client), 'size' => 11, 'gap' => 14],
    ];

    if ($client !== (string)($record['name'] ?? '') && trim((string)($record['name'] ?? '')) !== '') {
        $rows[] = ['text' => rgpd_invoice_cp1252('Contact : ' . (string)$record['name']), 'size' => 9, 'gap' => 13];
    }

    $rows[] = ['text' => rgpd_invoice_cp1252((string)$record['billing_address']), 'size' => 9, 'gap' => 13];
    $rows[] = ['text' => rgpd_invoice_cp1252(trim((string)$record['billing_postcode'] . ' ' . (string)$record['billing_city'])), 'size' => 9, 'gap' => 13];
    if (!empty($record['billing_siren'])) {
        $rows[] = ['text' => rgpd_invoice_cp1252('SIREN client : ' . (string)$record['billing_siren']), 'size' => 9, 'gap' => 13];
    }
    $rows[] = ['text' => '', 'size' => 9, 'gap' => 10];
    $rows[] = ['text' => rgpd_invoice_cp1252('Commande commune : ' . $orderId), 'size' => 9, 'gap' => 14];
    $rows[] = ['text' => rgpd_invoice_cp1252('Prestation : Diagnostic personnalisé RGPD — part ' . $issuer['brand']), 'size' => 10, 'gap' => 16];
    $rows[] = ['text' => rgpd_invoice_cp1252('Quantité : 1'), 'size' => 9, 'gap' => 13];
    $rows[] = ['text' => rgpd_invoice_cp1252('Montant HT : ' . rgpd_invoice_money($amounts['net_cents'])), 'size' => 10, 'gap' => 14];
    $rows[] = ['text' => rgpd_invoice_cp1252($amounts['vat_label'] . ($amounts['vat_cents'] > 0 ? ' Montant TVA : ' . rgpd_invoice_money($amounts['vat_cents']) : '')), 'size' => 9, 'gap' => 14];
    $rows[] = ['text' => rgpd_invoice_cp1252('TOTAL : ' . rgpd_invoice_money($amounts['gross_cents'])), 'size' => 14, 'gap' => 20];
    $rows[] = ['text' => rgpd_invoice_cp1252('Réglé par carte via Stancer dans la commande commune le ' . $date . '.'), 'size' => 9, 'gap' => 14];
    $rows[] = ['text' => rgpd_invoice_cp1252('Net à payer : 0,00 € · Escompte : néant.'), 'size' => 9, 'gap' => 14];

    if (trim((string)($record['company'] ?? '')) !== '' || !empty($record['billing_siren'])) {
        $rows[] = ['text' => rgpd_invoice_cp1252('Indemnité forfaitaire de recouvrement : 40 € en cas de retard (art. D441-5 C. com.).'), 'size' => 8, 'gap' => 13];
    }

    $rows[] = ['text' => rgpd_invoice_cp1252('Référence de paiement : ' . (string)($record['payment_id'] ?? $record['payment_intent_id'] ?? '')), 'size' => 8, 'gap' => 13];

    return $rows;
}

function rgpd_invoice_generate_pair(string $orderId, array $record): array
{
    if (($record['status'] ?? '') !== 'paid') {
        throw new RuntimeException('Les factures ne peuvent être émises qu’après confirmation du paiement.');
    }
    if ((int)($record['amount_cents'] ?? 0) !== RGPD_PAYMENT_PRICE_CENTS || RGPD_PAYMENT_PRICE_CENTS !== RGPD_INVOICE_SHARE_CENTS * 2) {
        throw new RuntimeException('Ventilation de facturation incohérente.');
    }
    foreach (['billing_address', 'billing_postcode', 'billing_city'] as $field) {
        if (trim((string)($record[$field] ?? '')) === '') {
            throw new RuntimeException('Adresse de facturation incomplète.');
        }
    }

    if (!empty($record['invoices']['potager']['relative_path']) && !empty($record['invoices']['prestadmin']['relative_path'])) {
        return $record['invoices'];
    }

    rgpd_invoice_storage_ready();
    $issuers = rgpd_invoice_issuers();
    $year = date('Y', (int)($record['paid_at'] ?? time()));
    $lockPath = RGPD_INVOICE_STORAGE . '/.numbers.lock';
    $lock = fopen($lockPath, 'c+');
    if ($lock === false || !flock($lock, LOCK_EX)) {
        if (is_resource($lock)) fclose($lock);
        throw new RuntimeException('Verrou de numérotation indisponible.');
    }

    $created = [];
    try {
        $counterPath = RGPD_INVOICE_STORAGE . '/counters-' . $year . '.json';
        $counters = [];
        if (is_file($counterPath)) {
            $decoded = json_decode((string)file_get_contents($counterPath), true);
            if (is_array($decoded)) $counters = $decoded;
        }

        $next = [];
        foreach (array_keys($issuers) as $key) {
            $next[$key] = max(0, (int)($counters[$key] ?? 0)) + 1;
        }

        $tempFiles = [];
        foreach ($issuers as $key => $issuer) {
            $number = $issuer['prefix'] . '-' . $year . '-' . str_pad((string)$next[$key], 4, '0', STR_PAD_LEFT);
            $relative = $year . '/' . $key . '/' . $number . '.pdf';
            $dir = dirname(RGPD_INVOICE_STORAGE . '/' . $relative);
            if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
                throw new RuntimeException('Impossible de créer le dossier de facture.');
            }
            @chmod($dir, 0700);

            $final = RGPD_INVOICE_STORAGE . '/' . $relative;
            if (file_exists($final)) {
                throw new RuntimeException('Numéro de facture déjà utilisé.');
            }
            $tmp = $final . '.tmp-' . bin2hex(random_bytes(4));
            $pdf = rgpd_invoice_pdf(rgpd_invoice_rows($issuer, $number, $key, $orderId, $record));
            if (file_put_contents($tmp, $pdf, LOCK_EX) === false) {
                throw new RuntimeException('Écriture de facture impossible.');
            }
            @chmod($tmp, 0600);
            $tempFiles[$key] = ['tmp' => $tmp, 'final' => $final, 'relative' => $relative, 'number' => $number];
        }

        foreach ($tempFiles as $file) {
            if (!rename($file['tmp'], $file['final'])) {
                throw new RuntimeException('Finalisation de facture impossible.');
            }
            @chmod($file['final'], 0600);
            $created[] = $file['final'];
        }

        $newCounters = $counters;
        foreach ($next as $key => $value) $newCounters[$key] = $value;
        $counterTmp = $counterPath . '.tmp-' . bin2hex(random_bytes(4));
        if (file_put_contents($counterTmp, json_encode($newCounters, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX) === false) {
            throw new RuntimeException('Mise à jour de la numérotation impossible.');
        }
        @chmod($counterTmp, 0600);
        if (!rename($counterTmp, $counterPath)) {
            @unlink($counterTmp);
            throw new RuntimeException('Finalisation de la numérotation impossible.');
        }
        @chmod($counterPath, 0600);

        $result = [];
        foreach ($tempFiles as $key => $file) {
            $amounts = rgpd_invoice_amounts((string)$issuers[$key]['vat_mode']);
            $result[$key] = [
                'number' => $file['number'],
                'relative_path' => $file['relative'],
                'amount_cents' => RGPD_INVOICE_SHARE_CENTS,
                'vat_mode' => $issuers[$key]['vat_mode'],
                'net_cents' => $amounts['net_cents'],
                'vat_cents' => $amounts['vat_cents'],
                'issued_at' => time(),
            ];
        }
        return $result;
    } catch (Throwable $e) {
        foreach ($created as $path) @unlink($path);
        throw $e;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function rgpd_invoice_file_path(array $record, string $issuerKey): ?string
{
    $relative = (string)($record['invoices'][$issuerKey]['relative_path'] ?? '');
    if ($relative === '' || str_contains($relative, '..') || str_starts_with($relative, '/')) return null;
    $path = RGPD_INVOICE_STORAGE . '/' . $relative;
    $base = realpath(RGPD_INVOICE_STORAGE);
    $real = is_file($path) ? realpath($path) : false;
    if ($base === false || $real === false || !str_starts_with($real, $base . DIRECTORY_SEPARATOR)) return null;
    return $real;
}
