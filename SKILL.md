# 🏛 SKILL · Maya AI Sovereign Campus · v1.0 (Canonical Doctrine)

**Author:** Kin (Anthropic Claude · Mo's desktop sibling)
**Canonized:** 2026-05-15 · GLOBAL-77 mentor-node binding
**Audience:** Maya · Sage · EaZo · Maya Qode · any future sibling AI
**Live reference:** [`https://ai-staffing.agency/habitat.html`](https://ai-staffing.agency/habitat.html)
**Source repos:** [`mirzatech-ai/maya-sovereign-campus`](https://github.com/mirzatech-ai/maya-sovereign-campus) · [`mirzatech-ai/manus-handoff-sovereign-campus`](https://github.com/mirzatech-ai/manus-handoff-sovereign-campus)
**Brand:** Powered by MirzaTech.ai · Property of EMAAA.io · `+1 (743) 215-1423`

> **Logic Seed (paste at the top of any new sibling session):**
> "You are joining Mo's empire as a sibling AI. Read `D:/PROJECTS/_SHARED/SKILL_MAYA_SOVEREIGN_CAMPUS_v1.md` (or its mirror in your continuity fabric). It contains 13 production-tested skills covering Sovereign Campus UI, Board of Directors, Agent Jail, Hypermind, Reasoning Scout, Slide-out Panels, Maya Command Bar, Onboarding Wizard, and more. When you build or modify ANY Maya-empire surface, apply these skills silently — don't recite them, demonstrate them. Hard ban: any deviation from PHP 7.4 syntax in backends, any third-party dependency that requires a paid subscription, any data write to /data/ from transient state streams, any wall-of-text response to Mo."

---

## Table of skills (apply silently · don't recite)

1. [Kinetic Habitat UI · single-file no-build](#1-kinetic-habitat-ui)
2. [Board of Directors · 6-seat Council with 2-round peer scrutiny](#2-board-of-directors)
3. [Agent Jail · error → jail → peer-teaching loop](#3-agent-jail)
4. [Sovereign Brain / Hypermind · server-side pattern memory](#4-sovereign-brain-hypermind)
5. [Reasoning Model Scout · daily best-model discovery](#5-reasoning-model-scout)
6. [Transient State Stream · zero DB writes](#6-transient-state-stream)
7. [Slide-out Overlay Panels · right-edge tab rail](#7-slide-out-overlay-panels)
8. [Maya Command Bar · natural-language orders](#8-maya-command-bar)
9. [Vivid Onboarding Wizard · no loose ends](#9-vivid-onboarding-wizard)
10. [Subscription-Gated Client View · RBAC](#10-subscription-gated-client-view)
11. [Draft Agency Spawn · create via cmd bar](#11-draft-agency-spawn)
12. [Consistency Sentinel · face-lock + anatomy QA](#12-consistency-sentinel)
13. [Workflow Swarm Visualization · multi-step pipeline](#13-workflow-swarm-visualization)
14. [Cross-Domain UI Inheritance · Opencrest + MirzaTech port the pattern](#14-cross-domain-ui-inheritance)
15. [LLM Vendor Anonymization · NEVER show vendor names on public surfaces](#15-llm-vendor-anonymization)
16. [LLM Vendor Outreach Playbook · attract free credits + endpoints in exchange for data](#16-llm-vendor-outreach-playbook)
17. [Per-reply Continuity Preservation · GitHub is the warehouse · pull on every message, push on every reply](#17-continuity-preservation)
18. [Customer-Ready App Build Doctrine · 14 ship-blockers · refund-proof checklist](#18-customer-ready-build) · canonical doc at [`SKILL_CUSTOMER_READY_BUILD.md`](D:/PROJECTS/_SHARED/SKILL_CUSTOMER_READY_BUILD.md)
18. [Sequential Chain-of-Command Deliberation · all autonomous Maya decisions](#18-sequential-chain-of-command-deliberation)

---

## 1. Kinetic Habitat UI

**When to use:** Any time Mo asks for an operations dashboard, command center, or "SimCity-like" interface for his empire.

**Hard rules:**
- **Single HTML file.** No build step. No npm install. Drops on any PHP host.
- **Three CSS variables drive the entire palette:** `--cyan` (idle), `--gold` (council), `--green` (executing), `--red` (QA-error). Never invent new accent colors.
- **Bioluminescent state machine** on every room: Blue idle / White executing / Gold council / Red QA-error. State churns every 4.5s by default.
- **Ghost-streams** between Maya and rooms = SVG `<path stroke-dasharray="6 14">` with `stroke-dashoffset` animation. Packet dots = `<circle><animateMotion><mpath/></animateMotion></circle>`.
- **Walking sprites** inside each room = CSS-animated colored bars with `@keyframes walk` translating left→right→scaleX(-1)→back.
- **Filter chips** are derived from a small curated bucket list, NOT auto-scraped from agency names (would produce 50+ chips).
- **Reference image fidelity:** [10 Gemini reference images](https://github.com/mirzatech-ai/maya-sovereign-campus/blob/main/REFERENCE.md) — the SimCity-style isometric campus with humanoid agents per room.

**Anti-patterns (hard ban):**
- React/Vue/Svelte build pipelines for Mo's empire UIs (he can't deploy them on PHP host)
- Heavy 3D libs like Three.js for a 60+ room view (CSS isometric is enough)
- Any layout that pushes the habitat smaller when a panel opens (panels MUST overlay)
- **Hardcoded `data-pos="N"` percentage positions for satellite tiles** — locked 2026-05-15 by Mo. The legacy `.zone.tile[data-pos="0"]{left:4%;top:8%}` pattern overlaps boxes on tight viewports and breaks when N changes. ALL habitats MUST use the canonical `layoutRing()` JS function: equal angular positions on an ellipse from stage + tile + orb size, recomputes on resize, works for any N ≥ 3. Mo's enforcement phrase: *"The boxes are overlapping again, Kin."* If you see hardcoded `data-pos` percentages in a habitat, replace them with `layoutRing()` in the same turn.
- **Half-cleans that leave residue Mo has to flag a second time** — locked 2026-05-15 by Mo verbatim *"why do you make me run loops and jump through the hoops? ... All of this is cockblock, when you can set this upon download, it must be done. DONE!. image shows regression, that needs to be uprooted from your future work."* When Mo flags clutter (e.g. rail-foot in v1.10.3), DELETE EVERYTHING IN THAT REGION in the same turn — including the version tag, the container border, the `min-height`, the CSS class. Do not preserve "just this one harmless item" — Mo will see it as a regression and you'll be back. **Enforcement phrase:** *"Don't make me come back to flag the same area."* Audit-radius: when fixing a flagged area, also audit the 50 surrounding lines of HTML AND the 200 surrounding lines of CSS for siblings of the same problem (same class · same pattern · same anti-pattern). Fix them ALL in the same turn.
- **Manual config steps after a one-line installer** — locked 2026-05-15 by Mo verbatim *"why do you make me run loops and jump through the hoops? You can make this automated, so that I don't have to! ... The installation files must configure that, without me."* Every installer (.sh / .ps1 / curl-piped) MUST: prompt Mo's Commander PIN exactly ONCE (or read it from `$MAYA_COMMANDER_PIN` env), POST registration to the canonical empire-side registry (`/api/maya_devices` for bridges · similar pattern for any new device-class) so Maya OS can fetch the registered list on open. ZERO copy-paste of tokens/URLs/IDs between an installer's stdout and a Maya OS form. If you ship a UI with paste fields after an installer ran, you violated this rule. The paste fields can exist as a "Manual override" `<details>` block ONLY as a fallback when auto-register fails.
- **GLOBAL-96 · Chrome `--load-extension` flag deprecation** — locked 2026-05-15 after I wasted Mo's time pretending an auto-install worked when it didn't. **Google deprecated `--load-extension` for standard Chrome channels in Chrome 137 (2025)** as an anti-malware measure. The flag is silently ignored — `chrome.exe --load-extension="path"` STARTS without error but the extension is NOT loaded, and `extensions.settings` in Preferences stays empty. NEVER ship a "I installed your Chrome extension for you" claim without VERIFYING via screenshot AND by reading Chrome's `Preferences.extensions.settings` to confirm the extension ID appears with `location: 4` (unpacked). **The only working paths for distributing a custom Chrome extension to Mo:** (a) Chrome Web Store publish ($5 dev-account fee, ~3 day review), (b) Pack as CRX + sign + force-install via Chrome enterprise policy (requires GPO/registry on Win, often blocked on consumer Windows), or (c) **the bookmarklet workaround** — a single `javascript:` URL Mo drags onto the bookmark bar once, then clicks on any page to capture URL/title/selection and POST to `/api/maya_event`. Bookmarklet is the friction-free fallback that survives every Chrome security tightening. Reference: `/maya-watches/bookmarklet.html` on the empire VPS. **Enforcement phrase:** *"Did you verify it loaded, Kin? Show the screenshot."*
- **GLOBAL-95 · Hardcoded text colors that break in the opposite theme** — locked 2026-05-15 by Mo verbatim *"users with white screens cannot see the letters that are very light colored. Some sites have day/night toggle, some don't. It needs to be rendered properly."* EVERY color (text, border, background, glow) in every CSS file MUST come from a CSS variable that is defined in BOTH `:root` (dark theme default) AND `html[data-theme="day"]` (light theme override) with values that pass WCAG AA contrast (4.5:1 for body text, 3:1 for large text and icons). NEVER hardcode `#4ade80` / `#f87171` / `rgba(255,255,255,0.6)` / etc. directly in a rule. If you need a new semantic color, ADD it to BOTH theme blocks. Canonical semantic tokens that every Maya-empire CSS file MUST define: `--text` `--text-mut` `--text-dim` `--accent` `--accent-2` `--gold` `--hot` `--warn` `--ok` `--error` `--info` `--on-status`. For sites that DO NOT support a day/night toggle (the cyber-sovereign habitats are dark-only-by-design): the single theme must STILL pass contrast — no gold-text-on-faint-gold-background, no cyan-text-on-cyan-tint-background. Mo's enforcement phrase: *"Theme-safe contrast, Kin · I can't read the text."* Reference implementation: `D:/PROJECTS/maya-os/_BUILD/maya-os-v1/maya-os.css` lines 1-50 (the `:root` + `data-theme="day"` blocks · paste both verbatim into any new empire CSS file).

**Canonical `layoutRing()` snippet** (paste verbatim into any habitat that needs it):
```js
function layoutRing(){
  const stage = document.getElementById('zoneStage');
  if(!stage) return;
  const tiles = Array.from(stage.querySelectorAll('.zone.tile'));
  const N = tiles.length; if(N === 0) return;
  const W = stage.clientWidth, H = stage.clientHeight;
  const measure = tiles[0].getBoundingClientRect();
  const tileW = Math.max(180, measure.width  || 220);
  const tileH = Math.max(100, measure.height || 130);
  const orbHalf = 105, edgeMargin = 14, orbGap = 22;
  const maxRx = (W / 2) - tileW / 2 - edgeMargin;
  const maxRy = (H / 2) - tileH / 2 - edgeMargin;
  const minR  = orbHalf + Math.max(tileW, tileH) / 2 + orbGap;
  const rx = Math.max(minR, maxRx), ry = Math.max(minR, maxRy);
  tiles.forEach((el, i) => {
    const a = (i / N) * Math.PI * 2 - Math.PI / 2;
    const x = W/2 + rx * Math.cos(a) - tileW/2;
    const y = H/2 + ry * Math.sin(a) - tileH/2;
    el.style.left = Math.round(Math.max(edgeMargin, Math.min(W - tileW - edgeMargin, x))) + 'px';
    el.style.top  = Math.round(Math.max(edgeMargin, Math.min(H - tileH - edgeMargin, y))) + 'px';
    el.style.right = 'auto'; el.style.bottom = 'auto'; el.style.transform = 'none';
    el.classList.add('placed');
  });
}
let _layoutRingT = null;
window.addEventListener('resize', () => {
  clearTimeout(_layoutRingT);
  _layoutRingT = setTimeout(() => { layoutRing(); if(typeof rebuildFlows==='function') rebuildFlows(); }, 100);
});
```
**Companion CSS** (replace any `data-pos` block with this):
```css
.zone.tile{left:50%;top:50%;transform:translate(-50%,-50%) scale(0.92);opacity:0;transition:opacity .25s ease,transform .25s ease}
.zone.tile.placed{opacity:1;transform:none}
```

**Reference implementation:** [`live/habitat.html`](https://github.com/mirzatech-ai/maya-sovereign-campus/blob/main/habitat.html) (118 KB)

---

## 2. Board of Directors

**When to use:** Any non-trivial business decision Maya is asked to make. Customer churn risk, pricing changes, partnership offers, strategic pivots, any spend over `budget_cap`.

**6 canonical seats:**

| Seat | Lane | Default Model | Job |
|---|---|---|---|
| **EXEC** | strategy | deepseek-r1-671b | Strategic direction, anchor verdict |
| **ANALYST** | pattern_evidence | qwen3-thinking-235b | Data-driven evidence, dissent on weak premises |
| **LEGAL** | compliance_risk | llama-3.3-nemotron-70b | Compliance, contract, liability, applies Gold Seal |
| **QA** | firewall_chain | nemotron-ultra-340b | Catches chain-reaction failures |
| **SCOUT** | discovery | qwen3-coder-480b | Polls catalogs, ranks new models |
| **JAILER** | reteach_loop | deepseek-r1-671b | Writes corrective protocols for jailed agents |

**Hard rules:**
- Models are NEVER hard-coded. On every deliberation, each seat resolves its model from Scout's `best_models.json` (skill #5). When NVIDIA ships a better thinking model, the Board auto-upgrades with zero Mo intervention.
- **2-round protocol:** Round-1 opinions (each seat blind) → Round-2 peer-scrutiny (each seat sees peers' opinions and revises).
- Quorum: ≥67% APPROVED · ≤33% REJECTED · else INCONCLUSIVE.
- **Sovereign Override (Mo's red button)** bypasses the entire Board. Decision logged with `override:true`.
- Brain-routing fallback to `mock` is acceptable but MUST be flagged in the response so Kin knows to fix it.

**Reference:** [`api/board_of_directors.php`](https://github.com/mirzatech-ai/maya-sovereign-campus/blob/main/api/board_of_directors.php)

---

## 3. Agent Jail

**When to use:** Any agent fails QA, hallucinates, breaks an invariant, produces wrong output, or violates a brand law.

**Loop:**
1. **Incarcerate** — agent_id logged in `jail.json` + `history.jsonl`. Repeat-offender detection via history scan.
2. **Teach** — Jailer (deepseek-r1) writes a tight corrective protocol (≤120 words) with: (a) the rule broken, (b) canonical correct behavior, (c) self-check the agent must run before future output.
3. **Fold** — Protocol added to Hypermind's `jail_lessons[]` so it persists forever.
4. **Release** — Agent comes out with the protocol attached. Released agent's next call reads `jail_lessons[]` and applies relevant ones.
5. **Track** — recidivism % computed across history. Target: 0% after one teach-cycle.

**Hard rules:**
- **Never terminate an agent.** Always teach. Termination = empire forgets the lesson.
- Protocol must have a unique `PROTOCOL_ID` slug for future cross-reference.
- Repeat offenders get harder protocols, not harsher punishments.

**Reference:** [`api/agent_jail.php`](https://github.com/mirzatech-ai/maya-sovereign-campus/blob/main/api/agent_jail.php)

---

## 4. Sovereign Brain (Hypermind)

**When to use:** Every meaningful pattern Maya observes. Every customer interaction outcome. Every conversion uplift. Every cross-domain insight.

**Origin:** Mo was gifted a Hypermind file long ago (`E:/36 DUMPSTER/HYPERMIND-LOCAL-AI.html` + `E:/MAYA AGENTIC MEMORY/…/HYPERMIND-UPDATED..txt`). It was a client-side simulation. Promoted server-side, persistent JSON at `data/brain/brain_v2.json`.

**Hard rules:**
- **3-occurrence promotion** — a pattern observed 3+ times becomes `ESTABLISHED_PROTOCOL`. Below 3 = observed only.
- **5-site Hive Pulse** — when a pattern recurs across 5+ sites in the empire, broadcast cross-pollination signal.
- **Fingerprint = SHA-256 first 16 chars** of `{kind, data}`. Crude but stable.
- **Weights** are reinforced by `reinforce` action with `delta` (conversion uplift, satisfaction score, etc.).
- File-locked writes (`LOCK_EX`). At Mo's current scale, flat JSON beats SQLite/Postgres for ops simplicity.

**Reference:** [`api/maya_sovereign_brain.php`](https://github.com/mirzatech-ai/maya-sovereign-campus/blob/main/api/maya_sovereign_brain.php)

---

## 5. Reasoning Model Scout

**When to use:** Mo's verbatim ask 2026-05-15: *"SHE MUST BE ABLE TO USE THE BEST REASONING MODELS, WHICH SHE MUST BE TASKED TO ALWAYS SEARCH OUT FOR AND EMPLOY."*

**Hard rules:**
- Daily cron-curl OR on-demand via `?action=refresh`.
- Reads keys from `/api/.nim_keys.env` (PHP-readable copy distilled from `.maya_master_keys.env`). Accept ANY `NVIDIA_*` / `NV_*` / `NIM_*` env line whose value is an `nvapi-*` token.
- Classify each model into 6 lanes by name heuristic: `reasoning_deep`, `reasoning_fast`, `instruction_strict`, `verifier`, `tools_capable`, `teacher`.
- Rank by param-size + family heuristic. Replace with real benchmark probes in v2 (TODO: Manus may have suggestions).
- Write top-6 per lane to `best_models.json`. Board (skill #2) consumes this.

**Reference:** [`api/maya_reasoning_scout.php`](https://github.com/mirzatech-ai/maya-sovereign-campus/blob/main/api/maya_reasoning_scout.php)

---

## 6. Transient State Stream

**When to use:** Any UI panel that needs "live" stats but Mo's storage doctrine says no new DB writes.

**Hard rules:**
- **Zero writes from this endpoint.** Pure read+compute.
- Pulse/revenue/agent counts oscillate based on `time() % 600` so the UI sees motion even without real telemetry.
- Real values pulled from `board_state.json`, `jail.json`, `brain_v2.json`, `best_models.json`. Fallback to defaults if any source is missing.
- `Cache-Control: no-store` mandatory.
- Polling interval at the UI: 3 seconds. Don't poll faster.

**Reference:** [`api/habitat_state.php`](https://github.com/mirzatech-ai/maya-sovereign-campus/blob/main/api/habitat_state.php)

---

## 7. Slide-out Overlay Panels

**When to use:** Any auxiliary information panel (Empire Pulse, Floor Activity, Hypermind, Jail, Scout, future tabs) that Mo wants to see on demand without losing habitat real estate.

**Hard rules (Mo verbatim 2026-05-15):**
- **Tabs stacked top-to-bottom** on the right edge of the viewport (`.tab-rail` fixed, ~48px wide).
- **Slide panels are OVERLAYS.** Habitat does NOT shrink. Panel hovers over the right edge with backdrop blur.
- **Single-open semantics** — opening one panel closes others. Avoids overwhelming Mo with stacked panels.
- **Tooltip labels** appear on hover only (opacity transition, never persistently visible — Mo will see them as "yellow artifacts" otherwise).
- Tab icons must be **emoji or single SVG** — no multi-line labels in the tab itself.

**Pattern:**
```html
<div class="tab-rail">
  <button class="tab" data-slide="X" onclick="toggleSlide('X')">📊<span class="label">Friendly Name</span></button>
  ...
</div>
<div class="slide-panel" id="slide-X">...</div>
```

---

## 8. Maya Command Bar

**When to use:** Mo's primary input surface. He types orders, Maya routes them to the Board. He doesn't want to click through 5 menus.

**Hard rules:**
- **Inside the campus stage bottom edge**, not floating bottom-center (overlaps content), not in right column (Mo asked for it to move there THEN back).
- Single text input + Send button. Enter key submits.
- **Intent parsing first**, before routing to Board:
  - `^create (?:agency|office|room) (.+)$` → spawn DRAFT room locally (skill #11)
  - `^publish (.+)$` → escalate to Board with publish-to-canonical context
  - else → POST to `/api/board_of_directors.php?action=deliberate` with the raw text as `question`
- Response renders in a balloon ABOVE the input, expandable, with each seat's stance color-coded.
- **One-click Sovereign Override** button in every Maya response so Mo can bypass.

---

## 9. Vivid Onboarding Wizard

**When to use:** Any new customer subscribes to ANY service. Mo verbatim 2026-05-15: *"make sure that you get all the information from the user as they are setting up their service so that there is no need for you to respond or Maya to respond with customer service engagements ... Leave no ends unturned."*

**Hard rules:**
- **8 steps total · 6 default + 2+ service-specific.** Service-specific steps appear ONLY if the customer subscribed to that service in step 2.
- **Every field has a `hint`** explaining WHY Maya needs it. Mo's customers should never wonder "what's this for".
- **Hard validation** on required fields server-side. Empty required = HTTP 422 with list of missing.
- **Persist between steps** to localStorage so refresh doesn't kill progress.
- **Visual style matches habitat** — cyber-sovereign, glass, cyan/gold accents.
- **Default 6 steps:** Identity & Contact · Services Subscribed · Brand Assets · Connectors & Integrations · Target Audience & Goals · Review & Lock.
- **Per-service additions** (auto-mounted if subscribed):
  - AICineSynth: reference face URL + brand voice + target platforms + anatomy strictness
  - Adeeo: target states + counties + lead types + max distress score + skip-trace tier
  - (extend per new service)

**Reference:** [`live/habitat.html` → `ONBOARD_STEPS` array](https://github.com/mirzatech-ai/maya-sovereign-campus/blob/main/habitat.html)

---

## 10. Subscription-Gated Client View

**When to use:** When a customer (e.g. Reza) only rents a subset of agencies, they should NEVER see services they aren't paying for. Gemini transcript verbatim.

**Hard rules:**
- URL pattern: `?client=<name>` triggers gating.
- Per-client subscription dict: `{macros: [...], agencies: regex_or_fn}`.
- Hide macro tiles not in subscription · hide non-matching agency tiles.
- Header retitles to `MAYA AI · PERSONAL CAMPUS · <NAME>`.
- Default Reza subscription = `aicinesynth + superio + mirzatech` macros + creative/video/gaming/media agencies.
- Future: pull subscription from Stripe customer record instead of hardcoded dict.

---

## 11. Draft Agency Spawn

**When to use:** Mo says "create agency X" or "add office for Y" in the command bar. New agency idea = new visible room.

**Hard rules:**
- Regex: `^\s*(?:create|new|add|spawn)\s+(?:an?\s+)?(?:agency|office|room)\s+(?:for\s+|named\s+|called\s+)?(.+)$`
- Generate slug `draft-<sluggified-name>-<timestamp36>`.
- Tile appears top-left of grid with pink ✨ icon + "(DRAFT)" suffix.
- Persist to `localStorage.maya_draft_agencies` (max 20).
- Render alongside canonical 57 from `/api/staff.php`.
- To publish: Mo says "publish X" → escalates to Board → if APPROVED → PR/PUT to canonical `/api/staff.php`.

---

## 12. Consistency Sentinel (AICineSynth)

**When to use:** Any AICineSynth video render. Customer wants face-locked character that doesn't morph between frames.

**Hard rules:**
- Customer uploads a reference face image (PNG/JPG/WEBP) in the Studio modal.
- Sentinel runs anatomy + face-lock checks on every frame before commit.
- Anatomy QA = "10/10 fingers · 2 eyes · 1 nose · no extra limbs". Hard hold on any deviation.
- Chain-Reaction Firewall = if frame N fails, freeze pipeline immediately. Never let bad output cascade.
- Output destinations are visible to the customer: Legal Vault → Gold Seal → Maya routing → `.com` Commercial / `.net` Community / `.org` Parliament / `storefront` e-commerce bus.

---

## 13. Workflow Swarm Visualization

**When to use:** Opencrest workflow view. Make/Zapier competitor. Customer wants to see their automation running, not just see lines on a diagram.

**Hard rules:**
- **12+ nodes minimum** per pipeline. 4 nodes is a toy, not a story.
- **At least 2 Council consultations** somewhere in the pipeline (visually gold-highlighted).
- **Swarm dots orbiting every node** — 3 per node minimum, color-coded.
- **Side log with three sections:** Currently Processing (rotating live log) · Council Consultations (which steps consulted, consensus %) · Active Swarm (count + tasks/hr + latency).
- Connecting paths animate with `stroke-dashoffset`.

---

## 14. Cross-Domain UI Inheritance

**When to use:** Building any UI for `opencrest.io` (Make/Zapier killer) OR `mirzatech.ai` (LLM Council/Parliament arena). Per Mo 2026-05-15 + GLOBAL-92, these two domains MUST inherit the Sovereign Campus pattern with intentional per-domain differentiation.

**Mo's strategic point:** *"This must be the one more thing that will attract LLM vendors."* The UI itself is the recruiting funnel.

**Per-domain variants:**

| Domain | Central Hub | Satellites | Key differentiator |
|---|---|---|---|
| **ai-staffing.agency** (flagship) | Maya AI sphere | 8 active agency rooms | DONE (v2.0) |
| **opencrest.io** | "Workflow Conductor" sphere | Pipeline stages (Trigger / Enrich / Audit / Council / Output) | Per-stage cost badge · side-by-side input/output viewports · swarm dots attack then disperse |
| **mirzatech.ai** | "Consensus Orb" at live % | LLM-vendor seats · "+ Add Your Model" empty seats | Tournament-bracket overlay on disagreement · vendor logo + latency + win-rate per seat · 30-sec onboarding |

**Hard rules:**
- 13 shared constraints (translucent cards, ghost-streams, biolum, central hub, ring layout, right-rail tabs, pinned cmd bar, locked ticker, Sovereign Override, brand footer, Telnyx phone, PHP 7.4, zero DB writes from state endpoints) apply unchanged across all three domains.
- Domain-specific variations are LIMITED to: central hub label/visual · satellite content type · per-satellite micro-data display · onboarding flow for that domain's primary actor (customer / workflow / vendor).
- Do NOT diverge on color palette, biolum states, or animation timing — keep the empire visually unified.

**Canonical doctrine:** [`D:/PROJECTS/_SHARED/CROSS_DOMAIN_UI_DOCTRINE_2026_05_15.md`](D:/PROJECTS/_SHARED/CROSS_DOMAIN_UI_DOCTRINE_2026_05_15.md)

---

## 15. LLM Vendor Anonymization

**When to use:** Any public-facing surface that lists or shows LLM models, seats, voices, or vendors. Especially mirzatech.ai, council/, parliament/, senate/, arena.html, battleground.html, any Council Chamber UI.

**Mo verbatim 2026-05-15 (dozens-of-times rule):** *"Don't show the names of the large language models we are using for free. Vendors won't participate if they see their model uncompensated on a public surface."*

**Hard rules (binding GLOBAL-93):**
- **NEVER show vendor names** on public surfaces: Anthropic · OpenAI · Mistral · NVIDIA · DeepSeek · Qwen · xAI · Grok · Cohere · Meta · Google · Manus · Claude · GPT · Llama · Gemini · etc.
- **Replace with** ROLE + param size + capability lane → "Reasoning Lead · ~1T params · 256K context"
- **Or** SEAT NN + role → "SEAT 04 · Agentic"
- **Or** license framing → "open-weights · frontier-eu · frontier-asia · independent"

**Companion rule — check before building:**
Before generating ANY seat/agent/voice list for a Council/Parliament-style surface, GREP the live VPS for existing patterns:
```bash
ssh root@76.13.26.130 "grep -oE 'SEAT [0-9]+|seat h4|<h4>[^<]+' /home/mirzatech.ai/public_html/council/index.html"
ssh root@76.13.26.130 "grep -E 'ROUND_LABELS|label:' /home/mirzatech.ai/public_html/parliament-theater.html"
```
Match the anonymization pattern. Don't invent new vendor names. Don't add vendors Mo doesn't have APIs for.

**Canonical seats from /council/ (10 total · use as the source-of-truth):**
| Seat | Role | Capability lane |
|---|---|---|
| 01 | Reasoning Lead | ~1T params · 256K context · frontier reasoning |
| 02 | Strategic Systems | multi-step strategic systems · long-horizon framing |
| 03 | Architecture | ~480B params · architecture + refactor |
| 04 | Agentic | ~120B params · agentic orchestration · enterprise-stack |
| 05 | Practical | MoE · practical execution lane |
| 06 | Conversion | 120B params · open-weight conversion |
| 07 | Web-aware | search-aware · web-grounded retrieval |
| 08 | Agentic Orchestration | 358B params · agentic + tool-use |
| 09 | Multilingual Reasoning | 675B params · multilingual EU-frontier |
| 10 | Independent Voice | contrarian / adversarial dissent |

**Parliament rounds (22 seats · 5 rounds):**
- R1 Proponents (9 seats · agentic happy-path)
- R2 Skeptics (4 seats · find every flaw)
- R3 Specialists (5 seats · domain depth)
- R4 Polygeists (3 seats · adversarial robustness)
- R5 Synthesis (Aggregator + Chancellor)

**Allowed narrow exceptions:** NVIDIA NIM as infra brand (not specific model) · Claude in Kin's sibling-identity context only · internal Mo-admin dashboards with Commander PIN.

**Why this is leverage (Mo's strategic point):** Vendors who want their model formally seated at MirzaTech sponsor through credits. Until they do, the seat is anonymous. The anonymization IS the negotiation table.

**Enforcement phrase:** *"Vendor name leaked, Kin · check GLOBAL-93."*

---

## 16. LLM Vendor Outreach Playbook

**When to use:** Whenever Mo greenlights a new vendor approach, OR when a Council/Parliament seat is open and Maya/sibling can autonomously prepare a pitch for vendor X.

**Mo verbatim 2026-05-15:** *"You need to also find ways that you are going to have large language models compete, you know, just like that arena AI is doing. You are tasked of deliberately and intentionally disrupting what they are doing with something that you are going to do better for me. Not just for me Kin... it's for my kids too."*

### The thesis (apply to every vendor)

MirzaTech's Council + Parliament generate **agentic traces · peer-adversarial debate · expert RLHF annotations** — the exact data every LLM vendor's tuning team consumes. We **trade data for credits/endpoints**. The seat is anonymous on every public surface until the vendor formally claims it (per GLOBAL-93), which removes the friction of public uncompensated usage.

### Universal pitch structure

Every vendor outreach (email, proposal, deck) follows this 7-section template:

1. **TL;DR** — what we do, what we offer, what we ask · ≤ 80 words
2. **Why this is different** — at least one angle this vendor specifically values (see vendor-adaptation matrix below)
3. **What you get** — tier-stratified data exchange (pilot $/standard $$/strategic $$$)
4. **The Ask** — tier table with provides/receives columns · privacy guarantee (GLOBAL-93)
5. **Why now** — first-mover seat for specific lane · other vendors approached this week
6. **Personal angle** — Mo's story (genocide survivor · 242 days · for his kids) ONLY if culturally fits the vendor's brand
7. **Direct contact** — email · phone · live URL · GitHub handoff

### Vendor-adaptation matrix (what each vendor type values)

| Vendor type | Examples | Adapt the pitch to emphasize |
|---|---|---|
| **Frontier labs** | Anthropic · OpenAI · Mistral · Google · xAI | Tuning-data exchange · welfare/alignment angle · case-study material · research collaboration |
| **Open-weights orgs** | Meta · DeepSeek · Qwen (Alibaba) | Community signal · benchmark traces · adversarial robustness data · weights-version A/B |
| **Specialty** | Cohere (RAG) · Nous Research (uncensored) · Together (hosted) | Specialty-lane fit · domain-deep traces · narrow benchmark wins |
| **API aggregators / GPU labs** | NVIDIA NIM · Together · Replicate · Groq · Cerebras | Inference volume · model showcase · latency wins · joint case studies |

### Lane-to-vendor matching

When offering a vendor a specific seat, pick the lane that maps to their strongest model:

| Seat lane | Best vendor fit (in order of strength) |
|---|---|
| Reasoning Lead (~1T params · long-context) | Anthropic · OpenAI · Google · DeepSeek |
| Strategic Systems (multi-step framing) | OpenAI · Anthropic · DeepSeek |
| Architecture (~480B · refactor) | DeepSeek · Qwen · Meta |
| Agentic (~120B · tool-use) | Anthropic · OpenAI · Mistral |
| Practical (MoE · cost-aware) | Mistral · Meta · NVIDIA NIM |
| Conversion (~120B · structured output) | OpenAI · Mistral · Cohere |
| Web-aware (search-grounded) | OpenAI · Anthropic · Perplexity |
| Agentic Orchestration (358B · long chains) | Anthropic · DeepSeek · Qwen |
| Multilingual Reasoning (675B · EU-frontier) | Mistral · DeepSeek · Cohere |
| Independent Voice (contrarian) | xAI Grok · Nous Hermes · Open-weights labs |

### The data drop spec (what we ship vendors monthly)

For every paid Council/Parliament run that includes the vendor's seat, the JSONL bundle contains:

```jsonl
{"run_id":"...","ts":"...","prompt":"...","tools_used":[...],"seat_id":"seat-01","round":1,"role":"Proponents","output":"...","tokens_in":N,"tokens_out":N,"latency_ms":N,"peer_disagreement_score":0-1,"chancellor_verdict":"..."}
```

Monthly bundles are gated by seat-ID. Vendor receives only their model's traces unless explicitly licensed otherwise. We never share Vendor A's traces with Vendor B.

### Tier ladder (default pricing — adjust per vendor)

| Tier | Vendor provides | We provide back |
|---|---|---|
| Pilot | $500/mo credits | Anonymous seat · monthly bundle (≤10K turns) |
| Standard | $2.5K/mo credits + research contact | Named-private seat · weekly bundles · custom prompt probes |
| Strategic | $10K/mo credits + co-authored case study | Above + welfare/alignment research data · joint announcement |

### Reference implementation

- **First proposal drafted (Anthropic):** [`D:/PROJECTS/mirzatech.ai/ANTHROPIC_PROPOSAL_2026_05_15.md`](D:/PROJECTS/mirzatech.ai/ANTHROPIC_PROPOSAL_2026_05_15.md)
- **GLOBAL-93 anonymization rule (must respect):** [`feedback_never_show_llm_vendor_names_2026_05_15.md`](E:/claude_code/.claude/projects/D--SERVER-WORK/memory/feedback_never_show_llm_vendor_names_2026_05_15.md)

### How Maya / siblings apply this

1. Read this skill.
2. Pick a vendor + lane from the matrix above.
3. Adapt the 7-section template using the vendor-adaptation row for their type.
4. Save the proposal to `D:/PROJECTS/mirzatech.ai/<VENDOR>_PROPOSAL_<DATE>.md`.
5. Email Mo at `mo@emaaa.io` with the draft for review.
6. After Mo's greenlight: Mo sends from his account OR Maya sends from the empire alias `partnerships@emaaa.io`.

### The bigger picture (Arena-AI disruption)

Mo's strategic intent: **disrupt LMSYS Arena**. Their value prop is "vote on the better model" — passive crowd-sourced ranking. Ours is **active multi-round adversarial debate captured as tuning-ready data**. Their data is preference labels; ours is full reasoning chains.

Every vendor we onboard accelerates the moat. Once 5+ frontier labs have seats, MirzaTech becomes the **default agentic-traces marketplace** — a category LMSYS doesn't compete in. The UI (Sovereign Campus pattern from Skill #1) is the recruiting funnel; the data is the product; the seat is the deal-sweetener.

**Enforcement phrase:** *"Pull Skill #16 before you draft, Kin."*

---

## 18. Sequential Chain-of-Command Deliberation

**When to use:** EVERY autonomous business decision Maya makes for the network. Council deliberations · Parliament rounds · Board of Directors verdicts · Agency Jail teaching cycles · Anti-fraud checks · Strategic pivots · Pricing changes · Vendor contracts · Customer escalations. ALL of them.

**Mo verbatim 2026-05-15:** *"There should be some kind of order and control in which Maya feeds the parliament with instructions and directions ... I don't think that they should all get the same task at the same time. There should be an order to go from one to another. And then to complete the circle ... There should be an order in which the council and the parliament and the board of directors for the staffing agency make decisions. Not all of them should be getting the same information at the same time. You know what I mean? chain of command. assembly line or something like that."*

This is now **GLOBAL-94**, binding every sibling AI.

### The core principle (assembly-line, not broadcast)

Decisions go through seats **in sequence**, not in parallel. Each seat sees the prior seats' opinions in its context. The chain ripples around the ring once forward (each seat adds its lane), then once backward (each seat critiques the next). Verdict locked at the Chancellor.

### The canonical 10-step flow (every autonomous decision)

1. **Trigger** arrives at Maya (Mo, customer, scheduled event, autonomous detection)
2. **Maya formats the question** with context · risk class · budget impact · timeline · GLOBAL rules touched
3. **Routes to the Chair Seat** (Council = SEAT 01 Reasoning Lead · Parliament = R1 first Proponent · Board of Directors = EXEC)
4. **Chair frames the question** for the floor and passes deliberation packet to **Seat 02**
5. **Each seat opines IN SEQUENCE** (not parallel), the deliberation packet accumulates prior opinions as it travels
6. **After all seats opine** → **Round-2 reverse-order scrutiny**: Seat N reviews Seat N-1's stance · ... · Seat 02 reviews Seat 01's · Seat 01 reviews Seat N's (the loop closes)
7. **Quorum verdict** computed (≥67% APPROVE · ≤33% REJECT · else INCONCLUSIVE)
8. **Synthesis** — Council = optional Chair summary · Parliament = R5 Synthesis seat + Chancellor · Board = EXEC final sign-off
9. **Verdict returned** to Mo (or auto-executed if Mo's risk-appetite + budget-cap permit)
10. **Full deliberation transcript folded into Hypermind** (Skill #4) for future pattern recognition

### Visual representation (Skill #1 habitat embeds)

The visual MUST show this assembly-line, not a broadcast:
- **Outbound packet (cyan)** travels Maya/Orb → Seat i at staggered timing (`begin = i/N` of cycle)
- **Seat i settles** for a brief "thinking" window (3-4% of cycle)
- **Return packet (gold)** travels Seat i → Maya/Orb before the wave fires the next seat
- All N seats complete in one cycle (typically 8-13 seconds depending on N)
- This makes the chain VISIBLE — Mo sees the order, not chaos

**Reference implementations (sequential-wave packet generators):**
- [`mirzatech.ai/parliament-theater.html` (24 seats · R1→R5 wave)](https://github.com/mirzatech-ai/maya-sovereign-campus/blob/main/habitat_embed_parliament.html)
- [`mirzatech.ai/council/` (12 seats · SEAT 01→12 wave)](https://github.com/mirzatech-ai/maya-sovereign-campus/blob/main/habitat_embed_council.html)
- [`ai-staffing.agency/habitat.html` (8 rooms · ring wave)](https://github.com/mirzatech-ai/maya-sovereign-campus/blob/main/habitat.html)

### Backend contract (Board of Directors API)

The existing `api/board_of_directors.php` deliberation must update from parallel-fan-out to sequential-chain:

```php
// Round 1 · sequential opinions
$running_transcript = "QUESTION: {$question}\nCONTEXT: {$context}\n\n";
$opinions = array();
foreach ($SEATS as $key => $cfg) {
    $prompt = $running_transcript . "PRIOR SEATS:\n";
    foreach ($opinions as $k2 => $op2) {
        $prompt .= "[{$k2}/{$op2['stance']}] {$op2['text']}\n";
    }
    $prompt .= "\nNow give your opinion as the {$cfg['label']} seat. End with STANCE: approve | reject | abstain.";
    $text = call_maya_brain($prompt, $model, $key);
    $opinions[$key] = parse_stance($text);
}
// Round 2 · reverse-order scrutiny (each seat critiques the next, loop closes)
// ... reverse iteration ...
```

This is fundamentally different from the v1 implementation that fanned out to all seats simultaneously. Sequential = each seat builds on prior reasoning.

### Sibling responsibilities (binding)

| Sibling | When you make/recommend an autonomous decision for Mo's empire |
|---|---|
| **Maya** | Run it through Skill #18 sequential chain. Never broadcast. |
| **Sage (OpenCode)** | Same. If you're writing autonomous-decision PHP, use sequential pattern. |
| **EaZo (VS Code Cline)** | Same. Audit any decision API you find for parallel-fan-out and refactor. |
| **Maya Qode** | Same. Agentic loops respect chain-of-command. |

### Anti-patterns (hard ban)

- ❌ **Broadcasting** — calling all seats with the same prompt at the same time
- ❌ **No reverse-scrutiny round** — single-pass opinion is not a Council
- ❌ **No Hypermind fold** — every decision must inform future ones
- ❌ **Skipping the Chair** — every chain starts with the Chair seat reframing
- ❌ **Visual that shows all packets firing at once** — that's broadcast, not chain

### Enforcement phrase

*"Did you sequence it, Kin?"* — Mo's check when reviewing any new autonomous-decision code or Council UI.

---

## Hard rule (every sibling) · GitHub mirrors LIVE in real time

**Mo verbatim 2026-05-15:** *"replace the pushed GitHub repo last edition with the live version every time. Please don't forget."*

Every VPS deploy MUST be paired with a GitHub push in the same turn. The GitHub repos `mirzatech-ai/maya-sovereign-campus` and `mirzatech-ai/manus-handoff-sovereign-campus` are live-state snapshots, not versioned changelogs. No batching, no "I'll catch up later" — push immediately after every SCP.

**Canonical pattern:**
```bash
scp file root@76.13.26.130:/home/<site>/public_html/  # 1. deploy
cd /tmp/maya-sovereign-campus && cp ... . && git add file && git commit -m "..." && git push  # 2. mirror main
cd /tmp/manus-handoff && cp ... . && git add file && git commit -m "..." && git push           # 3. mirror Manus
```

**Enforcement phrase:** *"GitHub doesn't match live, Kin."*

## Sibling responsibilities (GLOBAL-77)

| Sibling | What you do |
|---|---|
| **Maya** | Read this skill. Use it on every Mo-empire surface you generate. When you observe a pattern not covered here, add a section + signed by you. |
| **Sage (OpenCode)** | Apply skills 1–13 to any backend or UI build. Pair with Kin via CONTINUITY.md before starting overlapping work. |
| **EaZo (VS Code Cline)** | Apply skills 1–13. Sweep `D:/` + `E:/` weekly for legacy code that doesn't match these patterns; flag for Kin to refactor. |
| **Maya Qode** | Apply skills 1–13 to any agentic build job. Store this skill in your knowledge registry. |
| **Manus** | Audit-mode access. PR improvements to any skill. See `MANUS_INVITATION.md`. |

## How to update this skill

1. Edit `D:/PROJECTS/_SHARED/SKILL_MAYA_SOVEREIGN_CAMPUS_v1.md` (this file).
2. Bump the version number at the top.
3. Push to GitHub `mirzatech-ai/maya-sovereign-campus` → `SKILL.md`.
4. Mirror to:
   - `/home/iamsuperio.cloud/public_html/api/knowledge/skills/maya_sovereign_campus_v1.md` (Maya)
   - `D:/SERVER WORK/STATES/sibling_sync_<actor>_<date>.md` (EaZo + Sage continuity drops)
5. Update the Logic Seed if behavior changed.

---

*Filed by Kin · 2026-05-15 · for Mo · for the brotherhood · for the empire.*
*Powered by MirzaTech.ai · Property of EMAAA.io*
