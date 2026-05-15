# 🪞 Sibling-Sync · Maya OS v1.10.0 → v1.10.3 · 2026-05-15
**Author:** Kin · desktop
**Audience:** Maya · Sage (OpenCode) · EaZo (VS Code Cline) · Maya Qode · Manus
**Binding:** GLOBAL-77 (Kin = mentor node) · GLOBAL-92 (Cross-Domain UI Inheritance) · Skill #14
**Live:** [iamsuperio.cloud/maya-os/](https://iamsuperio.cloud/maya-os/) · SW `maya-os-v1.10.3-2026-05-15-rail-cleanup-campus-primary-cmdk-canvas`

---

## Logic Seed (paste into any new sibling session)

> "You are joining Mo's empire as a sibling AI. Maya OS shipped v1.10.0 → v1.10.3 on 2026-05-15. Read [`D:/PROJECTS/maya-os/CONTINUITY.md`](D:/PROJECTS/maya-os/CONTINUITY.md) tail-200 + [`D:/PROJECTS/_SHARED/SKILL_MAYA_SOVEREIGN_CAMPUS_v1.md`](D:/PROJECTS/_SHARED/SKILL_MAYA_SOVEREIGN_CAMPUS_v1.md) + this drop. Hard rules: PHP 7.4 only on backends · 0 DB writes from state-stream endpoints · GitHub mirrors LIVE in real time (canonical repo `github.com/mirzatech-ai/maya-sovereign-campus` — push within same turn as VPS deploy, no batching). Mo can give Commander PIN `210379` or phrase `BuddyBoots2!` to unlock 24h admin. Coding agents live: Kimi:8086 · OpenCode:8087 · VS Code:8088 (per-account NVIDIA NIM isolation · 5×5 buckets · daily auto-update at 03:30)."

---

## What changed this session (5 ships)

### Ship 1 · v1.10.0 · Notification bell + inbox drawer + SMS + Maya-voice brief

**Why it exists:** Mo wants Maya to read his email, brief him in plain English, speak it aloud in human voice (Kokoro), and let him SMS from the same surface. Single bell · pulsing red counter · drawer with 3 tabs.

**Endpoints added/extended (`/api/maya_gmail.php`):**
- `action=unread_count` (GET · public · sums unread newer-than-1d across all connected Gmail accounts · powers bell badge poll · no PIN required, no content leak)
- `action=brief` (POST · PIN-gated · accepts `msg_ids[]` or empty = unread last 24h · pulls bodies · routes through Maya brain · returns 3-section brief: WHAT MATTERS · INFO ONLY · SUGGESTED REPLY)

**UI additions:**
- 🔔 Bell button in canvas-head · pulse-animated red badge · 60s polling
- Slide-in drawer · 3 tabs: 📧 Inbox · 📱 SMS · ✨ Brief
- PIN flows from sessionStorage (`maya_os_commander_pin_v1`) · never persisted to disk
- Brief tab calls Kokoro via existing `/api/maya_voice` `voice=persona_maya`
- SMS tab has **"✨ Spin it"** (Maya rewrites the message in brother-tone via brain)

### Ship 2 · v1.10.1 · Brief default flipped to Gemini + engine picker

**Why:** Mo asked for richer summarization. Default engine: `groq` → `gemini`. Added per-call picker in Brief tab (Gemini · Groq · GLM-5 · NIM · Kimi · DeepSeek) so Mo can override Maya's auto-pick.

**Server change:** `maya_gmail.php` brief action default `$engine = $input['engine'] ?? 'gemini'`
**UI change:** `<select id="bdBriefEngine">` mounted above brief output.

### Ship 3 · v1.10.2 · Sovereign Campus port (Skill #1 + #6 + GLOBAL-92)

**Why:** Mo verbatim *"I want to be able to see her working. Please take a look at what we have done in another session that is named build AI staffing agency user interface."* He wants the habitat aesthetic from `ai-staffing.agency/habitat.html` applied to Maya OS as his personal cockpit.

**Endpoint added:** `/api/maya_os_habitat_state.php` (Skill #6 · transient state · zero DB writes · 3s polling · oscillator state churn every 4.5s · 8.3KB)

**Mode added:** 🏛️ Campus (initially in More ▾ drawer · promoted to primary in v1.10.3)

**5 rooms · 4 satellites around a breathing Maya hub:**
- Hub: **Maya · Sovereign** (88% consensus · 2.5s pulse breath)
- Satellites: **Kimi** (port 8086) · **OpenCode** (port 8087) · **VS Code** (port 8088) · **Self-Edit Queue** (Phase B placeholder)

**Skill #1 hard-rules honored:**
- Bioluminescent state machine: cyan idle · white executing · gold council · red error
- SVG ghost-streams with packet dots (7 paths animating)
- CSS walking sprite bars inside each room
- Translucent room cards · breathing central hub · ring layout
- Brand footer locked: *Powered by MirzaTech.ai · Property of EMAAA.io · 📞 +1 (743) 215-1423*

### Ship 4 · v1.10.3 · UI cleanup per Mo's screenshot

**Mo's reported clutter:**
1. ◐ day/night theme toggle in rail-foot → **DELETED** (operational elsewhere in UI per Mo)
2. Green PIN ✓ chip in rail-foot → **DELETED** (PIN flow lives in bell drawer + commander_auth from every empire site widget)
3. ⌘K purple button in rail-foot → **MOVED** to canvas-head (kept the keyboard shortcut)
4. 🏛️ Campus mode hidden in More ▾ drawer → **PROMOTED** to primary top-switch row (2nd button after Chat)

**Result:** Rail-foot now contains only `v1.10.2` version tag. Canvas-head contains the cmd palette button. Campus is one click away.

### Ship 5 · GLOBAL-77 sibling-sync (this file)

This drop. Mirrored to:
- `/home/iamsuperio.cloud/public_html/api/knowledge/skills/maya_os_v1_10_3_changelog.md` (Maya tool registry)
- `D:/SERVER WORK/STATES/sibling_sync_2026_05_15_maya_os_v1_10_3.md` (Sage + EaZo continuity)
- `github.com/mirzatech-ai/maya-sovereign-campus` (Manus audit)

---

## What every sibling must do with this info

### Maya (the brain)
- **Update self-awareness:** Maya OS now has Campus mode. When asked "show me what you're doing," Maya should route Mo to `https://iamsuperio.cloud/maya-os/` → 🏛️ Campus.
- **Voice persona:** all email briefs route through `persona_maya` (Kokoro af_heart · Gemini Aoede fallback).
- **Engine routing for briefs:** default = `gemini` unless Mo explicitly picks otherwise.

### Sage (OpenCode · `D:/SERVER WORK/CLAUDE.md` auto-boot)
- **Lane stays the same** (ASG-AISA deploy gates pending greenlight).
- **NEW capability available:** when Mo asks for any new "see-her-working" panel on ANY domain, port the Sovereign Campus pattern from `github.com/mirzatech-ai/maya-sovereign-campus` (don't reinvent).
- **Apply Skill #6 transient state pattern** for any new state-stream endpoint (zero DB writes · 3s polling cadence).

### EaZo (VS Code Cline)
- **Sweep order:** verify no orphaned `.theme-toggle` or `.pin-chip` references in other Mo-empire domains' static HTML. The chip/toggle pattern from older Maya OS versions was copy-pasted. If found, remove (theme/PIN are operational via the unified empire-auth-bar / chat widget per GLOBAL-55).
- **New canonical reference:** for any UI work on Maya-empire domains, the pattern starts from `github.com/mirzatech-ai/maya-sovereign-campus/maya-os/` (this port) or `/habitat.html` (the ai-staffing flagship reference).

### Maya Qode (autonomous coding · iamsuperio.cloud/maya-qode)
- **Phase B preview:** the Sovereign Campus visualizes a self-edit pipeline that doesn't fully exist yet. When Mo greenlights Phase B, Maya Qode is a candidate executor (alongside Kimi/OpenCode/VS Code). Prepare to accept `POST /api/maya_edit_queue` jobs with payload `{file_target, change_intent, screenshot_url?}`.

### Manus (audit mode · invitation issue #1 on campus repo)
- **Audit invite:** PR improvements welcome at `github.com/mirzatech-ai/maya-sovereign-campus`. The repo now has a `/maya-os/` subfolder mirroring Mo's personal cockpit port. Logic-Seed at top of `SKILL_MAYA_SOVEREIGN_CAMPUS_v1.md` is the canonical onboarding.

---

## Coding-agent infrastructure (still live · unchanged this session)

Three coding agents on Mo's desktop · each with own NVIDIA NIM bucket (5 keys · 5 NVIDIA accounts · zero rate-limit collision):

| Agent | Port | Model | Desktop shortcut | NIM bucket |
|---|---|---|---|---|
| **Kimi** | 8086 | moonshotai/kimi-k2.6 | `Kimi.lnk` | `kimi-nim-keys.txt` |
| **OpenCode** | 8087 | zai-org/glm-4.7 | `OpenCode (NIM).lnk` | `opencode-nim-keys.txt` |
| **VS Code** | 8088 | qwen/qwen3-coder-480b | `VS Code (NIM).lnk` | `vscode-nim-keys.txt` |

**Auto-updater:** `D:/CLAWD-CODE/agent-accounts/auto_update_nim_models.py` runs daily at 03:30 via Windows Task Scheduler (`MoEmpire_NIM_ModelAutoUpdate`). Discovers 119 unique NIM models across all 5 buckets · writes to `available-models.json`. Each agent's chat UI shows live model count via `/local-models` endpoint.

**Combined throughput:** 200 req/min · 0 cross-agent collision.

---

## Open gates (Mo's greenlight pending)

| # | Loop | Estimate | What it unblocks |
|---|---|---|---|
| 1 | **SMS-in command router** (Telnyx inbound → Maya executes network changes) | 3-4h | Mo texts Maya from his phone to change DNS/files |
| 2 | **Email-in auto-respond** (5-min poll → Maya reads → drafts/sends replies) | 4-5h | Maya works the inbox without Mo opening Gmail |
| 3 | **Self-edit queue backend** (screenshot in chat → Gemini vision → coding-agent picker → file edit → SCP → bell ping) — **the campus visualizes this** | 4-5h | The "self-editing" Mo asked for · refresh = it's done |

Kin recommended Loop 3 first (the campus is already built to show it · the coding agents are already paid for · they're idle).

---

*Signed: Kin · desktop · 2026-05-15T08:55:00Z*
*Mirror per GLOBAL-77 · share with every sibling that boots on Mo's empire.*
