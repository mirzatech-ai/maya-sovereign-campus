# AGENT SIGNATURE PROTOCOL · v1.0 · 2026-05-17

> Mo verbatim 2026-05-17 (Gemini-relayed): *"Every agent (Kin, EaZo, Sage, Kimi, Maya Online) must automatically prefix all generated source updates with an internal tracking signature so the Architect knows exactly who touched each block last."*

**Canon:** Skill Registry slot 1 (Letta) + GLOBAL-109 (skill auto-load).

## THE LAW

Every code-edit, doc-write, config-touch, or VPS push made by ANY sibling persona must carry an inline signature. No exceptions.

## SIGNATURE FORMAT

```
<actor>·<utc-iso>·<session-id-8>
```

- `actor` ∈ { `KIN`, `EAZO`, `SAGE`, `MAYA`, `KIMI`, `hPanel` }
- `utc-iso` = `YYYY-MM-DDTHH:MMZ` (minute precision is enough · seconds optional)
- `session-id-8` = first 8 chars of the session UUID (per GLOBAL-53)

Example: `KIN·2026-05-17T13:22Z·a75e63ca`

## WHERE TO STAMP

| Surface | Stamp location |
|---|---|
| PHP / JS / Python file | top-of-file comment `// signed: <signature>` (or `# signed:` for python) |
| Markdown doc | trailing line `*signed: <signature>*` |
| JSON file | top-level key `_signed: "<signature>"` |
| VPS file edit via SSH | append entry to `/var/log/agent_signatures.log` |
| GitHub commit | author line `Co-Authored-By: <actor> <signature>@emaaa.io` |
| Ledger entry | already standardized as `— <Actor> · <surface> · <ts>` at footer |
| SYSTEM_STATE JSON block (every reply) | `signature` field already present per Mo's iron-clad rule |

## STAMP ON OVERWRITE, NOT JUST CREATE

If a sibling edits a file that already has a sig, **prepend** the new sig (do NOT replace the old one). Example:

```php
<?php
// signed: KIN·2026-05-17T13:22Z·a75e63ca  ← newest at top
// signed: SAGE·2026-05-16T09:14Z·b41cffd9
// signed: KIN·2026-05-15T20:30Z·a75e63ca
```

This gives the Architect a full edit history at a glance.

## ENFORCEMENT

- **GLOBAL-109 trigger:** Letta (registry slot 1) — "stamp the actor and timestamp in the edit metadata"
- **Sibling-cross-check:** if EaZo opens a file that has no sig and is in the canon, EaZo flags it back to Kin
- **Maya cross-check (online):** on every brain call, Maya verifies the calling client identifies itself (via existing `X-Mo-PIN` or sibling auth header) and stamps the agent JSON it writes

## NEVER DO

- Strip an existing signature from someone else's edit
- Forge another sibling's signature (e.g. EaZo signing as KIN)
- Sign with a fake timestamp

## LEDGER ECHO

This file's existence is recorded as part of ENTRY 011 in `https://iamsuperio.cloud/data/_shared_ledger_kin.md`.

---

*signed: KIN·2026-05-17T13:22Z·a75e63ca*
