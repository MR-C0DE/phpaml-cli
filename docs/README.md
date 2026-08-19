# Documentation PHPAML

Bienvenue dans la documentation officielle de PHPAML et de son environnement
autonome AML.

## Parcours recommandé

1. [Installer AML](installation.md)
2. [Créer un premier projet](demarrage-rapide.md)
3. [Comprendre l’architecture](architecture.md)
4. [Découvrir toutes les commandes](cli.md)
5. [Écrire des routes et des contrôleurs](routage-controleurs.md)
6. [Construire les vues et charger les fichiers publics](vues-et-public.md)
7. [Utiliser la base de données et la sécurité](donnees-securite.md)
8. [Créer un build et déployer par SSH/SFTP](build-deployment.md)
9. [Tester la production et dépanner](tests-production-depannage.md)
10. [Gérer le SEO avec AML](seo.md)

## Ce que fournit PHPAML

- MVC : contrôleurs, modèles et vues PHP ;
- routeur HTTP avec paramètres, noms et middlewares ;
- conteneur d’injection de dépendances ;
- requêtes et réponses HTML, JSON ou redirections ;
- vues partielles, échappement HTML et champs CSRF ;
- sessions, validation et en-têtes de sécurité ;
- PDO, requêtes simples et migrations transactionnelles ;
- serveur local avec actualisation automatique ;
- CLI autonome avec PHP et Composer privés.

## Version documentée

Cette documentation décrit PHPAML CLI **1.7.0-beta.9**, notamment le build de
production, le déploiement SSH/SFTP, le diagnostic IA et la boîte à outils SEO.
La version installée est visible avec :

```bash
aml version
aml --update --check
```
