# 📱💻 Maya Device Bridge

**One bridge architecture · runs on phone / laptop / desktop · Maya sees them all the same way.**
**Canonized:** 2026-05-15 · Kin · per Mo
**Hosts:** Samsung Fold 5 (Termux) · Windows laptop · macOS · Linux

## Install (pick your device)

### Termux on phone (Android)
```bash
bash <(curl -fsSL https://iamsuperio.cloud/phone-bridge/install.sh)
```

### Windows laptop
```powershell
iwr https://iamsuperio.cloud/phone-bridge/install.ps1 -UseB | iex
```

### macOS or Linux
```bash
bash <(curl -fsSL https://iamsuperio.cloud/phone-bridge/install.sh)
```

All three print a bridge token at the end. Paste it into **Maya OS → 📱 Device Bridge** (top header pill).

## Same endpoints on every host

The bridge auto-detects platform on boot and exposes only what's available:

| Endpoint | Termux phone | Windows | macOS | Linux |
|---|---|---|---|---|
| `GET /health` | ✅ | ✅ | ✅ | ✅ |
| `GET /files?path=` (storage root: `~/storage/shared` on Termux · `~` elsewhere) | ✅ | ✅ | ✅ | ✅ |
| `POST /shell {cmd}` (bash on POSIX · PowerShell on Windows) | ✅ | ✅ | ✅ | ✅ |
| `POST /ssh {host, user?, cmd?}` | ✅ (if `pkg install openssh`) | ✅ (Windows OpenSSH) | ✅ | ✅ |
| `POST /sys/clipboard-get` / `clipboard-set` | ✅ (termux-clipboard-*) | ✅ (PowerShell) | ✅ (pbpaste/pbcopy) | ✅ (xclip / xsel / wl-copy) |
| `POST /sys/open {path or url}` | ✅ (termux-open) | ✅ (`start`) | ✅ (`open`) | ✅ (xdg-open) |
| `POST /sys/notify {title, message}` | ✅ | ✅ (msg.exe) | ✅ (osascript) | ✅ (notify-send) |
| `POST /sys/env` · list-processes | ✅ | ✅ | ✅ | ✅ |
| `POST /termux/<api>` (clipboard · camera · contacts · SMS · location · etc.) | ✅ Termux only | ❌ | ❌ | ❌ |

`/health` returns the live capability map so Maya OS knows what each connected device can do.

## Security (same on every host)

- Binds `127.0.0.1` by default · only same-device PWA can reach unless you opt in to `--bind 0.0.0.0` (LAN) or a Cloudflare Tunnel (Phase 2)
- Every protected endpoint requires `X-Maya-Bridge-Token` header
- Token is 48 bytes of `secrets.token_urlsafe()` · stored at `~/.maya_bridge_token` (mode 0600 on POSIX · ACL by user profile on Windows)
- Destructive shell needles (`rm -rf /` · `dd if=` · `mkfs` · `:(){:|:&};:` · `shutdown` · `reboot` · `format c:` · `del /s /q c:\` · etc.) require `{confirm: true}` in body
- Path-traversal blocked on `/files` (resolved path must stay under declared storage root)
- All requests logged to `~/.maya_bridge.log`

## Phase status (honest)

| Phase | What it unlocks | Estimate | Status |
|---|---|---|---|
| **Phase 1** | Each device's files + shell + SSH + clipboard + native open/notify (and Termux:API on phone) | shipped today | ✅ |
| **Phase 2** | Cloudflare Tunnel · Maya brain on VPS reaches any of your devices from anywhere | ~30 min | ⏳ |
| **Phase 3** | Native Android APK with Accessibility Service — read Chrome tabs, OCR any screen, watch cross-app taps. Phones only. | ~6-10h separate build | ⏳ |

## Multi-device pattern

Run the bridge on every device you own (phone + laptop + desktop). Maya OS PWA lets you save **multiple bridge profiles**:
- "Phone": `http://127.0.0.1:8765` (when used inside the PWA on the phone itself · or via Cloudflare Tunnel URL from any device)
- "Laptop": `http://192.168.x.x:8765` (LAN reach from phone) or Cloudflare Tunnel URL
- "Desktop": same pattern

Maya can now run jobs on whichever device has the right resource: laptop for heavy computes · phone for camera/SMS/location/contacts · desktop for the local coding agents (Kimi/OpenCode/VS Code on ports 8086-8088).

## Files in this bundle

| File | Purpose |
|---|---|
| `server.py` | The cross-platform bridge · auto-detects host · same code on Termux/Windows/macOS/Linux |
| `install.sh` | One-liner installer for Termux + macOS + Linux |
| `install.ps1` | One-liner installer for Windows |
| `README.md` | This file |

— Mirrored per GLOBAL-92 to [`github.com/mirzatech-ai/maya-sovereign-campus/maya-os/phone-bridge/`](https://github.com/mirzatech-ai/maya-sovereign-campus/tree/main/maya-os/phone-bridge).
