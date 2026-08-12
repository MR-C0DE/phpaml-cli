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
aml debug                 # analyse et simulation
aml debug "le CSS manque" # ajoute une observation facultative
aml debug --fix           # demande avant chaque modification
aml debug --fix --yes     # applique les modifications sûres sans question
```

L’agent reçoit le diagnostic AML et un nombre limité de fichiers de
configuration. Il ne reçoit jamais `.env`. Les modifications sont limitées au
projet, et `.env`, `.git` et `aml_env` sont interdits. Les fichiers remplacés
sont sauvegardés dans `aml_env/storage/debug-backups/`. Seules les commandes de
diagnostic explicitement autorisées peuvent être exécutées.
