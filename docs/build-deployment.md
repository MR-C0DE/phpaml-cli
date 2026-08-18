# Build and deployment / Build et déploiement

This guide documents PHPAML CLI 1.6 production archives and deployment. La
seconde partie fournit la même référence en français.

## English

### Create a production build

```bash
aml test
aml build
```

Use `aml build --skip-tests` only when the tests have already passed in the
same revision. AML checks `public/.htaccess`, then writes:

- `output/phpaml-build.zip`;
- `output/phpaml-build.zip.sha256`;
- `output/manifest.json`.

The archive excludes secrets and non-runtime material: `.env`, `.git/`, test
files, logs, SQLite databases, temporary files, `output/`, `tools/`, and
`deliverables/`. The public document root is `public/`; routes must be exposed
as `/about`, never `/index.php/about`.

If ZIP finalization, checksum creation, disk space, or output permissions fail,
AML removes incomplete build artifacts and exits with an error.

### Configure a deployment profile

```bash
aml deploy:configure production \
  --host example.com \
  --user deploy \
  --path /home/deploy/site \
  --port 22 \
  --key ~/.ssh/id_ed25519

aml deploy:check production
aml deploy production
```

Profiles live in `~/.phpaml/deploy.json` with mode `600`. Passwords are never
stored; use an SSH key.

Available strategies:

- `releases`: timestamped releases, a `current` symlink, and atomic rollback;
- `public-html`: shared hosting where public files live in `public_html` and
  application internals stay outside the document root;
- `sftp-only`: hosts that provide SFTP but no remote shell.

All strategies use `build-manifest.json`; there is no hard-coded `app/` or
`src/` directory list. For `public-html` and `sftp-only`, the previous remote
manifest is used to remove only obsolete files previously owned by PHPAML.
Local SFTP staging data is removed after both successful and failed transfers.
The build checksum is verified before every transfer, including `--skip-build`.
Remote deployment remains a beta feature until SSH/SFTP end-to-end CI is added.

```bash
aml deploy:prune production --keep 5
```

Shared-hosting example:

```bash
aml deploy:configure hostinger \
  --host example.com \
  --user deploy \
  --path /home/user/domains/example.com \
  --strategy public-html \
  --public-path /home/user/domains/example.com/public_html \
  --key ~/.ssh/id_ed25519

aml deploy:check hostinger
aml deploy hostinger
```

`--public-path` may point to a document root outside `--path`, as is common for
subdomains. AML adapts the deployed front controller so it can load the private
application from `--path`. With the `releases` strategy, if the host created a
real `current` directory beforehand, AML preserves it as
`current.pre-aml-<release>` before activating the symlink.

Open a manual session with `aml ssh hostinger` or `aml sftp hostinger`. For the
`releases` strategy, restore the previous release with:

```bash
aml deploy:rollback production
```

### Network failures

AML retries temporary download failures (connection errors, HTTP 408, 429, and
5xx responses such as 503) up to three times. If GitHub remains unavailable,
wait and retry or use a previously cached package with `--offline` where the
command supports it.

## Français

### Créer un build de production

```bash
aml test
aml build
```

Utilisez `aml build --skip-tests` uniquement si les tests ont déjà réussi sur
la même révision. AML vérifie `public/.htaccess`, puis crée :

- `output/phpaml-build.zip` ;
- `output/phpaml-build.zip.sha256` ;
- `output/manifest.json`.

L’archive exclut les secrets et les éléments inutiles en production : `.env`,
`.git/`, tests, journaux, bases SQLite, fichiers temporaires, `output/`,
`tools/` et `deliverables/`. La racine publique du domaine est `public/` ; une
route doit être `/about`, jamais `/index.php/about`.

Si la création du ZIP, du checksum, l’espace disque ou les permissions de
`output/` échouent, AML supprime les fichiers incomplets et retourne une erreur.

### Configurer un profil de déploiement

```bash
aml deploy:configure production \
  --host example.com \
  --user deploy \
  --path /home/deploy/site \
  --port 22 \
  --key ~/.ssh/id_ed25519

aml deploy:check production
aml deploy production
```

Les profils sont conservés dans `~/.phpaml/deploy.json` avec les permissions
`600`. Aucun mot de passe n’est enregistré : utilisez une clé SSH.

Stratégies disponibles :

- `releases` : versions horodatées, lien `current` et retour arrière atomique ;
- `public-html` : hébergement mutualisé avec les fichiers publics dans
  `public_html` et le code interne hors de la racine web ;
- `sftp-only` : hébergeur offrant SFTP sans terminal SSH.

Toutes les stratégies utilisent `build-manifest.json`, sans liste codée en dur
pour `app/` ou `src/`. Avec `public-html` et `sftp-only`, l’ancien manifeste
distant permet de supprimer uniquement les fichiers PHPAML devenus obsolètes.
Le dossier temporaire SFTP local est supprimé après un succès comme un échec.
Le checksum du build est vérifié avant chaque transfert, même avec
`--skip-build`. Le déploiement distant reste en bêta tant qu’une CI SSH/SFTP de
bout en bout n’est pas disponible.

```bash
aml deploy:prune production --keep 5
```

Exemple pour un hébergement mutualisé comme Hostinger :

```bash
aml deploy:configure hostinger \
  --host example.com \
  --user deploy \
  --path /home/user/domains/example.com \
  --strategy public-html \
  --public-path /home/user/domains/example.com/public_html \
  --key ~/.ssh/id_ed25519

aml deploy:check hostinger
aml deploy hostinger
```

`--public-path` peut désigner une racine web située ailleurs que `--path`, comme
c’est souvent le cas pour un sous-domaine. AML adapte automatiquement le
contrôleur frontal déployé afin qu’il charge l’application privée depuis
`--path`. Avec la stratégie `releases`, si l’hébergeur avait déjà créé un vrai
dossier `current`, AML le conserve sous le nom
`current.pre-aml-<release>` avant d’activer le lien symbolique.

Ouvrez une session manuelle avec `aml ssh hostinger` ou `aml sftp hostinger`.
Avec la stratégie `releases`, restaurez la version précédente avec :

```bash
aml deploy:rollback production
```

### Erreurs réseau

AML retente jusqu’à trois fois les erreurs de téléchargement temporaires :
connexion, HTTP 408, 429 et erreurs 5xx comme 503. Si GitHub reste inaccessible,
attendez puis recommencez, ou utilisez un paquet déjà en cache avec `--offline`
lorsque la commande le permet.
