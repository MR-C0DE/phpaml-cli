# Installation

AML est distribué avec PHP 8.4 et Composer. Une installation séparée de PHP,
Composer ou Node.js n’est pas nécessaire.

Téléchargez toujours les fichiers depuis la
[release GitHub officielle](https://github.com/MR-C0DE/phpaml-cli/releases/latest).

## Windows x64

1. Téléchargez `phpaml-<version>-windows-x64.exe`.
2. Lancez l’installateur.
3. Ouvrez un nouveau terminal.
4. Vérifiez l’installation.

```powershell
aml version
aml doctor
```

AML est installé dans `%LOCALAPPDATA%\Programs\PHPAML` et son dossier `bin` est
ajouté au `PATH` de l’utilisateur. Aucun droit administrateur n’est requis.
L’exécutable n’étant pas encore signé, Windows SmartScreen peut afficher un
avertissement.

## macOS ARM64

Le paquet actuel cible les Mac Apple Silicon (`arm64`).

1. Téléchargez `phpaml-<version>-macos-arm64.pkg`.
2. Ouvrez le paquet et suivez l’assistant.
3. Vérifiez l’installation.

```bash
aml version
aml doctor
```

AML est installé dans `/usr/local/lib/aml` et la commande est exposée dans
`/usr/local/bin/aml`. Le paquet n’étant pas encore signé par Apple, macOS peut
demander une confirmation dans Réglages Système > Confidentialité et sécurité.

## Linux x64 — Debian et Ubuntu

```bash
sudo dpkg -i phpaml-<version>-linux-x64.deb
aml version
aml doctor
```

Le paquet installe AML dans `/opt/phpaml` et crée `/usr/local/bin/aml`.

## Installer un projet

Depuis la racine d’un projet créé par AML :

```bash
aml install
```

Le modèle déclare sa version compatible du moteur dans `info.json`. AML la
sélectionne automatiquement. Une option `--version` donnée explicitement reste
prioritaire.

## Archives portables

Chaque release propose aussi :

- `aml-windows-x64.zip` ;
- `aml-macos-arm64.tar.gz` ;
- `aml-linux-x64.tar.gz`.

Extrayez l’archive puis lancez `bin/aml` (`bin/aml.exe` sous Windows). Une
installation portable est détectée par `aml --update` et n’est jamais remplacée
automatiquement.

## Vérifier l’intégrité

Chaque paquet possède un fichier `.sha256`.

```bash
shasum -a 256 -c phpaml-<version>-macos-arm64.pkg.sha256
sha256sum -c phpaml-<version>-linux-x64.deb.sha256
```

Sous PowerShell :

```powershell
Get-FileHash .\phpaml-<version>-windows-x64.exe -Algorithm SHA256
```

Comparez le résultat avec la valeur du fichier `.sha256`.

## Mettre AML à jour

```bash
aml --update --check
aml --update
```

`aml --update` choisit le paquet natif, le télécharge depuis GitHub et vérifie son
SHA-256 avant de lancer l’installation.
