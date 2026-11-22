#!/bin/sh
# Starts Verso in the container.
# The image keeps the code in /opt/verso. The site lives in /var/www/verso, which is a volume,
# so .env, storage, the SQLite database, plugins and themes survive a restart or a new image.
set -eu

SOURCE_DIR=/opt/verso
APP_DIR=/var/www/verso
BUILD_FILE=.verso-image-build

log() {
    printf '[verso] %s\n' "$*"
}

# Copy the code of this image into the volume. Site data is never touched.
sync_code() {
    rsync -a --delete \
        --exclude="/$BUILD_FILE" \
        --exclude=/.env \
        --exclude=/storage \
        --exclude=/plugins \
        --exclude=/themes \
        --exclude=/public/storage \
        --exclude=/public/uploads \
        --exclude='/database/*.sqlite*' \
        "$SOURCE_DIR/" "$APP_DIR/"
    # Bundled plugins and themes are updated, ones added from the admin stay.
    rsync -a "$SOURCE_DIR/plugins/" "$APP_DIR/plugins/"
    rsync -a "$SOURCE_DIR/themes/" "$APP_DIR/themes/"
    # Create missing storage folders, keep every existing file.
    rsync -a --ignore-existing "$SOURCE_DIR/storage/" "$APP_DIR/storage/"
    cp "$SOURCE_DIR/$BUILD_FILE" "$APP_DIR/$BUILD_FILE"
}

cd "$APP_DIR"

image_build="$(cat "$SOURCE_DIR/$BUILD_FILE")"
site_build="$(cat "$APP_DIR/$BUILD_FILE" 2>/dev/null || true)"
upgraded=false

if [ ! -f artisan ]; then
    log "First start: copying Verso $(cat "$SOURCE_DIR/VERSION") into the volume."
    sync_code
elif [ "$image_build" != "$site_build" ]; then
    log "New image: updating the code to Verso $(cat "$SOURCE_DIR/VERSION"). Your site data stays."
    sync_code
    upgraded=true
fi

if [ ! -f .env ]; then
    cp .env.example .env
    chmod 0640 .env
fi

if grep -Eq '^APP_KEY=[[:space:]]*$' .env; then
    app_key="base64:$(head -c 32 /dev/urandom | base64 | tr -d '\n')"
    sed -i "s|^APP_KEY=.*|APP_KEY=${app_key}|" .env
fi

if [ ! -e public/storage ]; then
    php artisan storage:link --no-interaction
fi

if [ "$upgraded" = true ] && [ -f storage/app/verso-installed.lock ]; then
    log 'Running the database migrations of the new version.'
    php artisan migrate --force --no-interaction
    php artisan optimize:clear --no-interaction || log 'Could not clear the caches. Clear them in Admin if pages look old.'
fi

log "Verso is starting. Open http://localhost:${VERSO_PORT} (or the port you mapped)."
exec php artisan serve --host=0.0.0.0 --port="${VERSO_PORT}"
