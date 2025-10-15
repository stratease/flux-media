# Flux Media

Advanced image and video optimization plugin for WordPress. Converts images to WebP/AVIF and videos to AV1/WebM with high-quality settings, featuring a modern React-based admin interface with hash routing and comprehensive API integration.

**Source Code**: [https://github.com/stratease/flux-media](https://github.com/stratease/flux-media)

## 🚀 Features

### Image Optimization
- **Hybrid Approach**: Creates both WebP and AVIF formats for optimal performance
- **WebP Conversion**: Convert JPEG/PNG images to WebP format
- **AVIF Conversion**: Convert images to next-generation AVIF format
- **Smart Serving**: Serves AVIF where supported, WebP as fallback
- **Quality Control**: Configurable quality settings (60-100%)
- **Automatic Processing**: Convert on upload and bulk process existing media
- **Format Detection**: Automatically detect and use GD or Imagick
- **Smart Fallbacks**: Graceful degradation when formats aren't supported

### Video Optimization
- **AV1 Encoding**: Convert MP4 videos to AV1 format using libaom-av1
- **WebM Encoding**: Convert videos to WebM format using libvpx-vp9
- **High Quality**: Industry-standard encoding settings with CRF control
- **PHP-FFmpeg Integration**: Uses PHP-FFmpeg library for robust video processing
- **Batch Processing**: Queue and process multiple videos
- **Format Support**: Convert from various formats (MP4, AVI, MOV, etc.) to AV1/WebM

### Freemium Model
- **Free Tier**: 100 image conversions + 20 video conversions per month
- **Auto-Queue**: Existing media automatically queued for next month
- **Quota Tracking**: Real-time quota monitoring and progress display
- **Upgrade Path**: Clear upgrade options for unlimited conversions

### Modern Admin Interface
- **React Router**: Hash-based routing with Link components for seamless navigation
- **Material-UI**: Professional, responsive design system with Grid layout
- **React Query**: Efficient data fetching with caching and background updates
- **Auto-Save**: Real-time settings saving with visual feedback
- **Skeleton Loading**: Professional loading states for all pages
- **WordPress Integration**: Native WordPress authentication and API integration
- **WordPress i18n**: Full internationalization support with translation functions
- **Real-time Updates**: Live system status and conversion progress
- **Debug Mode**: Enhanced error reporting and exception output in development

### Public-Facing Features
- **Automatic Image Optimization**: Seamlessly serves WebP/AVIF formats to all visitors
- **WordPress Integration**: Hooks into WordPress image rendering system
- **Hybrid Approach**: Uses `<picture>` tags for optimal browser support
- **Responsive Images**: Full support for WordPress responsive image features
- **Attribute Preservation**: Maintains all WordPress image attributes and functionality
- **Progressive Enhancement**: Graceful fallback to original images when needed
- **Zero Configuration**: Works automatically without theme or content changes

## 🏗️ Architecture

### Frontend Architecture
- **React 18**: Modern functional components with hooks
- **React Router**: Hash-based client-side routing with Link components (`#/overview`, `#/settings`, etc.)
- **Material-UI**: Component library with Grid system for responsive layouts
- **TanStack Query**: Server state management, caching, and background updates
- **WordPress apiFetch**: Native WordPress API integration
- **WordPress i18n**: Full internationalization with `__()`, `_x()`, and `_n()` functions
- **Babel**: JavaScript transpilation for modern ES6+ features
- **Path Mapping**: `@flux-media` alias for clean imports
- **Skeleton Loading**: Professional loading states using MUI Skeleton components
- **Auto-Save Context**: Global state management for form auto-saving
- **Grid Layout**: MUI Grid components replacing explicit flex styles for consistency

### Backend Architecture
- **PHP 7.4+**: Modern PHP with type hints and OOP
- **WordPress REST API**: Custom endpoints with authentication and debug mode
- **Dependency Injection**: Container-based service management
- **Service Layer**: Separated business logic from WordPress hooks
- **Database Abstraction**: WordPress database layer with custom tables
- **Debug Mode**: Enhanced error reporting with exception output in development
- **Data Sanitization**: Comprehensive input validation and sanitization
- **PHP-FFmpeg**: Professional video processing library for AV1/WebM conversion

### Component Architecture
- **Smart/Dumb Components**: Clear separation of data and presentation
- **React Query Hooks**: Encapsulated data fetching with caching and error handling
- **Skeleton Components**: Professional loading states for all pages
- **Auto-Save System**: Context-based form auto-saving with visual feedback
- **Error Boundaries**: Comprehensive error handling
- **Container Pattern**: Data fetching containers with presentation components
- **React Router Links**: Proper navigation using Link components for accessibility
- **MUI Grid System**: Consistent responsive layouts using Grid components
- **WordPress i18n**: Internationalized text throughout all components

## 📋 System Requirements

### Server Requirements
- **PHP 7.4+** with GD or Imagick extension
- **WordPress 5.0+**
- **FFmpeg** with the following codecs:
  - `libaom-av1` for AV1 encoding
  - `libvpx-vp9` for WebM encoding
  - `libopus` for audio encoding
- **Memory**: Minimum 256MB PHP memory limit
- **Storage**: Sufficient disk space for converted media
- **Composer**: For PHP dependency management

### Development Requirements
- **Node.js 16+** (for building React frontend)
- **npm or yarn**
- **Babel**: JavaScript transpilation with module resolver
- **Webpack**: Asset bundling and optimization
- **WordPress i18n**: For internationalization support
- **ESLint**: Code linting and formatting

## 📁 Project Structure

```
flux-media/
├── assets/
│   └── js/
│       ├── src/
│       │   ├── App.js                    # Main React Router app with hash routing
│       │   ├── admin/
│       │   │   └── index.js             # Admin entry point
│       │   ├── components/
│       │   │   ├── common/              # Reusable UI components
│       │   │   │   ├── PageSkeleton.js  # Skeleton loading components
│       │   │   │   ├── FluxMediaIcon.js # Brand icon component
│       │   │   │   ├── Breadcrumbs.js   # Navigation breadcrumbs
│       │   │   │   └── LoadingSpinner.js # Loading spinner component
│       │   │   ├── features/            # Feature-specific components
│       │   │   │   ├── SystemStatusCard.js # System status display
│       │   │   │   ├── QuotaProgressCard.js # Quota progress display
│       │   │   │   └── ConversionStatsCard.js # Conversion statistics
│       │   │   ├── pages/               # Page components
│       │   │   │   ├── OverviewPage.js  # Overview page
│       │   │   │   ├── SettingsPage.js  # Settings page
│       │   │   │   └── LogsPage.js      # Logs page with pagination
│       │   │   └── index.js             # Barrel exports
│       │   ├── hooks/                   # React Query hooks
│       │   │   ├── useSystemStatus.js   # System status hook
│       │   │   ├── useOptions.js        # Options management hook
│       │   │   ├── useConversions.js    # Conversion operations hooks
│       │   │   ├── useAutoSaveForm.js   # Auto-save form hook
│       │   │   ├── useQuotaProgress.js  # Quota progress hook
│       │   │   ├── useLogs.js           # Logs hook with pagination
│       │   │   └── useCleanup.js        # Cleanup operations hook
│       │   ├── contexts/
│       │   │   └── AutoSaveContext.js   # Auto-save state management
│       │   ├── services/
│       │   │   └── api.js              # WordPress apiFetch service
│       │   ├── theme/
│       │   │   └── index.js            # MUI theme configuration
│       │   └── utils/                  # Utility functions
│       └── dist/                       # Built assets
├── src/
│   ├── Core/
│   │   ├── Plugin.php                  # Main plugin class
│   │   ├── Container.php               # Dependency injection
│   │   ├── Database.php                # Database management
│   │   └── Options.php                 # Options management
│   ├── Services/
│   │   ├── ImageConverter.php          # Image conversion service with hybrid approach
│   │   ├── VideoConverter.php          # Video conversion service
│   │   ├── ConversionTracker.php       # Conversion tracking
│   │   └── QuotaManager.php            # Freemium quota management
│   ├── Converters/                     # Converter interface implementations (integrated into Services)
│   ├── Processors/
│   │   ├── GDProcessor.php             # GD image processor
│   │   ├── ImagickProcessor.php        # Imagick processor
│   │   └── FFmpegProcessor.php         # FFmpeg video processor
│   ├── Interfaces/
│   │   ├── Converter.php               # Universal converter interface
│   │   ├── ImageProcessorInterface.php # Image processor contract
│   │   └── VideoProcessorInterface.php # Video processor contract
│   ├── Models/
│   │   └── ConversionRecord.php        # Conversion data model
│   ├── Admin/
│   │   └── Admin.php                   # WordPress admin integration
│   ├── Public/
│   │   └── ImageRenderer.php           # Public-facing image rendering service
│   ├── Api/
│   │   ├── RestApiManager.php          # REST API manager
│   │   └── Controllers/                # Individual API controllers
│   │       ├── BaseController.php      # Base controller class
│   │       ├── SystemController.php    # System status endpoints
│   │       ├── LogsController.php      # Logs endpoints
│   │       ├── OptionsController.php   # Options endpoints
│   │       ├── ConversionsController.php # Conversion endpoints
│   │       ├── QuotaController.php     # Quota endpoints
│   │       ├── FilesController.php     # File operation endpoints
│   │       └── CleanupController.php   # Cleanup endpoints
│   └── Utils/
│       ├── Logger.php                  # Logging utility
│       ├── DatabaseHandler.php         # Database log handler
│       └── StructuredLogger.php        # Structured logging utility
├── tests/                              # Test files
├── vendor/                             # Composer dependencies
├── composer.json                       # PHP dependencies
├── package.json                        # Node.js dependencies
├── webpack.config.js                   # Webpack configuration
├── babel.config.js                     # Babel configuration
└── flux-media.php                      # Main plugin file
```

## 🔧 Installation

### From Source (Development)

1. **Clone the repository**:
   ```bash
   git clone https://github.com/stratease/flux-media.git
   cd flux-media
   ```

2. **Install PHP dependencies**:
   ```bash
   composer install
   ```

3. **Install Node.js dependencies**:
   ```bash
   npm install
   ```

4. **Build React frontend**:
   ```bash
   npm run build
   ```

5. **Install in WordPress**:
   - Copy the entire `flux-media` directory to `/wp-content/plugins/`
   - Activate the plugin through the WordPress admin

### From WordPress.org (Production)

1. Download from the WordPress plugin directory
2. Upload to `/wp-content/plugins/flux-media/`
3. Activate the plugin through the WordPress admin

### System Requirements
1. Ensure GD or Imagick is installed with WebP/AVIF support
2. Install FFmpeg with required codecs
3. Configure PHP memory limits (minimum 256MB)
4. Set up proper file permissions

## 🎯 Usage

### Admin Interface
Access the plugin through **WordPress Admin > Flux Media**. The interface includes:

- **Overview**: System status, quota usage, and conversion statistics with skeleton loading
- **Settings**: Configure conversion options, hybrid approach, and quality settings with auto-save
- **Auto-Save**: All settings automatically save as you type with visual feedback
- **Skeleton Loading**: Professional loading states while data is being fetched
- **Internationalization**: Full translation support using WordPress i18n functions
- **Responsive Design**: MUI Grid system for consistent layouts across devices
- **Navigation**: React Router Link components for accessible navigation

#### URL Routing
The admin interface uses WordPress admin URLs with hash-based routing for seamless navigation:

- **Main Page**: `admin.php?page=flux-media` (defaults to `#/overview`)
- **Overview**: `admin.php?page=flux-media#/overview`
- **Settings**: `admin.php?page=flux-media#/settings`

The interface uses React Router with hash-based navigation for a single-page application experience, with all pages rendered within the same React application container.

### API Architecture
The REST API follows WordPress REST API best practices with separate controller classes for each resource:

#### Controller Structure
```
src/Api/Controllers/
├── BaseController.php          # Base class with common functionality
├── SystemController.php        # System status and test endpoints
├── LogsController.php          # Logs retrieval with pagination
├── OptionsController.php       # Plugin options management
├── ConversionsController.php   # Conversion operations and statistics
├── QuotaController.php         # Quota and plan management
├── FilesController.php         # File operations
└── CleanupController.php       # Cleanup operations
```

#### API Endpoints
All endpoints are prefixed with `/wp-json/flux-media/v1/` and require admin authentication:

##### System Endpoints (`SystemController`)
- `GET /test` - Test endpoint to verify API functionality
- `GET /system/status` - Get system status and capabilities

##### Logs Endpoints (`LogsController`)
- `GET /logs` - Get logs with pagination, filtering, and search
  - Query parameters: `page`, `per_page`, `level`, `search`

##### Options Endpoints (`OptionsController`)
- `GET /options` - Get plugin options
- `POST /options` - Update plugin options

##### Conversion Endpoints (`ConversionsController`)
- `GET /conversions/stats` - Get conversion statistics
- `GET /conversions/recent` - Get recent conversions
- `POST /conversions/start` - Start a conversion job
- `POST /conversions/cancel/{jobId}` - Cancel a conversion job
- `POST /conversions/bulk` - Start bulk conversion

##### Quota Endpoints (`QuotaController`)
- `GET /quota/progress` - Get quota usage information
- `GET /quota/plan` - Get current plan information

##### File Endpoints (`FilesController`)
- `DELETE /files/delete/{attachmentId}/{format}` - Delete converted file

##### Cleanup Endpoints (`CleanupController`)
- `POST /cleanup/temp-files` - Cleanup temporary files
- `POST /cleanup/old-records` - Cleanup old conversion records

##### AJAX Endpoints (WordPress AJAX)
- `POST /wp-admin/admin-ajax.php?action=flux_media_convert_attachment` - Convert specific attachment
- `POST /wp-admin/admin-ajax.php?action=flux_media_disable_conversion` - Disable conversion for attachment
- `POST /wp-admin/admin-ajax.php?action=flux_media_enable_conversion` - Enable conversion for attachment

### Response Format
All API responses use WordPress apiFetch format. React Query handles error states and caching automatically.

Success responses return data directly:
```json
{
  "gd_available": true,
  "imagick_available": false,
  "webp_support": true,
  "avif_support": true
}
```

Error responses are handled by React Query and WordPress apiFetch:
```json
{
  "code": "rest_forbidden",
  "message": "Sorry, you are not allowed to do that.",
  "data": {
    "status": 403
  }
}
```

## 🛠️ Development

### Frontend Development
```bash
# Development mode with hot reload
npm run dev

# Production build
npm run build

# Lint code
npm run lint

# Fix linting issues
npm run lint:fix
```

### Path Mapping
The project uses `@flux-media` path mapping for clean imports:
```javascript
import { useSystemStatus } from '@flux-media/hooks/useSystemStatus';
import { SystemStatusCard, OverviewPageSkeleton } from '@flux-media/components';
import { apiService } from '@flux-media/services/api';
import { useAutoSaveForm } from '@flux-media/hooks/useAutoSaveForm';
import { __, _x } from '@wordpress/i18n';
```

### WordPress i18n Integration
All text strings are internationalized using WordPress translation functions:
```javascript
import { __, _x, _n } from '@wordpress/i18n';

// Basic translation
const title = __('Flux Media Settings', 'flux-media');

// Context-specific translation
const label = _x('Quality', 'Image quality setting', 'flux-media');

// Pluralization
const count = _n('%d image', '%d images', total, 'flux-media');
```

### Component Patterns

#### Smart/Dumb Component Architecture
- **Smart Components**: Handle data fetching with React Query hooks
- **Dumb Components**: Pure presentation components with props
- **Container Pattern**: Data fetching containers with presentation components
- **Hook Separation**: Custom hooks for data logic, components for presentation

#### Loading States
- **Skeleton Components**: Professional loading states using MUI Skeleton
- **Page-Specific Skeletons**: Tailored loading states for each page
- **Perceived Performance**: Users see content structure while loading
- **Smooth Transitions**: Seamless transition from skeleton to content

#### State Management
- **React Query**: Server state management with caching and background updates
- **Auto-Save Context**: Global state management for form auto-saving
- **Local State**: useState for component-specific state
- **Context Providers**: Global state for shared functionality

#### Layout & Navigation
- **MUI Grid System**: Responsive layouts using Grid components (no explicit flex styles)
- **React Router Links**: Accessible navigation using Link components
- **Hash Routing**: Client-side routing with hash-based navigation
- **Breadcrumb Navigation**: Clear navigation hierarchy

#### Internationalization
- **WordPress i18n**: Internationalized text throughout all components
- **Translation Functions**: Use `__()`, `_x()`, and `_n()` for all text
- **Context-Aware**: Proper context for ambiguous translations
- **Pluralization**: Handle singular/plural forms correctly

#### Error Handling
- **Error Boundaries**: Comprehensive error handling at component level
- **React Query Errors**: Automatic error handling for API calls
- **User Feedback**: Clear error messages and recovery options
- **Debug Mode**: Enhanced error reporting in development

#### Performance Optimization
- **Code Splitting**: Lazy loading for route-based components
- **Memoization**: React.memo for expensive components
- **Debounced Actions**: Auto-save with debounced API calls
- **Optimistic Updates**: Immediate UI feedback for better UX

### Backend Development

#### Service Architecture
- **Dependency Injection**: Use container for service management
- **Interface Segregation**: Define clear contracts for all services
- **Single Responsibility**: Each service has one clear purpose
- **Error Handling**: Comprehensive error handling with debug mode
- **Structured Logging**: Consistent logging format for troubleshooting

#### Converter Interface Architecture
The plugin uses a unified converter interface integrated into the Services layer:

```php
// Fluent API for all converters
$converter = new ImageConverter($logger);
$result = $converter
    ->from('/path/to/source.jpg')
    ->to('/path/to/destination.webp')
    ->with_options(['quality' => 85])
    ->convert();

if (!$result) {
    $errors = $converter->get_errors();
    $lastError = $converter->get_last_error();
}

// WordPress-specific functionality still available
$results = $converter->process_uploaded_image($attachment_id);
```

#### Converter Features
- **Fluent Interface**: Chainable method calls for clean API
- **Format Constants**: Predefined constants for all supported formats
- **Error Tracking**: Comprehensive error collection and reporting
- **Validation**: Input validation with detailed error messages
- **WordPress Integration**: Maintains WordPress-specific functionality
- **Unified Architecture**: Single class per converter type with both interfaces

#### Format Constants
The plugin uses predefined constants for all supported formats to ensure consistency and maintainability:

**Image Format Constants:**
```php
use FluxMedia\Interfaces\Converter;

// Image formats
Converter::FORMAT_WEBP = 'webp'
Converter::FORMAT_AVIF = 'avif'
Converter::FORMAT_JPEG = 'jpeg'
Converter::FORMAT_PNG = 'png'
Converter::FORMAT_GIF = 'gif'

// Video formats
Converter::FORMAT_AV1 = 'av1'
Converter::FORMAT_WEBM = 'webm'
Converter::FORMAT_MP4 = 'mp4'
Converter::FORMAT_OGV = 'ogv'

// Audio formats
Converter::FORMAT_OPUS = 'opus'
Converter::FORMAT_AAC = 'aac'
Converter::FORMAT_MP3 = 'mp3'

// Conversion types
Converter::TYPE_IMAGE = 'image'
Converter::TYPE_VIDEO = 'video'
Converter::TYPE_AUDIO = 'audio'
Converter::TYPE_DOCUMENT = 'document'
```

**Usage Example:**
```php
// Instead of hardcoded strings
$converted_files = $converter->get_converted_files( $attachment_id );
if ( isset( $converted_files['webp'] ) ) { // ❌ Don't do this

// Use constants for consistency
if ( isset( $converted_files[ Converter::FORMAT_WEBP ] ) ) { // ✅ Do this
```

#### WordPress Integration
- **WordPress Coding Standards**: Follow WPCS guidelines strictly
- **REST API Best Practices**: Controller-per-resource architecture
- **Database Abstraction**: Use WordPress database layer
- **Security**: Proper sanitization, validation, and escaping
- **Debug Mode**: Enhanced error reporting in development

## 📊 Database Schema

### Custom Tables
- `wp_flux_media_conversions` - Enhanced conversion records with file size tracking
  - `id` - Primary key
  - `attachment_id` - WordPress attachment ID
  - `file_type` - Format (webp, avif, av1, webm)
  - `original_size` - Original file size in bytes
  - `converted_size` - Converted file size in bytes
  - `size_savings` - Bytes saved
  - `converted_at` - Conversion timestamp
- `wp_flux_media_quota_usage` - Quota tracking
- `wp_flux_media_settings` - Plugin settings
- `wp_flux_media_logs` - Structured logging with pagination support

### WordPress Integration
- Uses WordPress options API for configuration
- Integrates with WordPress media library
- Follows WordPress database abstraction
- Enhanced post meta for conversion tracking
- AJAX handlers for attachment management

## 🔒 Security

### Authentication
- All admin endpoints require `manage_options` capability
- WordPress nonce verification for all requests
- Proper sanitization and validation of inputs
- Debug mode for enhanced error reporting in development

### File Handling
- Secure file upload validation
- Proper file type checking
- Safe temporary file handling

## 🧪 Testing

### Frontend Testing
```bash
# Run tests (when implemented)
npm test

# Run tests with coverage
npm run test:coverage
```

### Backend Testing
- PHPUnit tests for core functionality
- WordPress test suite integration
- Mock services for isolated testing

## 📈 Performance

### Frontend Optimization
- Code splitting with React.lazy()
- Material-UI tree shaking
- Webpack bundle optimization
- TanStack Query caching and background updates
- Skeleton loading for perceived performance
- Auto-save with debounced API calls
- MUI Grid system for efficient layouts
- WordPress i18n for optimized translations
- React Router Link components for better navigation

### Backend Optimization
- Efficient database queries
- Background processing for conversions
- Proper memory management
- Caching for system status
- Debug mode for development efficiency
- Comprehensive input sanitization

## 🚀 Deployment

### Production Build
```bash
# Build optimized assets
npm run build

# Verify build
ls -la assets/js/dist/
```

### WordPress Deployment
1. Build frontend assets
2. Upload plugin files
3. Activate plugin
4. Configure system requirements
5. Test functionality

## 🆕 Recent Updates

### Latest Improvements (v0.1.0)
- **Decoupled Architecture**: Complete separation of business logic from WordPress dependencies
- **Provider Pattern**: WordPressProvider handles all WordPress integration while services remain pure
- **Dependency Inversion**: LoggerInterface and NoopLogger for testable, decoupled components
- **Pure Business Logic**: ImageConverter and VideoConverter are now WordPress-independent
- **Single Source of Truth**: Centralized Settings class with constants for all default values
- **Unified Converter Interface**: Fluent API for all file processing with consistent error handling
- **REST API Refactoring**: Controller-per-resource architecture with proper separation of concerns
- **Database Logging**: Structured logging system with pagination and search capabilities
- **Logs Admin Page**: Complete logs management interface with filtering and pagination
- **Enhanced Video Processing**: Improved FFmpeg integration with detailed status reporting
- **Consistent Service Architecture**: Standardized ImageConverter and VideoConverter with structured logging
- **WordPress i18n Integration**: Full internationalization support with `__()`, `_x()`, and `_n()` functions
- **React Router Enhancement**: Proper Link components for accessible navigation
- **MUI Grid System**: Replaced explicit flex styles with Grid components for consistency
- **Debug Mode**: Enhanced error reporting and exception output in development
- **Data Sanitization**: Comprehensive input validation and sanitization
- **Component Refactoring**: Improved component architecture with better separation of concerns
- **Navigation Breadcrumbs**: Added breadcrumb navigation component
- **Enhanced Error Handling**: Better error boundaries and user feedback
- **Performance Optimizations**: Improved loading states and caching strategies
- **PHPUnit Testing**: Migrated from Codeception to PHPUnit for better test isolation
- **Format Constants**: Centralized format constants for consistency across the codebase
- **File Size Tracking**: Comprehensive tracking of original and converted file sizes with savings calculations
- **Quota Management**: Proper quota counting for hybrid conversions (counts each format separately)
- **Attachment Management**: Convert and disable buttons on attachment details screen with AJAX handling
- **Bulk Conversion**: Automated cron job for processing existing media with smart skipping
- **Conversion Statistics**: Detailed savings statistics displayed on overview page and attachment screens
- **Database Schema**: Enhanced conversion tracking with file size and savings percentage columns
- **WordPress Integration**: Full integration with WordPress media library and attachment management

## 🎨 Key Features

### Hybrid Image Conversion
The plugin implements a hybrid approach for optimal image performance:

- **Dual Format Creation**: Creates both WebP and AVIF formats simultaneously
- **Smart Serving**: Serves AVIF where supported, WebP as fallback
- **WordPress Integration**: Uses `<picture>` tags or server detection
- **Risk-Free**: No compatibility issues since both formats are created
- **Maximum Performance**: Best of both worlds for all browsers

### Auto-Save System
Professional auto-save functionality with visual feedback:

- **Real-Time Saving**: Settings save automatically as you type
- **Debounced API Calls**: 1.5-second delay to prevent excessive requests
- **Visual Feedback**: Spinner during save, checkmark when complete
- **Error Handling**: Clear error messages if save fails
- **Context-Based**: Global state management for all forms

### Attachment Management
Comprehensive media file management with conversion controls:

- **Convert Button**: Manual conversion trigger on attachment details screen
- **Disable Conversion**: Exclude specific files from automatic conversion
- **File Size Tracking**: Real-time display of original vs converted file sizes
- **Savings Statistics**: Detailed breakdown of space savings per attachment
- **AJAX Integration**: Seamless conversion without page reloads
- **Conversion Status**: Clear visual indicators for conversion state
- **Bulk Processing**: Automated cron job for processing existing media
- **Smart Skipping**: Avoids re-converting already processed files

### Skeleton Loading
Professional loading states for all pages:

- **MUI Skeleton Components**: Consistent with Material Design
- **Page-Specific Skeletons**: Tailored loading states for each page
- **Perceived Performance**: Users see content structure while loading
- **Smooth Transitions**: Seamless transition from skeleton to content

## 🔮 Future Features

### Planned Enhancements
- **CDN Integration**: CloudFlare, AWS CloudFront support
- **External Services**: Integration with external conversion APIs
- **Advanced Analytics**: Detailed conversion metrics and insights
- **Batch Processing**: Improved bulk conversion workflows
- **API Extensions**: Webhook support and external integrations
- **Snackbar Notifications**: Toast notifications for auto-save feedback
- **Translation Files**: Complete translation files for multiple languages
- **Advanced Debugging**: Enhanced debugging tools and logging

### Technical Roadmap
- **Performance**: Further optimization and caching improvements
- **Scalability**: Support for high-volume sites
- **Monitoring**: Enhanced logging and monitoring capabilities
- **Testing**: Comprehensive test coverage

## 📝 Coding Standards

### PHP Standards

#### Naming Conventions
- **Classes**: PascalCase (e.g., `ImageConverter`, `FFmpegProcessor`)
- **Methods**: snake_case (e.g., `get_processor_info()`, `convert_to_webp()`)
- **Properties**: snake_case (e.g., `$logger`, `$structured_logger`, `$processor`)
- **Constants**: UPPER_SNAKE_CASE (e.g., `TYPE_IMAGE`, `FORMAT_WEBP`)
- **Files**: PascalCase matching class name (e.g., `ImageConverter.php`)

#### Code Structure
- **WordPress Coding Standards**: Follow WPCS guidelines strictly
- **PSR-4 Autoloading**: Proper namespace usage with `FluxMedia\` prefix
- **Type Hints**: Use PHP 7.4+ type declarations for all parameters and returns
- **Documentation**: Comprehensive PHPDoc comments with `@since TBD`
- **Use Statements**: Always use shortened qualified class names
- **No Underscore Prefixes**: Properties and methods use standard naming without underscores
- **Dependency Injection**: Use container for service management
- **Interface Segregation**: Define clear contracts for all processors and services

#### File Organization
- **Single Responsibility**: Each class/component has one purpose
- **Namespace Structure**: Mirror directory structure in namespaces
- **Interface-First**: Define interfaces before implementations
- **Error Handling**: Comprehensive error boundaries and structured logging
- **Database Abstraction**: Use WordPress database layer with custom tables

### JavaScript Standards

#### Naming Conventions
- **Components**: PascalCase (e.g., `SystemStatusCard`, `OverviewPage`)
- **Files**: PascalCase matching component name (e.g., `SystemStatusCard.js`)
- **Hooks**: camelCase starting with 'use' (e.g., `useSystemStatus`, `useAutoSaveForm`)
- **Variables/Functions**: camelCase (e.g., `apiService`, `handleTabChange`)
- **Constants**: UPPER_SNAKE_CASE (e.g., `API_BASE_URL`)

#### Code Structure
- **ESLint**: React and JavaScript linting rules
- **Modern ES6+**: Use modern JavaScript features (arrow functions, destructuring, etc.)
- **Component Documentation**: JSDoc for components and hooks
- **WordPress i18n**: Use WordPress translation functions for ALL text strings
- **MUI Grid**: Use Grid components for responsive layouts (no explicit flex styles)
- **React Router**: Use Link components for navigation (no manual hash changes)
- **Path Mapping**: Use `@flux-media` alias for clean imports
- **Functional Components**: Use React hooks, no class components

#### File Organization
- **Component Structure**: Smart/Dumb component separation
- **Hook Organization**: Custom hooks in dedicated `hooks/` directory
- **Service Layer**: API services in dedicated `services/` directory
- **Context Usage**: Global state management with React Context
- **Error Boundaries**: Comprehensive error handling
- **Skeleton Loading**: Professional loading states using MUI Skeleton

### Folder Structure Standards

#### Frontend Structure
```
assets/js/src/
├── components/
│   ├── common/          # Reusable UI components
│   ├── features/        # Feature-specific components
│   └── pages/          # Page-level components
├── hooks/              # Custom React hooks
├── contexts/           # React Context providers
├── services/           # API and external services
├── theme/              # MUI theme configuration
└── utils/              # Utility functions
```

#### Backend Structure
```
src/
├── Core/               # Core plugin functionality
├── Services/           # Business logic services (with Converter interface)
├── Processors/         # Image/video processors
├── Interfaces/         # Contract definitions
├── Models/             # Data models
├── Api/
│   └── Controllers/    # REST API controllers
├── Admin/              # WordPress admin integration
├── Public/             # Public-facing features
└── Utils/              # Utility classes
```

### API Standards

#### REST API Structure
- **Controller per Resource**: One controller class per API resource
- **Base Controller**: Common functionality in `BaseController`
- **Endpoint Naming**: RESTful naming conventions
- **Authentication**: WordPress nonce verification
- **Error Handling**: Consistent error response format
- **Debug Mode**: Enhanced error reporting in development

#### Response Format
- **Success Responses**: Return data directly
- **Error Responses**: WordPress apiFetch format
- **Pagination**: Standard WordPress pagination parameters
- **Filtering**: Query parameters for filtering and search

### Database Standards

#### Table Naming
- **Custom Tables**: `wp_flux_media_` prefix
- **Column Naming**: snake_case
- **Indexes**: Proper indexing for performance
- **Foreign Keys**: WordPress post/option relationships

#### Data Handling
- **Sanitization**: All input data sanitized
- **Validation**: Comprehensive input validation
- **Escaping**: Output escaping for security
- **WordPress Integration**: Use WordPress database abstraction

### Testing Standards

#### Frontend Testing
- **Component Testing**: Test component behavior and rendering
- **Hook Testing**: Test custom hooks in isolation
- **Integration Testing**: Test component interactions
- **Accessibility Testing**: Ensure WCAG compliance

#### Backend Testing
- **Unit Testing**: Test individual classes and methods
- **Integration Testing**: Test service interactions
- **WordPress Testing**: Use WordPress test suite
- **Mock Services**: Mock external dependencies

### Performance Standards

#### Frontend Performance
- **Code Splitting**: Use React.lazy() for route-based splitting
- **Bundle Optimization**: Webpack optimization and tree shaking
- **Caching**: React Query caching and background updates
- **Loading States**: Skeleton components for perceived performance
- **Debouncing**: Auto-save with debounced API calls

#### Backend Performance
- **Database Queries**: Efficient queries with proper indexing
- **Memory Management**: Proper memory usage for large files
- **Background Processing**: Async processing for conversions
- **Caching**: Cache system status and configuration
- **Error Handling**: Structured logging for performance monitoring

### Security Standards

#### Authentication & Authorization
- **WordPress Integration**: Use WordPress user capabilities
- **Nonce Verification**: All API requests verified
- **Input Sanitization**: All user input sanitized
- **Output Escaping**: All output properly escaped
- **File Validation**: Secure file upload handling

#### Data Protection
- **Sensitive Data**: No sensitive data in logs
- **Error Messages**: User-friendly error messages
- **Debug Mode**: Enhanced errors only in development
- **File Permissions**: Proper file system permissions

## 🤝 Contributing

### Development Setup
1. Fork the repository
2. Install dependencies (`composer install && npm install`)
3. Build frontend (`npm run build`)
4. Create feature branch
5. Implement changes with tests
6. Submit pull request

### Code Review Process
- All changes require code review
- Tests must pass
- Documentation must be updated
- Follow established coding standards

## 📄 License

GPL-2.0-or-later - See [LICENSE](LICENSE) file for details.

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

## 🆘 Support

### Documentation
- This README provides comprehensive setup and usage information
- Code comments explain implementation details
- API documentation is embedded in endpoint methods

### Troubleshooting
- Check system requirements
- Verify PHP extensions (GD/Imagick, FFmpeg)
- Review error logs
- Test with default WordPress theme

### Common Issues
- **Memory Limits**: Increase PHP memory_limit for large files
- **File Permissions**: Ensure proper write permissions
- **FFmpeg Codecs**: Verify required codecs are installed
- **React Build**: Ensure Node.js dependencies are installed
- **Translation Issues**: Check that WordPress i18n functions are properly imported
- **Grid Layout**: Ensure MUI Grid components are used instead of flex styles
- **Navigation**: Use React Router Link components for proper navigation
- **Debug Mode**: Enable WordPress debug mode for enhanced error reporting

---

**Note**: This plugin is designed for modern WordPress installations with proper server configuration. Ensure all system requirements are met before installation.