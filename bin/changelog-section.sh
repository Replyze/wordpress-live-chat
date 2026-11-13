#!/usr/bin/env bash
# Prints one version's section of CHANGELOG.md (the GitHub release notes).
# Usage: bin/changelog-section.sh 1.2.1
set -euo pipefail
version="${1:?usage: bin/changelog-section.sh <version>}"
cd "$(dirname "$0")/.."
awk -v v="$version" '
  index($0, "## [" v "]") == 1 { on = 1; next }
  on && /^## \[/ { exit }
  on && /^\[[^]]+\]: / { exit }
  on { print }
' CHANGELOG.md
