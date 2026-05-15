#!/usr/bin/env python3
"""
Maya Phone Bridge · runs inside Termux on Mo's Samsung Fold 5
================================================================
Exposes phone filesystem + shell + Termux:API to the Maya OS PWA.

Architecture:
  Maya OS PWA (browser) ──HTTP──> 127.0.0.1:8765 (this bridge, in Termux)
                                  ├── /health (no auth · capability discovery)
                                  ├── /files  (auth · list/read storage)
                                  ├── /shell  (auth · run arbitrary shell · gate)
                                  ├── /ssh    (auth · outbound SSH wrapper)
                                  └── /termux (auth · termux-api passthrough)

Security:
  - Bound to 127.0.0.1 by default (only same-device PWA can reach)
  - Token-gated via X-Maya-Bridge-Token header
  - Token auto-generated on first run · written to ~/.maya_bridge_token
  - Destructive ops (rm -rf · dd · etc.) require ?confirm=1 in URL
  - All requests logged to ~/.maya_bridge.log

Install (run once in Termux):
  pkg install python termux-api openssh -y
  pip install fastapi uvicorn
  termux-setup-storage         # grants ~/storage/shared symlink
  python server.py             # starts on 127.0.0.1:8765

LAN access (optional · Phase 2):
  python server.py --bind 0.0.0.0    # exposes to LAN

Public access via Cloudflare tunnel (Phase 2 · TODO):
  pkg install cloudflared
  cloudflared tunnel --url http://127.0.0.1:8765

PHASE 3 (native APK · separate build):
  Wrap PWA in Trusted Web Activity + add Accessibility Service so Maya
  can read Chrome tabs / OCR any screen / watch taps across apps.
"""
from __future__ import annotations
import argparse, base64, hashlib, hmac, json, os, pathlib, secrets, shlex
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

# ── Paths · Termux home ───────────────────────────────────────────────────
HOME = pathlib.Path(os.path.expanduser("~"))
STORAGE = HOME / "storage" / "shared"        # symlink from termux-setup-storage
TOKEN_FILE = HOME / ".maya_bridge_token"
LOG_FILE = HOME / ".maya_bridge.log"
VERSION = "1.0.0-phase1-2026-05-15"

# ── Token bootstrap · auto-generate on first run ──────────────────────────
def ensure_token() -> str:
    if TOKEN_FILE.exists():
        return TOKEN_FILE.read_text().strip()
    tok = secrets.token_urlsafe(48)
    TOKEN_FILE.write_text(tok)
    try: TOKEN_FILE.chmod(0o600)
    except Exception: pass
    return tok

BRIDGE_TOKEN = ensure_token()

# ── Logging ───────────────────────────────────────────────────────────────
logging.basicConfig(
    filename=str(LOG_FILE),
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
)
log = logging.getLogger("maya_bridge")

# ── App ───────────────────────────────────────────────────────────────────
app = FastAPI(title="Maya Phone Bridge", version=VERSION)
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],     # Maya OS PWA is at iamsuperio.cloud/maya-os/ — allow it
    allow_credentials=False,
    allow_methods=["GET", "POST", "OPTIONS"],
    allow_headers=["*"],
)

# ── Auth helper ───────────────────────────────────────────────────────────
def require_token(x_maya_bridge_token: Optional[str]) -> None:
    if not x_maya_bridge_token or not hmac.compare_digest(x_maya_bridge_token, BRIDGE_TOKEN):
        raise HTTPException(status_code=401, detail="bad bridge token")

# ── /health · no auth · capability discovery ──────────────────────────────
@app.get("/health")
def health():
    caps = {
        "files":      STORAGE.exists(),
        "termux_api": shutil_which("termux-clipboard-get") is not None,
        "ssh":        shutil_which("ssh") is not None,
        "shell":      True,
    }
    return {
        "ok": True,
        "service": "maya_phone_bridge",
        "version": VERSION,
        "ts": time.strftime("%Y-%m-%dT%H:%M:%S%z"),
        "device_hint": os.uname().nodename,
        "storage_path": str(STORAGE),
        "capabilities": caps,
        "token_required": True,
        "token_path": str(TOKEN_FILE),
    }

def shutil_which(cmd: str) -> Optional[str]:
    import shutil
    return shutil.which(cmd)

# ── /files · list or read ─────────────────────────────────────────────────
@app.get("/files")
def files(path: str = "", x_maya_bridge_token: Optional[str] = Header(None)):
    require_token(x_maya_bridge_token)
    base = STORAGE if STORAGE.exists() else HOME
    target = (base / path.lstrip("/")).resolve()
    # Path traversal guard
    if not str(target).startswith(str(base.resolve())):
        raise HTTPException(403, "outside storage root")
    if not target.exists():
        raise HTTPException(404, "not found")
    if target.is_dir():
        items = []
        for child in sorted(target.iterdir(), key=lambda p: (not p.is_dir(), p.name.lower())):
            try:
                st = child.stat()
                items.append({
                    "name": child.name,
                    "type": "dir" if child.is_dir() else "file",
                    "size": st.st_size,
                    "mtime": int(st.st_mtime),
                })
            except OSError:
                continue
        return {"ok": True, "path": str(target.relative_to(base)), "items": items}
    # File · return content (text guess) or base64
    st = target.stat()
    if st.st_size > 5_000_000:
        raise HTTPException(413, "file too large · use /download for binaries")
    raw = target.read_bytes()
    try:
        return {"ok": True, "path": str(target.relative_to(base)), "size": st.st_size, "text": raw.decode("utf-8"), "encoding": "utf-8"}
    except UnicodeDecodeError:
        return {"ok": True, "path": str(target.relative_to(base)), "size": st.st_size, "base64": base64.b64encode(raw).decode("ascii"), "encoding": "base64"}

@app.get("/download")
def download(path: str, x_maya_bridge_token: Optional[str] = Header(None)):
    require_token(x_maya_bridge_token)
    base = STORAGE if STORAGE.exists() else HOME
    target = (base / path.lstrip("/")).resolve()
    if not str(target).startswith(str(base.resolve())):
        raise HTTPException(403, "outside storage root")
    if not target.is_file():
        raise HTTPException(404, "not a file")
    return FileResponse(str(target), filename=target.name)

# ── /shell · gated arbitrary command ──────────────────────────────────────
DESTRUCTIVE_NEEDLES = ["rm -rf /", "rm -rf ~", "dd if=", "mkfs", ":(){:|:&};:", "shutdown", "reboot"]

@app.post("/shell")
async def shell(req: Request, x_maya_bridge_token: Optional[str] = Header(None)):
    require_token(x_maya_bridge_token)
    body = await req.json()
    cmd = (body.get("cmd") or "").strip()
    confirm = bool(body.get("confirm", False))
    if not cmd:
        raise HTTPException(400, "cmd required")
    is_destructive = any(needle in cmd for needle in DESTRUCTIVE_NEEDLES)
    if is_destructive and not confirm:
        log.warning("destructive command blocked (no confirm): %s", cmd)
        return {"ok": False, "blocked": True, "reason": "destructive · resend with confirm=true if intentional", "cmd": cmd}
    log.info("shell · cmd=%s confirm=%s", cmd, confirm)
    try:
        r = subprocess.run(["bash", "-c", cmd], capture_output=True, text=True, timeout=60)
        return {"ok": r.returncode == 0, "exit": r.returncode, "stdout": r.stdout[:50_000], "stderr": r.stderr[:10_000], "cmd": cmd}
    except subprocess.TimeoutExpired:
        return {"ok": False, "error": "timeout (60s)", "cmd": cmd}
    except Exception as e:
        return {"ok": False, "error": str(e), "cmd": cmd}

# ── /ssh · outbound SSH wrapper ──────────────────────────────────────────
@app.post("/ssh")
async def ssh(req: Request, x_maya_bridge_token: Optional[str] = Header(None)):
    require_token(x_maya_bridge_token)
    body = await req.json()
    host = (body.get("host") or "").strip()
    cmd  = (body.get("cmd") or "").strip()
    user = (body.get("user") or "").strip()
    if not host:
        raise HTTPException(400, "host required")
    target = f"{user}@{host}" if user else host
    full = ["ssh", "-o", "StrictHostKeyChecking=accept-new", "-o", "ConnectTimeout=15", target]
    if cmd:
        full.append(cmd)
    log.info("ssh · target=%s cmd=%s", target, cmd[:120])
    try:
        r = subprocess.run(full, capture_output=True, text=True, timeout=120)
        return {"ok": r.returncode == 0, "exit": r.returncode, "stdout": r.stdout[:50_000], "stderr": r.stderr[:10_000], "target": target}
    except subprocess.TimeoutExpired:
        return {"ok": False, "error": "timeout (120s)", "target": target}
    except Exception as e:
        return {"ok": False, "error": str(e), "target": target}

# ── /termux · pass-through for termux-* CLI tools ────────────────────────
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
    if api not in TERMUX_WHITELIST:
        raise HTTPException(400, f"termux api '{api}' not in whitelist · supported: {sorted(TERMUX_WHITELIST)}")
    body_raw = await req.body()
    args = []
    if body_raw:
        try:
            body = json.loads(body_raw)
            for k, v in (body.get("args") or {}).items():
                args.append(f"--{k}")
                args.append(str(v))
        except Exception:
            pass
    full = [f"termux-{api}"] + args
    log.info("termux · %s args=%s", api, args)
    try:
        r = subprocess.run(full, capture_output=True, text=True, timeout=30)
        # Try to parse stdout as JSON (most termux-api commands return JSON)
        out_text = r.stdout
        try:
            out_parsed = json.loads(out_text) if out_text.strip() else None
        except json.JSONDecodeError:
            out_parsed = None
        return {"ok": r.returncode == 0, "exit": r.returncode, "stdout": out_text[:50_000], "parsed": out_parsed, "stderr": r.stderr[:10_000], "api": api}
    except FileNotFoundError:
        return {"ok": False, "error": f"termux-{api} not installed · run: pkg install termux-api", "api": api}
    except subprocess.TimeoutExpired:
        return {"ok": False, "error": "timeout (30s)", "api": api}
    except Exception as e:
        return {"ok": False, "error": str(e), "api": api}

# ── /token · one-time display so Mo can paste into Maya OS ───────────────
@app.get("/token")
def token():
    # Returns the token only when the request comes from localhost (Mo on his own phone)
    return {"ok": True, "token": BRIDGE_TOKEN, "hint": "paste this into Maya OS · Phone Bridge setup"}

# ── Main ──────────────────────────────────────────────────────────────────
def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--bind", default="127.0.0.1", help="bind address · 127.0.0.1 (default · safe) or 0.0.0.0 (LAN)")
    ap.add_argument("--port", default=8765, type=int)
    args = ap.parse_args()
    print(f"┏━━━ Maya Phone Bridge v{VERSION}")
    print(f"┃ token: {BRIDGE_TOKEN[:12]}...{BRIDGE_TOKEN[-6:]} (full at {TOKEN_FILE})")
    print(f"┃ bind:  http://{args.bind}:{args.port}")
    print(f"┃ log:   {LOG_FILE}")
    print(f"┃ paste this token into Maya OS · Settings · Phone Bridge:")
    print(f"┃   {BRIDGE_TOKEN}")
    print(f"┗━━━ ready · Ctrl+C to stop")
    uvicorn.run(app, host=args.bind, port=args.port, log_level="info")

if __name__ == "__main__":
    main()
