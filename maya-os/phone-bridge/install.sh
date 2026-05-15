#!/data/data/com.termux/files/usr/bin/bash
# Maya Phone Bridge · one-shot installer for Termux on Mo's Samsung Fold 5
# ============================================================================
# Run in Termux:
#   bash <(curl -fsSL https://iamsuperio.cloud/phone-bridge/install.sh)
#
# What this does:
#   1. pkg update + install python, termux-api, openssh, curl
#   2. pip install fastapi + uvicorn
#   3. termux-setup-storage (grants ~/storage/shared symlink with user dialog)
#   4. Download server.py to ~/maya-bridge/
#   5. Create maya-bridge launcher in $PREFIX/bin/maya-bridge
#   6. Auto-start the bridge on Termux boot (via ~/.termux/boot/start-maya-bridge)
#   7. Print the bridge token for Mo to paste into Maya OS
set -euo pipefail

echo "┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "┃ Maya Phone Bridge · Phase 1 installer"
echo "┃ This will let Maya OS see your phone's files + run shell on it"
echo "┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# 1. Termux packages
echo ""
echo "→ Updating Termux packages..."
pkg update -y && pkg upgrade -y
pkg install -y python termux-api openssh curl

# 2. Python packages
echo ""
echo "→ Installing Python deps (fastapi + uvicorn)..."
pip install --upgrade pip
pip install fastapi uvicorn

# 3. Storage permission · prompts user
echo ""
echo "→ Granting storage access (a dialog will pop up · tap ALLOW)..."
termux-setup-storage || true
sleep 2

# 4. Download server
BRIDGE_DIR="$HOME/maya-bridge"
mkdir -p "$BRIDGE_DIR"
echo ""
echo "→ Downloading server.py to $BRIDGE_DIR ..."
curl -fsSL "https://iamsuperio.cloud/phone-bridge/server.py" -o "$BRIDGE_DIR/server.py"
chmod +x "$BRIDGE_DIR/server.py"

# 5. Launcher
LAUNCHER="$PREFIX/bin/maya-bridge"
echo ""
echo "→ Installing launcher at $LAUNCHER ..."
cat > "$LAUNCHER" <<'EOF'
#!/data/data/com.termux/files/usr/bin/bash
exec python "$HOME/maya-bridge/server.py" "$@"
EOF
chmod +x "$LAUNCHER"

# 6. Boot autostart (Termux:Boot must be installed separately from F-Droid for this to fire)
BOOT_DIR="$HOME/.termux/boot"
mkdir -p "$BOOT_DIR"
cat > "$BOOT_DIR/start-maya-bridge" <<'EOF'
#!/data/data/com.termux/files/usr/bin/bash
termux-wake-lock
exec python "$HOME/maya-bridge/server.py" >> "$HOME/.maya_bridge.log" 2>&1
EOF
chmod +x "$BOOT_DIR/start-maya-bridge"

# 7. First-run · prime the token + show it
echo ""
echo "→ Generating bridge token..."
python -c "
import os, secrets, pathlib
p = pathlib.Path(os.path.expanduser('~/.maya_bridge_token'))
if not p.exists():
    p.write_text(secrets.token_urlsafe(48))
    p.chmod(0o600)
print(p.read_text().strip())
" > /tmp/_maya_token.txt
BRIDGE_TOKEN=$(cat /tmp/_maya_token.txt)
rm -f /tmp/_maya_token.txt

echo ""
echo "→ Auto-registering this device with Maya OS..."
DEVICE_NAME=$(hostname 2>/dev/null || echo "unknown")
case "$(uname -s)" in
    *)   PLATFORM="termux";;
esac
if [ -n "${OSTYPE:-}" ]; then
    case "$OSTYPE" in
        darwin*) PLATFORM="macos";;
        linux*)  [ -n "${TERMUX_VERSION:-}" ] && PLATFORM="termux" || PLATFORM="linux";;
    esac
fi
# Read PIN from env or prompt once
PIN="${MAYA_COMMANDER_PIN:-}"
if [ -z "$PIN" ]; then
    echo ""
    echo "  Commander PIN required ONCE to register this device with Maya OS."
    echo "  Set MAYA_COMMANDER_PIN env var to skip this prompt on future installs."
    echo ""
    printf "  Commander PIN: "
    read -r PIN
fi

DEFAULT_URL="http://127.0.0.1:8765"
REG_PAYLOAD=$(cat <<JSON
{"action":"register","pin":"$PIN","device_name":"$DEVICE_NAME","platform":"$PLATFORM","url":"$DEFAULT_URL","token":"$BRIDGE_TOKEN","capabilities":{"files":true,"shell":true,"ssh":true,"termux_api":$([ "$PLATFORM" = "termux" ] && echo true || echo false),"clipboard":true,"open":true,"notify":true}}
JSON
)
REG_RESPONSE=$(curl -s -X POST "https://iamsuperio.cloud/api/maya_devices" \
    -H 'Content-Type: application/json' -d "$REG_PAYLOAD" --max-time 15 2>&1)
if echo "$REG_RESPONSE" | grep -q '"ok":true'; then
    REG_ID=$(echo "$REG_RESPONSE" | grep -o '"id":"[^"]*"' | head -1 | cut -d'"' -f4)
    REGISTERED=1
else
    REGISTERED=0
fi

echo ""
echo "┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "┃ ✓ INSTALLED"
echo "┃"
if [ "$REGISTERED" = "1" ]; then
echo "┃ ✓ AUTO-REGISTERED with Maya OS as device: $DEVICE_NAME ($PLATFORM)"
echo "┃   device id: $REG_ID"
echo "┃   no manual setup needed in Maya OS · just open + go"
else
echo "┃ ✗ Auto-registration failed: $REG_RESPONSE"
echo "┃   (manual fallback: paste the token below into Maya OS)"
echo "┃   token: $BRIDGE_TOKEN"
fi
echo "┃"
echo "┃ Start the bridge now:    maya-bridge"
echo "┃ Or expose to LAN:        maya-bridge --bind 0.0.0.0"
echo "┃ Log file:                $HOME/.maya_bridge.log"
echo "┃"
echo "┃ It auto-starts when Termux boots (if Termux:Boot is installed)."
echo "┃ Get Termux:Boot from F-Droid for that."
echo "┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "Type 'maya-bridge' to start it now."
