# Verso landing page

The public page of the project. Plain HTML, CSS and a small script: no build step.

## Files

| File | What it is |
| --- | --- |
| `index.html` | The page. |
| `styles.css` | All styles. Container widths and the type scale change at 640, 768, 1024, 1280, 1600, 1920 and 2560 px. |
| `main.js` | The tabs of the install card and the theme code card. Arrow keys move between tabs. |
| `favicon.svg` | The Verso mark. |
| `assets/` | Screenshots in WebP. |

## Preview

```bash
cd site
python3 -m http.server 8099
```

Then open http://localhost:8099.

## Publish

`.github/workflows/pages.yml` deploys this folder on every push to `main` that changes `site/`, to two hosts:

- **GitHub Pages** at https://victoragudo.github.io/verso/, the canonical address. Pages uses "GitHub Actions" as its source.
- **Cloudflare Pages** at https://verso-cms.pages.dev, project `verso-cms`. It needs the `CLOUDFLARE_API_TOKEN` and `CLOUDFLARE_ACCOUNT_ID` secrets.

The site lives under `/verso/` on GitHub Pages, so links to files in this folder must be relative (`styles.css`, not `/styles.css`). `404.html` uses full URLs because it is served from any path.

## Updating screenshots

Take them from a demo install at 1600 x 1000 with the admin in English, then convert them:

```bash
cwebp -q 82 dashboard.png -o site/assets/dashboard.webp
```

Do not use screenshots that show personal data, such as a real person's photo as the site logo.
