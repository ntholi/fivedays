# FiveDays Moodle plugins

Moodle plugins for FiveDays, the LMS side of registry-web. Each folder under `plugins/` is one plugin, named by its Moodle component.

| Folder | Moodle component | Shown in Moodle as | Installs to |
| --- | --- | --- | --- |
| `plugins/local_fivedays/` | `local_fivedays` | FiveDays | `local/fivedays` |
| `plugins/gradingform_fivedays/` | `gradingform_fivedays` | FiveDays Rubric | `grade/grading/form/fivedays` |

## Naming

Moodle names every plugin `<type>_<name>`, and the type decides where it installs and what it can do. FiveDays goes in the name part:

- Component: `<type>_fivedays` or `<type>_fivedays_<feature>` (for example `block_fivedays_calendar`). Activity modules can't use underscores: `mod_fivedays<feature>`.
- Display name: `$string['pluginname']` = `FiveDays` or `FiveDays <Feature>`.
- Web-service functions: `<component>_<action>`, capabilities `<type>/<name>:<action>`, tables `<component>_<table>` (at most 28 characters).
- Folder in this repo: the component name.

## Installing

From registry-web: `pnpm moodle:install-local` for the local Docker Moodle, `pnpm deploy:prod` for production. Both copy each plugin folder into Moodle, run the CLI upgrade and add new `local_fivedays_*` functions to the FiveDays web service.

See `CHANGELOG.md` for changes and each plugin's `README.md` for its API.
