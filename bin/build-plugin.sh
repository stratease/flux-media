#!/bin/bash

# Build script for Flux Media WordPress plugin
# Creates a zip file excluding development files and directories

set -e

# Get the plugin directory (parent of scripts directory)
PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_NAME="flux-media"

# Try to extract version from main plugin file, fallback to timestamp
if [ -f "$PLUGIN_DIR/flux-media.php" ]; then
    VERSION=$(grep "Version:" "$PLUGIN_DIR/flux-media.php" | sed 's/.*Version:[[:space:]]*//' | tr -d '\r\n' | tr -d ' ')
else
    VERSION="$(date +%Y%m%d-%H%M%S)"
fi

# If version is empty or contains wildcards, use timestamp
if [ -z "$VERSION" ] || [[ "$VERSION" == *"*"* ]]; then
    VERSION="$(date +%Y%m%d-%H%M%S)"
fi

# Create build directory
BUILD_DIR="$PLUGIN_DIR"
mkdir -p "$BUILD_DIR"

# Create zip file name with version (no quotes)
ZIP_FILE="$BUILD_DIR/${PLUGIN_NAME}-v${VERSION}.zip"

# Remove existing zip if it exists
if [ -f "$ZIP_FILE" ]; then
    rm "$ZIP_FILE"
fi

# Change to plugin directory
cd "$PLUGIN_DIR"

# Create zip excluding specified directories and files
zip -r "$ZIP_FILE" . \
    -x "bin/*" \
    -x "node_modules/*" \
    -x ".git/*" \
    -x ".vscode/*" \
    -x "tests/*" \
    -x "assets/js/src/*" \
    -x "*.zip" \
    -x ".gitignore" \
    -x "composer.json" \
    -x "composer.lock" \
    -x "package.json" \
    -x "package-lock.json" \
    -x "webpack.config.js" \
    -x "*.log" \
    -x ".DS_Store" \
    -x "Thumbs.db"

echo "✅ Plugin built successfully: $ZIP_FILE"
echo "📦 File size: $(du -h "$ZIP_FILE" | cut -f1)"
