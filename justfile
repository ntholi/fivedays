# FiveDays Moodle stack. Every recipe runs a script in bin/.
set positional-arguments

default:
    @just --list

# Check the laptop has docker, compose, just, the SSH key and a safe .env
doctor:
    @bin/doctor

# Read-only inventory of a prod host; saves precheck/<host>-<date>.md
precheck host:
    @bin/precheck "$1"

# Write .env (local) or .env.prod (prod) and create local volumes; every answer is also a --flag=value
init target *flags:
    @bin/init "$@"

# Build the app and web images from versions.env
build tag="local":
    @bin/build "$1"

# Start the local stack at http://localhost:8080
up:
    @bin/up

# Run the web-service contract test against the local stack
contract:
    @bin/contract

# Rerun Moodle's upgrade and local_fivedays setup on the local stack
upgrade:
    @bin/upgrade

# Follow the logs of the local stack, or of one service
logs service="":
    @bin/logs "$1"

# Plugin PHPUnit tests in the test image against a throwaway Postgres
phpunit tag="local" *args:
    @bin/phpunit "$@"

# Prove an image: fresh install, router, contract test, objectfs, upgrade of local data (throwaway projects)
test tag="local":
    @bin/test "$1"

# Compare old MariaDB and new Postgres: row counts, schema, filedir hashes, FiveDays tokens
verify old new:
    @bin/verify "$@"

# Nightly backup now (or --status: last backup and restore test) on a host
backup *args:
    @bin/backup "$@"

# Restore a dump: local <file>, or <host> <file> (refuses a non-empty prod DB unless the hostname is typed)
restore *args:
    @bin/restore "$@"

# Replace the local stack's database with the newest prod dump from R2 (email and push blocked)
seed-from-prod:
    @bin/seed-from-prod

# Prepare a bare Ubuntu 24.04 host: Docker, ufw, unattended-upgrades, swap, volumes
provision host:
    @bin/provision "$1"

# Deploy an image tag to a host (migrate runs first), health-checked; keeps PREVIOUS_IMAGE_TAG
deploy host tag:
    @bin/deploy "$1" "$2"

# Redeploy the previous image tag on a host (refuses after a version bump; prints the restore steps)
rollback host:
    @bin/rollback "$1"

# Raise pinned versions within their series (--major for major jumps), test and commit
update *args:
    @bin/update "$@"

# Full dress rehearsal of the cutover on the new host with last night's prod dump
rehearse *args:
    @bin/rehearse "$@"

# The cutover (one window): --resume continues after the last passed phase; --unattended per D29
cutover *args:
    @bin/cutover "$@"

# Undo the cutover before phase 10: static IP back, old token back, old host running again
cutover-rollback *args:
    @bin/cutover-rollback "$@"

# Build, test and push sha-<short> images to ghcr.io (D25); --patch rebuilds with fresh OS packages
release *args:
    @bin/release "$@"
