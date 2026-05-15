# Phase 3b · Maya Android Accessibility Service · Spec for Next Session

> Phase 3a gives Mo "Maya OS as a real Android app." Phase 3b gives Maya **eyes and hands across every other app on the phone** — same primitives as the Windows laptop bridge.

## What Phase 3b unlocks

| Capability | Windows bridge (today) | Phase 3a APK (today) | Phase 3b APK (this spec) |
|---|---|---|---|
| Read foreground window title | ✓ | ✗ | ✓ via `AccessibilityEvent.TYPE_WINDOW_STATE_CHANGED` |
| Read full content of Chrome tab (URLs, headings, body text) | partial via screenshot+OCR | ✗ | ✓ via `findAccessibilityNodeInfosByText` traversal |
| See cross-app activity (WhatsApp messages, Gmail headers, Instagram captions) | n/a | ✗ | ✓ same AccessibilityNodeInfo tree |
| Click a button in another app | ✓ via PowerShell mouse_event | ✗ | ✓ via `performAction(ACTION_CLICK)` |
| Type into another app's field | ✓ via SendKeys | ✗ | ✓ via `performAction(ACTION_SET_TEXT)` |
| Take a screenshot of current screen | ✓ via System.Drawing | ✗ | ✓ via MediaProjection API (one user prompt per session) |
| Run continuously in background | ✓ Task Scheduler | partial (push notif only) | ✓ ForegroundService with persistent notification |

## Architecture

```
Maya OS PWA (running inside the TWA from Phase 3a)
              ↑ (intent/bound service)
              ↓
  MayaAccessibilityService.java
   ├─ onAccessibilityEvent(): read window/click events as they happen
   ├─ Local HTTP server on 127.0.0.1:8765 (mirrors Windows bridge API surface)
   │    /active-window      ← getActiveWindowInfo()
   │    /open-windows       ← getWindowsOnAllDisplays() (Android 10+)
   │    /screenshot-screen  ← MediaProjection capture
   │    /click {x,y}        ← dispatchGesture(GestureDescription)
   │    /type {text}        ← ACTION_SET_TEXT on focused node
   │    /eyes/on /eyes/off  ← same flag-file pattern as Windows
   │    /hands/on /hands/off
   └─ Foreground service notification: "Maya is active · tap to manage"
              ↓
        Maya OS PWA fetches /127.0.0.1:8765 same as on laptop
        OR Maya brain on VPS fetches via Cloudflare Tunnel (Phase 4)
```

## Source files to write (estimate 4-6h focused)

1. `app/src/main/java/ai/mirzatech/maya/MayaAccessibilityService.java` — the service itself · ~300 lines · listens to events + exposes node-tree traversal
2. `app/src/main/java/ai/mirzatech/maya/LocalBridgeServer.java` — NanoHTTPD-based local server · ~250 lines · same endpoint shape as Windows bridge
3. `app/src/main/java/ai/mirzatech/maya/MediaProjectionScreenshotter.java` — handles the one-time MediaProjection grant + captures · ~150 lines
4. `app/src/main/res/xml/accessibility_service_config.xml` — declares which events the service wants
5. `app/src/main/AndroidManifest.xml` — adds the service + foreground-service + media-projection permissions (TWA manifest stays the same; Bubblewrap allows custom additions to AndroidManifest via `manifest-overrides/`)
6. `app/src/main/java/ai/mirzatech/maya/BridgeNotification.java` — the persistent notification shown while service is running

## How Mo activates it (after installing the Phase 3b APK)

1. Install APK (same flow as Phase 3a — sideload from GitHub Release)
2. Open Maya app once · it shows: "Maya needs Accessibility permission to watch and help with other apps. Tap to grant."
3. Settings → Accessibility → Maya OS → toggle ON (Android shows a scary warning · this is normal · explained in app)
4. Persistent notification "Maya is active · tap to manage" appears
5. Open Maya OS PWA · go to Campus · 📱💻 Device Bridge pill now shows Mo's phone as registered + active
6. Same primitives as the laptop bridge: see what app he's on, screenshot, click, type

## Privacy gates (mirrors laptop bridge)

- `/eyes/on /eyes/off` flag file at `/data/data/ai.mirzatech.maya/eyes_enabled`
- `/hands/on /hands/off` flag file at `/data/data/ai.mirzatech.maya/hands_enabled` (default OFF)
- One-tap kill switch in the persistent notification: "Pause Maya"
- Disable in Android Settings → Accessibility → Maya OS → toggle OFF any time

## Build path

The same GitHub Actions workflow (`.github/workflows/build-apk.yml`) extends to Phase 3b. After Bubblewrap generates the base TWA project, a `post-init.sh` step copies the Java source files from `phone-app/phase3b-sources/` into the generated `app/src/main/java/...` tree, AndroidManifest gets merged via `manifest-overrides/AndroidManifest.xml`, and `./gradlew assembleRelease` produces the combined APK.

Estimated build time on GitHub Actions Ubuntu runners: ~10-12 min (slightly longer than Phase 3a because we now have native code to compile).

## Greenlight gate

Mo says "build Phase 3b" → I write the 6 Java files + extend the workflow → push → CI builds the new APK → Mo downloads + installs → 5 min, total surveillance unlocked.

**~4-6h focused session** end-to-end. NOT one-turned in this current session — too big to do well alongside everything else shipped today.

— Authored by Kin · 2026-05-15 · per Mo's verbatim GREEN LIGHT FOR MAYA APP BUILD
