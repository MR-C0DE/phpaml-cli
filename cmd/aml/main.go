package main

import (
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"runtime"
	"strings"
)

func language() string {
	if value := strings.ToLower(os.Getenv("AML_LANG")); value == "en" || value == "fr" {
		return value
	}
	base := os.Getenv("HOME")
	if runtime.GOOS == "windows" {
		base = os.Getenv("APPDATA")
		if base == "" {
			base = os.Getenv("USERPROFILE")
		}
	}
	content, err := os.ReadFile(filepath.Join(base, ".phpaml", "language"))
	if err == nil && strings.TrimSpace(strings.ToLower(string(content))) == "fr" {
		return "fr"
	}
	return "en"
}

func message(en, fr string) string {
	if language() == "fr" {
		return fr
	}
	return en
}

func installationHome(executable string) (string, error) {
	resolved, err := filepath.EvalSymlinks(executable)
	if err != nil {
		return "", err
	}
	return filepath.Dir(filepath.Dir(resolved)), nil
}

func writableHome() string {
	cache, err := os.UserCacheDir()
	if err != nil || cache == "" {
		cache = os.TempDir()
	}
	return filepath.Join(cache, "phpaml")
}

func main() {
	executable, err := os.Executable()
	if err != nil {
		fmt.Fprintln(os.Stderr, message("AML: unable to determine the installation directory", "AML : impossible de déterminer le dossier d’installation"))
		os.Exit(1)
	}

	home, err := installationHome(executable)
	if err != nil {
		fmt.Fprintln(os.Stderr, message("AML: unable to resolve the installation directory", "AML : impossible de résoudre le dossier d’installation"))
		os.Exit(1)
	}
	php := filepath.Join(home, "runtime", "php", "bin", "php")
	if runtime.GOOS == "windows" {
		php = filepath.Join(home, "runtime", "php", "php.exe")
	}
	cli := filepath.Join(home, "runtime", "bin", "aml.php")
	state := writableHome()
	temporary := filepath.Join(state, "tmp")
	composerHome := filepath.Join(state, "composer")

	if _, err := os.Stat(php); err != nil {
		fmt.Fprintln(os.Stderr, message("AML: private PHP runtime not found", "AML : runtime PHP privé introuvable"))
		os.Exit(1)
	}
	if _, err := os.Stat(cli); err != nil {
		fmt.Fprintln(os.Stderr, message("AML: CLI engine not found", "AML : moteur CLI introuvable"))
		os.Exit(1)
	}
	if err := os.MkdirAll(temporary, 0755); err != nil {
		fmt.Fprintln(os.Stderr, message("AML: unable to prepare the private temporary directory", "AML : impossible de préparer le dossier temporaire privé"))
		os.Exit(1)
	}
	if err := os.MkdirAll(composerHome, 0755); err != nil {
		fmt.Fprintln(os.Stderr, message("AML: unable to prepare the private Composer cache", "AML : impossible de préparer le cache Composer privé"))
		os.Exit(1)
	}

	command := exec.Command(php, append([]string{cli}, os.Args[1:]...)...)
	command.Stdin = os.Stdin
	command.Stdout = os.Stdout
	command.Stderr = os.Stderr
	command.Env = append(
		os.Environ(),
		"AML_HOME="+home,
		"AML_CACHE_HOME="+state,
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
