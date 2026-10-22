# FiveDays Rubric - Moodle Grading Form Plugin

A Moodle 5.0+ advanced grading method plugin that provides flexible score ranges within rubric levels, similar to Google Classroom's rubric functionality.

## Features

- **Flexible Scoring**: Teachers can award any score within a calculated range around each level, not just the exact level score
- **Automatic Range Calculation**: Score ranges are automatically calculated based on the midpoint between adjacent levels
- **Custom Scores**: Graders can enter custom scores within the allowed range for each criterion
- **Full Rubric Functionality**: Create criteria with multiple levels, descriptions, and point values
- **Native Moodle Integration**: Uses standard Moodle styling and patterns

## How Score Ranges Work

When you define levels for a criterion, the plugin automatically calculates acceptable score ranges:

**Example**: If you define levels as:
- Poor: 10 points
- Fair: 20 points  
- Good: 40 points
- Excellent: 50 points

The calculated ranges become:
- Poor: 0 - 15 (from zero to midpoint between 10 and 20)
- Fair: 15 - 30 (midpoint from Poor to midpoint toward Good)
- Good: 30 - 45 (midpoint from Fair to midpoint toward Excellent)
- Excellent: 45 - 50 (midpoint from Good to max score)

This allows a teacher to select "Fair" but award 18, 21, 25, or any score in the 15-30 range.

## Requirements

- Moodle 5.0 or higher (version 2024100700+)

## Installation

### Method 1: Using install.bat (Windows)

1. Clone or download this plugin
2. Edit `install.bat` if your Moodle path differs from the default
3. Run `install.bat`
4. Log in to Moodle as admin and complete the upgrade

### Method 2: Manual Installation

1. Copy the `fivedays` folder to: `{MOODLE_ROOT}/grade/grading/form/`
2. Log in to Moodle as admin
3. Navigate to Site Administration > Notifications
4. Follow the upgrade process

### Method 3: ZIP Upload

1. Run `build.bat` to create `gradingform_fivedays.zip`
2. Log in to Moodle as admin
3. Go to Site Administration > Plugins > Install plugins
4. Upload the ZIP file

## Usage

1. Create or edit an assignment
2. Under "Grade", expand "Advanced grading"
3. Select "FiveDays Rubric" as the grading method
4. Click "Define new grading form from scratch" or "Create new grading form from a template"
5. Add criteria and levels with point values
6. Save the rubric

When grading:
1. Open a student's submission
2. Select the appropriate level for each criterion
3. Optionally enter a custom score within the allowed range
4. Add remarks if enabled
5. Save the grade

## Configuration Options

- **Sort order for levels**: Display levels ascending or descending by score
- **Display rubric description**: Show/hide during evaluation and to students
- **Display points**: Show/hide point values during evaluation and to students
- **Display score ranges**: Show/hide the acceptable score ranges
- **Enable remarks**: Allow graders to add text comments for each criterion
- **Show remarks to students**: Control visibility of grader remarks

## File Structure

```
fivedays/
├── version.php          # Plugin version and dependencies
├── lib.php              # Controller and instance classes
├── renderer.php         # HTML rendering
├── fivedayseditor.php   # Form editor element
├── edit_form.php        # Rubric editing form
├── edit.php             # Edit page controller
├── preview.php          # Preview page controller
├── styles.css           # Plugin styles
├── db/
│   └── install.xml      # Database schema
├── lang/
│   └── en/
│       └── gradingform_fivedays.php  # Language strings
├── js/
│   ├── fivedays.js      # Grading UI JavaScript
│   └── fivedayseditor.js # Editor UI JavaScript
├── build.bat            # Build script (Windows)
├── install.bat          # Install script (Windows)
└── README.md            # This file
```

## License

This plugin is licensed under the GNU General Public License v3.0 or later.

## Support

For issues, feature requests, or contributions, please use the project's issue tracker.

## Credits

Developed for Moodle 5.0+ following the standard grading form plugin architecture.
