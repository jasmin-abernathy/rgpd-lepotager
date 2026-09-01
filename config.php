<?php
declare(strict_types=1);

/*
 * Configuration du mini-site RGPD Prestadmin × Le Potager.
 * Aucun mot de passe SMTP n'est nécessaire dans cette V1 :
 * le formulaire utilise mail() côté serveur.
 *
 * Pour une mise en production plus robuste, le README explique
 * comment remplacer ce transport par PHPMailer/SMTP sans toucher
 * au formulaire ni au routage commercial.
 */

return [
    'site_url' => 'https://rgpd.lepotager.org',

    // Adresses publiques de réception
    'potager_email' => 'contact@lepotager.org',
    'prestadmin_email' => 'contact@prestadmin57.fr',

    // Adresse dédiée aux demandes liées aux données / opposition
    'rights_email' => 'jleveque@lepotager.org',

    // Adresse d'expédition locale. Elle doit exister côté hébergement.
    'from_email' => 'contact@lepotager.org',
    'from_name' => 'RGPD — Prestadmin x Le Potager',

    // Limitation anti-abus du formulaire
    'max_submissions_per_hour' => 5,

    // Informations d'affichage
    'prestadmin' => [
        'name' => 'Prestadmin',
        'email' => 'contact@prestadmin57.fr',
        'site' => 'https://www.prestadmin57.fr',
    ],
    'potager' => [
        'name' => 'Le Potager du Web',
        'email' => 'contact@lepotager.org',
        'site' => 'https://www.lepotager.org',
    ],
];
