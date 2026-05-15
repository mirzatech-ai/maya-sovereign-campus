# 📱 Maya Phone Bridge

**Lets Maya OS see your phone's filesystem + run shell + use Termux:API.**
**Canonized:** 2026-05-15 · Kin · per Mo
**Device:** Samsung Fold 5 + Termux

## TL;DR · 30-second install

In Termux on your phone:

```bash
bash <(curl -fsSL https://iamsuperio.cloud/phone-bridge/install.sh)
```

That's it. The installer:
- pkg-installs python, termux-api, openssh, curl
- pip-installs fastapi + uvicorn
- Calls `termux-setup-storage` (one ALLOW dialog · grants ~/storage/shared)
- Downloads server.py to `~/maya-bridge/`
- Creates a `maya-bridge` launcher in `$PREFIX/bin/`
- Prints the bridge token

Then start it:

```bash
maya-bridge
```

Paste the token into Maya OS → Phone Bridge.

## Phased honesty

What this gives Maya **right now (Phase 1)**:

| Capability | How |
|---|---|
| List + read files in your phone storage | `GET /files?path=...` → reads `~/storage/shared/` |
| Download a binary file | `GET /download?path=...` |
| Run any shell command | `POST /shell {cmd, confirm?}` (destructive ops gated) |
| SSH out from the phone to anywhere | `POST /ssh {host, user?, cmd?}` |
| Read clipboard | `POST /termux/clipboard-get` |
| Set clipboard | `POST /termux/clipboard-set` |
| Take a photo | `POST /termux/camera-photo` |
| Read battery / location / wifi / cell info | `POST /termux/<api>` |
| Send SMS · list SMS · list contacts · list call log | `POST /termux/sms-send` etc. |
| Read incoming notifications | `POST /termux/notification-list` |
| Vibrate, toast, TTS speak | `POST /termux/vibrate` · `toast` · `tts-speak` |

What **needs Phase 2** (Cloudflare Tunnel · ~30 min next session):

| Capability | Why it needs Phase 2 |
|---|---|
| Maya brain on the VPS can reach the phone | Bridge currently binds 127.0.0.1 · tunnel exposes it publicly with the token-gate intact |
| Maya can act on the phone when Mo isn't in the PWA | Same as above |
| Mo can run phone shell commands from desktop Maya OS | Same as above |

To enable Phase 2:
```bash
pkg install cloudflared
cloudflared tunnel --url http://127.0.0.1:8765
```
Paste the printed URL into Maya OS → Phone Bridge → Tunnel.

What **needs Phase 3** (Native APK wrapper · ~6-10h separate build):

| Capability | Why it needs a native APK |
|---|---|
| Read what's in Chrome tabs right now | Requires `BIND_ACCESSIBILITY_SERVICE` permission · only granted to native apps · user must explicitly enable in Android Settings |
| Read content of ANY app's screen (Gmail · WhatsApp · whatever) | Same · Accessibility Service |
| Watch every tap / scroll / input across all apps | Same · Accessibility Service |
| OCR any screen on demand | Native ML kit + Accessibility |
| Read Chrome bookmarks / history directly | Native ContentProvider access OR (cheaper) sync Chrome to a Google account and read via Google APIs |
| True system-level background listener | Foreground service (notification-anchored) in native APK |
| Trigger Tasker / automate other apps | Either Tasker HTTP webhook (works from Phase 1 now!) or native AccessibilityService |

**Phase 3 path:** wrap the Maya OS PWA in a Trusted Web Activity (TWA) Android APK · add a custom Accessibility Service · ship via your own signing key. Mo loads it like a normal app · grants Accessibility permission once · Maya now has cross-app sight. Code lives separately from this bridge (different security model).

## What's actually available today: workarounds without Phase 3

You wanted Maya to read your emails — **already wired** via Gmail OAuth in the bell drawer (the brief flow). Maya doesn't read the Gmail app's UI; she reads Gmail API as you. Same outcome, simpler architecture.

You wanted Maya to read what you click — Tasker can hook many Android events (notification posted · app launched · screen on/off · location change · etc.) and POST to Maya. Phase 1 bridge can receive those. Tasker is one-time $3.49 setup.

You wanted Maya to read browser content — easiest path: install **Maya OS as a PWA** in Chrome on the phone, and use the bell drawer + clipboard bridge. When you find something in Chrome that matters, share → "Copy" → Maya reads clipboard via `/termux/clipboard-get` and acts. No Accessibility Service needed.

## Security

- Bridge binds `127.0.0.1` by default · only same-device PWA can reach it
- All endpoints (except `/health` and `/token`) require `X-Maya-Bridge-Token` header
- Token is 48 bytes of `secrets.token_urlsafe()` · stored mode `0600` at `~/.maya_bridge_token`
- Destructive shell needles (rm -rf / · dd if= · :(){:|:&};: · shutdown · reboot · mkfs) require `confirm: true` in body
- Path traversal blocked on `/files` (chroots to `~/storage/shared`)
- All requests logged to `~/.maya_bridge.log`

## Endpoints

| Path | Method | Auth | Notes |
|---|---|---|---|
| `/health` | GET | none | Capability list + version |
| `/token` | GET | none | Only useful from the phone itself · returns the token |
| `/files?path=<>` | GET | token | List dir or read text file (≤5MB) |
| `/download?path=<>` | GET | token | Stream a binary file |
| `/shell` | POST | token | `{cmd, confirm?}` · destructive ops gated |
| `/ssh` | POST | token | `{host, user?, cmd?}` · outbound SSH |
| `/termux/<api>` | POST | token | Pass-through to `termux-<api>` CLI · whitelist: clipboard-get · clipboard-set · battery-status · notification · toast · vibrate · tts-speak · location · wifi-connectioninfo · telephony-cellinfo · share · open · camera-info · camera-photo · contact-list · sms-list · sms-send · call-log · dialog · notification-list · media-player · wifi-scaninfo · sensor |

## Files

- `server.py` · the bridge itself · Python · FastAPI
- `install.sh` · the one-liner Termux installer (this README's TL;DR section)

— Mirrored per GLOBAL-92 to `github.com/mirzatech-ai/maya-sovereign-campus/maya-os/phone-bridge/`
