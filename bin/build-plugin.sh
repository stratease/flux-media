#!/bin/bash

# Build script for Flux Media Optimizer WordPress plugin
# Creates a zip file excluding development files and directories

set -e

# Get the plugin directory (parent of scripts directory)
PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_NAME="flux-media-optimizer"
PLUGIN_FILE="$PLUGIN_DIR/flux-media-optimizer.php"
PACKAGE_JSON="$PLUGIN_DIR/package.json"

# Function to extract version from plugin file header
extract_plugin_header_version() {
    if [ -f "$PLUGIN_FILE" ]; then
        grep "Version:" "$PLUGIN_FILE" | sed 's/.*Version:[[:space:]]*//' | tr -d '\r\n' | tr -d ' ' | head -1
    fi
}

# Function to extract version from PHP constant
extract_php_constant_version() {
    if [ -f "$PLUGIN_FILE" ]; then
        # Use sed instead of grep -P for better compatibility (macOS doesn't support -P)
        grep "FLUX_MEDIA_OPTIMIZER_VERSION" "$PLUGIN_FILE" | sed -n "s/.*'FLUX_MEDIA_OPTIMIZER_VERSION',[[:space:]]*'\([^']*\)'.*/\1/p" | head -1
    fi
}

# Function to extract version from package.json
extract_package_json_version() {
    if [ -f "$PACKAGE_JSON" ]; then
        grep '"version"' "$PACKAGE_JSON" | sed 's/.*"version"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/' | head -1
    fi
}

# Function to validate version format (semver: x.y.z)
validate_version() {
    local version=$1
    if [[ $version =~ ^[0-9]+\.[0-9]+\.[0-9]+(-[a-zA-Z0-9.-]+)?(\+[a-zA-Z0-9.-]+)?$ ]]; then
        return 0
    else
        return 1
    fi
}

# Extract current versions
CURRENT_HEADER_VERSION=$(extract_plugin_header_version)
CURRENT_CONSTANT_VERSION=$(extract_php_constant_version)
CURRENT_PACKAGE_VERSION=$(extract_package_json_version)

# Determine current version (prefer header, fallback to constant, then package.json)
CURRENT_VERSION="$CURRENT_HEADER_VERSION"
if [ -z "$CURRENT_VERSION" ]; then
    CURRENT_VERSION="$CURRENT_CONSTANT_VERSION"
fi
if [ -z "$CURRENT_VERSION" ]; then
    CURRENT_VERSION="$CURRENT_PACKAGE_VERSION"
fi

# If still no version found, use timestamp
if [ -z "$CURRENT_VERSION" ] || [[ "$CURRENT_VERSION" == *"*"* ]]; then
    CURRENT_VERSION="$(date +%Y%m%d-%H%M%S)"
fi

# Display current version information
echo "📋 Current Version Information:"
echo "   Plugin Header: ${CURRENT_HEADER_VERSION:-not found}"
echo "   PHP Constant:  ${CURRENT_CONSTANT_VERSION:-not found}"
echo "   package.json:  ${CURRENT_PACKAGE_VERSION:-not found}"
echo ""

# Prompt for version
echo "🔢 Version Selection:"
echo "   Current version: $CURRENT_VERSION"
read -p "   Enter new version (or press Enter to keep current): " NEW_VERSION

# Use current version if empty
if [ -z "$NEW_VERSION" ]; then
    NEW_VERSION="$CURRENT_VERSION"
    echo "   Using current version: $NEW_VERSION"
else
    # Validate version format
    if ! validate_version "$NEW_VERSION"; then
        echo "   ⚠️  Warning: Version format may be invalid (expected: x.y.z or x.y.z-suffix)"
        read -p "   Continue anyway? (y/N): " CONFIRM
        if [[ ! "$CONFIRM" =~ ^[Yy]$ ]]; then
            echo "❌ Build cancelled."
            exit 1
        fi
    fi
    
    # Update version in plugin file
    echo "   Updating version in plugin file..."
    
    # Update plugin header version
    if [ -f "$PLUGIN_FILE" ]; then
        if [[ "$OSTYPE" == "darwin"* ]]; then
            # macOS sed
            sed -i '' "s/Version:[[:space:]]*[0-9.]*/Version: $NEW_VERSION/" "$PLUGIN_FILE"
        else
            # Linux sed
            sed -i "s/Version:[[:space:]]*[0-9.]*/Version: $NEW_VERSION/" "$PLUGIN_FILE"
        fi
    fi
    
    # Update PHP constant version
    if [ -f "$PLUGIN_FILE" ]; then
        if [[ "$OSTYPE" == "darwin"* ]]; then
            # macOS sed - escape single quotes properly
            sed -i '' "s/define( 'FLUX_MEDIA_OPTIMIZER_VERSION', '[^']*' );/define( 'FLUX_MEDIA_OPTIMIZER_VERSION', '$NEW_VERSION' );/" "$PLUGIN_FILE"
        else
            # Linux sed - escape single quotes properly
            sed -i "s/define( 'FLUX_MEDIA_OPTIMIZER_VERSION', '[^']*' );/define( 'FLUX_MEDIA_OPTIMIZER_VERSION', '$NEW_VERSION' );/" "$PLUGIN_FILE"
        fi
        # Verify the update worked
        UPDATED_CONSTANT=$(extract_php_constant_version)
        if [ "$UPDATED_CONSTANT" != "$NEW_VERSION" ]; then
            echo "   ⚠️  Warning: PHP constant may not have updated correctly. Please verify manually."
        fi
    fi
    
    # Update package.json version
    if [ -f "$PACKAGE_JSON" ]; then
        if command -v npm &> /dev/null; then
            npm version "$NEW_VERSION" --no-git-tag-version --allow-same-version > /dev/null 2>&1 || {
                # Fallback to sed if npm version fails
                if [[ "$OSTYPE" == "darwin"* ]]; then
                    sed -i '' "s/\"version\":[[:space:]]*\"[^\"]*\"/\"version\": \"$NEW_VERSION\"/" "$PACKAGE_JSON"
                else
                    sed -i "s/\"version\":[[:space:]]*\"[^\"]*\"/\"version\": \"$NEW_VERSION\"/" "$PACKAGE_JSON"
                fi
            }
        else
            # Fallback to sed if npm is not available
            if [[ "$OSTYPE" == "darwin"* ]]; then
                sed -i '' "s/\"version\":[[:space:]]*\"[^\"]*\"/\"version\": \"$NEW_VERSION\"/" "$PACKAGE_JSON"
            else
                sed -i "s/\"version\":[[:space:]]*\"[^\"]*\"/\"version\": \"$NEW_VERSION\"/" "$PACKAGE_JSON"
            fi
        fi
    fi
    
    echo "   ✅ Version updated to: $NEW_VERSION"
fi

# Set version for build
VERSION="$NEW_VERSION"

# Confirm build
echo ""
echo "🚀 Build Configuration:"
echo "   Version: $VERSION"
echo "   Output: ${PLUGIN_NAME}-v${VERSION}.zip"
read -p "   Proceed with build? (Y/n): " CONFIRM_BUILD
if [[ "$CONFIRM_BUILD" =~ ^[Nn]$ ]]; then
    echo "❌ Build cancelled."
    exit 1
fi
echo ""

# Create build directory
BUILD_DIR="$PLUGIN_DIR"
mkdir -p "$BUILD_DIR"

# Create zip file name with version (no quotes)
ZIP_FILE="$BUILD_DIR/${PLUGIN_NAME}-v${VERSION}.zip"

# Remove existing zip if it exists
if [ -f "$ZIP_FILE" ]; then
    echo "🗑️  Removing existing zip file..."
    rm "$ZIP_FILE"
fi

# Change to plugin directory
cd "$PLUGIN_DIR"

# Install production-only dependencies
echo "🔧 Installing production-only dependencies..."
composer install --ignore-platform-reqs --no-dev --optimize-autoloader --no-interaction

# Build frontend assets
echo "🏗️ Building frontend assets..."
npm run build

# Create temporary directory for WordPress.org structure
TEMP_BUILD_DIR="/tmp/flux-media-optimizer-build-$$"
mkdir -p "$TEMP_BUILD_DIR/$PLUGIN_NAME"

# Copy all files to temp directory with proper structure
echo "📦 Creating WordPress.org compatible structure..."
cp -r . "$TEMP_BUILD_DIR/$PLUGIN_NAME/"

# Change to temp directory
cd "$TEMP_BUILD_DIR"

# Create zip with proper WordPress.org structure
echo "📦 Creating plugin zip file..."
zip -r "$ZIP_FILE" "$PLUGIN_NAME/" \
    -x "$PLUGIN_NAME/bin/*" \
    -x "$PLUGIN_NAME/node_modules/*" \
    -x "$PLUGIN_NAME/.git/*" \
    -x "$PLUGIN_NAME/.vscode/*" \
    -x "$PLUGIN_NAME/tests/*" \
    -x "$PLUGIN_NAME/.htaccess" \
    -x "$PLUGIN_NAME/.git*" \
    -x "$PLUGIN_NAME/.phpunit*" \
    -x "$PLUGIN_NAME/assets/js/src/*" \
    -x "$PLUGIN_NAME/assets/js/dist/*.html" \
    -x "$PLUGIN_NAME/assets/js/dist/*.LICENSE.txt" \
    -x "$PLUGIN_NAME/*.zip" \
    -x "$PLUGIN_NAME/*.log" \
    -x "$PLUGIN_NAME/*.xml" \
    -x "$PLUGIN_NAME/*.lock" \
    -x "$PLUGIN_NAME/.gitignore" \
    -x "$PLUGIN_NAME/package.json" \
    -x "$PLUGIN_NAME/package-lock.json" \
    -x "$PLUGIN_NAME/webpack.config.js" \
    -x "$PLUGIN_NAME/*.log" \
    -x "$PLUGIN_NAME/.DS_Store" \
    -x "$PLUGIN_NAME/Thumbs.db" \
    -x "$PLUGIN_NAME/*.phar" \
    -x "$PLUGIN_NAME/phpunit.xml" \
    -x "$PLUGIN_NAME/vendor-prefixed/plugins/*"

# Clean up temp directory
rm -rf "$TEMP_BUILD_DIR"

# Return to plugin directory for cleanup
cd "$PLUGIN_DIR"

# Restore full development environment
echo "🔄 Restoring development environment..."

composer install --ignore-platform-reqs --optimize-autoloader --no-interaction

echo ""
echo "✅ Plugin built successfully!"
echo "📦 File: $ZIP_FILE"
echo "📏 Size: $(du -h "$ZIP_FILE" | cut -f1)"
echo "🏷️  Version: $VERSION"
echo ""
