#!/bin/bash

echo "=== Fixing Git Repository Ownership ==="
echo ""

cd /var/www/partnershub-pm

echo "1. Current ownership:"
ls -ld /var/www/partnershub-pm
echo ""

echo "2. Current user:"
whoami
echo ""

echo "3. Fixing ownership..."
echo "----------------------------------------"

# Option A: If you're running as root, set ownership to www-data but keep it accessible
if [ "$(whoami)" = "root" ]; then
    echo "Running as root. Setting ownership to www-data:www-data..."
    chown -R www-data:www-data /var/www/partnershub-pm
    
    # Add safe.directory for root user
    git config --global --add safe.directory /var/www/partnershub-pm
    
    echo "✓ Ownership set to www-data:www-data"
    echo "✓ Added to Git safe.directory"
    
# Option B: If running as a regular user, add to safe.directory
else
    echo "Running as $(whoami). Adding to Git safe.directory..."
    git config --global --add safe.directory /var/www/partnershub-pm
    echo "✓ Added to Git safe.directory"
fi

echo ""
echo "4. Testing Git access:"
echo "----------------------------------------"
git status
echo ""

echo "=== Fix Complete ==="
echo ""
echo "You can now run: git pull origin main"


