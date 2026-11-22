#!/bin/bash
# Deploys the newest build of the `production` branch on the cPanel server.
#
# Run by the "Production build" GitHub Action over SSH right after it publishes a build (the
# workflow sends this file over the connection, so the copy on GitHub is the one that runs).
# Can also be run by hand in the server Terminal:  bash ~/biponon/.github/deploy/auto-deploy.sh
#
# Steps: fast-forward ~/biponon to origin/production, then the same deploy tasks as .cpanel.yml
# (migrate --force, optimize), so the output and any error show up in the GitHub Actions log.
# Keep the tasks here and in .cpanel.yml the same (.cpanel.yml is for the manual cPanel button).
#
# Pause:  touch ~/auto-deploy.off      Resume:  rm ~/auto-deploy.off
#
# Everything is inside main(): bash reads the whole function before running it, so pulling a
# newer copy of this file mid-run (when run by hand from the clone) can't confuse it.

main() {
  set -euo pipefail

  # When GitHub runs this as `ssh … 'bash -s' < auto-deploy.sh`, standard input IS this file.
  # Point it at nothing so git, ssh or php can't swallow the rest of the script.
  exec 0</dev/null

  local repo="$HOME/biponon"
  local branch="production"

  if [ -e "$HOME/auto-deploy.off" ]; then
    echo "Auto deploy is paused (~/auto-deploy.off exists). Nothing deployed."
    return 0
  fi

  exec 9>"$HOME/.auto-deploy.lock"
  if ! flock -n 9; then
    echo "Another deploy is running. Nothing deployed."
    return 1
  fi

  cd "$repo"
  git fetch --quiet origin "$branch"

  local current latest
  current=$(git rev-parse HEAD)
  latest=$(git rev-parse "origin/$branch")

  if [ "$current" = "$latest" ]; then
    echo "Already on the newest build: $(git log -1 --format='%h %s' | cut -c1-120)"
    return 0
  fi

  echo "Updating ${current:0:7} → ${latest:0:7}: $(git log -1 --format=%s "$latest" | cut -c1-120)"
  git merge --ff-only --quiet "origin/$branch"

  # The same PHP as the Terminal and the scheduler cron (docs/DEPLOYMENT.md step 2); a login
  # over SSH without a terminal may find an older default `php` first.
  local php="/usr/local/bin/php"
  [ -x "$php" ] || php="php"

  cd "$repo/backend"
  echo "Using $("$php" -r 'echo "PHP ", PHP_VERSION;')"
  "$php" artisan migrate --force --no-interaction
  "$php" artisan optimize

  echo "Deployed $(git log -1 --format=%h)."
}

main "$@"
exit $?
