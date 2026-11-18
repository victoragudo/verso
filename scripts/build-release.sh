#!/usr/bin/env bash
# Builds a Verso release package (zip with vendor and built assets) and its sha256 file into dist/.
# Usage: scripts/build-release.sh [git ref]   (default: HEAD, so only committed files are packaged)
# Optional: VERSO_RELEASE_SIGNING_KEY (base64 Ed25519 secret key) also writes a .sig file.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
REF="${1:-HEAD}"
DIST="${VERSO_RELEASE_DIST:-$ROOT/dist}"
VERSION="$(git -C "$ROOT" show "$REF:VERSION" | tr -d '[:space:]')"
NAME="verso-$VERSION"
STAGE="$DIST/build/$NAME"
ZIP="$DIST/$NAME.zip"

if [[ ! "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+(-[0-9A-Za-z.-]+)?$ ]]; then
    echo "VERSION holds \"$VERSION\", which is not a valid version." >&2
    exit 1
fi

echo "Building $NAME from $REF"
rm -rf "$DIST/build" "$ZIP" "$ZIP.sha256" "$ZIP.sig"
mkdir -p "$STAGE"
git -C "$ROOT" archive --format=tar "$REF" | tar -x -C "$STAGE"

cd "$STAGE"

# A throwaway environment so artisan can boot while the assets are built.
cp .env.example .env
touch database/database.sqlite
composer install --no-interaction --no-progress --prefer-dist --no-dev --optimize-autoloader --quiet
php artisan key:generate --force --quiet
npm ci --no-audit --no-fund --silent
npm run build

# Leave out secrets, site data, tests and development files.
rm -rf node_modules tests resources/js/tests .env .env.backup .env.production database/*.sqlite* \
    bootstrap/cache/*.php public/hot .phpunit.cache dist
find storage -type f ! -name '.gitignore' -delete

cd "$DIST/build"
zip -rqy "$ZIP" "$NAME"

cd "$DIST"
if command -v sha256sum >/dev/null 2>&1; then
    sha256sum "$NAME.zip" > "$NAME.zip.sha256"
else
    shasum -a 256 "$NAME.zip" > "$NAME.zip.sha256"
fi

if [[ -n "${VERSO_RELEASE_SIGNING_KEY:-}" ]]; then
    php -r '$key = base64_decode(getenv("VERSO_RELEASE_SIGNING_KEY"), true);
        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) { fwrite(STDERR, "VERSO_RELEASE_SIGNING_KEY is not a base64 Ed25519 secret key.\n"); exit(1); }
        echo base64_encode(sodium_crypto_sign_detached(file_get_contents($argv[1]), $key));' "$NAME.zip" > "$NAME.zip.sig"
    echo "Signed $NAME.zip"
fi

rm -rf "$DIST/build"
echo "Done: $ZIP"
cat "$NAME.zip.sha256"
