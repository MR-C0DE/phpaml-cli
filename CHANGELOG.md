# Changelog

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
