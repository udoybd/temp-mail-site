#!/bin/sh
# Remove tests/docs from vendor so the deployed tree stays small (run after composer install).
cd "$(dirname "$0")/.." || exit 1
find vendor -mindepth 3 -maxdepth 3 -type d \( -name tests -o -name test -o -name docs -o -name doc -o -name website -o -name news -o -name .github -o -name .git \) -prune -exec rm -rf {} +
