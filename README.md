# Flux Media

Advanced image and video optimization plugin for WordPress. Converts images to WebP/AVIF and videos to AV1/WebM with high-quality settings, featuring a modern React-based admin interface with hash routing and comprehensive API integration.

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
│       │   ├── App.js                    # Main React Router app
│       │   ├── admin/
│       │   │   └── index.js             # Admin entry point
│       │   ├── pages/
│       │   │   ├── OverviewPage.js      # Overview page
│       │   │   └── SettingsPage.js      # Settings page
│       │   ├── components/
│       │   │   ├── common/              # Reusable UI components
│       │   │   │   ├── PageSkeleton.js  # Skeleton loading components
│       │   │   │   ├── FluxMediaIcon.js # Brand icon component
│       │   │   │   └── Breadcrumbs.js   # Navigation breadcrumbs
│       │   │   ├── features/            # Feature-specific components
│       │   │   └── index.js             # Barrel exports
│       │   ├── hooks/                   # React Query hooks
│       │   │   ├── useSystemStatus.js   # System status hook
│       │   │   ├── useOptions.js        # Options management hook
│       │   │   ├── useConversions.js    # Conversion operations hooks
│       │   │   ├── useAutoSaveForm.js   # Auto-save form hook
│       │   │   ├── useQuotaProgress.js  # Quota progress hook
│       │   │   ├── useLogs.js           # Logs hook
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
│   ├── Processors/
│   │   ├── GDProcessor.php             # GD image processor
│   │   ├── ImagickProcessor.php        # Imagick processor
│   │   └── FFmpegProcessor.php         # FFmpeg video processor
│   ├── Interfaces/
│   │   ├── ImageProcessorInterface.php # Image processor contract
│   │   └── VideoProcessorInterface.php # Video processor contract
│   ├── Models/
│   │   └── ConversionRecord.php        # Conversion data model
│   ├── Admin/
│   │   └── Admin.php                   # WordPress admin integration
│   ├── Api/
│   │   └── RestApi.php                 # REST API endpoints
│   └── Utils/
│       └── Logger.php                  # Logging utility
├── tests/                              # Test files
├── vendor/                             # Composer dependencies
├── composer.json                       # PHP dependencies
├── package.json                        # Node.js dependencies
├── webpack.config.js                   # Webpack configuration
└── flux-media.php                      # Main plugin file
```

## 🔧 Installation

### 1. Download and Install
1. Download the plugin files
2. Upload to `/wp-content/plugins/flux-media/`
3. Activate the plugin through the WordPress admin

### 2. Install Dependencies
```bash
# Install PHP dependencies
composer install

# Install Node.js dependencies
npm install

# Build React frontend
npm run build
```

### 3. Configure System
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
- **Smart Components**: Handle data fetching with React Query hooks
- **Dumb Components**: Pure presentation components
- **Skeleton Components**: Professional loading states using MUI Skeleton
- **Container Pattern**: Data fetching containers with presentation components
- **React Query Hooks**: Encapsulated data fetching with caching and error handling
- **Auto-Save Context**: Global state management for form auto-saving
- **MUI Grid Layout**: Responsive layouts using Grid components instead of flex styles
- **React Router Links**: Accessible navigation using Link components
- **WordPress i18n**: Internationalized text throughout all components

### Backend Development
- Follow WordPress coding standards
- Use dependency injection for services
- Implement proper error handling with debug mode
- Add comprehensive logging
- Sanitize and validate all input data
- Use WordPress REST API best practices

## 📊 Database Schema

### Custom Tables
- `wp_flux_media_conversions` - Conversion records
- `wp_flux_media_quota_usage` - Quota tracking
- `wp_flux_media_settings` - Plugin settings

### WordPress Integration
- Uses WordPress options API for configuration
- Integrates with WordPress media library
- Follows WordPress database abstraction

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

### Latest Improvements (v1.0.0)
- **WordPress i18n Integration**: Full internationalization support with `__()`, `_x()`, and `_n()` functions
- **React Router Enhancement**: Proper Link components for accessible navigation
- **MUI Grid System**: Replaced explicit flex styles with Grid components for consistency
- **Debug Mode**: Enhanced error reporting and exception output in development
- **Data Sanitization**: Comprehensive input validation and sanitization
- **Component Refactoring**: Improved component architecture with better separation of concerns
- **Navigation Breadcrumbs**: Added breadcrumb navigation component
- **Enhanced Error Handling**: Better error boundaries and user feedback
- **Performance Optimizations**: Improved loading states and caching strategies

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
- **WordPress Coding Standards**: Follow WPCS guidelines
- **PSR-4 Autoloading**: Proper namespace usage
- **Type Hints**: Use PHP 7.4+ type declarations
- **Documentation**: Comprehensive PHPDoc comments

### JavaScript Standards
- **ESLint**: React and JavaScript linting rules
- **Prettier**: Code formatting (when configured)
- **Modern ES6+**: Use modern JavaScript features
- **Component Documentation**: JSDoc for components
- **WordPress i18n**: Use WordPress translation functions for all text
- **MUI Grid**: Use Grid components for responsive layouts
- **React Router**: Use Link components for navigation

### File Organization
- **Single Responsibility**: Each class/component has one purpose
- **Dependency Injection**: Use container for service management
- **Interface Segregation**: Define clear contracts
- **Error Handling**: Comprehensive error boundaries and logging

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

GPL v2 or later - See LICENSE file for details.

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