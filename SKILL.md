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
