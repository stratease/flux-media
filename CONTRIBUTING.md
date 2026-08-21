# Contributing to Flux Media Optimizer – Image & Video Optimization by Flux Plugins

Thank you for your interest in contributing to Flux Media Optimizer – Image & Video Optimization by Flux Plugins! This document provides comprehensive guidelines and information for contributors, including detailed architecture documentation and coding standards.

## 📋 Source Code

This plugin includes minified JavaScript and CSS assets built from source code. The original, human-readable source code is available in the GitHub repository:

* **Repository**: https://github.com/stratease/flux-media-optimizer
* **JavaScript Source**: `assets/js/src/` - React components and application code
* **Build Output**: `assets/js/dist/` - Compiled and minified production bundles
* **Build Tool**: webpack (configured in `package.json`)

To build from source:
```bash
npm install
npm run build
```

For development with hot reload:
```bash
npm run dev
```

## 🏗️ Architecture Overview

This plugin has been completely refactored with a modern, decoupled architecture that separates business logic from WordPress dependencies, making it highly maintainable and testable. Optional Flux cloud processing and CDN are available when licensed; local optimization remains the default.

### Key Architectural Changes

#### 1. **Decoupled Service Architecture**
- **Pure Business Logic**: `ImageConverter` and `VideoConverter` are now completely WordPress-independent
- **Provider Pattern**: `WordPressProvider` handles all WordPress integration while services remain pure
- **Dependency Inversion**: Uses interfaces and dependency injection for testable, decoupled components
- **Single Responsibility**: Each service has one clear purpose and well-defined boundaries

#### 2. **Unified Converter Interface**
- **Fluent API**: Chainable method calls for clean, readable code
- **Format Constants**: Centralized constants for all supported formats
- **Error Tracking**: Comprehensive error collection and reporting
- **WordPress Integration**: Maintains WordPress-specific functionality while being framework-agnostic

#### 3. **Modern React Frontend**
- **React 18**: Functional components with hooks
- **React Router**: Hash-based client-side routing
- **Material-UI**: Professional design system with Grid layout
- **TanStack Query**: Server state management with caching
- **WordPress i18n**: Full internationalization support
- **Skeleton Loading**: Professional loading states

#### 4. **Optional Flux cloud processing**
- **License**: Suite license via flux-plugins-common gates **optional** outbound cloud processing / CDN only — not local optimization, settings, Media Library status, or logs
- **Privacy Compliant**: Full compliance with WordPress.org SaaS guidelines
- **Local-First**: All core functionality works locally without any external service

#### 5. **Shared Library Architecture**
- **Flux Plugins Common**: This plugin uses `flux-plugins-common` for shared services across the Flux Plugins suite
- **Shared Services**: Menu system, account ID management, logging, and API client are provided by the shared library
- **Namespace Prefixing**: The shared library is namespace-prefixed using Strauss to avoid conflicts
- **Test / fixture layout**: See [Plugin test surfaces and runtime fixtures](https://github.com/stratease/flux-plugins-common/blob/master/README.md#plugin-test-surfaces-and-runtime-fixtures) in the common README (PHPUnit + ephemeral only; `assets/fixtures/` is runtime)
- **Repository**: See [flux-plugins-common repository](https://github.com/stratease/flux-plugins-common) for detailed documentation

##### Hook Naming Convention

**All hooks MUST follow the standard WordPress hook naming convention.** See the [Flux Plugins Common README](https://github.com/stratease/flux-plugins-common/blob/master/README.md#hook-naming-convention) for complete documentation on hook naming standards.

The pattern is: `{plugin_namespace}/{class_name}/{method_name}` with an optional `/{operation}` suffix.

- **Plugin Namespace**: The plugin's slug in snake_case (e.g., `flux_suite` for common library, `flux_media_optimizer` for this plugin)
- **Class Name**: The class name in snake_case (e.g., `MenuService` → `menu_service`)
- **Method Name**: The method name in snake_case (e.g., `register_top_level_menu`)
- **Operation (Optional)**: A specific operation within the method (e.g., `before`, `after`)

**Examples:**
- `flux_suite/menu_service/register_top_level_menu` - Fired when top-level menu is registered
- `flux_media_optimizer/image_converter/convert/before` - Fired before image conversion
- `flux_media_optimizer/wordpress_provider/register_hooks` - Fired when WordPress hooks are registered

**Important:** Always convert class names from PascalCase to snake_case when creating hook names. All parts of the hook name must use snake_case.

For complete hook naming guidelines, see: https://github.com/stratease/flux-plugins-common/blob/master/README.md#hook-naming-convention

## 📁 Project Structure

```
flux-media-optimizer/
├── app/                          # Main application code
│   ├── Services/                 # Business logic services
│   │   ├── ImageConverter.php
│   │   ├── VideoConverter.php
│   │   ├── WordPressProvider.php
│   │   ├── ConversionOrchestrator.php
│   │   ├── ConversionRetryService.php
│   │   ├── ConversionArtifactTransaction.php
│   │   ├── AttachmentDetailsPresenter.php
│   │   ├── AttachmentDetailsMountRenderer.php
│   │   ├── HeifCapabilityProbe.php
│   │   ├── MediaLibraryStatusService.php
│   │   ├── CleanupService.php
│   │   ├── AdminScriptUrl.php
│   │   ├── LocalProcessingService.php
│   │   ├── ExternalProcessingService.php
│   │   └── …                     # Other converters, processors, settings
│   ├── Http/Controllers/         # REST API controllers
│   └── Processors/               # GD / Imagick / FFmpeg processors
├── assets/
│   ├── fixtures/                 # Runtime probes (e.g. HEIF sequence) — ships in zip
│   └── js/{src,dist}/            # React admin + attachment island
├── src/assets/common/            # Copied common runtime JS/images
├── bin/                          # Strauss helpers; build/deploy via ./vendor/bin/*
├── tests/
│   ├── unit/                     # PHPUnit
│   ├── _support/files/           # Test-only samples
│   └── ephemeral/                # Docs for ephemeral-wp-test checks
└── …
```

Suite-wide layout rules (PHPUnit vs ephemeral, `assets/fixtures/`): [flux-plugins-common README](https://github.com/stratease/flux-plugins-common/blob/master/README.md#plugin-test-surfaces-and-runtime-fixtures).

## 🔧 Core Services Architecture

### ImageConverter Service
```php
// Pure business logic - no WordPress dependencies
$converter = new ImageConverter($logger);
$result = $converter
    ->from('/path/to/source.jpg')
    ->to('/path/to/destination.webp')
    ->with_options(['quality' => 85])
    ->convert();

// WordPress integration handled separately
$results = $converter->process_uploaded_image($attachment_id);
```

### WordPressProvider Service
```php
// Handles all WordPress integration
class WordPressProvider {
    public function register_hooks() {
        // Image rendering hooks
        add_filter('wp_get_attachment_url', [$this, 'handle_attachment_url_filter']);
        add_filter('wp_content_img_tag', [$this, 'handle_content_images_filter']);
        add_filter('render_block', [$this, 'handle_render_block_filter']);
    }
}
```

### Hybrid Image Rendering
The plugin implements a sophisticated hybrid approach for optimal image performance:

- **Hybrid Mode**: Creates `<picture>` elements with multiple `<source>` tags
- **Single Format Mode**: Replaces `src` attributes with optimized formats
- **Runtime Decision**: Hybrid setting determines output format, not processing
- **Format Priority**: AVIF > WebP > Original fallback

### GIF Animation Detection
The plugin includes a sophisticated GIF animation detector:

- **Imagick Method**: Uses Imagick to count frames for reliable detection
- **File-Based Fallback**: Binary file reading for environments without Imagick
- **WP_Filesystem**: Uses WordPress filesystem methods for file operations
- **Animation Preservation**: Animated GIFs use full-size source for all conversions

## 🚀 Getting Started

### Development Setup

1. **Fork the repository** on GitHub
2. **Clone your fork** locally:
   ```bash
   git clone https://github.com/{your-username}/flux-media-optimizer.git
   cd flux-media-optimizer
   ```

3. **Install dependencies**:
   ```bash
   # PHP dependencies
   composer install
   
   # Node.js dependencies
   npm install
   ```

4. **Build the frontend**:
   ```bash
   npm run build
   ```

5. **Set up WordPress** for testing

### Development Workflow

1. **Create a feature branch**:
   ```bash
   git checkout -b feature/your-feature-name
   ```

2. **Make your changes** following our coding standards

3. **Test your changes**:
   ```bash
   # Run PHP unit tests
   composer test

   # Ephemeral Playwright smoke/regression (requires local ephemeral-wp-test checkout)
   npm run test:ephemeral:smoke

   # Lint code
   npm run lint
   composer run lint
   ```

4. **Commit your changes**:
   ```bash
   git add .
   git commit -m "Add: Brief description of your changes"
   ```

5. **Push to your fork**:
   ```bash
   git push origin feature/your-feature-name
   ```

6. **Create a Pull Request** on GitHub

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
- **Documentation**: Comprehensive PHPDoc comments with `@since` tags using version numbers (e.g., `@since 2.0.1`)
- **Use Statements**: Always use shortened qualified class names
- **WP_Filesystem**: Use WordPress filesystem methods instead of direct PHP file operations
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
- **Path Mapping**: Use `@flux-media-optimizer` alias for clean imports
- **Functional Components**: Use React hooks, no class components

#### File Organization
- **Component Structure**: Smart/Dumb component separation
- **Hook Organization**: Custom hooks in dedicated `hooks/` directory
- **Service Layer**: API services in dedicated `services/` directory
- **Context Usage**: Global state management with React Context
- **Error Boundaries**: Comprehensive error handling
- **Skeleton Loading**: Professional loading states using MUI Skeleton

### Architecture Patterns

#### Service Layer
- **Dependency Injection**: Use container for service management
- **Interface Segregation**: Define clear contracts for all services
- **Single Responsibility**: Each service has one clear purpose
- **Error Handling**: Comprehensive error handling with structured logging
- **Processor Pattern**: WordPressProvider delegates operations to processors via `ProcessingServiceInterface`
  - Processing: `$processor->process($attachment_id)` handles both external and local processing
  - Deletion: `$processor->delete_attachment($attachment_id)` handles service-specific deletion logic
  - WordPressProvider never directly accesses service-specific implementations (e.g., `ExternalApiClient`)

#### Converter Interface
- **Fluent API**: Chainable method calls for clean code
- **Format Constants**: Predefined constants for all supported formats
- **Error Tracking**: Comprehensive error collection and reporting
- **WordPress Integration**: Maintains WordPress-specific functionality

#### React Components
- **Smart/Dumb Components**: Clear separation of data and presentation
- **React Query Hooks**: Encapsulated data fetching with caching
- **Skeleton Loading**: Professional loading states
- **Auto-Save System**: Context-based form auto-saving

## 🧪 Testing

### Backend Testing
- Write **PHPUnit tests** for new functionality
- Test **service layer** in isolation
- Mock **WordPress dependencies**
- Test **error conditions**

### Frontend Testing
- Test **React components** with React Testing Library
- Test **custom hooks** in isolation
- Test **API interactions**
- Ensure **accessibility compliance**

## 📊 Database Schema

### Custom Tables
- `wp_flux_media_optimizer_conversions` - Conversion records with file size tracking
- `wp_flux_plugins_logs` - Suite logging (flux-plugins-common; includes `plugin_slug`)
- `wp_flux_media_optimizer_settings` - Plugin settings (legacy table name in docs; options use WordPress options API)

### WordPress Integration
- Uses WordPress options API for configuration
- Integrates with WordPress media library
- Follows WordPress database abstraction
- Enhanced post meta for conversion tracking via `AttachmentMetaHandler`
- Size-specific conversion tracking (supports full size and all registered WordPress image sizes)

## 🚀 API Architecture

### REST API Structure
- **Controller per Resource**: One controller class per API resource
- **Base Controller**: Common functionality in `BaseController`
- **Authentication**: WordPress nonce verification
- **Error Handling**: Consistent error response format

### API Endpoints
Plugin endpoints are prefixed with `/wp-json/flux-media-optimizer/v1/`:

- `GET /status` - System status and capabilities
- `GET /options` - Plugin options
- `POST /options` - Update plugin options
- `GET /conversions/stats` - Conversion statistics
- `GET /attachments/{id}/details` - Attachment optimization panel payload

Logs: `GET /wp-json/flux-plugins-common/v1/logs` (see flux-plugins-common `RestApiService`)

## 🔧 Configuration

### Settings Management
```php
// Centralized settings with constants
class Settings {
    const DEFAULT_WEBP_QUALITY = 85;
    const DEFAULT_AVIF_QUALITY = 70;
    const DEFAULT_AVIF_SPEED = 5;
    
    public static function get($key, $default = null) {
        return get_option("flux_media_optimizer_{$key}", $default);
    }
}
```

### Format Constants
```php
// Consistent format constants
use FluxMedia\App\Services\Converter;

Converter::FORMAT_WEBP = 'webp'
Converter::FORMAT_AVIF = 'avif'
Converter::FORMAT_JPEG = 'jpeg'
Converter::FORMAT_PNG = 'png'
```

## 📈 Performance

### Frontend Optimization
- Code splitting with React.lazy()
- Material-UI tree shaking
- TanStack Query caching
- Skeleton loading for perceived performance
- Auto-save with debounced API calls

### Backend Optimization
- Efficient database queries
- Background processing for conversions
- Proper memory management
- Caching for system status
- Structured logging for monitoring

## 📋 Pull Request Guidelines

### Before Submitting
- [ ] Code follows coding standards
- [ ] Tests pass and coverage is maintained
- [ ] Documentation is updated
- [ ] No breaking changes (or clearly documented)
- [ ] Screenshots included for UI changes

### PR Description
- **Clear title** describing the change
- **Detailed description** of what was changed and why
- **Testing instructions** for reviewers
- **Screenshots** for UI changes
- **Breaking changes** clearly documented

## 🐛 Bug Reports

### Before Reporting
- Check **existing issues** for duplicates
- Test with **default WordPress theme**
- Verify **system requirements** are met
- Check **error logs** for details

### Bug Report Template
```markdown
**Describe the bug**
A clear description of what the bug is.

**To Reproduce**
Steps to reproduce the behavior:
1. Go to '...'
2. Click on '....'
3. Scroll down to '....'
4. See error

**Expected behavior**
What you expected to happen.

**Screenshots**
If applicable, add screenshots.

**Environment:**
- WordPress version:
- PHP version:
- Plugin version:
- Browser:

**Additional context**
Any other context about the problem.
```

## ✨ Feature Requests

### Before Requesting
- Check **existing feature requests**
- Consider if it fits the **plugin's scope**
- Think about **implementation complexity**

### Feature Request Template
```markdown
**Is your feature request related to a problem?**
A clear description of what the problem is.

**Describe the solution you'd like**
A clear description of what you want to happen.

**Describe alternatives you've considered**
Alternative solutions or features you've considered.

**Additional context**
Any other context or screenshots about the feature request.
```

## 🔒 Security

### Reporting Security Issues
- **Do NOT** create public GitHub issues for security vulnerabilities
- Email security issues to: eddie@fluxplugins.com
- Include **detailed reproduction steps**
- Allow **reasonable time** for response before disclosure

### Security Guidelines
- **Never commit** sensitive data (API keys, passwords, etc.)
- **Sanitize all inputs** and escape outputs
- **Follow WordPress security** best practices
- **Test for vulnerabilities** in your code

## 📚 Documentation

### Code Documentation
- **PHPDoc comments** for all classes and methods
- **Inline comments** for complex logic
- **README updates** for new features
- **API documentation** for new endpoints
- **Version Tagging**: Update `@since` tags to current version (e.g., `2.0.1`) when modifying code

### User Documentation
- **Clear installation** instructions
- **Usage examples** and screenshots
- **FAQ updates** for common issues
- **Troubleshooting guides**

## 🏷️ Release Process

### Version Numbering
- Follow **Semantic Versioning** (MAJOR.MINOR.PATCH)
- **MAJOR**: Breaking changes
- **MINOR**: New features (backward compatible)
- **PATCH**: Bug fixes (backward compatible)

### Release Checklist
- [ ] All tests pass
- [ ] Documentation updated
- [ ] Changelog updated
- [ ] Version numbers updated
- [ ] Assets built and committed
- [ ] Tagged release created

## 🤝 Community Guidelines

### Be Respectful
- **Respectful communication** in all interactions
- **Constructive feedback** in code reviews
- **Helpful responses** to questions
- **Inclusive environment** for all contributors

### Code of Conduct
- **Be welcoming** to newcomers
- **Be patient** with questions
- **Be constructive** in feedback
- **Be professional** in all interactions

## 📞 Getting Help

### Resources
- **GitHub Issues** for bug reports and feature requests
- **GitHub Discussions** for questions and ideas
- **WordPress.org Support** for user questions
- **Documentation** in README.md

### Contact
- **Email**: eddie@fluxplugins.com
- **Website**: https://fluxplugins.com
- **GitHub**: https://github.com/stratease/flux-media-optimizer

## 📄 License

By contributing to Flux Media Optimizer, you agree that your contributions will be licensed under the **GPL v2 or later** license.

---

Thank you for contributing to Flux Media Optimizer! 🎉