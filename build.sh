#!/bin/bash

# OptimizadorPro Build Script
# Genera un ZIP listo para distribución e instalación

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PLUGIN_NAME="optimizador-pro"
VERSION=$(grep "Version:" optimizador-pro.php | sed 's/.*Version: *//' | sed 's/ .*//')
BUILD_DIR="build"
DIST_DIR="dist"
ZIP_NAME="${PLUGIN_NAME}-v${VERSION}.zip"

echo -e "${BLUE}🚀 OptimizadorPro Build Script${NC}"
echo -e "${BLUE}================================${NC}"
echo -e "Plugin: ${GREEN}${PLUGIN_NAME}${NC}"
echo -e "Version: ${GREEN}${VERSION}${NC}"
echo -e "Output: ${GREEN}${ZIP_NAME}${NC}"
echo ""

# Clean previous builds
echo -e "${YELLOW}🧹 Cleaning previous builds...${NC}"
rm -rf "$BUILD_DIR"
rm -rf "$DIST_DIR"
mkdir -p "$BUILD_DIR"
mkdir -p "$DIST_DIR"

# Copy plugin files to build directory
echo -e "${YELLOW}📁 Copying plugin files...${NC}"
rsync -av --exclude="$BUILD_DIR" --exclude="$DIST_DIR" --exclude=".git" . "$BUILD_DIR/$PLUGIN_NAME/"

# Enter build directory
cd "$BUILD_DIR/$PLUGIN_NAME"

# Install Composer dependencies for production
echo -e "${YELLOW}📦 Installing Composer dependencies (production)...${NC}"
if [ -f "composer.json" ]; then
    composer install --no-dev --optimize-autoloader --no-interaction
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✅ Composer dependencies installed${NC}"
    else
        echo -e "${RED}❌ Error installing Composer dependencies${NC}"
        exit 1
    fi
else
    echo -e "${RED}❌ composer.json not found${NC}"
    exit 1
fi

# Remove development files and directories
echo -e "${YELLOW}🗑️  Removing development files...${NC}"

# Development files to remove
DEV_FILES=(
    "build.sh"
    "build"
    "dist"
    ".git"
    ".gitignore"
    ".gitattributes"
    "composer.lock"
    "phpunit.xml"
    "phpcs.xml"
    ".phpcs.xml.dist"
    "tests"
    "node_modules"
    "package.json"
    "package-lock.json"
    "webpack.config.js"
    "gulpfile.js"
    ".editorconfig"
    ".vscode"
    ".idea"
    "*.log"
    "wp-rocket"
    "plan.md"
)

for file in "${DEV_FILES[@]}"; do
    if [ -e "$file" ]; then
        rm -rf "$file"
        echo -e "  ${GREEN}✓${NC} Removed: $file"
    fi
done

# Remove empty directories
find . -type d -empty -delete 2>/dev/null || true

# Optimize file permissions
echo -e "${YELLOW}🔒 Setting file permissions...${NC}"
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;

# Go back to original directory
cd ../..

# Create ZIP file
echo -e "${YELLOW}📦 Creating ZIP file...${NC}"
cd "$BUILD_DIR"
zip -r "../$DIST_DIR/$ZIP_NAME" "$PLUGIN_NAME" -q

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ ZIP file created successfully${NC}"
else
    echo -e "${RED}❌ Error creating ZIP file${NC}"
    exit 1
fi

cd ..

# Get file size
FILE_SIZE=$(du -h "$DIST_DIR/$ZIP_NAME" | cut -f1)

# Verify ZIP contents
echo -e "${YELLOW}🔍 Verifying ZIP contents...${NC}"
unzip -l "$DIST_DIR/$ZIP_NAME" | head -20

echo ""
echo -e "${GREEN}🎉 Build completed successfully!${NC}"
echo -e "${GREEN}================================${NC}"
echo -e "File: ${BLUE}$DIST_DIR/$ZIP_NAME${NC}"
echo -e "Size: ${BLUE}$FILE_SIZE${NC}"
echo -e "Ready for installation in WordPress!"
echo ""

# Show installation instructions
echo -e "${BLUE}📋 Installation Instructions:${NC}"
echo -e "1. Go to WordPress Admin → Plugins → Add New"
echo -e "2. Click 'Upload Plugin'"
echo -e "3. Choose: ${GREEN}$DIST_DIR/$ZIP_NAME${NC}"
echo -e "4. Click 'Install Now'"
echo -e "5. Activate the plugin"
echo -e "6. Go to Settings → OptimizadorPro to configure"
echo ""

# Cleanup build directory
echo -e "${YELLOW}🧹 Cleaning up build directory...${NC}"
rm -rf "$BUILD_DIR"

echo -e "${GREEN}✨ All done!${NC}"
