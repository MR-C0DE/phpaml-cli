# Changelog

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
