# AML AI Debugger

`aml debug` analyse un projet PHPAML sans que l’utilisateur doive connaître la
cause du problème. DeepSeek, OpenAI et Claude sont pris en charge.

```bash
aml ai:configure deepseek --key VOTRE_CLE
aml ai:configure openai --key VOTRE_CLE --model gpt-5-mini
aml ai:configure claude --key VOTRE_CLE --model claude-sonnet-4-5
aml ai:show
```

La configuration personnelle est enregistrée sous `~/.phpaml/ai.json` avec des
permissions privées. Les variables `DEEPSEEK_API_KEY`, `OPENAI_API_KEY` et
`ANTHROPIC_API_KEY` ont priorité sur la clé enregistrée.

```bash
aml debug --yes                 # contexte minimal, strict par défaut
aml debug --include-code --yes  # autorise explicitement le code applicatif
aml debug "le CSS manque" # ajoute une observation facultative
aml debug --fix           # demande avant chaque modification
aml debug --fix --yes     # applique les modifications sûres sans question
aml debug:history         # liste les diagnostics enregistrés
aml debug:show <id>       # affiche le rapport complet
aml debug:rollback <id>   # restaure les fichiers précédents
```

Avant tout appel externe, AML affiche les fichiers concernés. Les clés, mots de
passe et tokens sont masqués automatiquement. Sans `--yes`, une confirmation
interactive est obligatoire. Par défaut, AML exclut le code applicatif et se
limite au diagnostic, à `phpaml.json`, `composer.json` et `.env.example`.
L’option `--include-code` autorise explicitement `configs/app.php`,
`public/index.php` et l’inventaire du code.

L’agent reçoit le diagnostic AML et un nombre limité de fichiers de
configuration. Il ne reçoit jamais `.env`. Les modifications sont limitées au
projet, et `.env`, `.git` et `runtime` sont interdits. Les fichiers remplacés
sont sauvegardés dans `runtime/storage/debug-backups/`. Seules les commandes de
diagnostic explicitement autorisées peuvent être exécutées.

Chaque exécution crée aussi un rapport JSON dans
`runtime/storage/debug-reports/`. Le rapport contient le diagnostic, les
corrections proposées ou appliquées et les résultats des commandes de
vérification. Il ne contient jamais la clé API. `debug:rollback` ne peut
restaurer que les sauvegardes appartenant au rapport indiqué.
