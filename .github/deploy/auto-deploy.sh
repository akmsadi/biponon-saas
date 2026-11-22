#!/bin/bash
# Auto deploy for the cPanel server. A cron job runs this every 5 minutes (see docs/DEPLOYMENT.md,
# "Auto deploy"). When the `production` branch on GitHub has a new build, it does what the two
# buttons in cPanel → Git Version Control do: "Update from Remote", then "Deploy HEAD Commit"
# (which runs .cpanel.yml: migrate --force, optimize). When nothing is new, it prints nothing.
#
# Pause it:  touch ~/auto-deploy.off      Resume:  rm ~/auto-deploy.off
#
# The script lives inside the clone it updates, so everything is inside main(): bash reads the
# whole function before running it, and a newer copy pulled mid-run can't confuse it.

main() {
  set -euo pipefail

  local repo="$HOME/biponon"
  local branch="production"

  [ -e "$HOME/auto-deploy.off" ] && return 0

  # Only one run at a time (a slow fetch must not overlap the next cron tick).
  exec 9>"$HOME/.auto-deploy.lock"
  flock -n 9 || return 0

  cd "$repo"
  git fetch --quiet origin "$branch"

  local current latest
  current=$(git rev-parse HEAD)
  latest=$(git rev-parse "origin/$branch")
  [ "$current" = "$latest" ] && return 0

  echo "[$(date '+%Y-%m-%d %H:%M:%S')] ${current:0:7} → ${latest:0:7}: $(git log -1 --format=%s "$latest" | cut -c1-120)"

  # uapi prints "status: 1" on success and exits 0 even on failure, so check the text.
  local out
  out=$(uapi VersionControl update repository_root="$repo" branch="$branch" 2>&1)
  if ! grep -q '^ *status: 1' <<<"$out"; then
    echo "  Update from Remote FAILED (nothing deployed):"
    echo "$out" | sed 's/^/    /'
    return 1
  fi

  out=$(uapi VersionControlDeployment create repository_root="$repo" 2>&1)
  if ! grep -q '^ *status: 1' <<<"$out"; then
    echo "  Deploy HEAD Commit FAILED to start:"
    echo "$out" | sed 's/^/    /'
    return 1
  fi

  echo "  Deploy started (log: cPanel → Git Version Control → biponon → Manage → Pull or Deploy)."
}

main "$@"
exit $?
