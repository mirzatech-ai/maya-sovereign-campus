#!/usr/bin/env bash
# continuity_pull.sh - SessionStart + UserPromptSubmit hook
# Mo's directive 2026-05-15: "Load every time from GitHub"
# Pulls latest from the canonical continuity warehouse so Kin and all siblings
# see the freshest doctrine before responding.
# NEVER blocks Claude Code - all errors logged and swallowed.

LOG="/d/SERVER WORK/.claude/continuity_pull.log"
TS=$(date -u +%FT%TZ)

# Drain stdin so the hook protocol doesn't deadlock
cat > /dev/null 2>&1 || true

{
    echo "[$TS] continuity_pull START"
    # Pull the canonical maya-sovereign-campus repo (the "warehouse")
    if [ -d /tmp/maya-sovereign-campus/.git ]; then
        cd /tmp/maya-sovereign-campus && \
            git fetch origin main --quiet 2>&1 && \
            git pull --ff-only origin main --quiet 2>&1 && \
            echo "[$TS] pulled $(git rev-parse --short HEAD)" || \
            echo "[$TS] pull failed (network or non-FF)"
    else
        echo "[$TS] repo missing at /tmp/maya-sovereign-campus - clone once via 'git clone https://github.com/mirzatech-ai/maya-sovereign-campus /tmp/maya-sovereign-campus'"
    fi
    echo "[$TS] continuity_pull END"
} >> "$LOG" 2>&1 &

# Don't wait - fire-and-forget
exit 0
