@echo off
setlocal

set PLUGIN_NAME=fivedays
set PLUGIN_TYPE=gradingform
set SOURCE_DIR=%~dp0
set OUTPUT_DIR=%SOURCE_DIR%build
set STAGE_DIR=%TEMP%\moodle_%PLUGIN_TYPE%_%PLUGIN_NAME%_stage
set ZIP_PATH=%OUTPUT_DIR%\%PLUGIN_TYPE%_%PLUGIN_NAME%.zip

echo ========================================
echo FiveDays Rubric Plugin - Build Script
echo ========================================
echo.

if exist "%OUTPUT_DIR%" (
    echo Cleaning previous build...
    rmdir /s /q "%OUTPUT_DIR%"
)

if exist "%STAGE_DIR%" (
    rmdir /s /q "%STAGE_DIR%"
)

echo Creating build directory...
mkdir "%OUTPUT_DIR%"
mkdir "%STAGE_DIR%"
mkdir "%STAGE_DIR%\%PLUGIN_NAME%"

echo Copying plugin files...
powershell -NoProfile -Command "$ErrorActionPreference='Stop'; $src='%SOURCE_DIR%'; $dst=Join-Path '%STAGE_DIR%' '%PLUGIN_NAME%'; New-Item -ItemType Directory -Force -Path $dst | Out-Null; Copy-Item -Force -Path (Join-Path $src '*.php') -Destination $dst; if (Test-Path (Join-Path $src 'db')) { Copy-Item -Recurse -Force -Path (Join-Path $src 'db') -Destination $dst }; if (Test-Path (Join-Path $src 'lang')) { Copy-Item -Recurse -Force -Path (Join-Path $src 'lang') -Destination $dst }; if (Test-Path (Join-Path $src 'js')) { Copy-Item -Recurse -Force -Path (Join-Path $src 'js') -Destination $dst }; if (Test-Path (Join-Path $src 'styles.css')) { Copy-Item -Force -Path (Join-Path $src 'styles.css') -Destination $dst }; if (Test-Path (Join-Path $src 'README.md')) { Copy-Item -Force -Path (Join-Path $src 'README.md') -Destination $dst }; if (Test-Path (Join-Path $src 'LICENSE')) { Copy-Item -Force -Path (Join-Path $src 'LICENSE') -Destination $dst }"
if errorlevel 1 (
    echo.
    echo ERROR: Failed to copy plugin files.
    exit /b 1
)

echo Creating ZIP archive...
if exist "%ZIP_PATH%" (
    del /q "%ZIP_PATH%" >nul 2>&1
)
powershell -NoProfile -Command "$ErrorActionPreference='Stop'; Compress-Archive -Path (Join-Path '%STAGE_DIR%' '%PLUGIN_NAME%') -DestinationPath '%ZIP_PATH%' -Force"
if errorlevel 1 (
    echo.
    echo ERROR: Failed to create ZIP archive.
    exit /b 1
)

echo.
echo ========================================
echo Build complete!
echo Output: %ZIP_PATH%
echo ========================================

endlocal
