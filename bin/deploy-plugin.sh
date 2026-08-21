#!/usr/bin/env bash
# Thin wrapper — SSOT is flux-plugins-common via Composer bin.
set -euo pipefail
PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PLUGIN_DIR"
if [[ ! -x ./vendor/bin/deploy-plugin.sh ]]; then
	echo "Missing ./vendor/bin/deploy-plugin.sh — run composer install (and fix-bin-wrappers)." >&2
	exit 1
fi
exec ./vendor/bin/deploy-plugin.sh "$@"
