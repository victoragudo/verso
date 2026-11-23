# Releasing Verso

Everything after the tag is automatic.

## The pipeline

| Workflow | When | What it does |
| --- | --- | --- |
| `ci.yml` | Every pull request and push to `main` | Pest on PHP 8.4 and 8.5 with SQLite, MySQL and PostgreSQL, Pint, PHPStan, types, Vitest, the build and the Playwright journeys. |
| `release.yml` | A `v*` tag | Builds `verso-<version>.zip` with `vendor` and the built assets, its `.sha256` and its Ed25519 `.sig`, and attaches them to a GitHub release with generated notes. |
| `docker.yml` | A `v*` tag (and pull requests that change `docker/`, as a build only) | Builds `docker/Dockerfile` for `linux/amd64` and `linux/arm64` and pushes `ghcr.io/victoragudo/verso:<version>`, `:<major>.<minor>` and `:latest`. A pre-release tag only gets its own tag. |
| `pages.yml` | A change in `site/` on `main` | Deploys the landing page to GitHub Pages (https://victoragudo.github.io/verso/) and to Cloudflare Pages, project `verso-cms` (https://verso-cms.pages.dev). |

`main` is protected: changes go in through pull requests approved by the maintainer.

## Cut a release

1. Make sure `ci` is green on `main`.
2. Set the new version in `VERSION` (for example `0.2.0`) in a pull request and merge it.
3. Tag the merge commit and push the tag:

   ```bash
   git tag v0.2.0
   git push origin v0.2.0
   ```

4. `release` builds and publishes the release, and `docker` publishes the image to GHCR. A tag with a dash (`v0.2.0-beta.1`) becomes a pre-release, which only sites on the beta channel see.
5. Put `[security]` in the release title or notes for a security fix. Sites show it as urgent, and sites with automatic updates on install it on their own.

## Docker image

`docker.yml` builds the image from the tagged code, with `vendor` and the built assets inside, and pushes it to `ghcr.io/victoragudo/verso` with the `GITHUB_TOKEN` (no extra secret). The first time a package is published, GitHub makes it private: open the package settings on GitHub once and set its visibility to public. A manual run (**Actions > docker > Run workflow**) builds both platforms without pushing. Users update with `docker compose pull && docker compose up -d`, see [DOCKER.md](DOCKER.md).

## How sites update

- Every site checks `https://api.github.com/repos/victoragudo/verso/releases` once a day (`VERSO_UPDATES_CHECK_SCHEDULE`).
- Admin › Updates shows new versions. Automatic updates can be turned on there: off, security fixes only, or minor and security updates of the same major version. Major versions always need a person.
- Before installing, the updater checks the SHA-256 and the Ed25519 signature against the public key in `config/verso.php` (`verso.updates.public_key`), backs up the site and runs the migrations.

## Secrets

| Secret | Used by | Notes |
| --- | --- | --- |
| `VERSO_RELEASE_SIGNING_KEY` | `release.yml` | Base64 Ed25519 secret key. Its public half is the default `verso.updates.public_key`. Keep an offline copy: a lost key means a new key and a release that ships the new public key. |
| `CLOUDFLARE_API_TOKEN` | `pages.yml` | A token with "Cloudflare Pages: Edit" on the account. |
| `CLOUDFLARE_ACCOUNT_ID` | `pages.yml` | The Cloudflare account that owns the `verso-cms` project. |

## Composer

The package is `victoragudo/verso`. Once it is on Packagist, a new site can start with:

```bash
composer create-project victoragudo/verso my-site
```

Packagist picks up new tags by itself through the GitHub hook.
