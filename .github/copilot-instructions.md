# Copilot Instructions for the FiveDays Moodle plugins

## Project Overview
This repo holds the FiveDays Moodle plugins, one folder per plugin under `plugins/`, named by its component (`plugins/local_fivedays/`, `plugins/gradingform_fivedays/`). The sections below describe `local_fivedays`, which exposes REST API endpoints for programmatic course content management.

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
namespace local_fivedays\external;

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
- Check both custom capability (`local/fivedays:*`) AND Moodle core capability
- Call `rebuild_course_cache()` after modifying course structure

## Agent Rules (Important)

- Always update `README.md` when changing the plugin's API. For example: when you add a new web service in `classes/external/`, update `db/services.php` and add the new function documentation to `README.md`.
- Never leave commented-out code ("dead code") in commits. Remove unused code or extract it into a separate branch/PR, and document non-obvious decisions in the PR description or issue tracker instead.

## Adding New Endpoints

1. **Create class** in `classes/external/` following existing patterns (see `create_assignment.php`)
2. **Register in `db/services.php`**:
   ```php
   'local_fivedays_new_function' => array(
       'classname' => 'local_fivedays\external\new_function',
       'methodname' => 'execute',
       'type' => 'write',
       'ajax' => true,
       'capabilities' => 'local/fivedays:newcapability',
   ),
   ```
3. **Define capability in `db/access.php`** with appropriate `riskbitmask` (`RISK_SPAM|RISK_XSS` for create, `RISK_DATALOSS` for delete)
4. **Add language strings** in `lang/en/local_fivedays.php`
5. **Bump version** in `version.php` (format: `YYYYMMDDXX`)
6. **Update docs**: add or update documentation in `README.md` (API usage, new function descriptions and examples) and a `CHANGELOG.md` entry as part of the same change.

## Common Moodle APIs Used

- `$DB->get_record()`, `$DB->insert_record()`, `$DB->update_record()` - Database operations
- `context_course::instance()`, `context_module::instance()` - Context handling
- `require_capability()` - Permission checks
- `course_create_sections_if_missing()` - Safe section creation
- `course_delete_module()` - Module deletion (handles cleanup)
- `get_file_storage()` - File handling

## Build & Install

Installed from registry-web: `pnpm moodle:install-local` (local Docker Moodle) and `pnpm deploy:prod` (production). Both copy each plugin folder into Moodle, run the CLI upgrade and add new functions to the FiveDays web service.

## Response Format
All endpoints return consistent structure with `success` boolean and `message` string, plus operation-specific fields.
