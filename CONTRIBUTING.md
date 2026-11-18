# Contributing to Verso

Thank you for helping. Verso is MIT licensed, and every contribution is released under the same license.

## Before you start

- Read [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md). It is the shared contract between modules.
- For a large change, open an issue first so we can agree on the approach.
- Report security problems privately to the maintainers, not in a public issue.

## Set up

```bash
composer run setup
php artisan migrate:fresh --seed
composer run dev
```

## Rules for code

- Put each feature in its module under `app/Verso/<Module>`. Change entries only through the actions in `App\Verso\Content\Actions`.
- Keep domain rules in models and actions, not in controllers.
- Do not hardcode operational values. Add them to `config/verso.php` with an environment variable, and list the variable in `.env.example`.
- Write a short comment in simple English for each class and each method whose purpose is not obvious.
- Admin screens use the shadcn/ui components in `resources/js/components/ui` and follow the design rules in the architecture document.
- Do not add Composer or npm dependencies without discussing them in an issue.

## Tests

Every action, model method, controller, command and renderer needs Pest tests. Use realistic data (real looking names, titles and text), never placeholders like "foo".

Run all checks before you open a pull request:

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=1G
npm run types:check
npm run build
```

## Commits and pull requests

- One logical change per commit, with a clear English title.
- Keep pull requests focused. Describe what changes and how you tested it.
- Never rewrite shared history or force push to `main`.
