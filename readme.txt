=== Flux Media Optimizer – Image & Video Optimization by Flux Plugins ===
Contributors: edaniels
Tags: media optimizer, video compression, webp, avif, cdn
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 4.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically optimize images, compress videos and deliver media via global CDN. Boost Core Web Vitals and SEO with 50-70% smaller file sizes.

== Description ==

### The Complete Media Performance Solution for WordPress

Automatically reduce image and video sizes by up to 70% and improve Core Web Vitals — no setup required.

Flux Media Optimizer is the all-in-one media optimizer plugin for WordPress – optimize images, compress videos, and deliver everything through a global CDN for lightning-fast page loads worldwide.

Transform your WordPress site's media performance with Flux Media Optimizer. Compress images, convert to next-gen formats (WebP & AVIF), optimize videos with modern formats (AV1, WebM), and serve media through a global CDN, all while maintaining the visual quality your visitors expect.

**All core features are available in the free version.** Gain additional benefits including offloaded processing, global CDN delivery, and advanced compression algorithms when you [purchase a license](https://fluxplugins.com/media-optimizer/).

### Professional-Grade Media Optimization

Flux Media Optimizer boosts your site's performance metrics and Google PageSpeed Insights scores with intelligently compressed and optimized media, including animated GIFs and supported HEIC/HEIF stills converted to WebP and AVIF. Rare animated HEIF sequences can preserve animation in WebP when supported by FFmpeg. Real-world performance improvements translate directly to better user experiences, higher engagement, and improved search rankings.

**Key Features:**

* **Modern Image Formats** – Creates WebP and/or AVIF outputs based on Settings (both enabled by default)
* **Smart Format Serving** – Direct URL replacement by default; optional experimental hybrid `<picture>` serving
* **Video Optimization** – FFmpeg-powered AV1 (MP4 container) and WebM generation with size & quality controls
* **GIF Support** – Full support for static and animated GIFs with animation preservation (Imagick required for local animated GIF conversion)
* **HEIC/HEIF Support** – Static HEIC/HEIF files convert when Imagick can decode them; libheif 1.18.2+ is recommended for modern iOS HDR/gain-map photos. Rare animated HEIF sequences become animated WebP when WebP and compatible FFmpeg support are available, with static first-frame output otherwise. Apple Live Photos (HEIC + MOV) are not supported as combined assets.
* **Automatic Processing** – Convert on upload and bulk process existing media with one click
* **Quality Control** – Configurable quality settings with version-specific AVIF optimization
* **Individual File Controls** – Disable or manually reconvert individual files for granular control
* **Media Library Status** – Optimization column and filters in the Media Library list view (Optimized, Pending, Failed, Disabled, Unprocessed)
* **Gutenberg Block Integration** – View image compression information directly in image blocks
* **WordPress Integration** – Seamless integration with galleries, responsive images, and all WordPress image functions
* **Optimize Supported Image Files** – Supports JPEG, PNG, GIF, and HEIC/HEIF sources when required server libraries are available.
* **No Limits** – Optimize all of your images and videos up to your server's capabilities
* **Global CDN Delivery (License Only)** – Optimized assets stored on Flux's Google Cloud CDN, ensuring lightning-fast delivery worldwide
* **Offloaded Processing (License Only)** – Heavy image/video conversion tasks handled by external service, reducing load on your server
* **Secure Integration** – Uses license key authentication and secure webhooks for reliable communication

**Perfect for:**

* Bloggers and content creators who want faster sites
* E-commerce stores needing better Core Web Vitals scores
* Anyone serious about website performance and SEO

Ready to supercharge your site's performance? Install Flux Media Optimizer today for instant image and video optimization.

### Next-Gen Formats – WebP & AVIF Conversion

Flux Media Optimizer automatically converts your images to modern WebP and AVIF formats, delivering superior compression and quality retention. These next-generation formats are recognized by all major performance testing tools, including Google PageSpeed Insights, as essential for optimal site performance.

**Performance benefits:** WebP lossless images average 26% smaller than PNGs, while WebP lossy images are typically 25-34% smaller than comparable JPG images. AVIF images achieve up to 60% size reduction compared to JPG or PNG formats.

**Simple setup:** Flux Media Optimizer enables easy implementation with local hosting – no CDN required. The plugin converts to WebP and/or AVIF according to Settings and serves optimized URLs to visitors, ensuring strong browser compatibility without requiring a CDN.

Flux Media Optimizer converts JPEG, PNG, GIF, and supported HEIC/HEIF sources to WebP and/or AVIF. Stop compromising on image quality or site performance – get both with Flux Media Optimizer.

### Intelligent Compression Without Quality Loss (with License)

Flux Media Optimizer uses advanced compression algorithms to reduce file sizes significantly without compromising visual quality. The plugin removes unnecessary metadata and optimizes image data, resulting in smaller files that look identical to the originals.

**Local Processing:** With local optimization, you have full control over your compression settings, allowing you to optimize according to your specific needs. Available formats depend on your server's installed libraries (GD, Imagick, FFmpeg).

**Remote Processing (License Only):** Our optimization engine analyzes each image to determine the best compression strategy, ensuring maximum file size reduction while maintaining the visual quality standards your visitors expect. All processing happens on our servers, reducing load on your server while delivering optimal results.

== Frequently Asked Questions ==

= Does this plugin work with any WordPress theme? =

Yes, Flux Media Optimizer works with any WordPress theme. It integrates seamlessly with WordPress's image rendering system and doesn't require any theme modifications. The plugin hooks into WordPress's core image functions, so it works automatically with any theme that uses standard WordPress image functions.

= Will this break my existing images or videos? =

No, Flux Media Optimizer creates new optimized versions while keeping your original media files as fallbacks. Your original images and videos remain untouched and are always available as a fallback for browsers that don't support modern formats or if you need to restore the originals.

= External services =

This plugin can optionally use the Flux Plugins API for license validation and for **optional** cloud media processing / CDN delivery. Local image and video optimization works without any external service, license, or outbound media upload.

* Service: Flux Plugins API. Default base URL: `https://api.fluxplugins.com`. Override with `FLUX_MEDIA_OPTIMIZER_EXTERNAL_SERVICE_URL` and/or `FLUX_PLUGINS_COMMON_EXTERNAL_SERVICE_URL` (common wins when both are set; otherwise the plugin override populates common). Timeout: `FLUX_MEDIA_OPTIMIZER_EXTERNAL_SERVICE_TIMEOUT` / `FLUX_PLUGINS_COMMON_EXTERNAL_SERVICE_TIMEOUT`.
* When requests occur: when a license key is activated or validated; when compatibility checks run via the shared library; and when you explicitly enable optional Flux cloud processing with a valid license (uploads and conversion jobs, webhook callbacks, CDN URLs).
* Data sent may include: license key (when provided), account ID (UUID), site URL/domain, plugin version, and media files/metadata only when optional cloud processing is enabled.
* Optional cloud processing and CDN are **opt-in**. They require enabling the setting and a valid license. You can turn them off at any time to return to local-only processing.

Optional newsletter subscription (settings screen):

* Service endpoint: `https://fluxplugins.com/wp-admin/admin-ajax.php?action=tnp&na=s`
* When: only when an administrator submits the Stay Updated form with email and privacy acceptance
* Data sent: email address and newsletter consent flag
* Privacy policy: https://fluxplugins.com/privacy-policy/
* Terms of use: https://fluxplugins.com/terms-of-service/

Privacy policy: https://fluxplugins.com/privacy-policy/

Terms of use: https://fluxplugins.com/terms-of-service/

= Does this work with existing images and videos? =

Yes! Flux Media Optimizer can bulk process all your existing images and videos. Just go to the settings page and enable bulk conversion to optimize your entire media library. However, some pages utilizing the media files may require updating, as they may have directly embedded the non-optimized format.

= Can I see which media files are optimized? =

Yes. The Media Library list view includes an Optimization column with status badges and a filter dropdown for Optimized, Pending, Failed, Disabled, and Unprocessed items. Local optimization status is always visible; cloud Pending/Failed states appear when optional Flux cloud processing is enabled.

= What is the CDN feature? =

The CDN (Content Delivery Network) feature is an optional service that stores your media files on a global network of servers. This ensures your images, videos and other media files load instantly for visitors worldwide, regardless of their location. The CDN feature requires explicit opt-in and a license key - all core functionality works locally without it.

= How much space will I save? =

Currently the original files are still stored on your system for fallback, so there is no storage benefit. It will optimize page load speeds and reduce the download size for your webpages. The optimized files are typically 50-70% smaller than the originals, which means faster page loads and better user experience.

= What if my server doesn't support WebP or AVIF? =

Flux Media Optimizer automatically detects your server's capabilities and only creates formats that are supported. If your server doesn't support modern formats, you can purchase a license to utilize the external processing and CDN service, which handles all format conversions on external servers.

= Does this work with animated GIFs? =

Yes! Flux Media Optimizer fully supports both static and animated GIFs. When Imagick is available, animated GIFs are converted while preserving their animation. The plugin automatically detects whether a GIF is animated and handles it appropriately.

= Does this work with HEIC/HEIF (including iPhone photos)? =

Yes, when server libraries can decode the source file. Static HEIC/HEIF requires Imagick with HEIC/HEIF decode support; libheif 1.18.2+ is recommended for modern iOS HDR/gain-map photos. Files convert to WebP and/or AVIF according to Settings. Rare animated HEIF sequences (`msf1`) preserve animation in WebP when WebP is enabled and compatible FFmpeg support is available; otherwise the plugin creates static first-frame output in enabled formats. AVIF output is static. Apple Live Photos (HEIC + paired MOV) are not supported as combined assets. Undecodable HEIC/HEIF files appear as Failed in Media Library.

= Will this slow down my site? =

No, Flux Media Optimizer is designed to improve your site's performance, not slow it down. Media optimization happens in the background, and the plugin uses efficient caching to minimize any impact. However, compressing images and especially videos can be CPU intensive during conversion operations. With the optional license service, all compression operations are done on our remote servers, reducing your server load. Your pages will load faster as optimized media is served efficiently.

= Can I control the quality of the optimized images? =

Yes, Flux Media Optimizer provides comprehensive quality control settings. You can adjust the quality settings for WebP and AVIF formats separately, allowing you to balance file size and image quality according to your needs.

= Does this work with WooCommerce? =

Yes, Flux Media Optimizer works with WooCommerce and automatically optimizes product images. The plugin integrates with WordPress's media library, so all images uploaded through WooCommerce are automatically optimized.

= What video formats are supported? =

Flux Media Optimizer supports video optimization with FFmpeg. You can convert videos to AV1 (stored in an MP4 container) and WebM with configurable bitrate and quality settings. The plugin processes videos in the background to reduce impact on your site's performance.

== Screenshots ==

1. See exactly how much media weight you saved.
2. Optimize the entire media library.
3. Every file shows its optimization status.
4. Local mode is free; cloud processing is optional.
5. Offload CPU-heavy video/image processing when you need it.
6. Serve optimized media globally with Flux CDN.

== Changelog ==

= 4.3.0 =
* Feature: Local HEIC/HEIF conversion with Imagick decode (libheif 1.18.2+ recommended for modern iOS photos), optional animated HEIF-to-WebP via FFmpeg, and separate HEIC capability reporting.
* Feature: Unified Action Scheduler retries for local and cloud failures (3 automatic attempts with progressive backoff), with processing routed by current license/settings and Flux logging on failures.
* Feature: Redesigned attachment details panel with async REST loading, compact skeleton placeholder, MUI Grid responsive layout that stays within the WordPress container, size accordion, format URLs, and a license-aware CDN upsell.
* Feature: Attachment details poll every 15 seconds while Pending (including locally deferred video conversions) and refresh Convert/Disable/Enable actions in place without a full page reload.
* Feature: Attachment details show only formats enabled and supported by the active media converter (WebP/AVIF for images, AV1/WebM for videos), with per-size savings badges compared to the same-size original.
* Feature: Attachment details and conversion controls use WordPress `edit_post` attachment capabilities.
* Fix: External processing activation requires a valid license; uninstall removes the correct option keys while preserving shared suite account ID; safer database table-drop SQL; external operations builder hardening.
* Fix: Image conversion publishes files and metadata/statistics together (no partial meta on multi-size failure); skipped retries no longer consume the retry budget; plugin/common external API URL constants stay aligned.

= 4.2.0 =
* Feature: Media Library shows optimization status and filter options (Optimized, Pending, Failed, Disabled, Unprocessed). Conversion stats API now reports failed external job counts. New constants added: FLUX_MEDIA_OPTIMIZER_STALE_JOB_THRESHOLD, FLUX_MEDIA_OPTIMIZER_FAILED_JOB_RETRY_LIMIT, FLUX_MEDIA_OPTIMIZER_CLEANUP_BATCH_SIZE.
* Feature: Daily cleanup cron recovers stale external jobs, automatically retries failed jobs with a set limit, and cleans expired admin notices.

= 4.1.6 =
* Security: Harden external webhook endpoint (account ID verification, job-state checks, CDN host allowlist, rate limiting).
* Security: Register webhook route only when external service is enabled and license is valid.
* Fix: Remove duplicate admin AJAX handler registration for attachment actions.
* Security: Avoid exposing raw exception messages in plugin REST error responses.
* Removed legacy plugin logs REST API; suite logs use flux-plugins-common.

= 4.1.5 =
* Guard against php 8.0 - we support php 8.1>.
* Removed some unused code and minor cleanup.
* Tested up to WordPress 7.0.

= 4.1.4 =
* Updating dependency to be compatible with php 8.

= 4.1.2 =
* Updated build scripts and added link on settings page.


== Upgrade Notice ==

= 4.3.0 =
Major update: HEIC/HEIF conversion, unified Action Scheduler retries, and a redesigned async attachment details panel with responsive layout, 15-second Pending polling, conditional format columns, video support, and per-size savings.

= 4.2.0 =
Adds Media Library optimization status visibility and daily cleanup for stale external jobs with bounded retries. Local features remain fully available without a license.

= 4.0.0 =
Major update with improved bulk optimization processing, fixed Action Scheduler bulk operations, and core system decoupling for future plugin integrations. Bulk optimization issues have been resolved for more reliable processing of existing media libraries.

= 3.0.0 =
Major update with optional CDN integration, enhanced video optimization, and improved architecture. All existing functionality continues to work as before. CDN features require explicit opt-in.

= 1.0.0 =
Initial release of Flux Media Optimizer by Flux Plugins with comprehensive media optimization features. Perfect for improving your site's Core Web Vitals and SEO performance.

== Privacy ==

**Default Behavior:**
By default, all image and video processing happens locally on your server. Your media files never leave your WordPress installation unless you explicitly opt-in to external processing services.

**Optional External Service:**
This plugin includes an optional external service integration that provides:
* **External File Processing**: Offloads heavy image and video conversion tasks to external servers, reducing load on your server
* **CDN Integration**: Stores all media files on a global CDN for faster delivery worldwide. Images and videos are processed and optimized, while other file types (PDFs, documents, etc.) are stored directly for CDN delivery

**What Data is Sent:**
When the external service is enabled (requires explicit user activation and a license key), the following data is sent to the external service:
* All media files (images, videos, PDFs, documents, etc.) that you upload
* Images and videos are processed and optimized; other file types are stored directly on the CDN
* Attachment metadata (file names, sizes, formats)
* License key for authentication
* Account ID (UUID) for service identification

**When Data is Sent:**
Data is only sent when:
* The external service is explicitly enabled by the user in plugin settings
* A valid license key is provided and activated
* Media files are uploaded or conversion is requested

**Service Provider:**
The external service is provided by Flux Plugins:
* **Terms of Service**: https://fluxplugins.com/terms-of-service/
* **Privacy Policy**: https://fluxplugins.com/privacy-policy/

**Important Notes:**
* External service is completely optional - all core functionality works locally without it
* External service requires explicit user consent and license activation
* You can disable external service at any time to return to local-only processing
* By default, the plugin uses local processing only

== Privacy Policy ==

Flux Media Optimizer is committed to protecting your privacy. By default, all image and video processing happens locally on your server - your media files never leave your WordPress installation.

**View our full privacy policy**: [https://fluxplugins.com/privacy-policy/](https://fluxplugins.com/privacy-policy/)

**Key points:**
* Local processing by default - no external data sharing
* Email collection for marketing purposes only with opt-in consent
* Account ID (UUID) generation: The plugin generates a unique identifier (UUID) locally on your server for service identification purposes. This UUID is stored only in your WordPress database and is used to match webhook requests and license validation. The UUID is NOT used for user tracking or analytics. On uninstall, plugin-specific options are removed while the shared suite account ID (`flux-plugins_account_id`) is preserved for other Flux Suite plugins. The UUID may be transmitted during license activation/validation, compatibility checks, and when optional cloud processing is enabled.
* Full compliance with WordPress.org guidelines and privacy regulations
