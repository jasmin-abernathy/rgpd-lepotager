<?php
declare(strict_types=1);

// L'index d'origine est découpé en fragments pour rester versionnable proprement
// via l'API GitHub. Les fragments sont bloqués en accès direct par .htaccess.
foreach (glob(__DIR__ . '/index.part*.php') ?: [] as $part) {
    require $part;
}
