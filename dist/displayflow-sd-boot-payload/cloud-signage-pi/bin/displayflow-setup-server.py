#!/usr/bin/env python3
import argparse
import json
import os
import re
import subprocess
from datetime import datetime, timezone
from http import HTTPStatus
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import parse_qs, urlparse


BASE_DIR = Path(os.environ.get("DISPLAYFLOW_BASE_DIR", "/usr/local/lib/displayflow"))
CONFIG_DIR = Path(os.environ.get("DISPLAYFLOW_CONFIG_DIR", "/etc/displayflow"))
STATE_DIR = Path(os.environ.get("DISPLAYFLOW_STATE_DIR", "/var/lib/displayflow"))
DEVICE_CONFIG_PATH = Path(os.environ.get("DISPLAYFLOW_DEVICE_CONFIG", str(CONFIG_DIR / "config.json")))
STATE_PATH = Path(os.environ.get("DISPLAYFLOW_STATE_FILE", str(STATE_DIR / "state.json")))
ENV_FILE = Path(os.environ.get("DISPLAYFLOW_ENV_FILE", "/etc/default/displayflow"))
SETUP_UI_DIR = BASE_DIR / "setup-ui"
AP_HOST = os.environ.get("DISPLAYFLOW_AP_HOST", "192.168.4.1")
SETUP_PORT = int(os.environ.get("DISPLAYFLOW_SETUP_PORT", "80"))
APPLY_SCRIPT = BASE_DIR / "bin" / "displayflow-apply-provisioning.sh"


def load_env_file(path: Path) -> dict:
    values = {}
    if not path.exists():
        return values

    for raw_line in path.read_text(encoding="utf-8").splitlines():
        line = raw_line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, value = line.split("=", 1)
        values[key.strip()] = value.strip().strip('"').strip("'")
    return values


ENV = load_env_file(ENV_FILE)
API_BASE_URL = ENV.get("DISPLAYFLOW_API_BASE_URL", os.environ.get("DISPLAYFLOW_API_BASE_URL", "https://babbage-ai.co.uk/Present")).rstrip("/")
FAILURE_THRESHOLD = int(ENV.get("DISPLAYFLOW_FAILURE_THRESHOLD", os.environ.get("DISPLAYFLOW_FAILURE_THRESHOLD", "3")))


def utc_now() -> str:
    return datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")


def atomic_write_json(path: Path, payload: dict, mode: int) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    tmp_path = path.with_suffix(".tmp")
    tmp_path.write_text(json.dumps(payload, indent=2) + "\n", encoding="utf-8")
    os.chmod(tmp_path, mode)
    os.replace(tmp_path, path)


def load_json(path: Path, default: dict) -> dict:
    if not path.exists():
        return dict(default)
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except Exception:
        return dict(default)


def merge_state(updates: dict) -> dict:
    current = load_json(
        STATE_PATH,
        {
            "mode": "setup",
            "provisioning_status": "unprovisioned",
            "consecutive_failures": 0,
            "last_error": "",
            "last_message": "",
            "setup_ssid": "",
            "setup_hotspot_ready": False,
        },
    )
    current.update(updates)
    current["updated_at"] = utc_now()
    atomic_write_json(STATE_PATH, current, 0o600)
    return current


def sanitize_screen_code(value: str) -> str:
    cleaned = re.sub(r"[^A-Za-z0-9_-]+", "", value or "").upper()
    return cleaned[:64]


def validate_payload(payload: dict) -> dict:
    wifi_ssid = str(payload.get("wifi_ssid", "")).strip()
    wifi_password = str(payload.get("wifi_password", "")).strip()
    screen_code = sanitize_screen_code(str(payload.get("screen_code", "")))

    errors = {}

    if not wifi_ssid or len(wifi_ssid) > 32:
        errors["wifi_ssid"] = "Enter the venue Wi-Fi name."

    if not wifi_password or len(wifi_password) < 8 or len(wifi_password) > 63:
        errors["wifi_password"] = "Enter the venue Wi-Fi password."

    if not screen_code or len(screen_code) < 4:
        errors["screen_code"] = "Enter a valid screen code."

    if errors:
        raise ValueError(json.dumps(errors))

    return {
        "wifi_ssid": wifi_ssid,
        "wifi_password": wifi_password,
        "screen_code": screen_code,
        "provisioned": False,
        "provisioned_at": "",
        "last_updated_at": utc_now(),
    }


def masked_config_summary() -> dict:
    config = load_json(DEVICE_CONFIG_PATH, {})
    if not config:
        return {}

    password = str(config.get("wifi_password", ""))
    masked = "*" * max(len(password), 8) if password else ""

    return {
        "wifi_ssid": config.get("wifi_ssid", ""),
        "wifi_password_masked": masked,
        "screen_code": config.get("screen_code", ""),
        "last_updated_at": config.get("last_updated_at", ""),
        "provisioned": bool(config.get("provisioned")),
    }


def launch_apply_service() -> None:
    subprocess.run(
        [
            "systemd-run",
            "--unit=displayflow-apply-provisioning",
            "--collect",
            "--property=Type=oneshot",
            str(APPLY_SCRIPT),
        ],
        check=True,
    )


class SetupHandler(BaseHTTPRequestHandler):
    server_version = "DisplayFlowSetup/1.0"

    def log_message(self, format_string, *args):
        message = "%s - - [%s] %s" % (
            self.client_address[0],
            self.log_date_time_string(),
            format_string % args,
        )
        print(message, flush=True)

    def end_headers(self):
        self.send_header("Cache-Control", "no-store, no-cache, must-revalidate")
        self.send_header("Pragma", "no-cache")
        super().end_headers()

    def _send_json(self, payload: dict, status: int = HTTPStatus.OK):
        body = json.dumps(payload).encode("utf-8")
        self.send_response(status)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def _send_redirect(self, location: str):
        self.send_response(HTTPStatus.FOUND)
        self.send_header("Location", location)
        self.send_header("Content-Length", "0")
        self.end_headers()

    def _send_file(self, path: Path, content_type: str):
        if not path.exists() or not path.is_file():
            self.send_error(HTTPStatus.NOT_FOUND)
            return

        body = path.read_bytes()
        self.send_response(HTTPStatus.OK)
        self.send_header("Content-Type", content_type)
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def _read_json_body(self) -> dict:
        length = int(self.headers.get("Content-Length", "0"))
        if length <= 0 or length > 65535:
            raise ValueError("Invalid request size.")

        raw_body = self.rfile.read(length)
        return json.loads(raw_body.decode("utf-8"))

    def do_GET(self):
        parsed = urlparse(self.path)
        path = parsed.path

        if path in {"/", "/index.html"}:
            self._send_file(SETUP_UI_DIR / "index.html", "text/html; charset=utf-8")
            return

        if path == "/app.css":
            self._send_file(SETUP_UI_DIR / "app.css", "text/css; charset=utf-8")
            return

        if path == "/app.js":
            self._send_file(SETUP_UI_DIR / "app.js", "application/javascript; charset=utf-8")
            return

        if path in {"/screen", "/screen.html"}:
            self._send_file(SETUP_UI_DIR / "screen.html", "text/html; charset=utf-8")
            return

        if path == "/screen.js":
            self._send_file(SETUP_UI_DIR / "screen.js", "application/javascript; charset=utf-8")
            return

        if path == "/api/status":
            state = load_json(STATE_PATH, {})
            config_summary = masked_config_summary()
            payload = {
                "success": True,
                "data": {
                    "state": state,
                    "config": config_summary,
                    "setup_host": f"http://{AP_HOST}",
                    "api_base_url": API_BASE_URL,
                    "failure_threshold": FAILURE_THRESHOLD,
                },
            }
            self._send_json(payload)
            return

        if path in {"/generate_204", "/hotspot-detect.html", "/ncsi.txt", "/connecttest.txt", "/success.txt", "/redirect"}:
            self._send_redirect("/")
            return

        if path == "/library/test/success.html":
            self._send_redirect("/")
            return

        self.send_error(HTTPStatus.NOT_FOUND)

    def do_POST(self):
        parsed = urlparse(self.path)

        if parsed.path != "/api/provision":
            self.send_error(HTTPStatus.NOT_FOUND)
            return

        try:
            payload = self._read_json_body()
            validated = validate_payload(payload)
        except ValueError as exc:
            try:
                field_errors = json.loads(str(exc))
            except Exception:
                field_errors = {"form": str(exc)}
            self._send_json({"success": False, "message": "Validation failed.", "errors": field_errors}, HTTPStatus.BAD_REQUEST)
            return
        except json.JSONDecodeError:
            self._send_json({"success": False, "message": "Invalid JSON payload."}, HTTPStatus.BAD_REQUEST)
            return

        existing = load_json(DEVICE_CONFIG_PATH, {})
        existing.update(validated)
        atomic_write_json(DEVICE_CONFIG_PATH, existing, 0o600)

        merge_state(
            {
                "mode": "transition",
                "provisioning_status": "pending_apply",
                "last_error": "",
                "last_message": "Configuration saved. Switching to venue Wi-Fi now.",
            }
        )

        try:
            launch_apply_service()
        except subprocess.CalledProcessError:
            merge_state(
                {
                    "mode": "setup",
                    "provisioning_status": "failed",
                    "last_error": "Could not start the provisioning worker.",
                    "last_message": "The setup service could not continue. Check system logs.",
                }
            )
            self._send_json(
                {"success": False, "message": "Could not start provisioning."},
                HTTPStatus.INTERNAL_SERVER_ERROR,
            )
            return

        self._send_json(
            {
                "success": True,
                "message": "Configuration saved. The hotspot will disconnect while the Pi joins the venue Wi-Fi.",
                "data": {
                    "next_steps": [
                        "Wait up to 60 seconds for the device to join the venue Wi-Fi.",
                        f"If setup fails, reconnect to {load_json(STATE_PATH, {}).get('setup_ssid', 'the setup hotspot')} and reopen http://{AP_HOST}.",
                    ]
                },
            }
        )


def main():
    parser = argparse.ArgumentParser(description="DisplayFlow local setup server")
    parser.add_argument("--host", default="0.0.0.0")
    parser.add_argument("--port", type=int, default=SETUP_PORT)
    args = parser.parse_args()

    server = ThreadingHTTPServer((args.host, args.port), SetupHandler)
    print(f"DisplayFlow setup server listening on http://{args.host}:{args.port}", flush=True)
    server.serve_forever()


if __name__ == "__main__":
    main()
