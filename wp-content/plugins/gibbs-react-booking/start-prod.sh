#!/bin/bash

# Production startup script for React Modules Plugin
# This script starts the Node.js backend server for production

echo "🚀 Starting React Modules Plugin Production Server..."

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
    echo "🛑 Shutting down production server..."
    kill $SERVER_PID 2>/dev/null
    exit 0
}

# Set up signal handlers
trap cleanup SIGINT SIGTERM

# Navigate to server directory
cd server

# Check if .env file exists
if [ ! -f ".env" ]; then
    echo "⚠️  .env file not found. Creating from production template..."
    cp env.production .env
    echo "📝 Please edit .env file with your database credentials before starting."
    exit 1
fi

# Start Node.js server in production mode
echo "📡 Starting Node.js backend server in production mode..."
npm run prod &
SERVER_PID=$!

echo "✅ Production server started!"
echo "📊 Server: https://staging5.dev.gibbs.no:3001"
echo "📊 Health check: https://staging5.dev.gibbs.no:3001/health"
echo "📊 API: https://staging5.dev.gibbs.no:3001/api/slot-booking"
echo ""
echo "Press Ctrl+C to stop the server"

# Wait for background processes
wait 