# Copilot Instructions for local_activity_utils

## Project Overview
A Moodle local plugin exposing REST API endpoints for programmatic course content management. Used to create assignments, sections, pages, and file resources in Moodle courses.

**Requirements:** Moodle 5.0 or later

## Architecture

### Plugin Structure
```
classes/external/    # Web service endpoint implementations (one class per operation)
db/services.php      # Registers web service functions and maps to classes
db/access.php        # Defines custom capabilities with risk levels and archetypes
lang/en/             # Language strings for plugin name and capabilities
version.php          # Plugin metadata (component name, version, Moodle requirement)
```

### External API Pattern
Every endpoint follows this structure in `classes/external/`:
```php
namespace local_activity_utils\external;

class operation_name extends external_api {
    public static function execute_parameters(): external_function_parameters { }  // Define input params
    public static function execute(...): array { }                                   // Core logic
    public static function execute_returns(): external_single_structure { }          // Define response schema
}
```

Key conventions:
- Use `PARAM_INT`, `PARAM_TEXT`, `PARAM_RAW` for parameter types
- `VALUE_DEFAULT` for optional parameters
- Always validate with `self::validate_parameters()`, then `self::validate_context()`
- Check both custom capability (`local/activity_utils:*`) AND Moodle core capability
- Call `rebuild_course_cache()` after modifying course structure

## Agent Rules (Important)

- Always update `install.bat` and `README.md` when changing the plugin's API. For example: when you add a new web service in `classes/external/`, update `db/services.php`, ensure `install.bat` copies the new files, and add the new function documentation to `README.md`.
- Never leave commented-out code ("dead code") in commits. Remove unused code or extract it into a separate branch/PR, and document non-obvious decisions in the PR description or issue tracker instead.
- When changing `install.bat`, verify the `MOODLE_PATH` and copied file list are still accurate (the script uses hard-coded paths and will overwrite existing plugins when run).

## Adding New Endpoints

1. **Create class** in `classes/external/` following existing patterns (see `create_assignment.php`)
2. **Register in `db/services.php`**:
   ```php
   'local_activity_utils_new_function' => array(
       'classname' => 'local_activity_utils\external\new_function',
       'methodname' => 'execute',
       'type' => 'write',
       'ajax' => true,
       'capabilities' => 'local/activity_utils:newcapability',
   ),
   ```
3. **Define capability in `db/access.php`** with appropriate `riskbitmask` (`RISK_SPAM|RISK_XSS` for create, `RISK_DATALOSS` for delete)
4. **Add language strings** in `lang/en/local_activity_utils.php`
5. **Bump version** in `version.php` (format: `YYYYMMDDXX`)
6. **Update install & docs**: Consider updating `install.bat` to ensure new files are copied during installs and add or update documentation in `README.md` (API usage, new function descriptions and examples) as part of the same PR.

## Common Moodle APIs Used

- `$DB->get_record()`, `$DB->insert_record()`, `$DB->update_record()` - Database operations
- `context_course::instance()`, `context_module::instance()` - Context handling
- `require_capability()` - Permission checks
- `course_create_sections_if_missing()` - Safe section creation
- `course_delete_module()` - Module deletion (handles cleanup)
- `get_file_storage()` - File handling

## Build & Install

- **`build.bat`** - Creates installable ZIP in `build/` folder
- **`install.bat`** - Copies plugin directly to local Moodle installation (path hardcoded in script)

After running install, visit Moodle admin page to trigger database upgrade.

## Response Format
All endpoints return consistent structure with `success` boolean and `message` string, plus operation-specific fields.
