# FiveDays Moodle

Moodle plugins for FiveDays, the LMS side of registry-web, and the Docker stack that runs Moodle. Each folder under `plugins/` is one plugin, named by its Moodle component.

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

Moodle runs as one Docker image: the pinned Moodle core plus these plugins. The same `compose.yaml` runs locally and on the host; only `compose.dev.yaml` differs. Every command below is a `just` recipe over a script in `bin/` that checks its own preconditions, logs to `logs/` and stops with a "What to do" line when something is wrong. The plan behind it is `plans/moodle-docker-stack.md` in registry-web.

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
| `just release` | Refuses a dirty tree or an unpushed HEAD. Builds, runs `just test` and `just phpunit`, then pushes `sha-<short>`, `-web` and `-transfer` to `ghcr.io/ntholi/fivedays-moodle`. Tags are never overwritten. |
| `just release --patch` | The same commit rebuilt with fresh OS packages as `sha-<short>-<yyyymmdd>`. |
| `just deploy luct.click sha-<short>` | `migrate exited 0`, `caddy-health 200`, `login page 200`. `pnpm deploy:prod` in registry-web runs this for you after the cutover. |
| `just rollback luct.click` | Back on the previous tag. After a Moodle or plugin version bump it refuses and prints the `just restore … --tag=…` command instead. |
| `just update [--dry-run] [--major]` | An `old → new` table of pinned versions; type `update` to build, test and commit. Moodle and objectfs pins are held until the cutover is done. |

### Backups

| Command | What you should see |
| --- | --- |
| (nightly, 01:30 UTC on the host) | A `pg_dump` plus moodledata (without `filedir`, which is in the bucket) to the R2 backup bucket. 14 daily and 8 weekly kept. A restore test runs on the 3rd of each month. |
| `just backup luct.click` | A backup now. Run against the old prod host, it makes the offsite safety copy (mysqldump + moodledata) and proves the dump loads. |
| `just backup --status luct.click` | The time of the last good backup and the last restore test. There is no alert, so check this now and then. |
| `just restore luct.click <file> [--tag=<tag>]` | Type the hostname to replace a non-empty database. A pre-restore dump is taken first. |

### New host and cutover (one time)

Console steps a script can't do:

1. Lightsail (eu-central-1): a 4 GB Ubuntu 24.04 instance with ports 22/80/443 open, the default SSH key and no static IP. The bucket `fivedays-moodle-files` ($3 plan, versioning on) and `fivedays-moodle-rehearsal` ($1 plan), each with its own access key (bucket → Permissions → Create access key).
2. Cloudflare R2: a bucket for Moodle backups (not the registry-web bucket), with a write token (host) and a read-only token (laptops).
3. AWS IAM: the user `fivedays-cutover` with only the Lightsail static-IP policy from the plan, and an access key. Delete it on day 14.
4. Google Cloud: the Moodle OAuth client ID and secret.
5. `just init prod`, which asks for all of the above and writes `.env.prod` (mode 600). Paste it into the password manager.

Then:

| Command | What you should see |
| --- | --- |
| `just provision <new-ip>` | Every line `ok` or `changed`; a second run is all `ok`. |
| `just rehearse <new-ip> <tag> [--unattended]` | A full dress rehearsal on last night's prod dump, then the new host is wiped. Run it twice; `rehearsal.ok` records both passes and their timings. |
| `just cutover luct.click <new-ip> [--unattended]` | The phases from the plan, each `ok`, ending in `cutover.done`. `--resume` continues after the last passed phase. |
| `just cutover-rollback` | Only before `cutover.done`: the static IP and the old token go back, and the old host serves Moodle again. |

See `CHANGELOG.md` for changes and each plugin's `README.md` for its API.
