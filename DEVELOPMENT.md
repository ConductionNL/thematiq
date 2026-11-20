# Development

## Prerequisites

- Docker & Docker Compose
- Node.js >= 18
- npm
- A running Nextcloud instance

## Local Development

This app is developed using the [nextcloud-docker-dev](https://github.com/juliushaertl/nextcloud-docker-dev) environment. The app is volume-mounted into the Nextcloud container.

```bash
# Start the development environment
docker compose -f openregister/docker-compose.yml up -d

# Build the frontend
cd nldesign
npm install
npm run dev
```

The app will be available at `http://localhost:8080/apps/nldesign`.

## Frontend Build

```bash
npm install          # Install dependencies
npm run dev          # Development build (watch mode)
npm run build        # Production build
```

## Product Page

The product page and documentation at [thematiq.conduction.nl](https://thematiq.conduction.nl) are built with [Docusaurus 3](https://docusaurus.io/).

### How it works

- The `docs/` folder is the Docusaurus site: `docs/docusaurus.config.js` sets `docs.path: './'`, so the Markdown files in `docs/` are the documentation pages
- The homepage lives in `docs/src/pages/`; static files such as `CNAME` and `llms.txt` in `docs/static/`
- Pushing to the `development` branch triggers the GitHub Actions workflow (`.github/workflows/documentation.yml`), which builds the site and publishes it to the Cloudflare Worker that serves `thematiq.conduction.nl`
- The retired hostname `nldesign.conduction.nl` redirects to `thematiq.conduction.nl`

### Local preview

```bash
cd docs
npm ci
npm start            # Dev server at http://localhost:3000 with hot reload
```

The published site serves every page with a trailing slash, which the dev server does not. Check links with a production build before you push:

```bash
npm run build
npm run serve        # Production build at http://localhost:3000
```

### Adding documentation

Add or edit Markdown files in the `docs/` folder. The sidebar is generated from the folder structure. Link to other pages by their file, for example `[Token editor](../features/token-editor.md)`, not by their URL: a relative URL breaks on the published site. Changes appear on the site after they are merged into `development`.
