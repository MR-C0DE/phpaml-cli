# AML CLI

AML est l’environnement autonome de PHPAML. L’installateur fournit la commande
native `aml`, un runtime PHP privé, Composer et le moteur CLI.

```bash
aml create mon-projet
cd mon-projet
aml install
aml serve
```

L’utilisateur n’a pas besoin d’installer PHP ou Composer séparément.

## Création depuis GitHub

`aml create` télécharge les releases officielles depuis
`MR-C0DE/phpaml-template`, vérifie leur checksum SHA-256 et conserve un cache
local.

```bash
aml create mon-projet
aml create mon-projet --version 0.1.0
aml create mon-projet --refresh
aml create mon-projet --offline
```

## Installation du moteur

`aml install` télécharge les releases officielles depuis
`MR-C0DE/phpaml-framework`, vérifie leur checksum SHA-256 et installe le moteur
dans `aml_env/framework/`.

```bash
aml install
aml install --version 0.1.0
aml install --refresh
aml install --production
```

## Dépôts

- `MR-C0DE/phpaml-cli` : CLI native et installateurs;
- `MR-C0DE/phpaml-framework` : moteur PHPAML;
- `MR-C0DE/phpaml-template` : modèle téléchargé par `aml create`.

## Paquet autonome macOS ARM64

Le paquet contient l’exécutable natif `aml`, PHP 8.4 privé, Composer privé et
le moteur CLI. Il se construit avec :

```bash
./scripts/build-macos-arm64.sh
```

Le workflow GitHub Actions produit aussi l’archive et son checksum à chaque
tag de version. L’installateur macOS se construit séparément, ou
automatiquement à la fin de la commande précédente :

```bash
./scripts/build-macos-pkg.sh
```

Il installe PHPAML dans `/usr/local/lib/aml` et expose la commande
`/usr/local/bin/aml`. Le paquet macOS Intel reste à ajouter.

## Installateur Windows x64

GitHub Actions compile un PHP Windows privé, construit `aml.exe`, puis produit
un installateur Inno Setup :

```powershell
.\scripts\build-windows-x64.ps1
```

L’installation se fait dans le profil de l’utilisateur, ne demande pas les
droits administrateur, ajoute `aml` au `PATH` et retire proprement cette entrée
lors de la désinstallation.

## Paquets Linux x64

GitHub Actions construit une archive portable et un paquet Debian autonome :

```bash
./scripts/build-linux-x64.sh
sudo dpkg -i dist/phpaml-1.0.0-linux-x64.deb
aml version
```

Le paquet installe PHPAML dans `/opt/phpaml` et expose `/usr/local/bin/aml`.
Il embarque son propre PHP et Composer : aucune installation préalable de PHP
n’est nécessaire.
