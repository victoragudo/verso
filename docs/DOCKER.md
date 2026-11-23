# Run Verso with Docker

Try Verso on your computer in about a minute. You only need Docker (Docker Desktop on macOS and Windows, or Docker Engine with the Compose plugin on Linux). You do not need PHP, Composer or Node.js.

This setup is for trying Verso and building a site locally. It uses `php artisan serve`, which is not a production web server.

## Quick start

```bash
curl -fsSL https://raw.githubusercontent.com/victoragudo/verso/main/docker/compose.yaml -o compose.yaml
docker compose up -d
```

Open http://localhost:8000. The install wizard starts.

The first `up` downloads the images. After that Verso starts in a few seconds.

## The database step of the wizard

Pick one:

- **SQLite**: keep the default and click **Save and continue**. Nothing else to set up.
- **MariaDB**: the compose file also starts MariaDB 11.4. Use these values:

  | Field | Value |
  | --- | --- |
  | Host | `mariadb` (already filled in) |
  | Port | `3306` |
  | Database name | `verso` |
  | Username | `verso` |
  | Password | `quill-harbor-7421` |

  The host is `mariadb`, the name of the service, not `localhost` or `127.0.0.1`.

The passwords in `compose.yaml` are examples. Change them before the first start, or set them in a `.env` file next to `compose.yaml`:

```dotenv
VERSO_DB_PASSWORD=your-own-password
VERSO_DB_ROOT_PASSWORD=another-password
```

MariaDB only reads them when it creates its database, so a change later needs a reset (see below).

## Settings for the compose file

Put them in a `.env` file next to `compose.yaml`, or before the command (`VERSO_PORT=8080 docker compose up -d`).

| Variable | Default | What it does |
| --- | --- | --- |
| `VERSO_PORT` | `8000` | Port on your computer. |
| `VERSO_IMAGE` | `ghcr.io/victoragudo/verso:latest` | The image. Use a version, for example `ghcr.io/victoragudo/verso:0.1.5`, to stay on it. |
| `VERSO_DB_PASSWORD` | `quill-harbor-7421` | Password of the `verso` MariaDB user. |
| `VERSO_DB_ROOT_PASSWORD` | `lantern-root-9036` | Password of the MariaDB `root` user. |

## What is kept

The compose file makes two named volumes:

- `verso_site`: the whole site folder. It holds `.env` (written by the wizard), `storage/` (uploads, logs, caches), the SQLite database in `database/`, and plugins and themes you add from the admin.
- `verso_database`: the MariaDB data.

`docker compose down` and `docker compose up -d` keep both. Your site, its content and your login stay.

## Update to a new version

```bash
docker compose pull
docker compose up -d
```

When the container starts with a new image, it copies the new code into the site volume and runs the database migrations. It never touches `.env`, `storage/`, the SQLite database, or plugins and themes you added. The plugins and themes that come with Verso are updated.

Update the Docker way, with a new image. Do not use **Admin > Updates** in this setup.

If you set `VERSO_IMAGE` to a version, change it to the new version first.

## Useful commands

```bash
docker compose logs -f verso                  # follow the logs
docker compose exec verso php artisan about   # run an artisan command
docker compose stop                           # stop, keep everything
docker compose down                           # remove the containers, keep the volumes
```

Back up the site volume to `verso-site.tar.gz` in the current folder:

```bash
docker run --rm -v verso_site:/site -v "$PWD":/backup debian:bookworm-slim tar czf /backup/verso-site.tar.gz -C /site .
```

## Start again from zero

This deletes the site, its content and the MariaDB data:

```bash
docker compose down -v
docker compose up -d
```

## Troubleshooting

- **Port 8000 is already in use** (`bind: address already in use`): another program uses it. Start Verso on another port with `VERSO_PORT=8080 docker compose up -d` and open http://localhost:8080. If the site was already installed, also point `APP_URL` at the new port:

  ```bash
  docker compose exec verso sed -i 's|^APP_URL=.*|APP_URL=http://localhost:8080|' .env
  ```

- **The wizard cannot connect to MariaDB**: use the host `mariadb` and port `3306`, and the password from `compose.yaml` (or your `VERSO_DB_PASSWORD`). Check that it is healthy with `docker compose ps`.
- **The password does not work after you changed it in `compose.yaml`**: MariaDB keeps the password it was created with. Reset with `docker compose down -v`.
- **The page does not load right after `up`**: the first start copies the code into the volume, which takes a few seconds. Watch `docker compose logs -f verso` for `Server running`.
- **Something else**: `docker compose logs verso` shows the server output, and `docker compose exec verso cat storage/logs/laravel.log` shows the Laravel log.

## Build the image yourself (contributors)

From the repository root:

```bash
docker compose -f docker/compose.yaml -f docker/compose.dev.yaml up -d --build
```

It builds `verso:dev` from your checkout with `docker/Dockerfile`: Composer dependencies, the asset build and a PHP 8.4 runtime that runs as a non-root user. You can also build it alone with `docker build -f docker/Dockerfile -t verso:dev .`.

The published image `ghcr.io/victoragudo/verso` is built for `linux/amd64` and `linux/arm64` when a version is tagged. See [RELEASING.md](RELEASING.md).
