<?php
declare(strict_types=1);

$base = dirname(__DIR__);
require __DIR__ . '/bootstrap-public-data.php';
rgpd_ensure_public_data($base);
require __DIR__ . '/lib-comparator.php';
rgpd_rebuild_public($base);
echo "OK\n";
