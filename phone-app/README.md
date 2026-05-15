# 📱 Maya OS · Android App (Phase 3a · TWA)

> Native Android app wrapping the Maya OS PWA. Built automatically by GitHub Actions · no toolchain required on Mo's laptop. Install the resulting APK on your Samsung Fold 5 like any sideloaded app.

## How Mo gets the APK on his phone

**One-time setup (~3 min):**

1. Go to **https://github.com/mirzatech-ai/maya-sovereign-campus/actions**
2. Click **"Build Maya OS Android APK"** in the left sidebar
3. Click **"Run workflow"** (top right) → leave defaults → click green Run workflow button
4. Wait ~5-8 min for the build (you'll see a yellow spinner → green checkmark)
5. Click into the completed run → scroll to "Artifacts" → download `maya-os-android-N` (zip with the APK inside)
6. Transfer to phone (USB cable · Google Drive · email yourself · Quick Share · whatever)

**On the phone (~30 sec):**

1. Settings → Apps → Special access → Install unknown apps → Chrome (or whatever app you're using to open the file) → Allow
2. Open the downloaded `.apk` from your Downloads notification → Install
3. **"Maya"** app icon appears in your launcher · tap to open

That's it. You're running Maya OS as a real Android app, off the Chrome PWA wrapping.

## What this Phase 3a gives you vs the browser PWA

| Capability | Browser PWA | Phase 3a APK | Phase 3b APK (queued) |
|---|---|---|---|
| Home screen icon | ✓ but adds via "Add to home" | ✓ native install | ✓ native install |
| No browser chrome (full screen) | ✓ standalone display | ✓ true fullscreen | ✓ |
| Share-to-Maya from any app | ✓ (manifest share_target) | ✓ (better Android integration) | ✓ |
| Push notifications | ✓ but Chrome must be running | ✓ system-level | ✓ |
| Survives Chrome tab close | ✗ | ✓ separate process | ✓ |
| Camera/mic permissions persist | partial | ✓ app-scope persistence | ✓ |
| Maya reads cross-app screens (Chrome tabs, WhatsApp, Gmail content) | ✗ | ✗ | ✓ Accessibility Service |
| Maya taps buttons across apps | ✗ | ✗ | ✓ Accessibility Service |

Phase 3a is the no-Accessibility version. The cross-app surveillance Mo wants is Phase 3b — separate ~4-6h custom Java/Kotlin build, scaffolded in `phase-3b-accessibility-spec.md`.

## How the build works (so Mo trusts what's running on his phone)

1. **Bubblewrap CLI** (Google's official PWA-to-APK tool) reads `iamsuperio.cloud/maya-os/manifest.webmanifest` and generates an Android project that wraps the PWA in a Trusted Web Activity (TWA).
2. **Gradle** builds the APK from that project.
3. **Keystore** signs the APK so Android will install it. First run generates a keystore; you base64-encode it and add to `MAYA_KEYSTORE_BASE64` secret so future builds use the same signing key (= updates over the old install instead of side-by-side installs).
4. **GitHub Actions** runs all of the above on Ubuntu runners (Google-trusted infrastructure, no Mo-laptop dependency).

The APK is signed by Mo's own keystore (kept in GitHub Secrets · never exposed in commits). The wrapped content is Maya OS itself · what's running on iamsuperio.cloud · which is Mo's own server.

## Keystore management (one-time after the first build)

After the FIRST workflow run, an artifact named `keystore-base64-FIRST-RUN-ONLY-add-to-secrets` will be available for 24h. Download it:

```bash
unzip keystore-base64-FIRST-RUN-ONLY-add-to-secrets.zip
cat keystore.base64.txt    # copy this entire value
```

In the GitHub repo: **Settings → Secrets and variables → Actions → New repository secret**:
- Name: `MAYA_KEYSTORE_BASE64`
- Secret: paste the base64 value
- (Optional) `MAYA_KEYSTORE_PASSWORD`: a custom keystore password (defaults to `mayaBuild123!`)

From then on, every build uses the same keystore = same signing identity = updates install over the previous version (not as a new app).

## What ships in the APK

- All of Maya OS (Campus, bell drawer, chat, Device Bridge controls, voice toggles)
- The Web Share Target API so "Maya" appears in Android's share sheet from any app
- App shortcut to Campus mode (long-press the Maya icon)
- System theme colors matching the dark Maya OS palette

## Files in this scaffold

- `twa-manifest.json` · Bubblewrap configuration · package id, icons, theme colors
- `.github/workflows/build-apk.yml` · CI workflow that builds and releases the APK
- `phase-3b-accessibility-spec.md` · spec for the cross-app surveillance addition (next session)

## Honest scope · what Phase 3a does NOT yet do

- ❌ Read what's in Chrome tabs (needs Accessibility Service · Phase 3b)
- ❌ Auto-capture screenshots when Maya wants (Android sandbox · needs MediaProjection permission flow · Phase 3b)
- ❌ Tap UI elements in OTHER apps (needs Accessibility Service · Phase 3b)
- ❌ Run a local HTTP bridge like the Windows laptop has (Android background-service restrictions · would need a foreground service notification · Phase 3b)

Phase 3a is the "Maya as a real Android app icon" win. Phase 3b is the "Maya watches and clicks across your whole phone" win. Stacked.

— Authored by Kin · 2026-05-15 · per Mo's "GREEN LIGHT FOR MAYA APP BUILD"
