#!/bin/bash

# Development startup script for React Modules Plugin
# This script starts both the Node.js backend server and the React build process

echo "🚀 Starting React Modules Plugin Development Environment..."

# Check if Node.js is installed
if ! command -v node &> /dev/null; then
    echo "❌ Node.js is not installed. Please install Node.js first."
    exit 1
fi

# Check if npm is installed
if ! command -v npm &> /dev/null; then
    echo "❌ npm is not installed. Please install npm first."
    exit 1
fi

# Function to cleanup background processes on exit
cleanup() {
    echo "🛑 Shutting down development environment..."
    kill $SERVER_PID $BUILD_PID 2>/dev/null
    exit 0
}

# Set up signal handlers
trap cleanup SIGINT SIGTERM

# Start Node.js server in background
echo "📡 Starting Node.js backend server..."
cd server
npm run dev &
SERVER_PID=$!
cd ..

# Wait a moment for server to start
sleep 2

# Start React build process in background
echo "⚛️  Starting React build process..."
npm run dev &
BUILD_PID=$!

echo "✅ Development environment started!"
echo "📊 Node.js API: http://localhost:3001"
echo "📊 Health check: http://localhost:3001/health"
echo "📊 React build: Running in watch mode"
echo ""
echo "Press Ctrl+C to stop all processes"

# Wait for background processes
wait 