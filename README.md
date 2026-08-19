# PHPAML

PHPAML est un mini-framework MVC en PHP 8.2+, inspiré de l’organisation de
Java EE et d’ASP.NET. Son environnement autonome AML fournit sa propre commande
`aml`, PHP 8.4 et Composer : l’utilisateur n’a pas besoin d’installer PHP.

```bash
aml create mon-projet
cd mon-projet
aml install
aml doctor
aml serve
```

## Documentation

- [Commencer ici](docs/README.md)
- [Installation](docs/installation.md)
- [Premier projet](docs/demarrage-rapide.md)
- [Architecture](docs/architecture.md)
- [Référence AML](docs/cli.md)
- [Routes et contrôleurs](docs/routage-controleurs.md)
- [Vues et fichiers publics](docs/vues-et-public.md)
- [Données, validation et sécurité](docs/donnees-securite.md)
- [Tests, production et dépannage](docs/tests-production-depannage.md)

## Plateformes

Les installateurs officiels couvrent Windows x64, macOS ARM64 et Linux x64
(Debian/Ubuntu). Ils sont disponibles dans la
[dernière release](https://github.com/MR-C0DE/phpaml-cli/releases/latest).

## Dépôts officiels

- [`phpaml-cli`](https://github.com/MR-C0DE/phpaml-cli) : AML et installateurs ;
- [`phpaml-framework`](https://github.com/MR-C0DE/phpaml-framework) : moteur ;
- [`phpaml-template`](https://github.com/MR-C0DE/phpaml-template) : modèle de projet.

PHPAML CLI 1.6.0 est la première version stable de l’environnement autonome.
La préversion 1.7.0-beta.10 inclut `runtime/`, `phpaml.json`, la commande
sécurisée `aml migrate:structure` pour les projets existants.
Elle intègre aussi AML View : `aml create-view-app mon-interface`
crée une application dédiée, installe `phpaml/view`, prépare une page interactive sécurisée et active les
générateurs `make:view-page`, `make:view-component` et `make:view-layout`.
PHPAML reste un jeune mini-framework : consultez la
[documentation de production](docs/tests-production-depannage.md) et validez
votre application avant de l’exposer publiquement.
