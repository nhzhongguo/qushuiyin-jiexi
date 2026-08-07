#!/bin/sh
# Start a_bogus signing server in background, then launch PHP dev server
A_BOGUS_PORT=${A_BOGUS_PORT:-9876}
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

nohup node "$SCRIPT_DIR/scripts/a_bogus_server.js" > /dev/null 2>&1 &
A_BOGUS_PID=$!
echo "a_bogus server started (PID: $A_BOGUS_PID, port: $A_BOGUS_PORT)"

# Trap to clean up on exit
trap "kill $A_BOGUS_PID 2>/dev/null; exit" INT TERM EXIT

php -S localhost:8000 -t "$SCRIPT_DIR/public" "$SCRIPT_DIR/public/router.php"
