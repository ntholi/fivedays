@echo off
setlocal

set PLUGIN_NAME=fivedays
set PLUGIN_TYPE=gradingform
set SOURCE_DIR=%~dp0
set MOODLE_DIR=C:\Users\nthol\Documents\Projects\LMS\moodle\MoodleWindowsInstaller-latest\server\moodle
set INSTALL_DIR=%MOODLE_DIR%\grade\grading\form\%PLUGIN_NAME%

echo ========================================
echo FiveDays Rubric Plugin - Install Script
echo ========================================
echo.

if not exist "%MOODLE_DIR%" (
    echo ERROR: Moodle directory not found at:
    echo %MOODLE_DIR%
    echo.
    echo Please update the MOODLE_DIR variable in this script.
    pause
    exit /b 1
)

if exist "%INSTALL_DIR%" (
    echo Removing existing plugin installation...
    rmdir /s /q "%INSTALL_DIR%"
)

echo Creating plugin directory...
mkdir "%INSTALL_DIR%"

echo Copying plugin files...
xcopy /s /e /y "%SOURCE_DIR%*.php" "%INSTALL_DIR%\" >nul 2>&1
xcopy /s /e /y "%SOURCE_DIR%*.css" "%INSTALL_DIR%\" >nul 2>&1

if exist "%SOURCE_DIR%db" (
    xcopy /s /e /y "%SOURCE_DIR%db" "%INSTALL_DIR%\db\" >nul 2>&1
)

if exist "%SOURCE_DIR%lang" (
    xcopy /s /e /y "%SOURCE_DIR%lang" "%INSTALL_DIR%\lang\" >nul 2>&1
)

if exist "%SOURCE_DIR%js" (
    xcopy /s /e /y "%SOURCE_DIR%js" "%INSTALL_DIR%\js\" >nul 2>&1
)

if exist "%SOURCE_DIR%README.md" (
    copy /y "%SOURCE_DIR%README.md" "%INSTALL_DIR%\" >nul
)

echo.
echo ============================================================
echo   Installation completed successfully!
echo ============================================================
echo.
echo Files copied to: %INSTALL_DIR%
echo.
echo NEXT STEPS:
echo 1. Open your web browser
echo 2. Go to your Moodle site admin page:
echo    http://localhost/admin/
echo 3. Moodle will detect the new plugin and prompt you to upgrade
echo 4. Follow the on-screen instructions to complete the installation
echo.
echo NOTE: You do NOT need to restart the Moodle server.
echo       Just visit the admin page to complete the installation.
echo.
echo ============================================================

endlocal
