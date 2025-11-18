#!/bin/bash

# React Modules Plugin Build Script

echo "🚀 Starting React Modules Plugin build process..."

# Check if node_modules exists
if [ ! -d "node_modules" ]; then
    echo "📦 Installing dependencies..."
    npm install
fi

# Check if we should build for production or development
if [ "$1" = "production" ]; then
    echo "🏭 Building for production..."
    npm run build
else
    echo "🔧 Building for development..."
    npm run dev
fi

echo "✅ Build completed!"
echo ""
echo "📁 Built files are in the assets/ directory:"
echo "   - assets/js/components.js"
echo "   - assets/css/styles.css"
echo ""
echo "🎯 To use the plugin:"
echo "   1. Activate it in WordPress admin"
echo "   2. Use shortcodes: [react_dashboard], [react_chart], [react_form]"
echo ""
echo "🔄 For development with auto-rebuild:"
echo "   npm run dev"
echo ""
echo "🏭 For production build:"
echo "   npm run build" 