# rgpd-lepotager

Code de **rgpd.lepotager.org** — mini-site et comparateur RGPD Le Potager / Prestadmin.

## Structure

- `index.php`, `contact.php`, `confidentialite.php`, `mentions-legales.php` : site principal ;
- `comparateur/` : comparateur RGPD ;
- `cron/` : collecte, découverte et reconstruction des données ;
- `cron/private/` : configuration et données de travail non publiques ;
- `assets/` : styles, scripts et identité visuelle ;
- `includes/site-header.php` : composant unique du header public ;
- `assets/site-header.css` : styles et variantes du header commun / Potager / Prestadmin.

## Header public : une seule source de vérité

Le header ne doit plus être recodé ou maquillé séparément selon les pages.

La structure commune est :

1. identité `RGPD au propre` + `organisation × numérique` ;
2. bandeau de marque selon le canal (`direct`, `potager`, `prestadmin`) ;
3. CTA unique `Diagnostic rapide` à droite, avec l'éclair `⌁` ;
4. navigation secondaire.

L'accueil et le comparateur utilisent tous les deux `includes/site-header.php`. Les différences de marque doivent rester limitées aux textes et aux variables/styles de `assets/site-header.css`.

Le diagnostic n'est pas dupliqué dans le hero : sur l'accueil le CTA du header ouvre directement le parcours ; depuis le comparateur il revient sur la racine avec `#diagnostic`, puis `assets/app.js` ouvre le parcours automatiquement.

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
