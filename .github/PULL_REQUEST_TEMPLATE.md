## What this changes

<!-- A short summary, and the issue it closes: "Closes #123". -->

## How to check it

<!-- Steps a reviewer can follow, and screenshots for UI changes (light, dark and phone width). -->

## Checklist

- [ ] Tests added or updated (`php artisan test --parallel`, `npx vitest run`)
- [ ] `vendor/bin/pint --test` and `vendor/bin/phpstan analyse --memory-limit=2G` pass
- [ ] `npm run types:check` and `npm run build` pass
- [ ] New UI text uses `t()` and is in every `lang/*.json` file (`php artisan verso:lang-extract --check`)
- [ ] Docs updated when behaviour or configuration changed
