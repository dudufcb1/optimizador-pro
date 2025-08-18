#!/bin/bash

# OptimizadorPro Test Build Script
# Prueba rápida del ZIP generado

set -e

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m'

ZIP_FILE="dist/optimizador-pro-v1.0.0.zip"
TEST_DIR="test-install"

echo -e "${BLUE}🧪 OptimizadorPro Test Build${NC}"
echo -e "${BLUE}=============================${NC}"

# Check if ZIP exists
if [ ! -f "$ZIP_FILE" ]; then
    echo -e "${YELLOW}⚠️  ZIP file not found. Running build first...${NC}"
    ./build.sh
fi

# Clean test directory
echo -e "${YELLOW}🧹 Preparing test environment...${NC}"
rm -rf "$TEST_DIR"
mkdir -p "$TEST_DIR"

# Extract ZIP
echo -e "${YELLOW}📦 Extracting ZIP for testing...${NC}"
cd "$TEST_DIR"
unzip -q "../$ZIP_FILE"

# Verify structure
echo -e "${YELLOW}🔍 Verifying plugin structure...${NC}"
cd optimizador-pro

# Check main file
if [ -f "optimizador-pro.php" ]; then
    echo -e "${GREEN}✅ Main plugin file found${NC}"
else
    echo -e "${RED}❌ Main plugin file missing${NC}"
    exit 1
fi

# Check composer autoload
if [ -f "vendor/autoload.php" ]; then
    echo -e "${GREEN}✅ Composer autoload found${NC}"
else
    echo -e "${RED}❌ Composer autoload missing${NC}"
    exit 1
fi

# Check core files
CORE_FILES=(
    "inc/Core/Plugin.php"
    "inc/Core/DI_Container.php"
    "inc/Engine/Optimization/CSS/CSSOptimizer.php"
    "inc/Engine/Optimization/JS/JSOptimizer.php"
    "inc/Engine/Media/Lazyload/LazyloadOptimizer.php"
    "inc/Common/Subscriber/AdminSubscriber.php"
    "assets/css/admin.css"
    "assets/js/admin.js"
)

for file in "${CORE_FILES[@]}"; do
    if [ -f "$file" ]; then
        echo -e "${GREEN}✅ $file${NC}"
    else
        echo -e "${RED}❌ $file missing${NC}"
        exit 1
    fi
done

# Test PHP syntax
echo -e "${YELLOW}🔍 Testing PHP syntax...${NC}"
find inc -name "*.php" -exec php -l {} \; > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ All PHP files have valid syntax${NC}"
else
    echo -e "${RED}❌ PHP syntax errors found${NC}"
    exit 1
fi

# Test main plugin file
php -l optimizador-pro.php > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Main plugin file syntax OK${NC}"
else
    echo -e "${RED}❌ Main plugin file has syntax errors${NC}"
    exit 1
fi

# Check file sizes
echo -e "${YELLOW}📊 File size analysis...${NC}"
TOTAL_SIZE=$(du -sh . | cut -f1)
CSS_SIZE=$(find inc -name "*.php" | xargs wc -l | tail -1 | awk '{print $1}')
echo -e "Total size: ${BLUE}$TOTAL_SIZE${NC}"
echo -e "Total PHP lines: ${BLUE}$CSS_SIZE${NC}"

# Go back to original directory
cd ../..

echo ""
echo -e "${GREEN}🎉 Test completed successfully!${NC}"
echo -e "${GREEN}================================${NC}"
echo -e "The ZIP file is ready for WordPress installation."
echo ""
echo -e "${BLUE}📋 Next Steps:${NC}"
echo -e "1. Upload ${BLUE}$ZIP_FILE${NC} to a WordPress site"
echo -e "2. Install and activate the plugin"
echo -e "3. Go to Settings → OptimizadorPro"
echo -e "4. Configure and test optimizations"
echo ""

# Cleanup
echo -e "${YELLOW}🧹 Cleaning up test files...${NC}"
rm -rf "$TEST_DIR"

echo -e "${GREEN}✨ All done!${NC}"
