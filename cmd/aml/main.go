package main

import (
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"runtime"
)

func main() {
	executable, err := os.Executable()
	if err != nil {
		fmt.Fprintln(os.Stderr, "AML: impossible de déterminer le dossier d’installation")
		os.Exit(1)
	}

	home := filepath.Dir(filepath.Dir(executable))
	php := filepath.Join(home, "runtime", "php", "bin", "php")
	if runtime.GOOS == "windows" {
		php = filepath.Join(home, "runtime", "php", "php.exe")
	}
	cli := filepath.Join(home, "aml_env", "bin", "aml.php")
	temporary := filepath.Join(home, "aml_env", "tmp")
	composerHome := filepath.Join(home, "aml_env", "cache", "composer")

	if _, err := os.Stat(php); err != nil {
		fmt.Fprintln(os.Stderr, "AML: runtime PHP privé introuvable")
		os.Exit(1)
	}
	if _, err := os.Stat(cli); err != nil {
		fmt.Fprintln(os.Stderr, "AML: moteur CLI introuvable")
		os.Exit(1)
	}
	if err := os.MkdirAll(temporary, 0755); err != nil {
		fmt.Fprintln(os.Stderr, "AML: impossible de préparer le dossier temporaire privé")
		os.Exit(1)
	}
	if err := os.MkdirAll(composerHome, 0755); err != nil {
		fmt.Fprintln(os.Stderr, "AML: impossible de préparer le cache Composer privé")
		os.Exit(1)
	}

	command := exec.Command(php, append([]string{cli}, os.Args[1:]...)...)
	command.Stdin = os.Stdin
	command.Stdout = os.Stdout
	command.Stderr = os.Stderr
	command.Env = append(
		os.Environ(),
		"AML_HOME="+home,
		"TMPDIR="+temporary,
		"COMPOSER_HOME="+composerHome,
	)
	if err := command.Run(); err != nil {
		if exitError, ok := err.(*exec.ExitError); ok {
			os.Exit(exitError.ExitCode())
		}
		fmt.Fprintln(os.Stderr, "AML:", err)
		os.Exit(1)
	}
}
