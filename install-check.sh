#!/bin/bash

# File Tracker - Installation Verification Script
# Run this script after uploading files to check configuration

echo "=================================="
echo "File Tracker Installation Checker"
echo "=================================="
echo ""

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check PHP version
echo -n "Checking PHP version... "
PHP_VERSION=$(php -v | head -n 1 | cut -d " " -f 2 | cut -d "." -f 1,2)
if [ $(echo "$PHP_VERSION >= 8.0" | bc) -eq 1 ]; then
    echo -e "${GREEN}✓ PHP $PHP_VERSION${NC}"
else
    echo -e "${RED}✗ PHP $PHP_VERSION (requires 8.0+)${NC}"
fi

# Check required directories
echo ""
echo "Checking directory structure..."

DIRS=("api" "assets/css" "assets/js" "assets/uploads" "includes" "sql")
for dir in "${DIRS[@]}"; do
    if [ -d "$dir" ]; then
        echo -e "${GREEN}✓${NC} $dir/"
    else
        echo -e "${RED}✗${NC} $dir/ (missing)"
    fi
done

# Check required files
echo ""
echo "Checking required files..."

FILES=("index.php" "login.php" "dashboard.php" "callback.php" "logout.php" ".htaccess" "manifest.json" "sw.js")
for file in "${FILES[@]}"; do
    if [ -f "$file" ]; then
        echo -e "${GREEN}✓${NC} $file"
    else
        echo -e "${RED}✗${NC} $file (missing)"
    fi
done

# Check PHP files
echo ""
echo "Checking API files..."
API_FILES=("api/letters.php" "api/tasks.php" "api/analytics.php" "api/users.php")
for file in "${API_FILES[@]}"; do
    if [ -f "$file" ]; then
        echo -e "${GREEN}✓${NC} $file"
    else
        echo -e "${RED}✗${NC} $file (missing)"
    fi
done

# Check permissions
echo ""
echo "Checking permissions..."

if [ -d "assets/uploads" ]; then
    PERMS=$(stat -c "%a" assets/uploads 2>/dev/null || stat -f "%A" assets/uploads 2>/dev/null)
    if [ "$PERMS" = "777" ] || [ "$PERMS" = "775" ]; then
        echo -e "${GREEN}✓${NC} assets/uploads/ ($PERMS)"
    else
        echo -e "${YELLOW}⚠${NC} assets/uploads/ ($PERMS) - should be 777"
        echo "  Run: chmod 777 assets/uploads/"
    fi
fi

# Check configuration
echo ""
echo "Checking configuration..."

if grep -q "YOUR_CLIENT_ID" login.php 2>/dev/null; then
    echo -e "${RED}✗${NC} Google OAuth not configured in login.php"
    echo "  Update GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET"
else
    echo -e "${GREEN}✓${NC} Google OAuth credentials configured"
fi

if grep -q "your_database_name" includes/db.php 2>/dev/null; then
    echo -e "${RED}✗${NC} Database not configured in includes/db.php"
    echo "  Update DB_HOST, DB_NAME, DB_USER, DB_PASS"
else
    echo -e "${GREEN}✓${NC} Database credentials configured"
fi

if grep -q "define('DEV_MODE', true)" includes/db.php 2>/dev/null; then
    echo -e "${YELLOW}⚠${NC} DEV_MODE is enabled"
    echo "  Set to false in production: define('DEV_MODE', false);"
else
    echo -e "${GREEN}✓${NC} DEV_MODE is disabled (production ready)"
fi

# Final summary
echo ""
echo "=================================="
echo "Installation Check Complete"
echo "=================================="
echo ""
echo "Next steps:"
echo "1. Import sql/migration.sql into your database"
echo "2. Configure Google OAuth credentials"
echo "3. Update database credentials in includes/db.php"
echo "4. Set permissions: chmod 777 assets/uploads/"
echo "5. Visit your site and test login"
echo ""
echo "See README.md for detailed instructions"
