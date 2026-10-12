#!/bin/sh
set -eu

php artisan optimize

exec "$@"
