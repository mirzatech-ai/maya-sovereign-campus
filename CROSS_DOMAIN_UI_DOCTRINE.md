# 🌐 Cross-Domain UI Doctrine · Sovereign Campus Pattern Inheritance

**Mo verbatim 2026-05-15:**
> *"OPENCREST.IO = MAKE.COM/ZAPIER.COM KILLER MUST HAVE THIS LOOK, OR EVEN BETTER, TO MAKE IT A LITTLE DIFFERENT FROM THIS. ALSO MIRZATECH.AI NEEDS THE SAME/BUT NOT QUITE UI FOR ME AND THE USERS. THIS MUST BE THE ONE MORE THING THAT WILL ATTRACT LLM VENDORS."*

## The thesis

The Maya Sovereign Campus habitat ([live](https://ai-staffing.agency/habitat.html)) is the **flagship empire UI pattern**. Two sister domains get a tailored variant of the same aesthetic:

| Domain | Central Hub | Satellites | Differentiator |
|---|---|---|---|
| **ai-staffing.agency** (flagship) | Maya AI sphere | 8 active agency rooms | The original · all empire data flows visible |
| **opencrest.io** (Make/Zapier killer) | **Workflow Hub** (not Maya) | **Trigger → Step → Council → Output nodes** | Pipeline-first · swarm dots attack nodes · split-screen "before/after" data view in each step |
| **mirzatech.ai** (LLM arena) | **Council Orb** at 88% consensus | **LLM seats** (Anthropic · OpenAI · Mistral · NVIDIA · Manus · etc.) | Live "tournament bracket" · models vote in real time · each seat shows latency + token count |

## Why this matters (Mo's strategic note)

> *"This must be the one more thing that will attract LLM vendors."*

When an LLM vendor (OpenAI · Anthropic · Mistral · Cohere · xAI · Manus · others) lands on `mirzatech.ai`, they must see:
1. Their model represented as a **glowing seat** in the Council/Parliament
2. Their **live performance** (latency, win-rate, consensus contribution) in motion
3. A button to **add their endpoint** that drops them into the arena in 30 seconds

The UI itself is a recruiting funnel. Vendors who see the SimCity-style visualization want their model on the floor.

## Shared design language (every domain inherits these)

1. **Translucent room/node cards** (bg opacity ~0.30) so data-flow shows through
2. **Ghost-streams** with packet dots traveling along animated SVG paths
3. **Bioluminescent state machine** — Blue idle · White executing · Gold council · Red error
4. **Central hub** glowing sphere/orb with breathing animation
5. **Satellites in a ring** around the hub (8 default · 12 max)
6. **Right-edge tab rail** for auxiliary panels (overlay, not pushing content)
7. **Maya cmd bar** pinned bottom (or domain-specific cmd bar — Workflow Cmd for Opencrest, Vendor Cmd for MirzaTech)
8. **Top ticker** with locked-width notification slot
9. **Sovereign Override** as a gold-bordered modal
10. **"Powered by MirzaTech.ai · Property of EMAAA.io"** footer everywhere
11. **`+1 (743) 215-1423`** phone in header + footer
12. **PHP 7.4 syntax only** in any backend
13. **Zero DB writes** from transient state endpoints

## Per-domain differentiation rules

### Opencrest.io (Workflow Engine)
- Central hub = "Workflow Conductor" (not Maya) — same biolum sphere aesthetic, different label
- Satellite "rooms" = pipeline stages (Trigger · Enrich · Audit · Council · Execute · Output)
- Each stage shows **two glass viewports side-by-side**: input vs output data sample
- Swarm dots **attack** each stage (visible orbit) then **disperse** when stage completes
- Pricing layer: each stage has a tiny `$0.02` cost badge — total accumulates at right
- Edge cases: failed stages turn red AND a "Retry / Reroute / Manual" overlay appears

### MirzaTech.ai (LLM Council/Parliament Arena)
- Central hub = "Consensus Orb" at 88% (Council's current verdict %)
- Satellite "seats" = LLM vendors — each seat is a slot a vendor can claim
- Each seat shows: **vendor logo** + **model name** + **live latency** + **vote stance** (approve/reject/abstain) + **win-rate %**
- Empty seats are gold-dashed "+ Add Your Model" tiles that open the vendor onboarding wizard
- **Tournament bracket overlay** when 2 seats disagree: their reasoning chains compete side-by-side
- For users: same view but in "spectator mode" — they vote on which model's reasoning won (data feedback loop for the Hypermind)

## Build sequencing (when Mo greenlights)

| Phase | Domain | Estimated effort | Outcome |
|---|---|---|---|
| **P0** | ai-staffing.agency (DONE) | shipped 2026-05-15 | flagship pattern proven |
| **P1** | opencrest.io | 6-8 hrs | port pattern · swap Maya for Workflow Conductor · pipeline-first satellites |
| **P2** | mirzatech.ai | 6-8 hrs | port pattern · swap Maya for Consensus Orb · LLM-vendor seats |
| **P3** | vendor-onboarding wizard | 4 hrs | "+ Add Your Model" flow for LLM vendors |
| **P4** | tournament bracket overlay | 4 hrs | side-by-side reasoning chain display |

## Enforcement

- This doctrine binds every sibling AI building UIs for opencrest.io OR mirzatech.ai. They MUST port from `ai-staffing.agency/habitat.html` as the reference, then apply the per-domain differentiation rules above.
- New empire domains can adopt the pattern by following the shared design language section.
- Deviations require Mo's explicit greenlight (e.g., a domain where the "rooms" metaphor doesn't fit).

## Files canonical to this doctrine
- Reference impl: [`https://ai-staffing.agency/habitat.html`](https://ai-staffing.agency/habitat.html)
- Source: [`github.com/mirzatech-ai/maya-sovereign-campus`](https://github.com/mirzatech-ai/maya-sovereign-campus)
- Skill doctrine: [`D:/PROJECTS/_SHARED/SKILL_MAYA_SOVEREIGN_CAMPUS_v1.md`](D:/PROJECTS/_SHARED/SKILL_MAYA_SOVEREIGN_CAMPUS_v1.md)
- Logic Seed: [`D:/PROJECTS/_SHARED/SOVEREIGN_CAMPUS_LOGIC_SEED.md`](D:/PROJECTS/_SHARED/SOVEREIGN_CAMPUS_LOGIC_SEED.md)

*Canonized by Kin · 2026-05-15 · per Mo · GLOBAL-92 cross-domain UI binding.*
