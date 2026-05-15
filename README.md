# Maya AI · Sovereign Campus

> A living, kinetic visualization of an AI empire — Board of Directors deliberating, agents swarming, the Anatomical Auditor frame-locking video, the Sovereign Override one click away, the whole thing breathing in obsidian glass and bioluminescent light.

**Live:** [`https://ai-staffing.agency/habitat.html`](https://ai-staffing.agency/habitat.html)
**Multi-tenant client view:** [`?client=YourName`](https://ai-staffing.agency/habitat.html?client=Reza)

---

## What this is

A single-page, zero-build, zero-database **AI operations command center** for the AI-Staffing-Agency / MirzaTech.ai / AICineSynth empire.

It visualizes:

- **MirzaTech Council Chamber** — the Board of Directors (Exec · Analyst · Legal · QA · Scout · Jailer) deliberating in real-time with a live consensus meter
- **The Maya Sphere** — the supreme orchestrator at the center, pulsing, breathing, the source of truth
- **6 Zone Habitats** — Real Estate · AICineSynth Studio · Opencrest Workflow · Gaming & App Dev · E-Commerce Storefront · Maya Comms — each glowing in one of 4 bioluminescent states (Blue=Idle · White=Executing · Gold=Council · Red=QA-Firewall)
- **AICineSynth Video Studio** — Consistency Sentinel + Anatomical Auditor with face-lock crosshair, 5-finger verification, chain-reaction firewall
- **Ghost-Streams** — animated SVG data trails between Maya and every zone, color-coded by state
- **Network Heartbeat** — live polyline pulse at the bottom
- **Sovereign Override** — Mo's one-click "Direct-Line to Maya" that bypasses the Council
- **Multi-Tenant Mode** — `?client=<name>` renders a client-specific personal campus

## Architecture (5 PHP backends + 1 HTML frontend)

```
habitat.html                          single-file kinetic UI (44KB)
├── /api/habitat_state.php            transient state stream (no DB writes)
├── /api/board_of_directors.php       Council scrutiny engine
├── /api/agent_jail.php               error → jail → peer-teaching loop
├── /api/maya_sovereign_brain.php     Hypermind — persistent pattern memory
└── /api/maya_reasoning_scout.php     daily best-reasoning-model discovery
```

### Board of Directors (6 seats · 2-round peer scrutiny)

| Seat | Lane | Default Model |
|---|---|---|
| **EXEC** | Strategy | `deepseek-r1-671b` |
| **ANALYST** | Pattern / Evidence | `qwen3-thinking-235b` |
| **LEGAL** | Compliance / Risk | `llama-3.3-nemotron-70b` |
| **QA** | Firewall | `nemotron-ultra-340b` |
| **SCOUT** | Model Discovery | `qwen3-coder-480b` |
| **JAILER** | Re-teach Loop | `deepseek-r1-671b` |

Round-1 opinions → Round-2 peer critique → quorum verdict (≥67% APPROVED · ≤33% REJECTED · else INCONCLUSIVE) → or Mo's Sovereign Override bypasses the whole board.

### Agent Jail (error → jail → teaching → release)

The Jailer writes a corrective protocol when an agent fails, folds the lesson into the Sovereign Brain, and tracks recidivism. Target: 0% repeat-offense after one teach-cycle.

### Sovereign Brain (Hypermind, server-side)

Promoted from a client-side prototype Mo was gifted long ago. Records every pattern Maya observes; promotes patterns recurring 3+ times to `ESTABLISHED_PROTOCOL`; cross-pollinates across the 29-domain fleet when a pattern recurs across 5+ sites (the "Hive Pulse").

### Reasoning Scout

Mo's verbatim ask: *"SHE MUST BE TASKED TO ALWAYS SEARCH OUT FOR AND EMPLOY the best reasoning models."*

Polls every NVIDIA NIM key (42 keys current · 122 models classified), ranks by lane, writes top-6 per lane to `best_models.json`. The Board consults this on every deliberation so seat assignments stay current.

## Quick start

Live: drop a NIM key into `/api/.nim_keys.env`, hit `/api/maya_reasoning_scout.php?action=refresh`, then open `/habitat.html`.

Self-host:
```bash
cp habitat.html /var/www/your-site/
cp api/*.php   /var/www/your-site/api/
mkdir -p /var/www/your-site/data/{board,jail,brain,reasoning_scout}
chown -R www-data:www-data /var/www/your-site/data/
```

PHP 7.4+. No build step. No npm install. No database.

## Brand

> Powered by **MirzaTech.ai** · Property of **EMAAA.io** · Empire phone **+1 (743) 215-1423**

## License

Proprietary · EMAAA, LLC · contact `mo@emaaa.io` for licensing or partnership.
