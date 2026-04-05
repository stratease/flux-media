=== Flux Media Optimizer – Image & Video Optimization by Flux Plugins ===
Contributors: edaniels
Tags: media optimizer, video compression, webp, avif, cdn
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 4.1.5
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

Flux Media Optimizer boosts your site's performance metrics and Google PageSpeed Insights scores with intelligently compressed and optimized media. Real-world performance improvements translate directly to better user experiences, higher engagement, and improved search rankings.

**Key Features:**

* **Hybrid Image Optimization** – Automatically creates both WebP and AVIF formats for optimal performance and browser compatibility
* **Smart Format Serving** – Uses `<picture>` tags or direct URL replacement based on settings for maximum compatibility
* **Video Optimization** – FFmpeg-powered MP4/WebM generation with size & quality controls
* **GIF Support** – Full support for static and animated GIFs with animation preservation (Imagick required for local animated GIF conversion)
* **Automatic Processing** – Convert on upload and bulk process existing media with one click
* **Quality Control** – Configurable quality settings with version-specific AVIF optimization
* **Individual File Controls** – Disable or manually reconvert individual files for granular control
* **Gutenberg Block Integration** – View image compression information directly in image blocks
* **WordPress Integration** – Seamless integration with galleries, responsive images, and all WordPress image functions
* **Optimize All Image Files** – Supports optimization for PNG, JPEG, and GIF files
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

**Simple setup:** Flux Media Optimizer's hybrid approach enables easy implementation with local hosting – no CDN required. The plugin automatically serves WebP or AVIF images to supported browsers while maintaining fallbacks for older browsers, ensuring maximum compatibility.

Flux Media Optimizer supports next-gen conversion for all image formats, including JPEG, PNG and GIF to WebP/AVIF. Stop compromising on image quality or site performance – get both with Flux Media Optimizer.

### Intelligent Compression Without Quality Loss (with License)

Flux Media Optimizer uses advanced compression algorithms to reduce file sizes significantly without compromising visual quality. The plugin removes unnecessary metadata and optimizes image data, resulting in smaller files that look identical to the originals.

**Local Processing:** With local optimization, you have full control over your compression settings, allowing you to optimize according to your specific needs. Available formats depend on your server's installed libraries (GD, Imagick, FFmpeg).

**Remote Processing (License Only):** Our optimization engine analyzes each image to determine the best compression strategy, ensuring maximum file size reduction while maintaining the visual quality standards your visitors expect. All processing happens on our servers, reducing load on your server while delivering optimal results.

== Frequently Asked Questions ==

= Does this plugin work with any WordPress theme? =

Yes, Flux Media Optimizer works with any WordPress theme. It integrates seamlessly with WordPress's image rendering system and doesn't require any theme modifications. The plugin hooks into WordPress's core image functions, so it works automatically with any theme that uses standard WordPress image functions.

= Will this break my existing images or videos? =

No, Flux Media Optimizer creates new optimized versions while keeping your original media files as fallbacks. Your original images and videos remain untouched and are always available as a fallback for browsers that don't support modern formats or if you need to restore the originals.

= Does this work with existing images and videos? =

Yes! Flux Media Optimizer can bulk process all your existing images and videos. Just go to the settings page and enable bulk conversion to optimize your entire media library. However, some pages utilizing the media files may require updating, as they may have directly embedded the non-optimized format.

= What is the CDN feature? =

The CDN (Content Delivery Network) feature is an optional service that stores your media files on a global network of servers. This ensures your images, videos and other media files load instantly for visitors worldwide, regardless of their location. The CDN feature requires explicit opt-in and a license key - all core functionality works locally without it.

= How much space will I save? =

Currently the original files are still stored on your system for fallback, so there is no storage benefit. It will optimize page load speeds and reduce the download size for your webpages. The optimized files are typically 50-70% smaller than the originals, which means faster page loads and better user experience.

= What if my server doesn't support WebP or AVIF? =

Flux Media Optimizer automatically detects your server's capabilities and only creates formats that are supported. If your server doesn't support modern formats, you can purchase a license to utilize the external processing and CDN service, which handles all format conversions on external servers.

= Does this work with animated GIFs? =

Yes! Flux Media Optimizer fully supports both static and animated GIFs. When Imagick is available, animated GIFs are converted while preserving their animation. The plugin automatically detects whether a GIF is animated and handles it appropriately.

= Will this slow down my site? =

No, Flux Media Optimizer is designed to improve your site's performance, not slow it down. Media optimization happens in the background, and the plugin uses efficient caching to minimize any impact. However, compressing images and especially videos can be CPU intensive during conversion operations. With the optional license service, all compression operations are done on our remote servers, reducing your server load. Your pages will load faster as optimized media is served efficiently.

= Can I control the quality of the optimized images? =

Yes, Flux Media Optimizer provides comprehensive quality control settings. You can adjust the quality settings for WebP and AVIF formats separately, allowing you to balance file size and image quality according to your needs.

= Does this work with WooCommerce? =

Yes, Flux Media Optimizer works with WooCommerce and automatically optimizes product images. The plugin integrates with WordPress's media library, so all images uploaded through WooCommerce are automatically optimized.

= What video formats are supported? =

Flux Media Optimizer supports video optimization with FFmpeg. You can convert videos to MP4 and WebM formats with configurable bitrate and quality settings. The plugin processes videos in the background to reduce impact on your site's performance.

== Screenshots ==

1. Modern admin interface showing system status and conversion statistics
2. Settings page with various settings and quality controls
3. Attachment details showing optimization status and file size comparisons

== Changelog ==

= 4.1.5 =
* Guard against php 8.0 - we support php 8.1>.
* Removed some unused code and minor cleanup.

= 4.1.4 =
* Updating dependency to be compatible with php 8.

= 4.1.2 =
* Updated build scripts and added link on settings page.


== Upgrade Notice ==

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
* Account ID (UUID) generation: The plugin generates a unique identifier (UUID) locally on your server for service identification purposes. This UUID is stored only in your WordPress database and is used to match webhook requests and license validation when you explicitly enable external services. The UUID is NOT used for user tracking or analytics. It is automatically removed when you uninstall the plugin. The UUID is only transmitted to external services when you explicitly enable external processing AND provide a license key.
* Full compliance with WordPress.org guidelines and privacy regulations
