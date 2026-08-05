@echo off
REM Start a_bogus signing server in background, then launch PHP dev server
set A_BOGUS_PORT=%A_BOGUS_PORT%
if "%A_BOGUS_PORT%"=="" set A_BOGUS_PORT=9876

set SCRIPT_DIR=%~dp0
start /B node "%SCRIPT_DIR%scripts\a_bogus_server.js"
echo a_bogus server started on port %A_BOGUS_PORT%

php -S localhost:8000 -t "%SCRIPT_DIR%public"
