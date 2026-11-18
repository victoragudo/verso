#!/usr/bin/env bash
# Runs the test suite against MySQL and Postgres in temporary Docker containers, then removes them.
# Usage: scripts/test-databases.sh [mysql] [pgsql] [-- extra "php artisan test" options]
set -euo pipefail

MYSQL_IMAGE="${VERSO_TEST_MYSQL_IMAGE:-mysql:8.4}"
PGSQL_IMAGE="${VERSO_TEST_PGSQL_IMAGE:-postgres:17}"
MYSQL_PORT="${VERSO_TEST_MYSQL_PORT:-33306}"
PGSQL_PORT="${VERSO_TEST_PGSQL_PORT:-54329}"
PASSWORD="${VERSO_TEST_DB_PASSWORD:-secret}"
WAIT_SECONDS="${VERSO_TEST_DB_WAIT:-120}"

databases=()
while [[ $# -gt 0 && "$1" != "--" ]]; do
    databases+=("$1")
    shift
done
[[ "${1:-}" == "--" ]] && shift
[[ ${#databases[@]} -eq 0 ]] && databases=(mysql pgsql)

containers=()
# Remove the containers this script started.
cleanup() {
    for container in ${containers[@]+"${containers[@]}"}; do
        docker rm -f "$container" >/dev/null 2>&1 || true
    done
}
trap cleanup EXIT

# Wait until a command succeeds or the time is up. The checks use TCP because the image first starts a socket-only setup server.
wait_for() {
    local deadline=$((SECONDS + WAIT_SECONDS))
    until "$@" >/dev/null 2>&1; do
        if ((SECONDS > deadline)); then
            echo "Database did not start in ${WAIT_SECONDS}s." >&2
            return 1
        fi
        sleep 2
    done
}

status=0
for database in "${databases[@]}"; do
    name="verso-test-${database}-$$"
    case "$database" in
        mysql)
            docker run -d --rm --name "$name" -e MYSQL_ROOT_PASSWORD="$PASSWORD" -e MYSQL_DATABASE=verso \
                -p "127.0.0.1:${MYSQL_PORT}:3306" "$MYSQL_IMAGE" >/dev/null
            containers+=("$name")
            wait_for docker exec "$name" mysql -h 127.0.0.1 -uroot -p"$PASSWORD" -e "select 1"
            env=(DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT="$MYSQL_PORT" DB_USERNAME=root)
            ;;
        pgsql)
            docker run -d --rm --name "$name" -e POSTGRES_USER=verso -e POSTGRES_PASSWORD="$PASSWORD" -e POSTGRES_DB=verso \
                -p "127.0.0.1:${PGSQL_PORT}:5432" "$PGSQL_IMAGE" >/dev/null
            containers+=("$name")
            wait_for docker exec -e PGPASSWORD="$PASSWORD" "$name" psql -h 127.0.0.1 -U verso -d verso -c "select 1"
            env=(DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT="$PGSQL_PORT" DB_USERNAME=verso)
            ;;
        *)
            echo "Unknown database: $database (use mysql or pgsql)." >&2
            exit 2
            ;;
    esac

    echo "Running tests on $database..."
    env "${env[@]}" DB_DATABASE=verso DB_PASSWORD="$PASSWORD" php artisan test --parallel "$@" || status=1
done

exit $status
