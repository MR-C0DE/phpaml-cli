package main

import (
	"os"
	"path/filepath"
	"testing"
)

func TestInstallationHomeFollowsCommandSymlink(t *testing.T) {
	root := t.TempDir()
	installed := filepath.Join(root, "lib", "aml")
	commandDirectory := filepath.Join(root, "bin")
	if err := os.MkdirAll(filepath.Join(installed, "bin"), 0755); err != nil {
		t.Fatal(err)
	}
	if err := os.MkdirAll(commandDirectory, 0755); err != nil {
		t.Fatal(err)
	}
	executable := filepath.Join(installed, "bin", "aml")
	if err := os.WriteFile(executable, []byte("aml"), 0755); err != nil {
		t.Fatal(err)
	}
	command := filepath.Join(commandDirectory, "aml")
	if err := os.Symlink(filepath.Join("..", "lib", "aml", "bin", "aml"), command); err != nil {
		t.Fatal(err)
	}

	home, err := installationHome(command)
	if err != nil {
		t.Fatal(err)
	}
	expected, err := filepath.EvalSymlinks(installed)
	if err != nil {
		t.Fatal(err)
	}
	if home != expected {
		t.Fatalf("installation home = %q, want %q", home, expected)
	}
}
