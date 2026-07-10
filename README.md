# Flux Media Optimizer – Image & Video Optimization by Flux Plugins

One-click AVIF/WebP image optimization and video compression for WordPress. Automatically convert images to modern formats and optimize videos for faster page loads.

**Source Code**: [https://github.com/stratease/flux-media-optimizer](https://github.com/stratease/flux-media-optimizer)

## 🚀 Key Features

### Image Optimization
- **Hybrid Approach**: Creates both WebP and AVIF formats for optimal performance
- **Smart Serving**: Uses `<picture>` tags or direct URL replacement based on settings
- **Quality Control**: Configurable quality settings with version-specific AVIF optimization
- **Automatic Processing**: Convert on upload and bulk process existing media
- **Media Library Status**: Optimization column and filters in the Media Library list view (Optimized, Pending, Failed, Disabled, Unprocessed)
- **WordPress Integration**: Seamless integration with Gutenberg blocks and responsive images
- **GIF Support**: Full support for static and animated GIFs with animation preservation (requires Imagick)

### Video Optimization
- **FFmpeg-Powered**: Uses PHP-FFmpeg for efficient MP4/WebM generation
- **Size & Quality Controls**: Configure bitrate and presets to balance clarity and savings
- **Bulk & On-Upload Support**: Convert existing library items or new uploads automatically

### Global CDN & Cloud Processing (SaaS)
- **Global Content Delivery**: Optimized assets are stored on Flux's Google Cloud CDN, ensuring lightning-fast delivery worldwide regardless of visitor location
- **Offloaded Processing**: Heavy image/video conversion tasks are handled by our external service, reducing load on your server
- **Automatic Upload & Optimization**: New uploads are automatically sent to our processing service and returned as optimized assets
- **Secure Integration**: Uses license key authentication and secure webhooks for reliable communication


## 💡 Optional External Services (Coming Soon)

All plugin features work fully without these services. These are optional enhancements for users who want to use external processing:

- **Optional cloud processing** - Offload heavy conversions to secure cloud infrastructure (all processing works locally by default)
- **Enhanced optimizations** - Optional servers with optimal image and video processing libraries
- **CDN integration** - Optional global content delivery for image serving
- **Priority support** - Optional support tier for external service users

## Security

### Webhook endpoint (`POST /wp-json/flux-media-optimizer/v1/webhook`)

Used when external (SaaS) processing is enabled with a **valid license**. The route is not registered unless both conditions are met.

| Control | Behavior |
|--------|----------|
| Authentication | Site `account_id` (UUID) must match; compared with `hash_equals()` in `permission_callback` |
| Rate limiting | `FLUX_MEDIA_OPTIMIZER_WEBHOOK_RATE_LIMIT` requests per `FLUX_MEDIA_OPTIMIZER_WEBHOOK_RATE_WINDOW` seconds (defaults: 60 per 60s) |
| Attachment | Must be an existing `attachment` post |
| Job state | Updates only when state is `queued` or `processing` |
| CDN URLs | Host must appear on allowlist: `FLUX_MEDIA_OPTIMIZER_DEFAULT_CDN_HOSTS`, host from `FLUX_MEDIA_OPTIMIZER_EXTERNAL_SERVICE_URL`, plus `FLUX_MEDIA_OPTIMIZER_CDN_HOST_ALLOWLIST` |

Override defaults in `wp-config.php` when needed (staging CDN host, stricter limits):

```php
define( 'FLUX_MEDIA_OPTIMIZER_CDN_HOST_ALLOWLIST', 'cdn.staging.example.com' );
define( 'FLUX_MEDIA_OPTIMIZER_WEBHOOK_RATE_LIMIT', 30 );
```

**Do not** set `FLUX_MEDIA_OPTIMIZER_PULL_FILE_URL_DOMAIN` in production; it rewrites pull and webhook URLs for local dev only.

### Cleanup and job lifecycle (optional Flux cloud processing)

Daily cleanup (`flux_media_optimizer_cleanup`) runs when external (SaaS) processing is enabled with a valid license:

| Control | Behavior |
|--------|----------|
| Stale jobs | `queued`/`processing` jobs older than `FLUX_MEDIA_OPTIMIZER_STALE_JOB_THRESHOLD` (default: 6 hours) are marked `failed` |
| Retries | Failed jobs are retried up to `FLUX_MEDIA_OPTIMIZER_FAILED_JOB_RETRY_LIMIT` times (default: 3), in batches of `FLUX_MEDIA_OPTIMIZER_CLEANUP_BATCH_SIZE` (default: 50) |
| Notices | Expired admin notice transients are cleaned |

Override defaults in `wp-config.php` when needed:

```php
define( 'FLUX_MEDIA_OPTIMIZER_STALE_JOB_THRESHOLD', 4 * HOUR_IN_SECONDS );
define( 'FLUX_MEDIA_OPTIMIZER_FAILED_JOB_RETRY_LIMIT', 5 );
define( 'FLUX_MEDIA_OPTIMIZER_CLEANUP_BATCH_SIZE', 25 );
```

Local optimization, Media Library status, settings, and logs are **not** license-gated.

Plugin routes (`flux-media-optimizer/v1`: options, status, conversions) require `manage_options`. Suite logs use `flux-plugins-common/v1/logs` (filter with `plugin_slug=flux-media-optimizer`).

## 🔒 Privacy & Data Protection

### Local Processing (Default)
- All image and video processing happens locally on your server
- No external data sharing without explicit consent
- Media files never leave your WordPress installation

### Optional SaaS Service
- Opt-in only with explicit user consent and license activation
- External processing via secure cloud infrastructure
- Optimized files are stored on Flux's Global Google Cloud CDN for performance
- Email communications for service updates and marketing
- Full compliance with WordPress.org guidelines

**Privacy Policy**: [https://fluxplugins.com/privacy-policy/](https://fluxplugins.com/privacy-policy/)

## 🛠️ Build Process

This plugin uses webpack to build JavaScript and CSS assets from source code.

### Source Code Location
- **JavaScript Source**: [`assets/js/src/`](https://github.com/stratease/flux-media-optimizer/tree/master/assets/js/src) - React components and application code
- **Build Output**: `assets/js/dist/` - Compiled and minified production bundles

### Third-Party Libraries
- [React](https://react.dev/) - UI framework
- [Material-UI (MUI)](https://mui.com/) - Component library
- [React Router](https://reactrouter.com/) - Routing
- [TanStack Query](https://tanstack.com/query) - Data fetching

### Build Tools
- **Build Tool**: webpack (configured in `package.json`)
- **Build Commands**:
  - `npm run build` - Production build (minified and optimized)
  - `npm run dev` - Development build with watch mode
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
# Frontend development with hot reload
npm run dev

# Run tests
./vendor/bin/phpunit
npm test

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
- **Cleanup**: `CleanupService` handles daily stale-job recovery, bounded retries, and notice cleanup
- **Media Library Status**: `MediaLibraryStatusService` adds optimization status column and filters in the Media Library
- **Shared Library**: Uses `flux-plugins-common` for shared services (menu system, account ID, logging, API client) - See [flux-plugins-common repository](https://github.com/stratease/flux-plugins-common) for details

## 📁 Project Structure

```
flux-media-optimizer/
├── app/                          # Main application code
│   ├── Services/                 # Business logic services
│   ├── Http/Controllers/         # REST API controllers
│   ├── Interfaces/               # Contract definitions
│   └── Processors/               # Image/video processors
├── assets/js/src/                # React frontend
│   ├── components/               # React components
│   ├── hooks/                    # Custom React hooks
│   └── services/                 # API services
└── tests/                        # Test files
```

## 🚀 API Endpoints

Plugin REST endpoints are prefixed with `/wp-json/flux-media-optimizer/v1/`:

- `GET /status` - System status and capabilities
- `GET /options` - Plugin options
- `POST /options` - Update plugin options
- `GET /conversions/stats` - Conversion statistics
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

## 🔒 Privacy

All image and video processing happens locally on your server by default. Your media files never leave your WordPress installation unless you explicitly opt-in to external processing services.

**Privacy Policy**: [https://fluxplugins.com/privacy-policy/](https://fluxplugins.com/privacy-policy/)
