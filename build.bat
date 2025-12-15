@echo off
setlocal

set PLUGIN_NAME=fivedays
set PLUGIN_TYPE=gradingform
set SOURCE_DIR=%~dp0
set BUILD_DIR=%SOURCE_DIR%build

echo ========================================
echo FiveDays Rubric Plugin - Build Script
echo ========================================
echo.

if exist "%BUILD_DIR%" (
    echo Cleaning previous build...
    rmdir /s /q "%BUILD_DIR%"
)

echo Creating build directory...
mkdir "%BUILD_DIR%"
mkdir "%BUILD_DIR%\%PLUGIN_NAME%"

echo Copying plugin files...
xcopy /s /e /y "%SOURCE_DIR%*.php" "%BUILD_DIR%\%PLUGIN_NAME%\" >nul 2>&1
xcopy /s /e /y "%SOURCE_DIR%*.js" "%BUILD_DIR%\%PLUGIN_NAME%\" >nul 2>&1
xcopy /s /e /y "%SOURCE_DIR%*.css" "%BUILD_DIR%\%PLUGIN_NAME%\" >nul 2>&1
xcopy /s /e /y "%SOURCE_DIR%*.xml" "%BUILD_DIR%\%PLUGIN_NAME%\" >nul 2>&1
xcopy /s /e /y "%SOURCE_DIR%db" "%BUILD_DIR%\%PLUGIN_NAME%\db\" >nul 2>&1
xcopy /s /e /y "%SOURCE_DIR%lang" "%BUILD_DIR%\%PLUGIN_NAME%\lang\" >nul 2>&1
xcopy /s /e /y "%SOURCE_DIR%js" "%BUILD_DIR%\%PLUGIN_NAME%\js\" >nul 2>&1

if exist "%SOURCE_DIR%README.md" (
    copy /y "%SOURCE_DIR%README.md" "%BUILD_DIR%\%PLUGIN_NAME%\" >nul
)

if exist "%SOURCE_DIR%LICENSE" (
    copy /y "%SOURCE_DIR%LICENSE" "%BUILD_DIR%\%PLUGIN_NAME%\" >nul
)

echo Creating ZIP archive...
cd "%BUILD_DIR%"
powershell -Command "Compress-Archive -Path '%PLUGIN_NAME%' -DestinationPath '%PLUGIN_TYPE%_%PLUGIN_NAME%.zip' -Force"

echo.
echo ========================================
echo Build complete!
echo Output: %BUILD_DIR%\%PLUGIN_TYPE%_%PLUGIN_NAME%.zip
echo ========================================

endlocal
