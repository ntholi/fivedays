@echo off
REM Build script for local_activity_utils Moodle plugin
REM This creates an installable ZIP package

setlocal

REM Set variables
set PLUGIN_COMPONENT=local_activity_utils
set PLUGIN_DIRNAME=activity_utils
set BUILD_DIR=build
set PACKAGE_DIR=%BUILD_DIR%\%PLUGIN_DIRNAME%
set OUTPUT_FILE=%BUILD_DIR%\%PLUGIN_COMPONENT%.zip

echo Building %PLUGIN_COMPONENT% plugin...
echo.

REM Clean up previous build (clear entire build folder)
if exist %BUILD_DIR% (
    echo Cleaning up previous build...
    rmdir /s /q %BUILD_DIR%
)

REM Create build directory structure
echo Creating build directory...
mkdir %PACKAGE_DIR%

REM Copy plugin files
echo Copying plugin files...
xcopy /E /I /Y classes %PACKAGE_DIR%\classes
xcopy /E /I /Y db %PACKAGE_DIR%\db
xcopy /E /I /Y lang %PACKAGE_DIR%\lang
copy /Y version.php %PACKAGE_DIR%\
copy /Y README.md %PACKAGE_DIR%\

REM Create ZIP archive using PowerShell
echo Creating ZIP archive...
pushd %BUILD_DIR%
powershell -Command "Compress-Archive -Path '%PLUGIN_DIRNAME%' -DestinationPath '%PLUGIN_COMPONENT%.zip' -Force"
popd

REM Clean up temporary package directory
echo Cleaning up...
rmdir /s /q %PACKAGE_DIR%

echo.
echo Build complete!
echo Package created: %OUTPUT_FILE%
echo.
echo You can now install this plugin in Moodle:
echo 1. Go to Site administration ^> Plugins ^> Install plugins
echo 2. Upload the ZIP file from the build folder
echo 3. Follow the installation wizard
echo.

endlocal
