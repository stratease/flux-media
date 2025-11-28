=== Flux Media Optimizer by Flux Plugins ===
Contributors: edaniels
Tags: image optimization, video compression, webp, avif, flux plugins
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 2.0.1
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Compress images to AVIF/WebP and optimize videos for 50-70% faster loads. Boost Core Web Vitals and improve SEO with automatic image and video optimization.

== Description ==

Transform your WordPress site's media performance. Flux Media Optimizer by Flux Plugins automatically converts your images to modern formats like WebP and AVIF, and optimizes your videos with MP4/WebM conversion for maximum performance.

**🚀 Key Benefits:**
* **50-70% smaller file sizes** - Dramatically reduce image and video file sizes without quality loss
* **Faster page loads** - Improve Core Web Vitals scores and user experience
* **Better SEO rankings** - Google rewards fast-loading sites with higher search rankings
* **Automatic optimization** - Works seamlessly with your existing content and themes
* **Zero configuration** - Set it and forget it - works out of the box

**✨ Smart Features:**
* **Hybrid approach** - Creates both WebP and AVIF formats for maximum browser compatibility
* **Video optimization** - Built-in MP4/WebM conversion powered by FFmpeg with configurable quality and bitrate settings
* **GIF support** - Full support for static and animated GIFs with animation preservation (requires Imagick for animated GIFs)
* **Automatic conversion** - Optimizes images and videos on upload and processes existing media
* **WordPress integration** - Works with Gutenberg blocks, galleries, responsive images, and video embeds
* **Quality control** - Adjustable compression settings for images (60-100% quality) and videos (bitrate and presets)
* **Bulk processing** - Convert thousands of existing images and videos with one click
* **Format detection** - Automatically uses the best available processor (GD or ImageMagick for images, FFmpeg for videos)

**🎯 Perfect for:**
* Bloggers and content creators who want faster sites
* E-commerce stores needing better Core Web Vitals scores
* Agencies managing multiple client sites
* Anyone serious about website performance and SEO

**💡 Pro Features (Coming Soon):**
* **Unlimited cloud processing** - Offload heavy conversions to our secure cloud infrastructure
* **Best optimizations** - Servers built with optimal image and video processing libraries to get the best results
* **CDN integration** - Global content delivery for lightning-fast image serving
* **Priority support** - Get help when you need it most

**🔒 Privacy & Security:**
* All processing happens locally on your server by default
* Your images never leave your WordPress installation unless you opt-in to external processing services
* Full compliance with WordPress.org guidelines and privacy regulations
* [View our privacy policy](https://fluxplugins.com/privacy-policy/)

**📊 Real Results:**
Users typically see:
* 50-70% reduction in image and video file sizes
* 2-4 second improvement in page load times
* 10-20 point increase in Google PageSpeed scores
* Better Core Web Vitals metrics (LCP, FID, CLS)

**🛠️ Technical Requirements:**
* PHP 8.0+ with GD or ImageMagick extension for images. Note: Some PHP/ImageMagick versions may not support AVIF file creation.
* FFmpeg for video optimization (optional but recommended)
* WordPress 6.2+
* No additional server configuration required

== Dependencies ==

This plugin uses the following third-party libraries:

**Production Dependencies:**
* **Monolog** (monolog/monolog) - Logging library for error tracking and debugging
* **PHP-FFmpeg** (php-ffmpeg/php-ffmpeg) - Video processing and conversion library

All production dependencies are included in the plugin package and do not require separate installation. Development dependencies are excluded from the production build to minimize file size.

== Build Process ==

This plugin uses webpack to build JavaScript and CSS assets from source code.

**Source Code Location:**
* JavaScript Source: `assets/js/src/` - React components and application code
* CSS Source: `assets/css/` (if exists) - Stylesheet source files
* Build Output: `assets/js/dist/` - Compiled and minified production bundles

**Build Tools:**
* Build Tool: webpack (configured in `package.json`)
* Build Commands:
  * `npm run build` - Production build (minified and optimized)
  * `npm run dev` - Development build with watch mode
  * `npm run start` - Development server with hot reload

**Building from Source:**
To build the plugin from source:

1. Install Node.js dependencies: `npm install`
2. Build production assets: `npm run build`
3. For development with hot reload: `npm run start`

The source code is available in the GitHub repository: https://github.com/stratease/flux-media

**🎨 Works with any theme** - Flux Media Optimizer integrates seamlessly with WordPress's image system, so it works with any theme without modifications.

**📱 Mobile optimized** - Smaller images mean faster mobile loading, crucial for mobile-first indexing and user experience.

**⚡ Performance focused** - Built specifically for WordPress performance optimization, helping you achieve those coveted green scores in Google PageSpeed Insights.

Ready to supercharge your site's performance? Install Flux Media Optimizer by Flux Plugins today for instant image and video optimization, and watch your Core Web Vitals improve!

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/flux-media-optimizer` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Navigate to the Flux Media Optimizer by Flux Plugins settings page to configure your optimization preferences
4. The plugin will automatically start optimizing new uploads based on your settings

== Frequently Asked Questions ==

= Does this plugin work with any WordPress theme? =

Yes, Flux Media Optimizer works with any WordPress theme. It integrates seamlessly with WordPress's image rendering system and doesn't require any theme modifications.

= What image and video formats are supported? =

The plugin supports converting JPEG, PNG, and GIF images to WebP and AVIF formats. For videos, it converts to optimized MP4 and WebM formats. It automatically detects which formats your server supports and uses the best available option. Note: Animated GIF support requires the Imagick extension - GD cannot preserve animation.

= How much space will I save? =

Most users see 50-70% reduction in image file sizes and significant savings on videos. The exact savings depend on your original media, but WebP typically saves 25-35% and AVIF can save 50-70% compared to JPEG. Video compression savings vary based on your quality and bitrate settings.

= Will this affect my site's SEO? =

Yes, in a positive way! Faster loading images improve your Core Web Vitals scores, which Google uses as a ranking factor. You'll likely see improvements in your Google PageSpeed Insights scores.

= Do I need to configure anything? =

No, Flux Media Optimizer works out of the box with sensible defaults. You can optionally adjust quality settings for images and bitrate/preset settings for videos, but the plugin will start optimizing images and videos immediately after activation.

= Is my data secure? =

Yes, by default all processing happens locally on your server. Your media files never leave your WordPress installation unless you explicitly opt-in to external processing services.


= Does this work with existing images and videos? =

Yes! Flux Media Optimizer can bulk process all your existing images and videos. Just go to the settings page and enable "Bulk Convert" to optimize your entire media library with WP Cron.

= Will this break my existing images or videos? =

No, Flux Media Optimizer creates new optimized versions while keeping your original media files as fallbacks. If anything goes wrong, your original images and videos remain untouched.

= What if my server doesn't support WebP or AVIF? =

Flux Media Optimizer automatically detects your server's capabilities and only creates formats that are supported. If your server doesn't support modern formats, the plugin will gracefully fall back to your original images.

= Can I convert animated GIFs? =

Yes! Flux Media Optimizer supports both static and animated GIFs. However, animated GIF conversion requires the Imagick PHP extension. GD cannot preserve animation, so if you only have GD available, animated GIFs will be converted but will lose their animation. The plugin will automatically use Imagick when available for animated GIFs.

== Screenshots ==

1. Modern admin interface showing system status and conversion statistics
2. Settings page with quality controls and bulk conversion options
3. Before/after comparison showing dramatic file size reductions
4. Conversion statistics displaying total space saved and performance improvements
5. Attachment details showing optimization status and file size comparisons

== Changelog ==

= 1.0.0 =
* Initial release
* Automatic WebP and AVIF image conversion
* Hybrid approach for maximum browser compatibility
* Bulk processing for existing media
* Modern React-based admin interface
* WordPress integration with Gutenberg blocks
* Quality control settings
* Conversion statistics and performance metrics
* Privacy-compliant architecture ready for Pro features

== Upgrade Notice ==

= 1.0.0 =
Initial release of Flux Media Optimizer by Flux Plugins with comprehensive media optimization features. Perfect for improving your site's Core Web Vitals and SEO performance.

== External Services ==

This plugin does not currently use any external services. All image and video processing happens locally on your server by default.

Future versions may include optional external service integrations for enhanced functionality, such as:
* **External File Processing**: Optional external file processing services that could improve image and video optimizations, speed up processing, and allow sites that do not have the required libraries installed to process media files.
* **CDN Integration**: Optional integration with CDN services for improved content delivery.

Any future external service integrations will be optional and require explicit user consent. By default, all processing will continue to happen locally on your server.

== Privacy Policy ==

Flux Media Optimizer is committed to protecting your privacy. By default, all image processing happens locally on your server - your images never leave your WordPress installation.

**View our full privacy policy**: [https://fluxplugins.com/privacy-policy/](https://fluxplugins.com/privacy-policy/)

Key points:
* Local processing by default - no external data sharing
* Email collection for marketing purposes only with opt-in consent
* Full compliance with WordPress.org guidelines and privacy regulations