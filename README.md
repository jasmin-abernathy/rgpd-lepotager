# rgpd-lepotager

Code de **rgpd.lepotager.org** — mini-site et comparateur RGPD Le Potager / Prestadmin.

## Structure

- `index.php`, `contact.php`, `confidentialite.php`, `mentions-legales.php` : site principal ;
- `comparateur/` : comparateur RGPD ;
- `cron/` : collecte, découverte et reconstruction des données ;
- `cron/private/` : configuration et données de travail non publiques ;
- `assets/` : styles, scripts et identité visuelle.

## Important : fichiers volontairement absents de Git

Le dépôt **ne contient pas les secrets ni les données runtime de production** :

- `cron/private/discovery-admin.php` — identifiants de la revue des sources ;
- `cron/private/candidates.json` — candidats découverts par le crawl ;
- `cron/private/observations-private.json` — observations internes ;
- `comparateur/data/sources.json` — données publiques générées par les cron ;
- fichiers de logs / derniers runs.

Des fichiers `.example` sont fournis pour une nouvelle installation.

### Première installation neuve

```bash
cp cron/private/discovery-admin.example.php cron/private/discovery-admin.php
cp cron/private/candidates.example.json cron/private/candidates.json
cp cron/private/observations-private.example.json cron/private/observations-private.json
cp comparateur/data/sources.example.json comparateur/data/sources.json
```

Ensuite, remplacer le mot de passe d'exemple dans `cron/private/discovery-admin.php`.

## Synchronisation d'un site live existant

Sur un serveur qui possède déjà ces fichiers runtime, **ne les supprimez pas** lors du déploiement. Ils sont ignorés par Git afin que les cron puissent les modifier sans salir le dépôt et sans conflit au prochain pull.

GitHub doit rester la source de vérité pour le **code**. Les données générées et secrets restent sur le serveur.

## Sécurité

- Le dossier `cron/` est bloqué depuis le Web par `.htaccess`.
- `cron/private/` est également bloqué par `.htaccess`.
- `config.php` est bloqué par le `.htaccess` racine.
- Aucun mot de passe réel ne doit être commité.
