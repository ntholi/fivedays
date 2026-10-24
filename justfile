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
