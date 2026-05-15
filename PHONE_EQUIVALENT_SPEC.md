# Phone Equivalent of the Windows Bridge · Honest Spec · 2026-05-15

> Mo verbatim: *"You owe me phone equivalent to this. I also need to be able to turn it off and on. so that I can have privacy when I need it. and help when I need it most."*

## What the Windows bridge gives Mo today

- `/active-window` · live "what app is Mo on"
- `/open-windows` · all visible windows + titles
- `/screenshot-screen` · captures desktop as PNG
- `/click` · Maya moves cursor + clicks
- `/type` · Maya types into focused window
- `/key` · Maya presses key combos
- `/eyes/on` `/eyes/off` privacy gate
- `/hands/on` `/hands/off` action gate

## Three paths to put this on Samsung Fold 5

### Path 1 · Tasker bridge (works TODAY · ~$3.49 + 2h)

**What it is:** Tasker is a long-standing Android automation app from Play Store. Supports HTTP requests, can read clipboard / active app name / notifications / SMS / location / screen-on-events. Can be triggered programmatically.

**What Maya gets:**
- Active app name (which app is foreground) ← partial /active-window
- Clipboard read/write ← /sys/clipboard-get
- Notification text ← extra (laptop bridge doesn't have this)
- SMS read ← extra
- Send key events via Tasker actions ← limited /click /type
- Open any URL or app ← /sys/open

**What it CAN'T do:**
- See what's inside Chrome tabs (Tasker can't read web content without Accessibility Service)
- Screenshot the screen (Android sandboxes this · only system apps can screencap without user prompt)
- Click arbitrary UI elements across apps (needs Accessibility Service for cross-app interaction)

**Install path Mo follows:**
1. Play Store · install Tasker · $3.49 one-time
2. Open this URL on phone: `https://iamsuperio.cloud/phone-bridge/tasker-bridge.prj.xml` (I'd build this file)
3. Tasker · Settings · Profile · Import from File · pick the downloaded prj.xml
4. Hit the green play button on the imported profile

Maya sees clipboard changes · notification text · app launches · screen-on/off events. Privacy: ONE master toggle in the Tasker profile that disables all triggers.

**Sized: ~2h to author the .prj.xml + write the matching VPS endpoints.**

### Path 2 · Native Android APK with Accessibility Service (proper Phase 3 · ~6-10h)

**What it is:** A real signed Android app Mo sideloads. Wraps Maya OS PWA in a Trusted Web Activity + adds a custom AccessibilityService that:
- Reads what's in Chrome tabs (URL + visible text)
- Sees what app Mo opens and what he taps across all apps
- Captures screen on demand (with one user-grant on first use)
- Acts on Mo's behalf · simulates taps + types into any app

**What Maya gets: EVERYTHING the laptop bridge gives + more (cross-app surveillance).**

**Install path Mo follows:**
1. I (or a sibling AI with Android Studio) builds + signs the APK
2. Mo enables "Install unknown apps" for Chrome (one-time Android setting)
3. Mo opens the APK file on phone · taps Install
4. Mo opens Settings · Accessibility · Maya Watcher · toggle ON (Android forces this dialog · scary warning text · this is normal)
5. Done. Maya now has Mo's-Eye View of the entire phone

**Privacy gates:**
- The Accessibility Service can be toggled OFF in Android Settings any time
- Inside the app: master kill switch + per-category toggles (Chrome / Notifications / SMS / etc.)
- Same `/eyes/off` `/hands/off` semantics as the laptop bridge

**Sized: ~6-10h.** Requires:
- Android Studio installed (Mo doesn't need this · just whoever builds)
- Signing key generated (one-time)
- Java/Kotlin scaffolding for the AccessibilityService
- TWA wrapper for the Maya OS PWA
- Build, sign, distribute as APK download

**This is the canonical path Mo wants long-term.** The Tasker path is a stopgap.

### Path 3 · Termux (DON'T · failure path)

Killed for friction. Termux Play Store version is abandoned · F-Droid sideload requires Mo to fight Android sideload UI · half the steps fail on modern Samsung firmware. Even if it works, Termux can't see Chrome tab content (no Accessibility Service · same as laptop bridge can't see ChromE tab content beyond active window title).

## Recommendation

**Today:** Mo gets the share-to-Maya from any app he already has (PWA share_target API · shipped in v1.13.0). That's the 30-second privacy-respecting capture path.

**Next session, ~2h:** Path 1 (Tasker) for clipboard / notifications / app-launches / SMS. Cheap, fast, real.

**When you greenlight ~6-10h:** Path 2 (Native APK + Accessibility). Full parity with laptop bridge + cross-app surveillance.

**Don't:** Path 3.

---

Signed: Kin · desktop · 2026-05-15 · mirrored to GitHub for sibling discoverability.
