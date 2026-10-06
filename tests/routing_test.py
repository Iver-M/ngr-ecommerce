"""Exercise the real entry point without a database or production credentials."""
import json
import os
from pathlib import Path
import shutil
import socket
import subprocess
import tempfile
import time
from urllib.error import HTTPError, URLError
from urllib.request import Request, urlopen

ROOT = Path(__file__).resolve().parents[1]
PHP = os.environ.get("PHP_BINARY", "php")


def response(url, data=None):
    request = Request(url, data=data)
    try:
        with urlopen(request, timeout=3) as reply:
            return reply.status, reply.read().decode()
    except HTTPError as error:
        return error.code, error.read().decode()


def check_mount(mount):
    with tempfile.TemporaryDirectory() as temp:
        document_root = Path(temp)
        project = document_root / mount if mount else document_root
        (project / "config").mkdir(parents=True)
        (project / "app" / "pages").mkdir(parents=True)
        (project / "assets").mkdir()
        shutil.copyfile(ROOT / "index.php", project / "index.php")
        (project / "config" / "routes.php").write_text(
            "<?php return ['index.php'=>'app/pages/echo.php',"
            "'echo.php'=>'app/pages/echo.php'];", encoding="utf-8"
        )
        (project / "app" / "pages" / "echo.php").write_text(
            "<?php header('Content-Type: application/json');"
            "echo json_encode(['method'=>$_SERVER['REQUEST_METHOD'],"
            "'query'=>$_GET,'body'=>file_get_contents('php://input'),"
            "'root'=>getcwd()===dirname(__DIR__,2)]);", encoding="utf-8"
        )
        (project / "assets" / "example.css").write_text("body{}", encoding="utf-8")
        (project / "config" / "local.php").write_text("<?php echo 'private';", encoding="utf-8")
        (project / "sendgrid.env").write_text("private-environment", encoding="utf-8")
        with socket.socket() as sock:
            sock.bind(("127.0.0.1", 0))
            port = sock.getsockname()[1]
        base = f"http://127.0.0.1:{port}/" + (mount + "/" if mount else "")
        server = subprocess.Popen(
            [PHP, "-n", "-S", f"127.0.0.1:{port}", "-t", str(document_root),
             str(project / "index.php")],
            stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL
        )
        try:
            for _ in range(100):
                if server.poll() is not None:
                    raise RuntimeError("PHP development server exited during startup")
                try:
                    status, body = response(base)
                    break
                except (URLError, ConnectionError):
                    time.sleep(0.05)
            else:
                raise RuntimeError("PHP development server did not start")
            assert status == 200 and json.loads(body)["root"]
            status, body = response(base + "echo.php?token=abc&order_id=42", b"quantity=3")
            result = json.loads(body)
            assert status == 200 and result["method"] == "POST"
            assert result["query"] == {"token": "abc", "order_id": "42"}
            assert result["body"] == "quantity=3" and result["root"]
            assert response(base + "unknown.php")[0] == 404
            assert response(base + "app/pages/echo.php")[0] == 404
            assert response(base + "config/local.php")[0] == 404
            assert response(base + "sendgrid.env")[0] == 404
            assert response(base + "assets/example.css") == (200, "body{}")
        finally:
            server.terminate()
            server.wait(timeout=5)


def check_settings():
    with tempfile.TemporaryDirectory() as temp:
        config = Path(temp)
        shutil.copyfile(ROOT / "config" / "settings.php", config / "settings.php")
        (config / "local.php").write_text(
            "<?php return ['LOCAL_ONLY'=>'private-value','OVERRIDE'=>'file-value'];",
            encoding="utf-8"
        )
        (config / "check.php").write_text(
            "<?php require __DIR__.'/settings.php';"
            "putenv('OVERRIDE=environment-value'); putenv('EMPTY_VALUE=');"
            "echo json_encode([app_setting('LOCAL_ONLY'),app_setting('OVERRIDE'),"
            "app_setting('MISSING','fallback'),app_setting('EMPTY_VALUE','fallback')]);",
            encoding="utf-8"
        )
        result = subprocess.check_output([PHP, "-n", str(config / "check.php")], text=True)
        assert json.loads(result) == ["private-value", "environment-value", "fallback", ""]


def check_production_config():
    with tempfile.TemporaryDirectory() as temp:
        output = Path(temp) / "local.php"
        environment = os.environ.copy()
        names = ["DB_HOST", "DB_NAME", "DB_USER", "DB_PASSWORD", "PAYPAL_CLIENT_ID",
                 "PAYPAL_CLIENT_SECRET", "SENDGRID_API_KEY", "MAIL_FROM", "SITE_URL",
                 "PAYPAL_API_URL"]
        for name in names:
            environment[name] = "test-'quoted-\\value"
        environment["SITE_URL"] = "https://example.com"
        environment["PAYPAL_API_URL"] = "https://api-m.sandbox.paypal.com"
        command = [PHP, "-n", str(ROOT / "scripts" / "write-production-config.php"), str(output)]
        result = subprocess.run(command, env=environment, capture_output=True, text=True)
        assert result.returncode == 0, result.stderr
        assert environment["DB_PASSWORD"] not in result.stdout + result.stderr
        readback = [PHP, "-n", "-r", "echo json_encode(require $argv[1]);", str(output)]
        settings = json.loads(subprocess.check_output(readback, text=True))
        assert settings["DB_PASSWORD"] == environment["DB_PASSWORD"]
        output.unlink()
        environment["DB_PASSWORD"] = ""
        result = subprocess.run(command, env=environment, capture_output=True, text=True)
        assert result.returncode != 0 and not output.exists()


check_mount("")
check_mount("newgrounds")
check_settings()
check_production_config()
print("Routing, POST/query preservation, configuration precedence and generation passed.")
