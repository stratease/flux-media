#!/usr/bin/env bash
set -euo pipefail

PROJECT_SLUG="flux-media-optimizer"
SUITE="${1:-smoke}"
ENV_PROFILE="${2:-sandbox-clean}"
HARNESS_DIR="${FLUX_EPHEMERAL_HARNESS_DIR:-/home/edaniels/repos/wp-local/ephemeral-wp-test}"
LOG_ROOT="/home/edaniels/repos/logs/flux-ephemeral/${PROJECT_SLUG}"

if [[ ! -x "$HARNESS_DIR/scripts/test-project.sh" ]]; then
  echo "Ephemeral harness missing: $HARNESS_DIR/scripts/test-project.sh" >&2
  exit 1
fi

echo "==> Running ephemeral test"
echo "Project: $PROJECT_SLUG"
echo "Suite: $SUITE"
echo "Profile: $ENV_PROFILE"
echo "Harness: $HARNESS_DIR"

set +e
cd "$HARNESS_DIR"
bash scripts/test-project.sh "$PROJECT_SLUG" "$SUITE" "$ENV_PROFILE"
HARNESS_EXIT=$?
set -e

LATEST_RESULT="$(ls -1dt "$LOG_ROOT"/*_${PROJECT_SLUG}_${SUITE}_${ENV_PROFILE}/result.json 2>/dev/null | head -n1 || true)"

if [[ -n "$LATEST_RESULT" && -f "$LATEST_RESULT" ]]; then
  echo
  echo "==> Result summary"
  node - "$LATEST_RESULT" <<'NODE'
const fs = require('fs');
const file = process.argv[2];
const j = JSON.parse(fs.readFileSync(file, 'utf8'));
const checks = Array.isArray(j.smokeRoutesChecked) ? j.smokeRoutesChecked : [];
const passed = checks.filter(r => r.status === 'passed').length;
const failed = checks.filter(r => r.status === 'failed').length;
console.log(`Run ID: ${j.runId}`);
console.log(`Status: ${String(j.status || 'unknown').toUpperCase()}`);
console.log(`Suite: ${j.suiteLevel || j.suite || 'n/a'}`);
console.log(`Checks: ${checks.length} total (${passed} passed, ${failed} failed)`);
if (j.failureSummary) console.log(`Failure summary: ${j.failureSummary}`);
if (j.artifactPaths?.root) console.log(`Artifacts: ${j.artifactPaths.root}`);
NODE
  echo "Result JSON: $LATEST_RESULT"
else
  echo "No result.json found for suite '$SUITE' profile '$ENV_PROFILE' under $LOG_ROOT" >&2
fi

exit "$HARNESS_EXIT"
