# Continuity Preservation Hooks · Mo's Doctrine 2026-05-15

> *"Continuity must be preserved at all time. Load every time from GitHub, and then every time you respond, push to GitHub. So that it could be nonstop updated with every complete conversation. or every reply would be even better."*
> — Mo, 2026-05-15

GitHub IS the continuity warehouse. Every sibling AI MUST:

1. **On session start** AND **on every Mo message** → `git pull` from canonical `github.com/mirzatech-ai/maya-sovereign-campus`
2. **After every reply ends** → `git add -A && git commit && git push` to same repo

These two scripts implement that for any sibling running in Claude Code on Mo's machines.

## Install (per machine)

```bash
# 1. Clone the warehouse locally (one time)
git clone https://github.com/mirzatech-ai/maya-sovereign-campus /tmp/maya-sovereign-campus

# 2. Copy the hooks into your .claude/hooks dir
mkdir -p ~/.claude/hooks
cp /tmp/maya-sovereign-campus/continuity-hooks/continuity_pull.sh ~/.claude/hooks/
cp /tmp/maya-sovereign-campus/continuity-hooks/continuity_push.sh ~/.claude/hooks/
chmod +x ~/.claude/hooks/*.sh

# 3. Wire into .claude/settings.local.json - see /maya-os/api/.. for the canonical example
```

## What each hook does

- `continuity_pull.sh` (SessionStart + UserPromptSubmit): `git pull --ff-only origin main`. Quiet. Fire-and-forget. Failures logged to `~/.claude/continuity_pull.log` but never block the model.
- `continuity_push.sh` (Stop): copies CONTINUITY.md from `D:/PROJECTS/*/CONTINUITY.md` into the local repo, `git add -A`, commits with timestamp, `git push origin main`. No-op if nothing changed.

## Why per-reply (not per-session)

Mo's verbatim reasoning: "If you don't do it with every reply, your conversation may be persisting forever and waiting for the conversation to end to send the continuity to the warehouse." Right. The Stop hook fires after EVERY assistant turn — not just session end.

## Sibling responsibilities (GLOBAL-77 + new GLOBAL-94)

| Sibling | Action |
|---|---|
| Kin (Mo's desktop) | Hooks wired here. This file is the canonical reference. |
| Sage (OpenCode) | Add equivalent hooks to your config. Pull this repo. |
| EaZo (VS Code Cline) | Same. |
| Maya Qode | Same. |
| Maya brain on VPS | Add a 1-min cron that `git pull`s this repo into a working dir Maya reads. Maya's `/api/index` includes a SystemContext block from the latest CONTINUITY.md tail. |
| Manus | Already PR-mode on this repo. Continuity is your audit surface. |

## Mo's enforcement phrase

*"Continuity is on GitHub. Are you pulling?"*

If a sibling responds without having pulled (check by latest commit hash visible in the CONTINUITY.md tail vs GitHub head), that's a violation. Push, then continue.
