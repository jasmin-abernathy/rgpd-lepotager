# Synchronisation du comparateur depuis la lune workers

Le crawler lourd `cron/update-prices.php` doit finir par tourner uniquement sur la lune workers.

Le site public conserve son fonctionnement actuel : il lit `comparateur/data/sources.json`. La lune 1 ne fait qu'un petit téléchargement HTTPS de ce fichier déjà calculé.

## Configuration

Sur la lune 1 :

```bash
cp cron/private/worker-sync.example.php cron/private/worker-sync.php
chmod 600 cron/private/worker-sync.php
```

Puis renseigne le même Bearer token que dans `lepotager-workers/.env` et passe `enabled` à `true`.

Le vrai fichier `worker-sync.php` est ignoré par Git.

## Test manuel

```bash
php cron/sync-from-worker.php
```

Résultat attendu :

```text
OK — cache RGPD synchronisé depuis la lune workers
```

Si workers est indisponible, si le token est faux ou si le JSON a un schéma inattendu, le script sort en erreur **sans remplacer le cache existant**.

## Cron léger après bascule

Exemple toutes les heures :

```cron
13 * * * * /CHEMIN/PHP /CHEMIN/LIVE/cron/sync-from-worker.php >> /CHEMIN/LOGS/rgpd-worker-sync.log 2>&1
```

Ne désactive l'ancien `update-prices.php` de la lune 1 qu'après plusieurs synchronisations réussies.
