#!/usr/bin/env bash
# continuity_push.sh - Stop hook (fires after every assistant turn completes)
# Mo's directive 2026-05-15: "every time you respond, push to GitHub so it
# could be nonstop updated with every complete conversation"
#
# Snapshots CONTINUITY.md changes + any modified files under D:/PROJECTS/
# that correspond to the canonical warehouse, copies them into the local
# git clone, commits, pushes. Swallows all errors so it never blocks Mo.

LOG="/d/SERVER WORK/.claude/continuity_push.log"
TS=$(date -u +%FT%TZ)
REPO="/tmp/maya-sovereign-campus"

# Drain stdin (Stop hook gets JSON payload we don't need)
cat > /dev/null 2>&1 || true

{
    echo "[$TS] continuity_push START"

    if [ ! -d "$REPO/.git" ]; then
        echo "[$TS] WARN: repo missing at $REPO - skipping"
        exit 0
    fi

    # Sync Maya OS continuity into the repo so it gets pushed
    if [ -f "/d/PROJECTS/maya-os/CONTINUITY.md" ]; then
        mkdir -p "$REPO/maya-os"
        cp "/d/PROJECTS/maya-os/CONTINUITY.md" "$REPO/maya-os/CONTINUITY.md" 2>/dev/null
    fi
    # Same for other domains (only if they exist in the repo)
    for d in mirzatech.ai opencrest.io ai-staffing.agency; do
        if [ -f "/d/PROJECTS/$d/CONTINUITY.md" ] && [ -d "$REPO/ports" ]; then
            cp "/d/PROJECTS/$d/CONTINUITY.md" "$REPO/ports/${d}_CONTINUITY.md" 2>/dev/null
        fi
    done

    cd "$REPO" || exit 0

    # Only commit if there's actually a change
    if git diff --quiet && git diff --cached --quiet; then
        echo "[$TS] no changes to push"
        exit 0
    fi

    git add -A 2>&1
    if git commit -m "auto: continuity sync $TS" --quiet 2>&1; then
        echo "[$TS] committed"
        if git push origin main --quiet 2>&1; then
            echo "[$TS] pushed to GitHub successfully"
        else
            echo "[$TS] push failed (network?) - changes are committed locally and will go up next time"
        fi
    else
        echo "[$TS] commit failed"
    fi

    echo "[$TS] continuity_push END"
} >> "$LOG" 2>&1 &

# Fire-and-forget so Stop returns immediately
exit 0
