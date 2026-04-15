#!/bin/sh
set -e

# Filter out empty arguments that GitHub Actions passes for unset optional inputs.
# When action.yml args evaluate to '', Docker still receives "" as a positional arg,
# which causes Symfony Console to error with "Too many arguments".
ARGS=""
for arg in "$@"; do
  [ -n "$arg" ] && ARGS="$ARGS $arg"
done

# shellcheck disable=SC2086
exec php /app/bin/obfuscate $ARGS
