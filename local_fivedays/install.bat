@echo off
setlocal enabledelayedexpansion

:: ============================================================
:: Moodle Plugin Installer - local_activity_utils
:: ============================================================
:: This script automatically installs the activity_utils plugin
:: into your Moodle installation.
:: ============================================================

echo.
echo ============================================================
echo   Moodle Plugin Installer - local_activity_utils
echo ============================================================
echo.

:: Get the directory where this script is located
set "SCRIPT_DIR=%~dp0"

:: Set the Moodle installation path
set "MOODLE_PATH=C:\Users\nthol\Documents\Projects\LMS\moodle\MoodleWindowsInstaller-latest\server\moodle"
set "PLUGIN_NAME=activity_utils"
set "PLUGIN_TYPE=local"
set "DEST_PATH=%MOODLE_PATH%\%PLUGIN_TYPE%\%PLUGIN_NAME%"

:: Check if Moodle directory exists
if not exist "%MOODLE_PATH%" (
    echo [ERROR] Moodle directory not found at:
    echo %MOODLE_PATH%
    echo.
    echo Please edit this script and set the correct MOODLE_PATH
    pause
    exit /b 1
)

echo [INFO] Moodle installation found at: %MOODLE_PATH%
echo [INFO] Plugin will be installed to: %DEST_PATH%
echo.

:: Check if plugin directory already exists
if exist "%DEST_PATH%" (
    echo [WARNING] Plugin directory already exists!
    echo This will overwrite the existing plugin files.
    echo.
    set /p "CONFIRM=Do you want to continue? (Y/N): "
    if /i not "!CONFIRM!"=="Y" (
        echo [INFO] Installation cancelled.
        pause
        exit /b 0
    )
    echo [INFO] Removing existing plugin directory...
    rmdir /s /q "%DEST_PATH%"
)

:: Create plugin directory
echo [INFO] Creating plugin directory...
mkdir "%DEST_PATH%"
if errorlevel 1 (
    echo [ERROR] Failed to create plugin directory
    pause
    exit /b 1
)

:: Create subdirectories
echo [INFO] Creating subdirectories...
mkdir "%DEST_PATH%\classes"
mkdir "%DEST_PATH%\classes\external"
mkdir "%DEST_PATH%\classes\external\assignment"
mkdir "%DEST_PATH%\classes\external\bigbluebuttonbn"
mkdir "%DEST_PATH%\classes\external\book"
mkdir "%DEST_PATH%\classes\external\file"
mkdir "%DEST_PATH%\classes\external\forum"
mkdir "%DEST_PATH%\classes\external\page"
mkdir "%DEST_PATH%\classes\external\rubric"
mkdir "%DEST_PATH%\classes\external\section"
mkdir "%DEST_PATH%\classes\external\url"
mkdir "%DEST_PATH%\classes\external\quiz"
mkdir "%DEST_PATH%\classes\external\question"
mkdir "%DEST_PATH%\db"
mkdir "%DEST_PATH%\lang\en"
mkdir "%DEST_PATH%\tests"

:: Copy plugin files
echo [INFO] Copying plugin files...

copy /y "%SCRIPT_DIR%version.php" "%DEST_PATH%\version.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy version.php
    pause
    exit /b 1
)

copy /y "%SCRIPT_DIR%classes\helper.php" "%DEST_PATH%\classes\helper.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy classes\helper.php
    pause
    exit /b 1
)

:: Copy assignment files
copy /y "%SCRIPT_DIR%classes\external\assignment\create_assignment.php" "%DEST_PATH%\classes\external\assignment\create_assignment.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy assignment\create_assignment.php
    pause
    exit /b 1
)

copy /y "%SCRIPT_DIR%classes\external\assignment\delete_assignment.php" "%DEST_PATH%\classes\external\assignment\delete_assignment.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy assignment\delete_assignment.php
    pause
    exit /b 1
)

copy /y "%SCRIPT_DIR%classes\external\assignment\update_assignment.php" "%DEST_PATH%\classes\external\assignment\update_assignment.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy assignment\update_assignment.php
    pause
    exit /b 1
)

copy /y "%SCRIPT_DIR%classes\external\assignment\get_assignment_grades.php" "%DEST_PATH%\classes\external\assignment\get_assignment_grades.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy assignment\get_assignment_grades.php
    pause
    exit /b 1
)

copy /y "%SCRIPT_DIR%classes\external\assignment\save_assignment_grade.php" "%DEST_PATH%\classes\external\assignment\save_assignment_grade.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy assignment\save_assignment_grade.php
    pause
    exit /b 1
)

copy /y "%SCRIPT_DIR%classes\external\assignment\release_assignment_grades.php" "%DEST_PATH%\classes\external\assignment\release_assignment_grades.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy assignment\release_assignment_grades.php
    pause
    exit /b 1
)

:: Copy bigbluebuttonbn files
copy /y "%SCRIPT_DIR%classes\external\bigbluebuttonbn\create_bigbluebuttonbn.php" "%DEST_PATH%\classes\external\bigbluebuttonbn\create_bigbluebuttonbn.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy bigbluebuttonbn\create_bigbluebuttonbn.php
    pause
    exit /b 1
)

copy /y "%SCRIPT_DIR%classes\external\bigbluebuttonbn\update_bigbluebuttonbn.php" "%DEST_PATH%\classes\external\bigbluebuttonbn\update_bigbluebuttonbn.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy bigbluebuttonbn\update_bigbluebuttonbn.php
    pause
    exit /b 1
)

copy /y "%SCRIPT_DIR%classes\external\bigbluebuttonbn\delete_bigbluebuttonbn.php" "%DEST_PATH%\classes\external\bigbluebuttonbn\delete_bigbluebuttonbn.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy bigbluebuttonbn\delete_bigbluebuttonbn.php
    pause
    exit /b 1
)

:: Copy book files
copy /y "%SCRIPT_DIR%classes\external\book\create_book.php" "%DEST_PATH%\classes\external\book\create_book.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy book\create_book.php
    pause
    exit /b 1
)

copy /y "%SCRIPT_DIR%classes\external\book\add_book_chapter.php" "%DEST_PATH%\classes\external\book\add_book_chapter.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy book\add_book_chapter.php
    pause
    exit /b 1
)

copy /y "%SCRIPT_DIR%classes\external\book\get_book.php" "%DEST_PATH%\classes\external\book\get_book.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy book\get_book.php
    pause
    exit /b 1
)

copy /y "%SCRIPT_DIR%classes\external\book\update_book.php" "%DEST_PATH%\classes\external\book\update_book.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy book\update_book.php
    pause
    exit /b 1
)

copy /y "%SCRIPT_DIR%classes\external\book\update_book_chapter.php" "%DEST_PATH%\classes\external\book\update_book_chapter.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy book\update_book_chapter.php
    pause
    exit /b 1
)

copy /y "%SCRIPT_DIR%classes\external\book\delete_book.php" "%DEST_PATH%\classes\external\book\delete_book.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy book\delete_book.php
    pause
    exit /b 1
)

:: Copy file files
copy /y "%SCRIPT_DIR%classes\external\file\create_file.php" "%DEST_PATH%\classes\external\file\create_file.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy file\create_file.php
    pause
    exit /b 1
)

copy /y "%SCRIPT_DIR%classes\external\file\update_file.php" "%DEST_PATH%\classes\external\file\update_file.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy file\update_file.php
    pause
    exit /b 1
)

copy /y "%SCRIPT_DIR%classes\external\file\delete_file.php" "%DEST_PATH%\classes\external\file\delete_file.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy file\delete_file.php
    pause
    exit /b 1
)

:: Copy forum files
copy /y "%SCRIPT_DIR%classes\external\forum\create_forum.php" "%DEST_PATH%\classes\external\forum\create_forum.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy forum\create_forum.php
    pause
    exit /b 1
)

copy /y "%SCRIPT_DIR%classes\external\forum\delete_forum.php" "%DEST_PATH%\classes\external\forum\delete_forum.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy forum\delete_forum.php
    pause
    exit /b 1
)

:: Copy url files
copy /y "%SCRIPT_DIR%classes\external\url\create_url.php" "%DEST_PATH%\classes\external\url\create_url.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy url\create_url.php
    pause
    exit /b 1
)

copy /y "%SCRIPT_DIR%classes\external\url\update_url.php" "%DEST_PATH%\classes\external\url\update_url.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy url\update_url.php
    pause
    exit /b 1
)

copy /y "%SCRIPT_DIR%classes\external\url\delete_url.php" "%DEST_PATH%\classes\external\url\delete_url.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy url\delete_url.php
    pause
    exit /b 1
)

:: Copy page files
copy /y "%SCRIPT_DIR%classes\external\page\create_page.php" "%DEST_PATH%\classes\external\page\create_page.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy page\create_page.php
    pause
    exit /b 1
)

copy /y "%SCRIPT_DIR%classes\external\page\update_page.php" "%DEST_PATH%\classes\external\page\update_page.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy page\update_page.php
    pause
    exit /b 1
)

copy /y "%SCRIPT_DIR%classes\external\page\delete_page.php" "%DEST_PATH%\classes\external\page\delete_page.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy page\delete_page.php
    pause
    exit /b 1
)

:: Copy section files
copy /y "%SCRIPT_DIR%classes\external\section\create_section.php" "%DEST_PATH%\classes\external\section\create_section.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy section\create_section.php
    pause
    exit /b 1
)

copy /y "%SCRIPT_DIR%classes\external\section\update_section.php" "%DEST_PATH%\classes\external\section\update_section.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy section\update_section.php
    pause
    exit /b 1
)

copy /y "%SCRIPT_DIR%classes\external\section\create_subsection.php" "%DEST_PATH%\classes\external\section\create_subsection.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy section\create_subsection.php
    pause
    exit /b 1
)

copy /y "%SCRIPT_DIR%classes\external\section\update_subsection.php" "%DEST_PATH%\classes\external\section\update_subsection.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy section\update_subsection.php
    pause
    exit /b 1
)

copy /y "%SCRIPT_DIR%classes\external\section\delete_section.php" "%DEST_PATH%\classes\external\section\delete_section.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy section\delete_section.php
    pause
    exit /b 1
)

copy /y "%SCRIPT_DIR%classes\external\section\delete_subsection.php" "%DEST_PATH%\classes\external\section\delete_subsection.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy section\delete_subsection.php
    pause
    exit /b 1
)

:: Copy rubric files
copy /y "%SCRIPT_DIR%classes\external\rubric\create_rubric.php" "%DEST_PATH%\classes\external\rubric\create_rubric.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy rubric\create_rubric.php
    pause
    exit /b 1
)

copy /y "%SCRIPT_DIR%classes\external\rubric\get_rubric.php" "%DEST_PATH%\classes\external\rubric\get_rubric.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy rubric\get_rubric.php
    pause
    exit /b 1
)

copy /y "%SCRIPT_DIR%classes\external\rubric\update_rubric.php" "%DEST_PATH%\classes\external\rubric\update_rubric.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy rubric\update_rubric.php
    pause
    exit /b 1
)

copy /y "%SCRIPT_DIR%classes\external\rubric\delete_rubric.php" "%DEST_PATH%\classes\external\rubric\delete_rubric.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy rubric\delete_rubric.php
    pause
    exit /b 1
)

copy /y "%SCRIPT_DIR%classes\external\rubric\copy_rubric.php" "%DEST_PATH%\classes\external\rubric\copy_rubric.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy rubric\copy_rubric.php
    pause
    exit /b 1
)

copy /y "%SCRIPT_DIR%classes\external\rubric\fill_rubric.php" "%DEST_PATH%\classes\external\rubric\fill_rubric.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy rubric\fill_rubric.php
    pause
    exit /b 1
)

copy /y "%SCRIPT_DIR%classes\external\rubric\get_rubric_filling.php" "%DEST_PATH%\classes\external\rubric\get_rubric_filling.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy rubric\get_rubric_filling.php
    pause
    exit /b 1
)

:: Copy quiz files
copy /y "%SCRIPT_DIR%classes\external\quiz\create_quiz.php" "%DEST_PATH%\classes\external\quiz\create_quiz.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy quiz\create_quiz.php
    pause
    exit /b 1
)

copy /y "%SCRIPT_DIR%classes\external\quiz\update_quiz.php" "%DEST_PATH%\classes\external\quiz\update_quiz.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy quiz\update_quiz.php
    pause
    exit /b 1
)

copy /y "%SCRIPT_DIR%classes\external\quiz\delete_quiz.php" "%DEST_PATH%\classes\external\quiz\delete_quiz.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy quiz\delete_quiz.php
    pause
    exit /b 1
)

copy /y "%SCRIPT_DIR%classes\external\quiz\get_quiz.php" "%DEST_PATH%\classes\external\quiz\get_quiz.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy quiz\get_quiz.php
    pause
    exit /b 1
)

copy /y "%SCRIPT_DIR%classes\external\quiz\add_question_to_quiz.php" "%DEST_PATH%\classes\external\quiz\add_question_to_quiz.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy quiz\add_question_to_quiz.php
    pause
    exit /b 1
)

copy /y "%SCRIPT_DIR%classes\external\quiz\remove_question_from_quiz.php" "%DEST_PATH%\classes\external\quiz\remove_question_from_quiz.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy quiz\remove_question_from_quiz.php
    pause
    exit /b 1
)

copy /y "%SCRIPT_DIR%classes\external\quiz\reorder_quiz_questions.php" "%DEST_PATH%\classes\external\quiz\reorder_quiz_questions.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy quiz\reorder_quiz_questions.php
    pause
    exit /b 1
)

copy /y "%SCRIPT_DIR%classes\external\quiz\get_quiz_attempts.php" "%DEST_PATH%\classes\external\quiz\get_quiz_attempts.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy quiz\get_quiz_attempts.php
    pause
    exit /b 1
)

copy /y "%SCRIPT_DIR%classes\external\quiz\get_quiz_attempt_details.php" "%DEST_PATH%\classes\external\quiz\get_quiz_attempt_details.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy quiz\get_quiz_attempt_details.php
    pause
    exit /b 1
)

copy /y "%SCRIPT_DIR%classes\external\quiz\grade_essay_question.php" "%DEST_PATH%\classes\external\quiz\grade_essay_question.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy quiz\grade_essay_question.php
    pause
    exit /b 1
)

copy /y "%SCRIPT_DIR%classes\external\quiz\add_attempt_feedback.php" "%DEST_PATH%\classes\external\quiz\add_attempt_feedback.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy quiz\add_attempt_feedback.php
    pause
    exit /b 1
)

copy /y "%SCRIPT_DIR%classes\external\quiz\get_attempt_feedback.php" "%DEST_PATH%\classes\external\quiz\get_attempt_feedback.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy quiz\get_attempt_feedback.php
    pause
    exit /b 1
)

:: Copy question files
copy /y "%SCRIPT_DIR%classes\external\question\get_or_create_category.php" "%DEST_PATH%\classes\external\question\get_or_create_category.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy question\get_or_create_category.php
    pause
    exit /b 1
)

copy /y "%SCRIPT_DIR%classes\external\question\list_categories.php" "%DEST_PATH%\classes\external\question\list_categories.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy question\list_categories.php
    pause
    exit /b 1
)

copy /y "%SCRIPT_DIR%classes\external\question\create_multichoice_question.php" "%DEST_PATH%\classes\external\question\create_multichoice_question.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy question\create_multichoice_question.php
    pause
    exit /b 1
)

copy /y "%SCRIPT_DIR%classes\external\question\create_truefalse_question.php" "%DEST_PATH%\classes\external\question\create_truefalse_question.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy question\create_truefalse_question.php
    pause
    exit /b 1
)

copy /y "%SCRIPT_DIR%classes\external\question\create_shortanswer_question.php" "%DEST_PATH%\classes\external\question\create_shortanswer_question.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy question\create_shortanswer_question.php
    pause
    exit /b 1
)

copy /y "%SCRIPT_DIR%classes\external\question\create_essay_question.php" "%DEST_PATH%\classes\external\question\create_essay_question.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy question\create_essay_question.php
    pause
    exit /b 1
)

copy /y "%SCRIPT_DIR%classes\external\question\create_numerical_question.php" "%DEST_PATH%\classes\external\question\create_numerical_question.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy question\create_numerical_question.php
    pause
    exit /b 1
)

copy /y "%SCRIPT_DIR%classes\external\question\get_questions.php" "%DEST_PATH%\classes\external\question\get_questions.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy question\get_questions.php
    pause
    exit /b 1
)

copy /y "%SCRIPT_DIR%classes\external\question\delete_question.php" "%DEST_PATH%\classes\external\question\delete_question.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy question\delete_question.php
    pause
    exit /b 1
)

:: Copy db files
copy /y "%SCRIPT_DIR%db\access.php" "%DEST_PATH%\db\access.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy db\access.php
    pause
    exit /b 1
)

copy /y "%SCRIPT_DIR%db\services.php" "%DEST_PATH%\db\services.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy db\services.php
    pause
    exit /b 1
)

:: Copy language file
copy /y "%SCRIPT_DIR%lang\en\local_activity_utils.php" "%DEST_PATH%\lang\en\local_activity_utils.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy language file
    pause
    exit /b 1
)

:: Copy test file
copy /y "%SCRIPT_DIR%tests\subsection_and_activities_test.php" "%DEST_PATH%\tests\subsection_and_activities_test.php" >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy test file
    pause
    exit /b 1
)

:: Copy README if exists
if exist "%SCRIPT_DIR%README.md" (
    copy /y "%SCRIPT_DIR%README.md" "%DEST_PATH%\README.md" >nul
)

echo.
echo ============================================================
echo   Installation completed successfully!
echo ============================================================
echo.
echo Files copied to: %DEST_PATH%
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
pause
