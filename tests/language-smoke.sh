#!/usr/bin/env bash
set -euo pipefail

project_root="$(cd "$(dirname "$0")/../../../.." && pwd)"
aml="$project_root/runtime/bin/aml"

english_help="$(AML_LANG=en "$aml" help)"
grep -q "Usage: aml <command>" <<<"$english_help"
grep -q "Show or change the CLI language" <<<"$english_help"

french_help="$(AML_LANG=fr "$aml" help)"
grep -q "Utilisation : aml <commande>" <<<"$french_help"
grep -q "Affiche ou change la langue du CLI" <<<"$french_help"

english_error="$(AML_LANG=en "$aml" unknown-command 2>&1 || true)"
grep -q "Error : Unknown command" <<<"$english_error"

french_error="$(AML_LANG=fr "$aml" unknown-command 2>&1 || true)"
grep -q "Erreur : Commande inconnue" <<<"$french_error"

echo "language smoke: OK"
