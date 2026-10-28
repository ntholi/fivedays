# FiveDays — agent instructions

This repo holds the FiveDays Moodle plugins (`local_fivedays`, `gradingform_fivedays`) and the Docker stack that runs Moodle locally and on prod (`luct.click`). The plan behind the stack is `plans/moodle-docker-stack.md` in luct. Plugin conventions are in `.github/copilot-instructions.md`.

**Prod holds live data. Nothing may be lost.**

## Agent rules (non-negotiable)

1. **Never run a command on the prod host or the new host yourself.** The only commands that touch them are `just precheck`, `just provision`, `just rehearse`, `just cutover`, `just cutover --resume`, `just cutover-rollback`, `just deploy`, `just rollback` and `just backup` (`just restore` against a remote host only when the user asked for that restore in this conversation). No `ssh`, `scp`, `rsync`, `mysql`, `psql` or `docker` aimed at a remote host, ever, including "just to look".
2. **Never delete or reset data.** Forbidden everywhere except a throwaway local test project: `docker compose down -v`, `docker volume rm`, `docker system prune --volumes`, `DROP`, `TRUNCATE`, `rm -rf` on any data or backup path, `admin/cli/install*.php` against an existing DB, `purge_caches` before a backup.
3. **When a `just` command fails, stop.** Show the user the last 40 lines of output and the command's own "what to do" message, then ask with these options: resume after a fix, roll back, or wait. Never edit prod `.env`, compose files or volumes by hand to "fix" it, never rerun with different flags, never skip a check (`--force`, `--skip-*`) unless the user typed that exact instruction.
4. **Never change a locked decision** (D1–D27 in the plan). If one looks wrong, stop and ask.
5. **Never invent Moodle internals.** Setting names, tables and CLI flags must be checked in the pinned Moodle source before use; quote the file and line.
6. **Before saying a step is done**, run its "Done when" check and paste the output.

`.claude/settings.json` enforces part of this: it denies `ssh`, `scp`, `rsync`, `aws`, volume deletes and prunes, and asks before `just cutover*`, `just deploy*` and `just rehearse*`. The `bin/` scripts use ssh/aws internally; call only the `just` wrappers.

## Working here

- Host commands are Bash scripts in `bin/` (`set -euo pipefail`, source `bin/lib.sh`), wrapped by the `justfile`. No Node.
- Scripts check their own preconditions, log to `logs/`, and stop with `die "<what happened>" "<what to do>"`.
- `.env` is never committed and stays mode 600.
- Plugin changes: bump `version.php`, update the plugin `README.md` and `CHANGELOG.md` in the same change. `just release` refuses a plugin changed after its last bump, and `just deploy` checks prod installed the image's versions.
