# Changelog

## 1.7.0-beta.13 — 2026-08-20

- rétablit le fonctionnement des nouvelles applications AML View avec
  `phpaml/engine` 0.1.0-beta.2 ;
- utilise automatiquement la ressource JavaScript externe lorsqu’elle est
  disponible, avec repli sûr vers le runtime inline protégé par nonce CSP ;
- migre aussi les intégrations `public/index.php` déjà générées par la bêta 12 ;
- ajoute une régression couvrant les deux générations du moteur.

## 1.7.0-beta.12 — 2026-08-20

- sélectionne pour `aml serve` un runtime PHP 8.2+ réellement compatible avec
  les extensions Composer du projet, notamment SQLite et MongoDB ;
- ajoute les commandes de génération d’API, les migrations de ressources, les
  jetons et la génération OpenAPI/client ;
- sert AML Engine comme ressource JavaScript versionnée afin d’améliorer la
  CSP, la mise en cache et le débogage navigateur ;
- crée récursivement les racines SFTP absentes avant le transfert ;
- étend la CI Linux, macOS et Windows au choix du runtime et ajoute les
  régressions API, View et déploiement correspondantes.

## 1.7.0-beta.11 — 2026-08-19

- améliore le starter AML View : header adaptatif, rythme vertical, cartes,
  responsive et thèmes clair/sombre réellement appliqués ;
- déplace migrations et seeders sous `runtime/database/` afin d’alléger la
  racine des projets ;
- étend `aml migrate:structure` pour déplacer automatiquement l’ancien dossier
  `database/` et actualiser ses références ;
- aligne le template officiel sur `0.4.0-beta.5`.

## 1.7.0-beta.10 — 2026-08-19

- Corrige les archives de production minimales afin que les fichiers générés
  par Composer ne chargent plus le bootstrap PHPStan retiré du build.
- Conserve les fonctions et les classes AML View/Engine nécessaires à
  l'exécution tout en excluant les outils de développement.
- Ajoute une régression de build qui valide directement les deux tables
  d'autoload Composer avant tout déploiement.

## 1.7.0-beta.9 — 2026-08-18

- builds de production allégés : exclusion de PHPStan, des tests, exemples,
  documentations et métadonnées de développement embarqués dans les modules ;
- conservation explicite du code exécutable `runtime/phpaml/*/src` dans
  l’archive ;
- couverture de non-régression du contenu des archives MVC et AML View.

## 1.7.0-beta.8 — 2026-08-18

- Template officiel aligné sur `0.4.0-beta.4` et Framework
  `0.2.1-beta.1` ;
- correction des avertissements `views_path` dans les nouveaux projets AML
  View ;
- rendu AML View envoyé dans le pipeline HTTP principal au lieu d’être remplacé
  par une fausse réponse 404 du routeur MVC ;
- validation de `CspNonce`, `FileApplication` et `EngineRuntime` avant de
  déclarer la création terminée ou de démarrer `aml serve`.

## 1.7.0-beta.7 — 2026-08-17

- `aml serve` démarre sur `127.0.0.1:8910` et sélectionne automatiquement le
  port suivant lorsqu’il est occupé ;
- déploiements construits depuis le manifeste, avec contrôle d’intégrité,
  nettoyage distant et prise en charge des applications `src/` ;
- durcissement du diagnostic IA, du CSRF et des en-têtes CSP ;
- création AML View verrouillée sur une version contenant `FileApplication`,
  installation explicite d’Engine et vérification réelle de l’autoload avant
  de déclarer le projet prêt ;
- `aml serve` détecte un runtime View incomplet et affiche une correction claire
  au lieu de laisser PHP produire une erreur fatale ;
- documentation et tests de création AML View actualisés ;
- Template officiel aligné sur `0.4.0-beta.3`.

## 1.7.0-beta.6 — 2026-08-17

- prise en charge de PHPAML View, Engine et Data ;
- structure de projet fondée sur `runtime/` et `phpaml.json`.
