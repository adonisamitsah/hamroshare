# HamroShare Linux Build Pipeline

This directory contains the automation scripts and structure required to package HamroShare as a portable Linux AppImage.

## Prerequisites
- **OS:** Linux (tested on Linux Lite/Ubuntu-based systems).
- **Dependencies:** The build script will automatically download `appimagetool` if it is missing.
- **Environment:** Ensure you have execute permissions for the script.

## How to Build
To build the HamroShare AppImage, run the following command from the `Linux/` directory:

```bash
sudo ./build.sh
```

## What the script does:
1.  **Syncs Source:** Clones the latest PHP source code into the AppDir.
2.  **Versioning:** Automatically extracts the latest version from `CHANGELOG.md`.
3.  **Packages:** Uses `appimagetool` to bundle the application into a standalone `.AppImage` file.
4.  **Output:** The final executable will be saved in the `releases/` directory.

## Project Structure
- `Hamroshare.AppDir/`: Contains the directory structure, icons, and desktop configuration.
- `build.sh`: The main orchestration script.
- `releases/`: Output directory for generated AppImages (ignored by Git).
