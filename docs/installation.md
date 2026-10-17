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

Le paquet actuel cible les Mac Apple Silicon (`arm64`). La
[bêta 1.7.0-beta.45](https://github.com/MR-C0DE/phpaml-cli/releases/tag/v1.7.0-beta.45)
propose un paquet signé avec Developer ID, notarié par Apple et accompagné de
son ticket de notarisation. Elle reste distincte du canal stable.

1. Téléchargez `phpaml-<version>-macos-arm64.pkg`.
2. Ouvrez le paquet et suivez l’assistant.
3. Vérifiez l’installation.

```bash
aml version
aml doctor
```

AML est installé dans `/usr/local/lib/aml` et la commande est exposée dans
`/usr/local/bin/aml`. L’installation demande les droits administrateur du Mac.
Le paquet signé de la bêta 1.7.0-beta.45 est accepté par Gatekeeper sans
désactiver les protections de macOS. Les anciennes distributions non signées
ne bénéficient pas de cette notarisation.

## Linux x64 — Debian et Ubuntu

```bash
sudo dpkg -i phpaml-<version>-linux-x64.deb
aml version
aml doctor
```

Le paquet installe AML dans `/opt/phpaml` et crée `/usr/local/bin/aml`.

## Réinstaller un projet

`aml create`, `aml create-view` et `aml create-api` installent maintenant le
projet automatiquement. Depuis la racine d’un projet existant, ou après une
création avec `--no-install`, utilisez :

```bash
aml install
```

Le modèle déclare sa version compatible du moteur dans `phpaml.json`. AML la
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

La commande sans version explicite suit le canal stable. Pour installer la
bêta signée, y compris pour remplacer une copie non signée portant déjà le
même numéro :

```bash
aml update --version 1.7.0-beta.45 --force
```

Sur macOS, saisissez le mot de passe administrateur lorsque `sudo` le demande.
Une installation portable télécharge et vérifie le paquet, puis indique son
chemin pour permettre de lancer l’installateur manuellement.
