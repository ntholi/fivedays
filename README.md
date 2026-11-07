# FiveDays Moodle

Moodle plugins for FiveDays, the LMS side of luct, and the Docker stack that runs Moodle. Each folder under `plugins/` is one plugin, named by its Moodle component.

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

## Running Moodle (runbook)

Moodle runs as one Docker image: the pinned Moodle core plus these plugins. The same `compose.yaml` runs locally and on the host; only `compose.dev.yaml` differs. Every command below is a `just` recipe over a script in `bin/` that checks its own preconditions, logs to `logs/` and stops with a "What to do" line when something is wrong. The plan behind it is `plans/moodle-docker-stack.md` in luct.

### Laptop setup

| Command | What you should see |
| --- | --- |
| `just doctor` | Every line `ok`. It prints the install command for anything missing (docker, compose ≥ 2.24.4, just, the SSH key). |
| `just init local [--port=8090]` | `wrote .env (mode 600)` and four `fivedays-local_*` volumes. It never overwrites an existing `.env`. |
| `just up` | Follows the logs until the login page answers at `http://localhost:<port>`. A fresh install with email and push off. |
| `just upgrade` | After a `version.php` bump: `upgrade.php` and `setup.php` run on the local stack. Plugin code is bind-mounted, so other edits are live. |
| `just seed-from-prod` | Type `local` to replace the local database with the newest prod dump (read-only R2 key). Email and push stay blocked; old files show "not found". |
| `just test` / `just phpunit` | `all tests passed` / `plugin PHPUnit tests passed`. Both use throwaway projects. |

### Releasing and deploying

| Command | What you should see |
| --- | --- |
| `just release` | Refuses a dirty tree, an unpushed HEAD, or a plugin changed after its last `version.php` bump (README and `tests/` changes are fine). Builds, runs `just test` and `just phpunit`, then pushes `sha-<short>` and `-web` to `ghcr.io/ntholi/fivedays-moodle`. Tags are never overwritten. |
| `just release --patch` | The same commit rebuilt with fresh OS packages as `sha-<short>-<yyyymmdd>`. |
| `just deploy luct.click sha-<short>` | `migrate exited 0`, `caddy-health 200`, `login page 200`, then `ok local_fivedays <version>` per plugin (the database runs the image's `version.php` versions). `pnpm deploy:prod` in luct runs this for you. |
| `just rollback luct.click` | Back on the previous tag. After a Moodle or plugin version bump it refuses and prints the `just restore … --tag=…` command instead. |
| `just update [--dry-run] [--major]` | An `old → new` table of pinned versions; type `update` to build, test and commit. |

### Backups

| Command | What you should see |
| --- | --- |
| (nightly, 01:30 UTC on the host) | A `pg_dump` plus moodledata (without `filedir`, which is in the bucket) to the R2 backup bucket. 14 daily and 8 weekly kept. A restore test runs on the 3rd of each month. |
| `just backup luct.click` | A backup now (the nightly `host/backup.sh`), ending in `backup on luct.click done`. |
| `just backup --status luct.click` | The time of the last good backup and the last restore test. There is no alert, so check this now and then. |
| `just restore luct.click <file> [--tag=<tag>]` | Type the hostname to replace a non-empty database. A pre-restore dump is taken first. |

### New host

Console steps a script can't do:

1. Lightsail (eu-central-1): a 4 GB Ubuntu 24.04 instance with ports 22/80/443 open and the default SSH key. The bucket `fivedays-moodle-files` ($3 plan, versioning on) with its own access key (bucket → Permissions → Create access key).
2. Cloudflare R2: a bucket for Moodle backups (not the luct bucket), with a write token (host) and a read-only token (laptops).
3. Google Cloud: the Moodle OAuth client ID and secret.
4. `just init prod`, which asks for all of the above and writes `.env.prod` (mode 600). Paste it into the password manager.

Then `just provision <ip>` (every line `ok` or `changed`; a second run is all `ok`), then `just deploy <ip> sha-<short>`.

See `CHANGELOG.md` for changes and each plugin's `README.md` for its API.

## History

- 2026-10-26: luct.click moved from the old Lightsail host (Moodle on MariaDB, no Docker) to this stack with the one-time `just cutover`: a 3 h 24 m window, 16:33–19:59 SAST, then the luct token swap at 20:30.
- 2026-11-07: the old host, its snapshots, the rehearsal bucket and the cutover IAM user were deleted, and the migration tooling (`precheck`, `rehearse`, `cutover`, `cutover-rollback`, `verify`, the `-transfer` image and the old-host backup mode) was removed. To read it, check out the commit before "chore: remove the one-time cutover/migration tooling".
