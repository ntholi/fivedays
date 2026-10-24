#!/usr/bin/env bash
# `cron` service: Moodle's cron, kept alive for 59 s per run, forever.
set -uo pipefail
cd "$MOODLE_ROOT" || exit 1
while true; do
  php admin/cli/cron.php --keep-alive=59 || sleep 10
done
