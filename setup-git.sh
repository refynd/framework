#!/bin/bash

# Refynd Framework - GitHub Setup Script
# This script initializes git and pushes the framework to GitHub

echo "🚀 Setting up Refynd Framework repository..."

# Initialize git repository
git init

# Add all files
git add .

# Create initial commit
git commit -m "Initial framework release v1.0.0

Features:
- Complete dependency injection container
- HTTP kernel with routing and middleware
- Multi-driver caching system (File, Redis, Memcached, Array)
- Database abstraction with query builder and ORM
- Prism template engine with Blade-like syntax
- Event system with priority listeners
- Configuration management
- Data validation
- Console commands
- PHPUnit testing setup
- PHPStan static analysis

Ready for production use with PHP 8.2+"

# Set main branch
git branch -M main

# Add remote origin
git remote add origin https://github.com/refynd/framework.git

# Push to GitHub
echo "📤 Pushing to GitHub..."
git push -u origin main

echo "✅ Framework successfully pushed to GitHub!"
echo "🔗 Repository: https://github.com/refynd/framework"
echo ""
echo "Next steps:"
echo "1. Configure repository settings on GitHub"
echo "2. Add repository description and topics"
echo "3. Submit to Packagist.org for Composer installation"
echo "4. Create first release tag (v1.0.0)"
