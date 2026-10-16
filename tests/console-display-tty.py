"""Check ANSI detection per output stream using a real Unix pseudo-terminal."""

import errno
import os
from pathlib import Path
import pty
import re
import select
import shutil
import subprocess
import tempfile
import time


php = os.environ.get("PHP_BINARY") or shutil.which("php")
if not php:
    raise RuntimeError("PHP_BINARY must point to a PHP executable")
probe = str(Path(__file__).with_name("console-display.php"))
assertions = 0


def expect(condition, message):
    global assertions
    assertions += 1
    if not condition:
        raise AssertionError(message)


def exercise(mode, stdout_tty=True, stderr_tty=True, **environment):
    master, slave = pty.openpty()
    env = dict(os.environ, AML_LANG="en", TERM="xterm-256color", NO_COLOR="", COLUMNS="60")
    env.update(environment)
    child = None
    try:
        with tempfile.TemporaryFile() as out, tempfile.TemporaryFile() as err:
            child = subprocess.Popen(
                [php, probe, "--probe", mode], stdin=subprocess.DEVNULL,
                stdout=slave if stdout_tty else out,
                stderr=slave if stderr_tty else err, env=env,
            )
            os.close(slave)
            slave = None
            captured = bytearray()
            deadline = time.monotonic() + 10
            while time.monotonic() < deadline:
                ready, _, _ = select.select([master], [], [], 0.05)
                if ready:
                    try:
                        chunk = os.read(master, 65536)
                        if not chunk:
                            break
                        captured.extend(chunk)
                    except OSError as error:
                        if error.errno == errno.EIO:
                            break
                        raise
            expect(child.wait(timeout=2) == 0, f"{mode}: child failed")
            out.seek(0)
            err.seek(0)
            return bytes(captured).replace(b"\r\n", b"\n"), out.read(), err.read()
    finally:
        if child is not None and child.poll() is None:
            child.kill()
            child.wait()
        if slave is not None:
            os.close(slave)
        os.close(master)


terminal, _, _ = exercise("colors")
expect(b"\x1b[34mblue\x1b[0m" in terminal, "Interactive stdout uses the requested color")
expect(b"\x1b[32m[OK] done\x1b[0m" in terminal, "Success is green")
expect(b"\x1b[31m[ERROR] failed\x1b[0m" in terminal, "Errors are red")
for value in ("1", "0"):
    terminal, _, _ = exercise("colors", NO_COLOR=value)
    expect(b"\x1b" not in terminal and b"[ERROR] failed" in terminal, "Nonempty NO_COLOR disables color without hiding status labels")
terminal, _, _ = exercise("colors", TERM="dumb")
expect(b"\x1b" not in terminal, "A dumb terminal receives plain text")
terminal, out, _ = exercise("colors", stdout_tty=False)
expect(out == b"blue" + os.linesep.encode() and b"\x1b[32m" in terminal, "Redirected stdout does not disable stderr colors")
terminal, _, err = exercise("colors", stderr_tty=False)
expect(b"\x1b[34m" in terminal and b"\x1b" not in err and b"[OK] done" in err, "Redirected stderr does not disable stdout colors")
terminal, _, _ = exercise("table")
expect(b"\x1b[1m| Nom" in terminal and b"\x1b[0m" in terminal, "Only table headers receive emphasis")
terminal, _, _ = exercise("progress")
expect(b"\r\x1b[2K" in terminal and b"500/500 (100%)" in terminal, "Interactive progress redraws the current line")
expect(terminal.endswith(b"\x1b[0m\n"), "Completion resets color and terminates the line")
expect(terminal.count(b"\r\x1b[2K") < 20, "Frequent increments are throttled")
terminal, _, _ = exercise("progress", NO_COLOR="1")
expect(b"\r\x1b[2K" in terminal and re.search(rb"\x1b\[[0-9;]*m", terminal) is None, "NO_COLOR removes colors while preserving progress updates")
terminal, _, _ = exercise("progress", TERM="dumb")
expect(b"\x1b" not in terminal and b"\r" not in terminal and terminal.count(b"\n") == 2, "Dumb terminals use concise progress summaries")
_, _, err = exercise("progress", stderr_tty=False)
expect(b"\x1b" not in err and b"\r" not in err and err.count(os.linesep.encode()) == 2, "Progress detection follows stderr, not stdout")
terminal, _, _ = exercise("progress-partial")
expect(b"1/3 (33%) - Interrupted" in terminal and b"100%" not in terminal and terminal.endswith(b"\n"), "Interrupted progress retains its real count and closes the line")
terminal, _, _ = exercise("progress-narrow", COLUMNS="30")
plain = re.sub(rb"\x1b\[[0-?]*[ -/]*[@-~]", b"", terminal)
expect(all(len(frame.strip(b"\n")) <= 29 for frame in plain.split(b"\r")), "Progress fits the configured terminal width")
expect(b"10/10 (100%)" in plain, "Narrow progress keeps the completion count visible")

print(f"Console terminal display tests passed ({assertions} assertions).")
