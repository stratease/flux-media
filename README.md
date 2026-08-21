# Flux Media Optimizer – Image & Video Optimization by Flux Plugins

One-click AVIF/WebP image optimization and video compression for WordPress. Automatically convert images to modern formats and optimize videos for faster page loads.

**Source Code**: [https://github.com/stratease/flux-media-optimizer](https://github.com/stratease/flux-media-optimizer)

## 🚀 Key Features

### Image Optimization
- **Modern formats**: Creates WebP and/or AVIF outputs based on Settings (both enabled by default)
- **Smart Serving**: Direct URL replacement by default; optional experimental hybrid `<picture>` serving (`image_hybrid_approach`, off by default)
- **Quality Control**: Configurable quality settings with version-specific AVIF optimization
- **Automatic Processing**: Convert on upload and bulk process existing media
- **Media Library Status**: Optimization column and filters in the Media Library list view (Optimized, Pending, Failed, Disabled, Unprocessed)
- **WordPress Integration**: Seamless integration with Gutenberg blocks and responsive images
- **GIF Support**: Full support for static and animated GIFs with animation preservation (requires Imagick)
- **HEIC/HEIF Support**: Static HEIC/HEIF (including typical iPhone stills and iOS HDR/gain-map files when Imagick can decode them; **libheif ≥ 1.18.2 recommended**). Rare animated HEIF sequences (`msf1`) convert to **animated WebP** via **FFmpeg** (`libwebp_anim`) only when WebP output is enabled; otherwise they become **static** first-frame WebP/AVIF per your format settings. AVIF for sequences is always a static first frame. **Not video or GIF.** Apple Live Photos (HEIC + paired MOV) are unsupported.

### HEIC/HEIF server requirements

| Input | Local requirement | Output |
|-------|-------------------|--------|
| Static HEIC/HEIF (incl. iOS gain maps / iPhone stills) | Imagick with HEIC/HEIF decode (`queryFormats` + per-file decode); **libheif ≥ 1.18.2 recommended** | WebP and/or AVIF per Settings |
| Animated HEIF sequences (`msf1`) | **FFmpeg** with `libwebp_anim` **and** WebP enabled in Settings | Animated WebP; static AVIF when AVIF enabled |
| Animated HEIF when WebP disabled or FFmpeg missing | Same Imagick decode as static | Static first-frame WebP/AVIF from enabled formats (no animation, no GIF/video) |
| Older / incomplete HEIC decode | Per-file decode fails | Attachment marked **Failed** with a decode error (not left Unprocessed) |
| Apple Live Photos (HEIC + MOV) | — | **Unsupported** (still HEIC may convert; MOV is not merged) |

Overview status chips expose **HEIC** (static decode) and **Animated HEIC** (FFmpeg sequence → animated WebP) separately from WebP/AVIF output badges.

Test fixtures for PHPUnit live under `tests/_support/files/` (`sample_static.heic`, `sample_animated.heif`). Runtime HEIF sequence probe ships under `assets/fixtures/heif-sequence-probe.heif` (not a test suite). Ephemeral smoke docs: [`tests/ephemeral/README.md`](tests/ephemeral/README.md). Suite layout standards: [flux-plugins-common — Plugin test surfaces and runtime fixtures](https://github.com/stratease/flux-plugins-common/blob/master/README.md#plugin-test-surfaces-and-runtime-fixtures).

### Video Optimization
- **FFmpeg-Powered**: Uses PHP-FFmpeg for efficient AV1 (MP4 container) and WebM generation
- **Size & Quality Controls**: Configure bitrate and presets to balance clarity and savings
- **Bulk & On-Upload Support**: Convert existing library items or new uploads automatically

### Global CDN & Cloud Processing (SaaS)
- **Global Content Delivery**: Optimized assets are stored on Flux's Google Cloud CDN, ensuring lightning-fast delivery worldwide regardless of visitor location
- **Offloaded Processing**: Heavy image/video conversion tasks are handled by our external service, reducing load on your server
- **Automatic Upload & Optimization**: New uploads are automatically sent to our processing service and returned as optimized assets
- **Secure Integration**: Uses license key authentication and secure webhooks for reliable communication


## Optional Flux Cloud Processing

When enabled with a valid license, heavy image and video conversions can be offloaded to Flux cloud infrastructure. Local optimization remains the default and works without any license or external service.

- **Optional cloud processing** — Offload heavy conversions to secure cloud infrastructure
- **Enhanced optimizations** — Servers with optimal image and video processing libraries
- **CDN integration** — Global content delivery for optimized assets
- **Priority support** — Support tier for external service users

See [Security](#security) and [Cleanup and job lifecycle](#cleanup-and-job-lifecycle-optional-flux-cloud-processing) for webhook and job lifecycle details.

## Security

### Webhook endpoint (`POST /wp-json/flux-media-optimizer/v1/webhook`)

Used when external (SaaS) processing is enabled with a **valid license**. The route is not registered unless both conditions are met.

| Control | Behavior |
|--------|----------|
| Authentication | Site `account_id` (UUID) must match; compared with `hash_equals()` in `permission_callback` |
| Signing | No HMAC/request signing today — protect `account_id` as a secret; rate limits and CDN host allowlist mitigate abuse |
| Rate limiting | `FLUX_MEDIA_OPTIMIZER_WEBHOOK_RATE_LIMIT` requests per `FLUX_MEDIA_OPTIMIZER_WEBHOOK_RATE_WINDOW` seconds (defaults: 60 per 60s). Non-positive limit/window values fail closed (reject) |
| Attachment | Must be an existing `attachment` post |
| Job state | Updates only when state is `queued` or `processing` |
| CDN URLs | Must use **HTTPS**. Host must appear on allowlist: `FLUX_MEDIA_OPTIMIZER_DEFAULT_CDN_HOSTS`, host from `FLUX_MEDIA_OPTIMIZER_EXTERNAL_SERVICE_URL`, plus `FLUX_MEDIA_OPTIMIZER_CDN_HOST_ALLOWLIST` |

Override defaults in `wp-config.php` when needed (staging CDN host, stricter limits):

```php
define( 'FLUX_MEDIA_OPTIMIZER_CDN_HOST_ALLOWLIST', 'cdn.staging.example.com' );
define( 'FLUX_MEDIA_OPTIMIZER_WEBHOOK_RATE_LIMIT', 30 );
```

**Do not** set `FLUX_MEDIA_OPTIMIZER_PULL_FILE_URL_DOMAIN` in production; it rewrites pull and webhook URLs for local dev only.

### Cleanup and job lifecycle (optional Flux cloud processing)

Automatic retries for **both local and cloud** failures use Action Scheduler (`flux_media_optimizer_retry_attachment`) via `ConversionRetryService`. On `flux_media_optimizer_conversion_failed`, the next retry is scheduled immediately (media-aware delay). Daily cleanup (`flux_media_optimizer_cleanup`) recovers stale cloud jobs, re-enqueues eligible failures still marked `failed`, and cleans expired admin notices.

| Control | Behavior |
|--------|----------|
| Stale jobs | `queued`/`processing` jobs older than `FLUX_MEDIA_OPTIMIZER_STALE_JOB_THRESHOLD` (default: 6 hours) are marked `failed` (cloud/external active only) |
| Retries | Failed jobs get up to `FLUX_MEDIA_OPTIMIZER_FAILED_JOB_RETRY_LIMIT` automatic retries (default: 3) after the initial failure. Delays are media-aware: **images** 1, 5, then 15 minutes; **videos** 5, 30, then 120 minutes. Submitted/deferred/completed/failed work consumes an attempt; skipped dispatch does not |
| Processor route | Each retry uses the processor currently selected by license/settings (`MediaProcessingServiceLocator`) so cloud→local fallback happens when the license disconnects |
| Orchestrator | Upload, manual Re-convert, bulk, and retry all dispatch through `ConversionOrchestrator` with explicit `completed` / `submitted` / `deferred` / `failed` / `skipped` outcomes |
| Atomic images | Image reconversion stages every requested size/format; commits files and metadata/statistics only when all succeed; otherwise rolls back staged output, preserves prior known-good files and DB state, and marks Failed |
| Manual Re-convert | Resets the retry cycle and queues a fresh conversion |
| Notices | Expired admin notice transients are cleaned |
| Batches | Cleanup enqueue scans use `FLUX_MEDIA_OPTIMIZER_CLEANUP_BATCH_SIZE` (default: 50) and paginate until an eligible batch is filled (exhausted retries cannot starve later failures) |

Override defaults in `wp-config.php` when needed:

```php
define( 'FLUX_MEDIA_OPTIMIZER_STALE_JOB_THRESHOLD', 4 * HOUR_IN_SECONDS );
define( 'FLUX_MEDIA_OPTIMIZER_FAILED_JOB_RETRY_LIMIT', 5 );
define( 'FLUX_MEDIA_OPTIMIZER_CLEANUP_BATCH_SIZE', 25 );
```

Local optimization, Media Library status, settings, and logs are **not** license-gated.

Most plugin REST routes (`flux-media-optimizer/v1`: options, status, conversions) require `manage_options`. `GET /attachments/{id}/details` requires `edit_post` on the attachment. Suite logs use `flux-plugins-common/v1/logs` (filter with `plugin_slug=flux-media-optimizer`).

External API base URL/timeout: bootstrap aligns `FLUX_MEDIA_OPTIMIZER_EXTERNAL_SERVICE_*` with `FLUX_PLUGINS_COMMON_EXTERNAL_SERVICE_*` (common wins when both are set; otherwise plugin overrides populate common).

## Design guidelines

### Brand header (settings + attachment surfaces)

Use the shared Flux rounded-square `BrandIcon` (from `flux-plugins-common`) at **28 × 28 px**, placed to the left of the product title. Do not introduce plugin-specific alternate logo treatments for admin headers. Attachment Media Library panels and the settings `PageLayout` header must share this size and layout.

### Attachment details panel

Core Media Library **classic attachment edit** mounts a React island via `AttachmentDetailsMountRenderer` on `attachment_fields_to_edit` (`show_in_edit` true, `show_in_modal` false). A compact PHP skeleton fills space until TanStack Query loads `GET /flux-media-optimizer/v1/attachments/{id}/details` (payload from `AttachmentDetailsPresenter`).

**Media modal / grid / editor media frame** do **not** use the compat `td.field` table cell. `assets/js/src/admin/attachment.js` injects the same mount markup after `.attachment-info` inside `.attachment-details` (MutationObserver), so the panel uses the full sidebar content width. Mount HTML is SSOT via `AttachmentDetailsMountRenderer::build_mount_html()`.

The panel shows media-neutral size accordion rows (images: registered sizes; videos: full size). Format columns are limited to enabled formats the active processor can produce (WebP/AVIF for images, AV1/WebM for videos). Each row compares savings against the same-size original using the smallest available output. While status is Pending (including locally deferred video work), the island polls every 15 seconds and refreshes in place without a full page reload.

Convert / Disable / Enable require `edit_post` on the attachment and use authenticated admin-ajax actions (`wp_ajax_flux_media_optimizer_*`), not REST writes. Convert / Re-convert is disabled while conversion is submitted or deferred (`processing` in the presenter payload). A CDN upsell links to `https://fluxplugins.com/buy` only when the license is **not** valid.

The attachment webpack entry (`assets/js/src/admin/attachment.js` → `assets/js/dist/attachment.bundle.js`) is **self-contained**: React, ReactDOM, MUI, Emotion, TanStack Query, theme, and attachment components are bundled. WordPress script dependencies stay an empty array because the entry imports no `@wordpress/*` packages. Production bundles are Git-tracked; source maps and `assets/js/src` are excluded from the WordPress.org zip via flux-plugins-common distribution excludes. Mounting uses idempotent DOM scan + `MutationObserver` (modal inject, classic edit PHP field, editor media modals).

**Layout density** uses CSS **container queries** on `.flux-media-optimizer-attachment-root` (`container-type: inline-size`), not MUI viewport breakpoints (`sm` / `md` / `lg` / `useMediaQuery`). Compact stacked layout (per-field labels, no table column headers, single-column variant cards) applies when the **parent container** is **≤ 480px** wide (typical media modal sidebar). Comfortable horizontal summary + multi-column cards apply above that. Panel title and Core badge use ellipsis overflow with tooltip + `aria-label` for the full string. Ephemeral check `flux-media-optimizer.attachment-details-panel` asserts modal mount is outside compat `td.field`, action controls are present, and narrow containment holds.

### Future: webhook attempt correlation

Reliable correlation of delayed cloud webhooks with a specific retry attempt requires cloud API support for a plugin-generated **attempt token** (or job generation) that the webhook echoes back. Today the webhook identifies the attachment only, so a late callback cannot be reliably distinguished from a newer retry. Plugin-only state or timestamp checks reduce overlap but cannot prove callback identity. Track as a future cloud-contract task (not implemented in 4.3.0).

## 🔒 Privacy & Data Protection

### Local Processing (Default)
- Image and video **media files** are processed locally on your server and never leave the WordPress installation unless optional cloud processing is enabled
- Compatibility checks and license validation may send limited site/plugin metadata (account ID, site URL/domain, plugin version, license key when provided) to the Flux Plugins API
- Optional newsletter subscribe form (settings UI) posts email + consent to Flux Plugins; see External services in `readme.txt`

### Optional SaaS Service
- Opt-in only with explicit user consent and license activation
- External processing via secure cloud infrastructure
- Optimized files are stored on Flux's Global Google Cloud CDN for performance
- Email communications for service updates and marketing (opt-in newsletter form)
- Full compliance with WordPress.org guidelines

**Account ID on uninstall:** Shared suite option `flux-plugins_account_id` is preserved so other Flux Suite plugins keep the same support identifier.

**Privacy Policy**: [https://fluxplugins.com/privacy-policy/](https://fluxplugins.com/privacy-policy/)

## 🛠️ Build Process

This plugin uses webpack to build JavaScript and CSS assets from source code.

### Source Code Location
- **JavaScript Source**: [`assets/js/src/`](https://github.com/stratease/flux-media-optimizer/tree/master/assets/js/src) - React components and application code
- **Build Output**: `assets/js/dist/` - Compiled and minified production bundles

### Third-Party Libraries
- **Admin bundle:** React, Material-UI (MUI), React Router, TanStack Query, and WordPress packages (`@wordpress/api-fetch`, `@wordpress/element`, `@wordpress/components`, `@wordpress/i18n`)
- **Attachment island:** Self-contained React/MUI/TanStack Query bundle (no `@wordpress/*` imports)

### Build Tools
- **Build Tool**: webpack (configured in `webpack.config.js`; npm scripts in `package.json`)
- **Build Commands**:
  - `npm run build` - Production build (minified and optimized)
  - `npm run dev` - Development build with watch mode (no HMR)
  - `npm run start` - Development server with hot reload

### Building from Source
To build the plugin from source:

1. Install Node.js dependencies:
   ```bash
   npm install
   ```

2. Build production assets:
   ```bash
   npm run build
   ```

3. For development with hot reload:
   ```bash
   npm run start
   ```
   Default webpack dev server port: **3000**. Without defining `FLUX_MEDIA_OPTIMIZER_DEV_SCRIPT_BASE` in `wp-config.php`, WordPress loads built bundles from `assets/js/dist/` even when `WP_DEBUG` and `SCRIPT_DEBUG` are on. For HMR, define the dev base in local config only (see [flux-plugins-common README — Optional local dev script base](https://github.com/stratease/flux-plugins-common#optional-local-dev-script-base-wp-config-only)).

The source code is available in the GitHub repository: [https://github.com/stratease/flux-media-optimizer](https://github.com/stratease/flux-media-optimizer)

## 🛠️ Quick Start

**Requires PHP 8.1+** and WordPress 5.8+.

### Installation
```bash
# Install PHP dependencies
composer install

# Install Node.js dependencies
npm install

# Build frontend
npm run build
```

### Development
```bash
# Frontend watch rebuild (no HMR)
npm run dev

# Frontend development server with hot reload
npm run start

# Run PHP unit tests
composer test

# Run ephemeral Playwright smoke/regression harness (requires local ephemeral-wp-test checkout)
npm run test:ephemeral:smoke

# Lint code
npm run lint
composer run lint
```

## 🏗️ Architecture

This plugin uses a modern, decoupled architecture that separates business logic from WordPress dependencies:

- **Pure Business Logic**: `ImageConverter` and `VideoConverter` are WordPress-independent
- **Provider Pattern**: `WordPressProvider` handles all WordPress integration
- **Dependency Injection**: Uses interfaces for testable, decoupled components
- **Unified Converter Interface**: Fluent API with centralized format constants
- **External Optimization**: `ExternalOptimizationProvider` manages communication with the SaaS processing service and CDN
- **Cleanup**: `CleanupService` handles daily stale-job recovery, notice cleanup, and enqueueing eligible retries
- **Conversion orchestration**: `ConversionOrchestrator` + `ConversionRequest` / `ConversionDispatchResult` unify upload, manual, bulk, and retry entry paths
- **Conversion retries**: `ConversionRetryService` owns bounded Action Scheduler retries (group `ActionSchedulerGroups::MEDIA_OPTIMIZER`) with `MediaAwareRetryDelayPolicy`
- **Atomic artifacts**: `ConversionArtifactTransaction` stages image outputs until all sizes/formats succeed
- **Attachment details**: `AttachmentDetailsPresenter` is the SSOT payload for the Media Library React island; `AttachmentDetailsMountRenderer` emits the skeleton mount; `GET /attachments/{id}/details` serves async loads; `AdminScriptUrl` resolves admin/attachment bundle URLs
- **Media Library Status**: `MediaLibraryStatusService` adds optimization status column and filters in the Media Library
- **Shared Library**: Uses `flux-plugins-common` for shared services (menu system, account ID, logging, API client) - See [flux-plugins-common repository](https://github.com/stratease/flux-plugins-common) for details

### Release packaging

Use shared `./vendor/bin/build-plugin.sh` (or the thin `bin/build-plugin.sh` wrapper that execs it). Optional `bin/plugin-dist-required-files.txt` lists required runtime artifacts for `verify-plugin-distribution.sh`. Commit production `assets/js/dist/*.bundle.js` and common runtime assets; exclude maps, webpack HTML, and `assets/js/src` from the WordPress.org zip.

## 📁 Project Structure

```
flux-media-optimizer/
├── app/                          # Main application code
│   ├── Services/                 # Business logic (converters, processors, orchestrator, retries, HEIC, attachment panel)
│   └── Http/Controllers/         # REST API controllers
├── assets/
│   ├── fixtures/                 # Runtime probes (ships in zip)
│   └── js/{src,dist}/            # React frontend
├── src/assets/common/            # Common library runtime assets
├── tests/                        # PHPUnit + ephemeral docs
└── …
```

Global test/fixture layout: [flux-plugins-common README](https://github.com/stratease/flux-plugins-common/blob/master/README.md#plugin-test-surfaces-and-runtime-fixtures).

## 🚀 API Endpoints

Plugin REST endpoints are prefixed with `/wp-json/flux-media-optimizer/v1/`:

- `GET /status` - System status and capabilities
- `GET /options` - Plugin options
- `POST /options` - Update plugin options
- `GET /conversions/stats` - Conversion statistics
- `GET /attachments/{id}/details` - Attachment optimization panel payload (requires `edit_post` on the attachment)
- `POST /webhook` - Callback endpoint for external processing service (SaaS + valid license only)

Suite logs (admin Logs screen): `GET /wp-json/flux-plugins-common/v1/logs?plugin_slug=flux-media-optimizer`

## 🔮 Future Roadmap

### Planned Enhancements
- **AI-Powered Optimization**: Machine learning-based compression
- **Advanced Analytics**: Detailed conversion metrics
- **Extended Format Support**: Additional formats via SaaS API

### Technical Improvements
- **Performance**: Further optimization and caching
- **Scalability**: Support for high-volume sites
- **Monitoring**: Enhanced logging and monitoring
- **Testing**: Comprehensive test coverage

## 🤝 Contributing

We welcome contributions! Please see our [Contributing Guidelines](CONTRIBUTING.md) for development setup, coding standards, and architecture details.

## 📄 License

GPL-2.0+ - See [LICENSE](LICENSE) file for details.

## 🆘 Support

- **Documentation**: See [Contributing Guidelines](CONTRIBUTING.md) for technical details
- **Email**: eddie@fluxplugins.com
- **Website**: https://fluxplugins.com
- **GitHub**: https://github.com/stratease/flux-media-optimizer
