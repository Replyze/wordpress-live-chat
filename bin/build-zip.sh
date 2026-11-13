#!/usr/bin/env bash
# Builds dist/replyze.zip with the replyze/ folder at its root, the layout
# WordPress expects from Plugins -> Add New Plugin -> Upload Plugin.
set -euo pipefail
cd "$(dirname "$0")/.."
rm -rf dist
mkdir -p dist
zip -r -X -q dist/replyze.zip replyze -x '*.DS_Store'
unzip -l dist/replyze.zip
