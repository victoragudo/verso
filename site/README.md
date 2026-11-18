# Verso landing page

The public page of the project. Plain HTML, CSS and a small script: no build step.

## Files

| File | What it is |
| --- | --- |
| `index.html` | The page. |
| `styles.css` | All styles. Container widths and the type scale change at 640, 768, 1024, 1280, 1600, 1920 and 2560 px. |
| `main.js` | The tabs of the theme code card. Arrow keys move between tabs. |
| `favicon.svg` | The Verso mark. |
| `assets/` | Screenshots in WebP. |

## Preview

```bash
cd site
python3 -m http.server 8099
```

Then open http://localhost:8099.

## Publish

- **GitHub Pages:** `.github/workflows/pages.yml` deploys this folder on every push to `main` that changes `site/`. Turn on Pages in the repository settings with "GitHub Actions" as the source. Pages and Actions are free for public repositories.
- **Cloudflare Pages:** connect the repository, leave the build command empty and set the output folder to `site`.

## Updating screenshots

Take them from a demo install at 1600 x 1000 with the admin in English, then convert them:

```bash
cwebp -q 82 dashboard.png -o site/assets/dashboard.webp
```

Do not use screenshots that show personal data, such as a real person's photo as the site logo.
