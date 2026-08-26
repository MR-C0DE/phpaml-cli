# Changelog

## Non publié

- normalise réellement `src/Controllers`, `src/Models`, `src/Middleware` et
  `src/Services` en minuscules sur les systèmes de fichiers macOS
  insensibles à la casse, afin que les projets restent compatibles Linux ;
- enregistre `src/views` dans le manifeste des applications AML View ;
- aligne la documentation moderne sur `phpaml.json`, `.env`, `routes/` et la
  configuration générée dans `runtime/config/app.php`.

## 1.7.0-beta.18 — 2026-08-26

- adapte l’installation AML View au point d’entrée composé introduit par
  Template `0.5.0-beta.3` ;
- adapte également l’intégration i18n à ce nouveau point d’entrée ;
- ajoute une régression reproduisant la structure exacte des nouveaux projets ;
- automatise l’assemblage des releases CLI multiplateformes après validation
  des builds et de leurs checksums.

## 1.7.0-beta.17 — 2026-08-26

- installe automatiquement le moteur et les dépendances après `aml create` et
  `aml create-api`, comme le faisait déjà `aml create-view-app` ;
- initialise `.env` pendant la création et ajoute `--no-install` pour différer
  explicitement l’installation ;
- réduit les trois premiers parcours à `create`, `cd` et `serve` ;
- protège les frontières entre Framework, Engine, View, Data et i18n dans la
  CI et documente leurs responsabilités ;
- ajoute un contrôle des ruptures d’API publique à chaque dépôt de paquet ;
- utilise Template `0.5.0-beta.3` et Framework `0.3.0-beta.3` pour les nouveaux
  projets.

## 1.7.0-beta.16 — 2026-08-24

- corrige l’installation du Framework depuis les archives GitHub dont le
  dossier racine contient maintenant le numéro de version ;
- rétablit le parcours public `aml create-view-app` avec Framework, View et
  Engine installés automatiquement ;
- ajoute une régression utilisant le format réel des archives Framework
  récentes.

## 1.7.0-beta.15 — 2026-08-23

- repositionne PHPAML comme une plateforme PHP autonome pour applications Web
  structurées, interfaces déclaratives et API ;
- remplace la vitrine du dépôt par un README anglais avec version française,
  démarrages rapides orientés résultat et carte des paquets officiels ;
- ajoute une capture actuelle de l’application Book Reader et des liens vers
  les démonstrations publiques ;
- adopte la licence MIT et documente la licence des contributions ;
- ajoute les guides de contribution et de conduite communautaire ;
- ajoute des formulaires GitHub pour mesurer les installations et premiers
  projets réussis ou échoués sans télémétrie cachée ;
- ajoute des modèles structurés pour les bugs, propositions et pull requests.
- ajoute un kit bilingue pour préparer le lancement de `1.7.0-beta.15` et
  mesurer l’activation par premier projet réussi.
- enregistre la baseline publique d’adoption avant lancement et documente les
  limites des compteurs de téléchargement GitHub.
- aligne les nouvelles applications sur Template `0.5.0-beta.2` et Framework
  `0.3.0-beta.2`, tous deux sous licence MIT.
- documente l’audit complet de préparation de la release et ses contrôles
  multiplateformes encore requis.

## 1.7.0-beta.14 — 2026-08-21

- ajoute `aml create-api` et les routes par classes dans `src/routes` ;
- remplace la configuration visible des projets modernes par `phpaml.json` et
  `.env`, avec cache interne dans `runtime/config/app.php` ;
- conserve automatiquement les anciens projets fondés sur `configs/*.php` ;
- configure API et Data sans recréer de dossier `configs/` ;
- rend la création API compatible avec les anciens Templates mis en cache ;
- ajoute un test croisé CLI, Framework et Data couvrant migration SQLite,
  validation et cycle CRUD complet ;
- aligne le Template officiel sur `0.5.0-beta.1`.

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
