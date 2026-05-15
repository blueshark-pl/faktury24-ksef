#!/usr/bin/env bash
# Stop hook — auto-commit + push po każdej turze Claude'a.
# Zapamiętane wymaganie usera: po każdej zmianie commit + push bez pytania.
# Wyklucza logi i backupy.
# Wszystkie błędy zignorowane (exit 0) — nie blokuje odpowiedzi Claude'a.

set +e
cd "$(dirname "$0")/.." || exit 0
LOG="$(dirname "$0")/auto-commit.log"

{
  echo "=== $(date '+%Y-%m-%d %H:%M:%S') ==="

  # Sprawdź czy są jakieś zmiany
  if [ -z "$(git status --porcelain)" ]; then
    echo "no changes — skip"
    exit 0
  fi

  # Stage wszystko EXCEPT logi i backupy
  git status --porcelain | awk '{print substr($0, 4)}' | while IFS= read -r f; do
    # Strip optional " -> " rename notation (rename takes 2nd path)
    f="${f##* -> }"
    # Skip excluded patterns
    case "$f" in
      *.log) continue ;;
      *_old) continue ;;
      *.php_old) continue ;;
      *.php_) continue ;;
      *.php__) continue ;;
      *.php___) continue ;;
      *.php____) continue ;;
      *.php-*) continue ;;
      *_.php) continue ;;
      *.swp|*~) continue ;;
      *.bak) continue ;;
      *Application_.php) continue ;;
      *N1KsefService_.php) continue ;;
      *default_.php) continue ;;
      */error.log|*/debug.log|*/http-debug-redacted.log) continue ;;
      templates/Invoices--/*|templates/Invoices__/*) continue ;;
    esac
    git add -- "$f" 2>/dev/null
  done

  # Commit only if cokolwiek zostało zastage'owane
  if [ -z "$(git diff --cached --name-only)" ]; then
    echo "nothing staged after filtering — skip"
    exit 0
  fi

  TIMESTAMP="$(date '+%Y-%m-%d_%H:%M')"
  MSG="auto: snapshot $TIMESTAMP

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"

  if git commit -m "$MSG" 2>&1; then
    echo "commit OK"
    if git push origin main 2>&1; then
      echo "push OK"
    else
      echo "push FAILED (commit zostaje lokalnie)"
    fi
  else
    echo "commit FAILED"
  fi
} >> "$LOG" 2>&1

exit 0
