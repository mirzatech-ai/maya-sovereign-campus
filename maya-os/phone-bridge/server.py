#!/usr/bin/env python3
"""
Maya Device Bridge · runs on Mo's phone (Termux) AND laptop (Windows/macOS/Linux)
==================================================================================
Same bridge architecture, three host targets. Maya OS PWA connects to whichever
device is reachable and uses the local resources of that device.

Capability list is computed at boot from what's actually installed:
  - Termux (Samsung Fold 5)  → +termux_api (clipboard/camera/contacts/SMS/etc)
  - Windows laptop           → +powershell, +clip, +explorer-open
  - macOS                    → +pbpaste/pbcopy, +open, +osascript
  - Linux                    → +xclip/xsel (if installed), +xdg-open

Endpoints (same on every host):
  GET  /health                            no auth · capabilities + version + platform
  GET  /token                             no auth · echo the bridge token (only useful locally)
  GET  /files?path=<>                     token · list dir / read text file
  GET  /download?path=<>                  token · stream a binary file
  POST /shell {cmd, confirm?}             token · shell exec (destructive ops gated)
  POST /ssh   {host, user?, cmd?}         token · outbound SSH
  POST /termux/<api>                      token · Termux:API passthrough (Termux only)
  POST /sys/<verb>                        token · platform-agnostic verbs: clipboard-get/set,
                                          open-file, open-url, notify, list-processes, env

Security (same across all hosts):
  - Bound to 127.0.0.1 by default
  - Token-gated via X-Maya-Bridge-Token header
  - Token auto-generated to ~/.maya_bridge_token (mode 0600 on POSIX)
  - Destructive shell needles gated (rm -rf / · dd if= · :(){:|:&};: · mkfs · shutdown · reboot · format C:)
  - Path traversal blocked on /files (chroot to declared user-storage root)
  - All requests logged to ~/.maya_bridge.log

Install:
  Termux:  bash <(curl -fsSL https://iamsuperio.cloud/phone-bridge/install.sh)
  macOS:   bash <(curl -fsSL https://iamsuperio.cloud/phone-bridge/install.sh)
  Linux:   bash <(curl -fsSL https://iamsuperio.cloud/phone-bridge/install.sh)
  Windows: iwr https://iamsuperio.cloud/phone-bridge/install.ps1 -UseB | iex
"""
from __future__ import annotations
import argparse, base64, hmac, json, os, pathlib, platform, secrets, shutil
import subprocess, sys, time, logging
from typing import Optional

try:
    from fastapi import FastAPI, Header, HTTPException, Request
    from fastapi.middleware.cors import CORSMiddleware
    from fastapi.responses import JSONResponse, FileResponse
    import uvicorn
except ImportError:
    print("Missing deps. Run: pip install fastapi uvicorn", file=sys.stderr)
    sys.exit(1)

# ── Platform detection ────────────────────────────────────────────────────
def detect_platform() -> dict:
    """Return {kind, label, storage_root, is_termux, is_windows, is_macos, is_linux}"""
    is_termux = os.environ.get("TERMUX_VERSION") is not None or pathlib.Path("/data/data/com.termux/files/usr").exists()
    if is_termux:
        return {"kind": "termux", "label": "Termux (Android)", "storage_root": "~/storage/shared", "is_termux": True, "is_windows": False, "is_macos": False, "is_linux": False}
    sysname = platform.system()
    if sysname == "Windows":
        return {"kind": "windows", "label": "Windows", "storage_root": "~", "is_termux": False, "is_windows": True, "is_macos": False, "is_linux": False}
    if sysname == "Darwin":
        return {"kind": "macos", "label": "macOS", "storage_root": "~", "is_termux": False, "is_windows": False, "is_macos": True, "is_linux": False}
    return {"kind": "linux", "label": "Linux", "storage_root": "~", "is_termux": False, "is_windows": False, "is_macos": False, "is_linux": True}

PLATFORM = detect_platform()
HOME = pathlib.Path(os.path.expanduser("~"))
STORAGE_ROOT_RAW = pathlib.Path(os.path.expanduser(PLATFORM["storage_root"])).resolve()
TOKEN_FILE = HOME / ".maya_bridge_token"
LOG_FILE = HOME / ".maya_bridge.log"
VERSION = "1.1.0-cross-platform-2026-05-15"

# ── Token bootstrap ───────────────────────────────────────────────────────
def ensure_token() -> str:
    if TOKEN_FILE.exists():
        return TOKEN_FILE.read_text().strip()
    tok = secrets.token_urlsafe(48)
    TOKEN_FILE.write_text(tok)
    if not PLATFORM["is_windows"]:
        try: TOKEN_FILE.chmod(0o600)
        except Exception: pass
    return tok

BRIDGE_TOKEN = ensure_token()

logging.basicConfig(filename=str(LOG_FILE), level=logging.INFO,
                    format="%(asctime)s [%(levelname)s] %(message)s")
log = logging.getLogger("maya_bridge")

# ── Capability detection · what verbs this host actually has ─────────────
def detect_capabilities() -> dict:
    caps = {"files": STORAGE_ROOT_RAW.exists(), "shell": True, "ssh": shutil.which("ssh") is not None}
    # Termux:API surface
    caps["termux_api"] = PLATFORM["is_termux"] and shutil.which("termux-clipboard-get") is not None
    # Sys verbs · what's wired for each platform's clipboard / open / notify
    if PLATFORM["is_windows"]:
        caps["clipboard"] = True   # via PowerShell Get/Set-Clipboard
        caps["open"]      = True   # via start
        caps["notify"]    = shutil.which("powershell.exe") is not None  # BurntToast optional · falls back to msg
    elif PLATFORM["is_macos"]:
        caps["clipboard"] = shutil.which("pbpaste") is not None and shutil.which("pbcopy") is not None
        caps["open"]      = shutil.which("open") is not None
        caps["notify"]    = shutil.which("osascript") is not None
    elif PLATFORM["is_termux"]:
        caps["clipboard"] = shutil.which("termux-clipboard-get") is not None
        caps["open"]      = shutil.which("termux-open") is not None
        caps["notify"]    = shutil.which("termux-notification") is not None
    else:
        caps["clipboard"] = shutil.which("xclip") is not None or shutil.which("xsel") is not None or shutil.which("wl-copy") is not None
        caps["open"]      = shutil.which("xdg-open") is not None
        caps["notify"]    = shutil.which("notify-send") is not None
    return caps

CAPABILITIES = detect_capabilities()

# ── App ───────────────────────────────────────────────────────────────────
app = FastAPI(title="Maya Device Bridge", version=VERSION)
app.add_middleware(CORSMiddleware, allow_origins=["*"], allow_credentials=False,
                   allow_methods=["GET", "POST", "OPTIONS"], allow_headers=["*"])

def require_token(x_maya_bridge_token: Optional[str]) -> None:
    if not x_maya_bridge_token or not hmac.compare_digest(x_maya_bridge_token, BRIDGE_TOKEN):
        raise HTTPException(status_code=401, detail="bad bridge token")

# ── /health · no auth ─────────────────────────────────────────────────────
@app.get("/health")
def health():
    return {
        "ok": True, "service": "maya_device_bridge", "version": VERSION,
        "ts": time.strftime("%Y-%m-%dT%H:%M:%S%z"),
        "device_hint": platform.node(), "platform": PLATFORM,
        "storage_root": str(STORAGE_ROOT_RAW), "capabilities": CAPABILITIES,
        "token_required": True, "token_path": str(TOKEN_FILE),
    }

@app.get("/token")
def token():
    # Returns token (useful only when curling localhost from the device itself)
    return {"ok": True, "token": BRIDGE_TOKEN, "hint": "paste this into Maya OS · Device Bridge setup"}

# ── /files · cross-platform safe listing/reading ─────────────────────────
@app.get("/files")
def files(path: str = "", x_maya_bridge_token: Optional[str] = Header(None)):
    require_token(x_maya_bridge_token)
    base = STORAGE_ROOT_RAW
    target = (base / path.lstrip("/\\")).resolve()
    # Path-traversal guard · cross-platform (use Path comparison)
    try:
        target.relative_to(base)
    except ValueError:
        raise HTTPException(403, "outside storage root")
    if not target.exists():
        raise HTTPException(404, "not found")
    if target.is_dir():
        items = []
        try:
            children = sorted(target.iterdir(), key=lambda p: (not p.is_dir(), p.name.lower()))
        except PermissionError:
            raise HTTPException(403, "permission denied listing dir")
        for child in children:
            try:
                st = child.stat()
                items.append({"name": child.name, "type": "dir" if child.is_dir() else "file",
                              "size": st.st_size, "mtime": int(st.st_mtime)})
            except OSError:
                continue
        return {"ok": True, "path": str(target.relative_to(base)).replace("\\", "/"), "items": items}
    st = target.stat()
    if st.st_size > 5_000_000:
        raise HTTPException(413, "file too large · use /download for binaries")
    raw = target.read_bytes()
    rel = str(target.relative_to(base)).replace("\\", "/")
    try:
        return {"ok": True, "path": rel, "size": st.st_size, "text": raw.decode("utf-8"), "encoding": "utf-8"}
    except UnicodeDecodeError:
        return {"ok": True, "path": rel, "size": st.st_size, "base64": base64.b64encode(raw).decode("ascii"), "encoding": "base64"}

@app.get("/download")
def download(path: str, x_maya_bridge_token: Optional[str] = Header(None)):
    require_token(x_maya_bridge_token)
    base = STORAGE_ROOT_RAW
    target = (base / path.lstrip("/\\")).resolve()
    try: target.relative_to(base)
    except ValueError: raise HTTPException(403, "outside storage root")
    if not target.is_file(): raise HTTPException(404, "not a file")
    return FileResponse(str(target), filename=target.name)

# ── /shell · cross-platform with platform-aware shell ────────────────────
DESTRUCTIVE_NEEDLES = ["rm -rf /", "rm -rf ~", "dd if=", "mkfs", ":(){:|:&};:",
                      "shutdown", "reboot", "format c:", "format c ", "del /s /q c:\\",
                      "rd /s /q c:\\", "Remove-Item -Recurse -Force c:\\"]

@app.post("/shell")
async def shell(req: Request, x_maya_bridge_token: Optional[str] = Header(None)):
    require_token(x_maya_bridge_token)
    body = await req.json()
    cmd = (body.get("cmd") or "").strip()
    confirm = bool(body.get("confirm", False))
    if not cmd: raise HTTPException(400, "cmd required")
    is_destructive = any(needle.lower() in cmd.lower() for needle in DESTRUCTIVE_NEEDLES)
    if is_destructive and not confirm:
        log.warning("destructive command blocked: %s", cmd)
        return {"ok": False, "blocked": True, "reason": "destructive · resend with confirm=true", "cmd": cmd}
    log.info("shell · cmd=%s", cmd[:200])
    try:
        if PLATFORM["is_windows"]:
            r = subprocess.run(["powershell.exe", "-NoProfile", "-NonInteractive", "-Command", cmd],
                               capture_output=True, text=True, timeout=60)
        else:
            r = subprocess.run(["bash", "-c", cmd], capture_output=True, text=True, timeout=60)
        return {"ok": r.returncode == 0, "exit": r.returncode,
                "stdout": (r.stdout or "")[:50_000], "stderr": (r.stderr or "")[:10_000], "cmd": cmd}
    except subprocess.TimeoutExpired:
        return {"ok": False, "error": "timeout (60s)", "cmd": cmd}
    except Exception as e:
        return {"ok": False, "error": str(e), "cmd": cmd}

# ── /ssh · same across all platforms (ssh CLI required) ──────────────────
@app.post("/ssh")
async def ssh(req: Request, x_maya_bridge_token: Optional[str] = Header(None)):
    require_token(x_maya_bridge_token)
    body = await req.json()
    host = (body.get("host") or "").strip()
    cmd  = (body.get("cmd") or "").strip()
    user = (body.get("user") or "").strip()
    if not host: raise HTTPException(400, "host required")
    target = f"{user}@{host}" if user else host
    full = ["ssh", "-o", "StrictHostKeyChecking=accept-new", "-o", "ConnectTimeout=15", target]
    if cmd: full.append(cmd)
    log.info("ssh · target=%s", target)
    try:
        r = subprocess.run(full, capture_output=True, text=True, timeout=120)
        return {"ok": r.returncode == 0, "exit": r.returncode,
                "stdout": (r.stdout or "")[:50_000], "stderr": (r.stderr or "")[:10_000], "target": target}
    except subprocess.TimeoutExpired:
        return {"ok": False, "error": "timeout (120s)", "target": target}
    except Exception as e:
        return {"ok": False, "error": str(e), "target": target}

# ── /termux/<api> · Termux only · pass-through ───────────────────────────
TERMUX_WHITELIST = {
    "clipboard-get", "clipboard-set", "battery-status", "notification",
    "toast", "vibrate", "tts-speak", "location", "wifi-connectioninfo",
    "telephony-cellinfo", "share", "open", "camera-info", "camera-photo",
    "contact-list", "sms-list", "sms-send", "call-log", "dialog",
    "notification-list", "media-player", "wifi-scaninfo", "sensor",
}

@app.post("/termux/{api}")
async def termux_api(api: str, req: Request, x_maya_bridge_token: Optional[str] = Header(None)):
    require_token(x_maya_bridge_token)
    if not PLATFORM["is_termux"]:
        raise HTTPException(400, f"termux api requires Termux host · this is {PLATFORM['label']} · use /sys/* instead")
    if api not in TERMUX_WHITELIST:
        raise HTTPException(400, f"termux api '{api}' not in whitelist · supported: {sorted(TERMUX_WHITELIST)}")
    body_raw = await req.body()
    args = []
    if body_raw:
        try:
            body = json.loads(body_raw)
            for k, v in (body.get("args") or {}).items():
                args.append(f"--{k}"); args.append(str(v))
        except Exception:
            pass
    full = [f"termux-{api}"] + args
    log.info("termux · %s args=%s", api, args)
    try:
        r = subprocess.run(full, capture_output=True, text=True, timeout=30)
        try: out_parsed = json.loads(r.stdout) if r.stdout.strip() else None
        except json.JSONDecodeError: out_parsed = None
        return {"ok": r.returncode == 0, "exit": r.returncode,
                "stdout": (r.stdout or "")[:50_000], "parsed": out_parsed,
                "stderr": (r.stderr or "")[:10_000], "api": api}
    except FileNotFoundError:
        return {"ok": False, "error": f"termux-{api} not installed", "api": api}
    except subprocess.TimeoutExpired:
        return {"ok": False, "error": "timeout (30s)", "api": api}
    except Exception as e:
        return {"ok": False, "error": str(e), "api": api}

# ── /sys/<verb> · cross-platform abstractions ────────────────────────────
@app.post("/sys/{verb}")
async def sys_verb(verb: str, req: Request, x_maya_bridge_token: Optional[str] = Header(None)):
    require_token(x_maya_bridge_token)
    body_raw = await req.body()
    body = {}
    if body_raw:
        try: body = json.loads(body_raw)
        except Exception: body = {}
    args = body.get("args") or {}

    def run(cmd_list, input_text=None, timeout=15):
        return subprocess.run(cmd_list, capture_output=True, text=True, input=input_text, timeout=timeout)

    try:
        if verb == "clipboard-get":
            if PLATFORM["is_windows"]:
                r = run(["powershell.exe", "-NoProfile", "-Command", "Get-Clipboard"])
                return {"ok": r.returncode == 0, "text": r.stdout}
            if PLATFORM["is_macos"]:
                r = run(["pbpaste"]); return {"ok": r.returncode == 0, "text": r.stdout}
            if PLATFORM["is_termux"]:
                r = run(["termux-clipboard-get"]); return {"ok": r.returncode == 0, "text": r.stdout}
            for tool in (["xclip", "-selection", "clipboard", "-o"], ["xsel", "--clipboard", "--output"], ["wl-paste"]):
                if shutil.which(tool[0]):
                    r = run(tool); return {"ok": r.returncode == 0, "text": r.stdout}
            return {"ok": False, "error": "no clipboard tool installed"}

        if verb == "clipboard-set":
            text = args.get("text", "")
            if PLATFORM["is_windows"]:
                r = run(["powershell.exe", "-NoProfile", "-Command", "Set-Clipboard -Value $input"], input_text=text)
                return {"ok": r.returncode == 0}
            if PLATFORM["is_macos"]:
                r = run(["pbcopy"], input_text=text); return {"ok": r.returncode == 0}
            if PLATFORM["is_termux"]:
                r = run(["termux-clipboard-set", text]); return {"ok": r.returncode == 0}
            for tool in (["xclip", "-selection", "clipboard"], ["xsel", "--clipboard", "--input"], ["wl-copy"]):
                if shutil.which(tool[0]):
                    r = run(tool, input_text=text); return {"ok": r.returncode == 0}
            return {"ok": False, "error": "no clipboard tool installed"}

        if verb == "open":
            target = args.get("path") or args.get("url", "")
            if not target: return {"ok": False, "error": "args.path or args.url required"}
            if PLATFORM["is_windows"]:
                r = run(["cmd.exe", "/c", "start", "", target]); return {"ok": r.returncode == 0}
            if PLATFORM["is_macos"]:
                r = run(["open", target]); return {"ok": r.returncode == 0}
            if PLATFORM["is_termux"]:
                r = run(["termux-open", target]); return {"ok": r.returncode == 0}
            if shutil.which("xdg-open"):
                r = run(["xdg-open", target]); return {"ok": r.returncode == 0}
            return {"ok": False, "error": "no open tool installed"}

        if verb == "notify":
            title = args.get("title", "Maya")
            message = args.get("message", "")
            if PLATFORM["is_windows"]:
                # msg.exe is reliable across Win editions; falls back gracefully
                cmd = f'msg "$env:USERNAME" /TIME:10 "{title}: {message}"'
                r = run(["powershell.exe", "-NoProfile", "-Command", cmd]); return {"ok": True}
            if PLATFORM["is_macos"]:
                r = run(["osascript", "-e", f'display notification "{message}" with title "{title}"'])
                return {"ok": r.returncode == 0}
            if PLATFORM["is_termux"]:
                r = run(["termux-notification", "--title", title, "--content", message]); return {"ok": r.returncode == 0}
            if shutil.which("notify-send"):
                r = run(["notify-send", title, message]); return {"ok": r.returncode == 0}
            return {"ok": False, "error": "no notify tool installed"}

        if verb == "env":
            keys = args.get("keys") or []
            if keys: out = {k: os.environ.get(k, "") for k in keys}
            else: out = {"USER": os.environ.get("USER") or os.environ.get("USERNAME", ""),
                         "HOME": str(HOME), "SHELL": os.environ.get("SHELL", ""),
                         "PATH_count": len(os.environ.get("PATH", "").split(os.pathsep))}
            return {"ok": True, "env": out}

        if verb == "list-processes":
            if PLATFORM["is_windows"]:
                r = run(["powershell.exe", "-NoProfile", "-Command",
                         "Get-Process | Select-Object Id,ProcessName,WorkingSet | ConvertTo-Json -Compress"], timeout=10)
                try:
                    parsed = json.loads(r.stdout) if r.stdout.strip() else []
                except Exception: parsed = []
                return {"ok": r.returncode == 0, "processes": parsed[:200]}
            else:
                r = run(["ps", "-ax", "-o", "pid,comm,rss"], timeout=10)
                return {"ok": r.returncode == 0, "raw": r.stdout[:50_000]}

        return {"ok": False, "error": f"unknown sys verb · supported: clipboard-get clipboard-set open notify env list-processes"}
    except subprocess.TimeoutExpired:
        return {"ok": False, "error": "timeout"}
    except Exception as e:
        return {"ok": False, "error": str(e)}

# ── Main ──────────────────────────────────────────────────────────────────
def main():
    # Force UTF-8 stdout on Windows (default cp1252 dies on box-drawing chars)
    try:
        sys.stdout.reconfigure(encoding="utf-8", errors="replace")
        sys.stderr.reconfigure(encoding="utf-8", errors="replace")
    except Exception:
        pass
    ap = argparse.ArgumentParser()
    ap.add_argument("--bind", default="127.0.0.1")
    ap.add_argument("--port", default=8765, type=int)
    args = ap.parse_args()
    # ASCII-safe banner (no fancy box chars) so this works on every console encoding
    print(f"=== Maya Device Bridge v{VERSION}")
    print(f"  platform: {PLATFORM['label']} ({PLATFORM['kind']})")
    print(f"  storage : {STORAGE_ROOT_RAW}")
    print(f"  caps    : " + " ".join(k for k, v in CAPABILITIES.items() if v))
    print(f"  bind    : http://{args.bind}:{args.port}")
    print(f"  log     : {LOG_FILE}")
    print(f"  token (paste into Maya OS - Device Bridge):")
    print(f"    {BRIDGE_TOKEN}")
    print(f"=== ready - Ctrl+C to stop")
    uvicorn.run(app, host=args.bind, port=args.port, log_level="info")

if __name__ == "__main__":
    main()
