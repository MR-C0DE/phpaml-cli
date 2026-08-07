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

Les installateurs Windows, macOS et Linux seront publiés dans GitHub Releases.
