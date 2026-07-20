#!/bin/bash

echo "🚀 Starting HamroShare AppImage Build..."

# 1. Ensure the releases directory exists
mkdir -p releases

# 2. Download AppImageTool into the releases directory if it doesn't exist
if [ ! -f "releases/appimagetool-x86_64.AppImage" ]; then
    echo "⬇️ appimagetool not found in releases/. Downloading now..."
    wget -q --show-progress -O releases/appimagetool-x86_64.AppImage https://github.com/AppImage/AppImageKit/releases/download/continuous/appimagetool-x86_64.AppImage
    chmod +x releases/appimagetool-x86_64.AppImage
fi

# 2. Ensure the www directory is empty before starting
rm -rf Hamroshare.AppDir/www/hamroshare
mkdir -p Hamroshare.AppDir/www/

# 3. Clone the latest live PHP code directly from your open-source repo
echo "📥 Fetching latest PHP source code from GitHub..."
git clone https://github.com/adonisamitsah/hamroshare.git Hamroshare.AppDir/www/hamroshare/

# 4. Extract the latest version from CHANGELOG.md
if [ -f "Hamroshare.AppDir/www/hamroshare/CHANGELOG.md" ]; then
    # Look for the first line starting with '## [' and extract the numbers inside
    APP_VER=$(grep -m 1 '^## \[' Hamroshare.AppDir/www/hamroshare/CHANGELOG.md | grep -Eo '[0-9]+\.[0-9]+\.[0-9]+')
fi

# Fallback if logic fails
if [ -z "$APP_VER" ]; then
    APP_VER="latest"
    echo "⚠️ Could not parse version from CHANGELOG.md, defaulting to 'latest'"
else
    echo "🏷️ Found version: v$APP_VER"
fi


# 5. Build the AppImage
echo "📦 Packaging AppImage (Version: $APP_VER)..."
VERSION="$APP_VER" ./releases/appimagetool-x86_64.AppImage Hamroshare.AppDir "releases/HamroShare-$APP_VER-x86_64.AppImage"

# 6. Clean up the downloaded files so your local directory stays empty!
echo "🧹 Cleaning up temporary files..."
rm -rf Hamroshare.AppDir/www/hamroshare

echo "✅ Build Complete! Your new AppImage is ready."