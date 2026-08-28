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
`deliverables/`. Bundled development tools, module tests, examples and
documentation are also removed while executable `runtime/phpaml/*/src`
directories remain available. The public document root is `public/`; routes
must be exposed as `/about`, never `/index.php/about`.

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
aml deploy production --dry-run
aml deploy:status production
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
`src/` directory list. Every file has its own SHA-256 fingerprint. After the
first deployment, AML compares the local and remote manifests, transfers only
added or modified files, removes files deleted from the project, and leaves
unchanged files untouched. For `releases`, the complete next release is rebuilt
on the server from the current release before the atomic symlink switch, so the
rollback remains available without uploading the whole project again. For
`public-html` and `sftp-only`, the previous remote manifest is also used to
remove only obsolete files previously owned by PHPAML.
Use `aml deploy <profile> --dry-run` to build and compare manifests while
leaving the server untouched. `aml deploy:status <profile>` performs the same
read-only comparison and reports whether local and production are synchronized.
Both commands list added (`+`), modified (`~`), and removed (`-`) paths.
Real deployments show the transferred size, the full-build equivalent, the
bytes and percentage saved, and milestone progress from build verification to
remote activation. Successful, synchronized, and failed attempts are retained
locally (up to 100 entries) without hostnames, paths, users, or secrets:

```bash
aml deploy:history
aml deploy:history production
```

The private history is stored in `~/.phpaml/deploy-history.json` with mode
`600` and displays the 20 most recent matching entries.
History updates are atomic and refuse symbolic-link destinations. Deployment
archives are validated before extraction, and read-only comparisons fail
clearly when the remote manifest cannot be reached instead of reporting a
misleading first deployment.
Local SFTP staging data is removed after both successful and failed transfers.
The build checksum is verified before every transfer, including `--skip-build`.
The CLI test suite starts an isolated SSH/SFTP server and validates real
transfers for classic MVC and AML View projects. Test provider-specific paths
and permissions on staging before promoting a production profile.

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
`tools/` et `deliverables/`. Les outils de développement embarqués, tests,
exemples et documentations des modules sont également retirés, tandis que les
dossiers exécutables `runtime/phpaml/*/src` sont conservés. La racine publique
du domaine est `public/` ; une route doit être `/about`, jamais
`/index.php/about`.

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
aml deploy production --dry-run
aml deploy:status production
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
pour `app/` ou `src/`. Chaque fichier possède sa propre empreinte SHA-256. Après
le premier déploiement, AML compare les manifestes local et distant, transfère
uniquement les fichiers ajoutés ou modifiés, supprime ceux retirés du projet et
ne renvoie pas les fichiers inchangés. Avec `releases`, la prochaine release
complète est reconstruite sur le serveur depuis la release courante avant le
basculement atomique : le rollback reste donc disponible sans retransférer tout
le projet. Avec `public-html` et `sftp-only`, l'ancien manifeste distant permet
également de supprimer uniquement les fichiers PHPAML devenus obsolètes.
Utilisez `aml deploy <profil> --dry-run` pour construire et comparer les
manifestes sans modifier le serveur. `aml deploy:status <profil>` effectue la
même comparaison en lecture seule et indique si le projet local et la
production sont synchronisés. Les deux commandes listent les chemins ajoutés
(`+`), modifiés (`~`) et supprimés (`-`).
Les déploiements réels affichent le volume transféré, l’équivalent du build
complet, les octets et le pourcentage économisés, ainsi qu’une progression par
étapes jusqu’à l’activation distante. Les tentatives réussies, déjà
synchronisées ou échouées sont conservées localement (100 entrées maximum),
sans serveur, chemin, utilisateur ni secret :

```bash
aml deploy:history
aml deploy:history production
```

Cet historique privé se trouve dans `~/.phpaml/deploy-history.json` avec les
permissions `600`; la commande affiche les 20 entrées correspondantes les plus
récentes.
Les mises à jour de l’historique sont atomiques et refusent une destination qui
est un lien symbolique. Les archives sont contrôlées avant extraction, et une
comparaison en lecture seule échoue clairement lorsque le manifeste distant est
inaccessible au lieu d’annoncer à tort un premier déploiement.
Le dossier temporaire SFTP local est supprimé après un succès comme un échec.
Le checksum du build est vérifié avant chaque transfert, même avec
`--skip-build`. La CI démarre désormais un vrai serveur SSH/SFTP isolé et valide
les transferts des projets MVC classiques comme AML View. Les chemins et
permissions propres à chaque hébergeur doivent néanmoins être testés sur un
sous-domaine de staging.

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
