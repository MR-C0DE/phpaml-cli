"""Exercise hidden input and terminal restoration on Unix, including Ctrl-C."""

import errno
import os
from pathlib import Path
import pty
import select
import shutil
import signal
import termios
import time


php = os.environ.get("PHP_BINARY") or shutil.which("php")
if not php:
    raise RuntimeError("PHP_BINARY must point to a PHP executable")
library = str(Path(__file__).resolve().parents[1] / "cli" / "console.php")
secret = 'pa ss"\'$!\\André'
probe = r'''
require $argv[1];
try {
    $value = \AML\Console\Console::secret('Password');
    echo $value === $argv[2] ? '<ACCEPTED>' : '<WRONG>';
} catch (\AML\Console\InputException $error) {
    echo '<CANCELLED>';
    exit(2);
}
'''


def exercise(mode):
    pid, terminal = pty.fork()
    if pid == 0:
        # Keep the terminal session alive like a user's shell, without restoring
        # its modes on the application's behalf. A session leader exiting on
        # Ctrl-C would hang up the terminal before the reader can restore it.
        application = os.fork()
        if application == 0:
            os.execv(php, [php, "-r", probe, "--", library, secret])
        signal.signal(signal.SIGINT, signal.SIG_IGN)
        _, application_status = os.waitpid(application, 0)
        for _ in range(100):
            if termios.tcgetattr(0)[3] & termios.ECHO:
                break
            time.sleep(0.01)
        code = os.waitstatus_to_exitcode(application_status)
        os._exit(code if code >= 0 else 128 - code)
    captured = bytearray()
    sent = False
    status = None
    deadline = time.monotonic() + 10
    try:
        while time.monotonic() < deadline:
            if not sent and not termios.tcgetattr(terminal)[3] & termios.ECHO:
                payload = {"normal": secret.encode() + b"\n", "eof": b"\x04", "interrupt": b"\x03"}[mode]
                os.write(terminal, payload)
                sent = True
            ready, _, _ = select.select([terminal], [], [], 0.02)
            if ready:
                try:
                    captured.extend(os.read(terminal, 4096))
                except OSError as error:
                    if error.errno != errno.EIO:
                        raise
            if status is None:
                done, child_status = os.waitpid(pid, os.WNOHANG)
                if done:
                    status = child_status
            if status is not None and termios.tcgetattr(terminal)[3] & termios.ECHO:
                break
        assert sent, f"{mode}: terminal echo was never disabled"
        assert status is not None, f"{mode}: hidden input did not finish"
        assert termios.tcgetattr(terminal)[3] & termios.ECHO, f"{mode}: terminal echo was not restored"
        assert secret.encode() not in captured, f"{mode}: password appeared in terminal output"
        if mode == "normal":
            assert os.waitstatus_to_exitcode(status) == 0 and b"<ACCEPTED>" in captured, captured
        elif mode == "eof":
            assert os.waitstatus_to_exitcode(status) == 2 and b"<CANCELLED>" in captured, captured
        else:
            assert os.waitstatus_to_exitcode(status) != 0, "Ctrl-C must cancel the program"
    finally:
        if status is None:
            os.killpg(pid, signal.SIGKILL)
            os.waitpid(pid, 0)
        os.close(terminal)


for mode in ("normal", "eof", "interrupt"):
    exercise(mode)
print("Hidden input passed: no echo, exact value, EOF and Ctrl-C restore the terminal.")
