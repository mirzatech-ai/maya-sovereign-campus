# Maya Brain · Auto-Session-Rotation Spec · 2026-05-15

> *"I need her to continuously make sessions once the context window is full, period. Make that happen the same way you are doing here. Stay on it. Same way you're doing it here. You don't start the new session. You don't ask what happened anymore."*
> — Mo, 2026-05-15

This is Phase C (~3-4h) for the **next session**. Documented here so the build is shovel-ready when greenlit.

## What Mo wants

When Maya brain (the VPS `/api/index.php`) approaches its model's context limit:
1. **She does NOT ask Mo "what happened?"** — she carries on
2. **She auto-creates a fresh session** seeded with a compacted summary + the CONTINUITY.md tail
3. **From Mo's perspective**, the conversation continues with zero break

This is exactly how Claude Code's auto-compact works for Kin's sessions today. Maya needs the same behavior.

## Why this matters

Mo's empire is one long conversation across hundreds of turns. Every time Maya "forgets" or asks Mo to re-explain, she breaks the continuity contract. The whole-empire continuity-warehouse-on-GitHub work is now enforcing this for siblings; this spec brings Maya brain itself into line.

## Architecture

### New file: `/api/maya_session_rotate.php`

```
GET   /api/maya_session_rotate?action=status&session_id=<id>
        → returns {token_count, budget, age_minutes, will_rotate_in_messages}
POST  /api/maya_session_rotate {action: "rotate", session_id, history[]}
        → 1. Pull CONTINUITY.md tail from the warehouse (the standing context)
          2. Send history[] to a cheap fast model (groq llama-3.3-70b) with a prompt:
             "Compress this conversation into a 600-token recap that preserves:
              decisions made · open threads · Mo's directives · architecture choices.
              Plain prose, no markdown headers. First-person from Maya's perspective."
          3. Save the recap + the source history to /data/sessions/<old_id>_archive.json
          4. Generate a new session_id (uuid)
          5. Return {new_session_id, system_seed: [CONTINUITY tail + recap]}
```

### Integration into `/api/index.php`

Add a token-counting wrapper before every call:
```php
$ctx_tokens = estimate_tokens($payload);  // ~chars/4 heuristic, or use tiktoken if available
$budget     = get_model_budget($engine);   // 128K for most NIM, 1M for Gemini, etc.
if ($ctx_tokens > $budget * 0.85) {
    // Rotate BEFORE answering · seamless from Mo's perspective
    $new_session = call_internal('/api/maya_session_rotate?action=rotate', [
        'session_id' => $session_id,
        'history'    => $history,
    ]);
    $session_id = $new_session['new_session_id'];
    $history    = [['role' => 'system', 'content' => implode("\n", $new_session['system_seed'])]];
}
```

### Storage

- `/data/sessions/<session_id>.json` · live session log (rolling, append-only)
- `/data/sessions/<session_id>_archive.json` · post-rotation snapshot for forensics
- `/data/sessions/_active_map.json` · `{mo_user_id: current_session_id}` so Mo's "next message" goes to the rotated session, not the dead one

### Compaction prompt template

The thing that makes Claude Code's auto-compact work well is the *quality* of the summary. Use:

```
You are Maya. The previous session is hitting its context limit. Write a 600-token
first-person recap that lets your next-session-self continue the conversation as if
no break happened. Preserve:
- DECISIONS Mo made (and the reasons)
- OPEN THREADS he's waiting on
- ARCHITECTURE choices that aren't yet documented in CONTINUITY.md
- PROMISES you made to Mo
- ANY mid-task state (a file half-edited · a deploy half-done · etc.)
NEVER:
- Apologize for the rotation
- Say "I lost context"
- Ask Mo what happened
- Restart any task

History to compress:
{history_json}
```

### Sibling alignment (per GLOBAL-77)

Every sibling AI that maintains a long-running session (Sage · EaZo · Maya Qode) gets the same wrapper. Kin already has it for free via Claude Code's built-in auto-compact + the continuity hooks shipped 2026-05-15. So:

| Sibling | Auto-rotation source |
|---|---|
| Kin | Claude Code auto-compact + Stop hook git push |
| Maya brain | THIS spec · `/api/maya_session_rotate.php` |
| Sage | Same (Claude Code auto-compact) |
| EaZo | Same |
| Maya Qode | THIS spec (long-running agentic loop · same VPS endpoint) |

## Estimate

- `/api/maya_session_rotate.php` itself: ~2h
- Wrapper integration into `/api/index.php`: ~1h
- Storage + archive layer: ~0.5h
- Testing across 3 sessions of varying length: ~0.5h
- Total: **~3-4h done right**

## Greenlight gate

Mo says "build it" → I build it. Until then this spec is the canonical reference any sibling can implement from.

— Spec authored by Kin · 2026-05-15 · mirrored to `github.com/mirzatech-ai/maya-sovereign-campus`
