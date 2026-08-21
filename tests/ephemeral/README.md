# Ephemeral harness (isolated Docker)

Ephemeral checks validate the **built/shipped** plugin in `ephemeral-wp-test` containers — not the site `docker-compose.yml` / local DB.

Suite layout standards: [flux-plugins-common — Plugin test surfaces and runtime fixtures](https://github.com/stratease/flux-plugins-common/blob/master/README.md#plugin-test-surfaces-and-runtime-fixtures).

Registry: `task-dashboard/config/flux-plugin-test.registry.json` → `plugins.flux-media-optimizer.testSuites`.

## Smoke checks

| Check ID | Purpose |
|----------|---------|
| `flux-media-optimizer.admin-shell` | Settings Overview shell loads |
| `flux-media-optimizer.heic-upload` | HEIC upload when Imagick libheif available (skips cleanly if not) |
| `flux-media-optimizer.attachment-details-panel` | Upload PNG → Media Library modal + classic edit → attachment island (`data-flux-media-attachment-*`) |

## Regression checks

| Check ID | Purpose |
|----------|---------|
| `flux-media-optimizer.plugin-active` | Plugin remains active after packaging install |
| `flux-media-optimizer.admin-shell` | Settings Overview shell loads |
| `flux-media-optimizer.attachment-details-panel` | Attachment details island happy path |
| `flux-media-optimizer.attachment-details-video` | Video attachment details panel when a video fixture is available |

Implemented in `ephemeral-wp-test/scripts/suite-checks.cjs`. Playwright stays user-path only (no direct REST for the panel happy path).

## Fixtures

| Path | Role |
|------|------|
| `tests/_support/files/sample_static.heic` | Plugin test sample (keep in sync with harness) |
| `ephemeral-wp-test/fixtures/flux-media-optimizer/sample_static.heic` | Harness HEIC copy |
| `ephemeral-wp-test/fixtures/flux-media-optimizer/sample_static.png` | Attachment panel smoke |
| `assets/fixtures/heif-sequence-probe.heif` | **Runtime** probe (ships in zip) — not an ephemeral fixture |

## Environment

- `FMO_HEIC_FIXTURE_PATH` — optional HEIC path inside Playwright container (default: `/workspace/fixtures/flux-media-optimizer/sample_static.heic`)
- `FMO_ATTACHMENT_FIXTURE_PATH` — optional PNG path (default: `/workspace/fixtures/flux-media-optimizer/sample_static.png`)
- `FMO_VIDEO_FIXTURE_PATH` — optional video path for `attachment-details-video` (default harness fixture when present)

When `/wp-json/flux-media-optimizer/v1/status` reports `heic_support: false`, the HEIC check exits without failure.

## Run locally

From the plugin directory:

```bash
npm run test:ephemeral:smoke
npm run test:ephemeral:regression
```
