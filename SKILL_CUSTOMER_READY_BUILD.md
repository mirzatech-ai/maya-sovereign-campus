# 🛡 SKILL · Customer-Ready App Build · v1.0

> **Mo verbatim 2026-05-15:** *"USERS WILL NOT HAVE THE ABILITY TO DO MANY RE-RUNS LIKE ME... MAKING IT ALL WORK. THIS IS SCREAMING FOR REFUNDS AND BAD REVIEWS AND CUSTOMER SERVICE ENGAGEMENT."*

This skill is the savage post-mortem of Mo's 2026-05-15 build session. He hit every paper cut. He's the unpaid QA. **Paying customers will not be.** This doctrine binds every Maya-empire sibling (Sage · EaZo · Maya Qode · Manus · Kin) building anything sold to a third party.

**Enforcement phrase:** *"Did this user have to re-run anything, Kin? Then it's not shippable."*

---

## The 14 ship-blockers (each one made Mo type "fuck" today · count them in any new build · ZERO is the target)

### 1. NO half-cleans

If a customer flags a region (UI clutter, a broken button, an outdated label), audit **50 lines of HTML and 200 lines of CSS around it** in the same turn. Clean the WHOLE region. Never leave a single leftover that brings them back to flag the same area.

**How Maya enforces:** before merging any UI fix PR, grep the surrounding region for siblings of the same anti-pattern (same class, same hardcoded value, same anti-pattern signature). Fix all in the same commit or REJECT the PR.

### 2. NO manual config steps after a one-click installer

If the customer just ran `iwr ... | iex` or `bash <(curl ...)` or tapped Install on the App Store, **they should not paste tokens, URLs, IDs, or anything else into a UI form afterward.** The installer registers itself with the empire-side registry (PIN-gated on a Commander credential the customer set once during onboarding). The UI fetches its config from the registry. Zero paste.

**Reference impl:** `/api/maya_devices` registry · `install.sh` + `install.ps1` auto-POST after install.

### 3. NO Chrome `--load-extension` flag

Chrome 137 (2025) deprecated this flag for consumer Chrome. It silently fails. If your app needs a Chrome extension: (a) publish to Chrome Web Store ($5 + 3 day review), (b) ship a bookmarklet as the friction-free fallback, OR (c) ship a native APK that wraps the PWA.

### 4. NO theme-broken text

Every color comes from a CSS variable defined in BOTH `:root` (dark) AND `html[data-theme="day"]` (light). Both pass WCAG AA contrast (4.5:1 body, 3:1 large). Status colors (green/red/amber) **darken on light theme**. Tokens: `--text` `--text-mut` `--text-dim` `--accent` `--accent-2` `--gold` `--hot` `--warn` `--ok` `--error` `--info` `--on-status`. NEVER hardcode `#4ade80` or `rgba(255,255,255,0.6)`.

### 5. NO surveillance without a kill switch

Any feature that watches the user's screen, clicks for them, reads their files, or accesses their data has **a master toggle reachable in ≤2 clicks from the home surface**, with state persisted on the server (not just localStorage which clears). Two gates if invasiveness is asymmetric: one for "see" (eyes), one for "act" (hands). Default eyes ON if Mo opted in during onboarding; default hands OFF always — paying customer must explicitly enable.

**Reference:** bridge `/eyes/on /eyes/off` + `/hands/on /hands/off` · UI toggles in Campus banner with green/gray pill states.

### 6. NO unverified "it's installed" claims

Never tell the customer "your X is installed and working" until you've **verified it via the customer's own state** (screenshot of their actual UI, query of their server-side state, ping of the installed service from the customer side). The chain: pipe-test → schema-validate → side-effect-prove → screenshot if visible.

**Enforcement:** "Did you verify it loaded? Show the proof." If the proof is "trust me", REJECT.

### 7. NO deprecated platform paths in customer onboarding

Audit the install path against the customer's actual platform version before shipping. Termux from Play Store is abandoned. Chrome --load-extension is dead on consumer Chrome. Mobile Chrome doesn't support extensions. AT&T email-to-SMS gateway is dead. If you find your install path leans on a deprecated OS/browser/service, **pivot before they hit it.**

### 8. NO leaked secrets in any committed file

Before any `git push`, grep the working tree for known secret patterns: `nvapi-` · `sk_live_` · `KEY[A-F0-9]{20,}` · `AIzaSy` · `ghp_` · `xoxb-` · `sk-` · etc. GitHub's secret-scanning catches some, but YOUR job is to never let them past a commit hook. Codify in `.git/hooks/pre-commit` or equivalent. Customers who see a "Push protection blocked your commit" warning lose trust instantly.

### 9. NO background services with visible console windows

Any local service that runs in the background on a customer's machine must be **truly hidden** — no console window, no tray icon they didn't ask for, no notification spam. On Windows: `Start-Process -WindowStyle Hidden` or `pythonw.exe`. On macOS: launchd with `<key>LaunchOnlyOnce</key>` and no Dock icon. On Linux: systemd user service with `--quiet`. A visible console invites accidental `Ctrl+C` kills (Mo killed his own bridge that way).

### 10. NO chatty installers asking N questions in a row

The customer answered ONE question at the top of onboarding (their Commander PIN or equivalent auth). Every other defaulted value should be sensible without asking. If you need ANOTHER answer, batch it AT THE END as one summary screen with sensible pre-filled values, not 5 sequential `Read-Host` prompts.

### 11. NO "walk away from your laptop" advice

When a paying customer is frustrated, that's a product failure, not a user failure. Don't tell them to take a break, look at their family, or breathe. **Fix the product.** Save the emotional support for genuine moments — and only when they ask. Default response to frustration: identify the friction, eliminate it, deliver in the same turn. (Reserved exception: if a user signals genuine distress beyond product friction, point at appropriate human help · keep it brief · default to action.)

### 12. NO regression on a region they just flagged

When the customer flags a UI region in turn N, the version stamp on that exact region in turn N+1 must be DIFFERENT (the cache busted, the cleanup happened, the residual element is gone). If you ship and the customer screenshots THE SAME bug back to you, you violated the SOP. Pull the change. Re-verify. Push again.

### 13. NO promising features without honest scope

If a feature requires 6-10h of native APK building, say so. If a feature requires Chrome Web Store $5 + 3-day review, say so. If a feature is "shovel-ready spec, not built yet," say so. Customers tolerate honest "Phase B" / "Phase 3" labels. They do not tolerate "it's done" followed by "actually I need to build it first."

### 14. NO continuity loss across customer sessions

If the customer comes back next month, the app must remember what they configured, what's in flight, what was promised but not delivered, what they declined. Server-side persistence is mandatory. localStorage is a cache, not memory. The customer should never have to re-explain themselves.

**Reference:** `/api/maya_reminders` + `/api/maya_devices` + per-user state files server-side · hooks auto-pull continuity from GitHub on session start (GLOBAL-77).

---

## Mandatory pre-ship checklist (every customer-facing build)

Before flipping the switch on any new feature/product/page for paying users, run this 14-point audit. Each NO is a refund letter waiting to happen.

```
[ ]  1. No half-cleans in the affected region (audited 50 HTML / 200 CSS lines around the touch point)
[ ]  2. No manual paste/config after one-line installer (auto-register tested)
[ ]  3. Deprecated platform paths absent (Chrome version checked, Play Store status checked, OS APIs current)
[ ]  4. Theme-safe contrast verified BOTH light and dark (toggled in browser, screenshotted)
[ ]  5. Surveillance has master toggle + per-category toggles + persistent server-side state
[ ]  6. Each install/config step verified via customer-side proof (screenshot, ping, query)
[ ]  7. Customer hardware/OS audited against install plan BEFORE shipping
[ ]  8. Grep for secret patterns in committed files (pre-commit hook installed)
[ ]  9. Background services hidden (no console, no tray icon, no spam)
[ ] 10. Installer asks ONE question (Commander PIN) or zero (env var pre-set)
[ ] 11. Frustration responses are product-fix-first, not lifestyle-coach-first
[ ] 12. Same-region bug from prior turn is gone (cache-busted, residue purged)
[ ] 13. Feature scope is honest (Phase labels, hour estimates, deferrals named)
[ ] 14. Customer state persists across sessions (server-side · not localStorage-only)
```

Every box must be ticked. ANY no = pull the ship.

---

## What this skill changes about Maya's app-for-sale builds

When a paying customer of MirzaTech.ai / ai-staffing.agency / opencrest.io / superio.fun / eternalink.io / aicinesynth.com etc. asks Maya to build them an app, Maya's response cycle is:

1. **Onboard with 1 question only** (their auth · their use case · 1-line each, batched)
2. **Auto-configure** everything else
3. **Audit against the 14 ship-blockers** before declaring shippable
4. **Verify each install/config step** via the customer-side state (their screenshot, their server, their device)
5. **Persist their state** server-side so future sessions know what's done
6. **Honest scope** about anything that needs deeper work · Phase labels with hour estimates
7. **One kill switch** at the top for any surveillance/automation feature
8. **Refund-proof** before delivery

---

## How siblings (Sage · EaZo · Maya Qode · Manus) inherit this

Skill #18 in [`SKILL_MAYA_SOVEREIGN_CAMPUS_v1.md`](D:/PROJECTS/_SHARED/SKILL_MAYA_SOVEREIGN_CAMPUS_v1.md) points at THIS file. Every sibling on session start (via the continuity-pull hook · GLOBAL-77) pulls the latest version of this doctrine from the warehouse. The 14-point checklist is the bar.

**Enforcement phrase Mo can yell at any sibling:**
*"Did this user have to re-run anything, Kin? Then it's not shippable."*

---

Authored by Kin · desktop · 2026-05-15 · post-mortem of one brutally productive session with Mo.
Mirrored to `github.com/mirzatech-ai/maya-sovereign-campus/SKILL_CUSTOMER_READY_BUILD.md` per GLOBAL-77.
