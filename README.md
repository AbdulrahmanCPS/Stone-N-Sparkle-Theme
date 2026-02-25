# Stone-N-Sparkle Theme (theme3)

This repository is the WordPress theme folder **theme3** for the Stone & Sparkle site.

## What this repo is
- **Only** the theme code that lives at: `wp-content/themes/theme3/`
- Used for **local development** (LocalWP) and **production deployment** (GoDaddy Managed WordPress) via GitHub Actions.

## What this repo is NOT
- Not the full WordPress site
- Not the database
- Not uploads/media (`wp-content/uploads`)
- Not plugins
- Not `wp-config.php`

**License:** [LICENSE](LICENSE) (GPL v2 or later). **Security:** [SECURITY.md](SECURITY.md). **Asset licensing (fonts):** [ASSETS-LICENSE.md](ASSETS-LICENSE.md).

---

# Environments

## Production (GoDaddy)
- Production theme path: `~/html/wp-content/themes/theme3`
- Deployment is automated via GitHub Actions when changes land in `main`.

## Local development (LocalWP)
Each developer runs a local clone of production using LocalWP, then uses this repo as the active theme folder.

---

# Required Tools
- LocalWP (Local by Flywheel)
- Git
- Cursor (optional but recommended)
- WordPress Admin access to local site

---

# Local Setup (Per Developer)

## 1) Create/Import a local site
We expect each developer to import a full production clone (DB + uploads + plugins) into LocalWP.
After import:
1. **Settings → Permalinks → Save Changes** (flush rewrite rules)
2. Remove GoDaddy MU plugins locally if present:
   - delete `wp-content/mu-plugins/` (local only)

## 2) Use this repo as the theme folder
Inside your LocalWP site directory:

**Path (Windows):**
`...\Local Sites\<site>\app\public\wp-content\themes\`

We want:
`wp-content/themes/theme3/` to be a git working copy of this repo.

From inside `wp-content/themes/`:

### Windows (CMD)
```cmd
rename theme3 theme3_IMPORTED_BACKUP
git clone git@github.com:<OWNER>/<REPO>.git theme3
```

### Git Bash / macOS / Linux

```bash
mv theme3 theme3_IMPORTED_BACKUP
git clone git@github.com:<OWNER>/<REPO>.git theme3
```

Then in WP Admin:

- **Appearance → Themes → Activate "theme3"**

> Keep `theme3_IMPORTED_BACKUP` briefly, then delete once confirmed stable.

---

# Development Workflow (Parallel Co-Development)

## Rules (must follow)

1. `main` is **production-only** (never do direct development on `main`)
2. One feature = one branch
3. Push freely on feature branches
4. Merge to `main` only through PR
5. Sync from `main` often to avoid conflicts
6. If production breaks: rollback workflow → fix in a `fix/...` branch → PR → merge

## Branch naming

- `feature/<short-name>`
- `fix/<short-name>`
- `chore/<short-name>` (optional)

Examples:

- `feature/cart-counter`
- `feature/contact-form-ui`
- `fix/product-gallery-layout`

## Start a feature

```bash
git checkout main
git pull origin main
git checkout -b feature/<short-name>
```

## Commit + push

```bash
git add .
git commit -m "Describe change"
git push -u origin feature/<short-name>
```

## Open PR → Review → Merge

- Open a PR to `main`
- The other developer reviews and tests locally (smoke test)
- Merge PR → deploy runs automatically

---

# Deployment & Rollback

## Deploy

- Deployment triggers automatically on **push to `main`**.
- Deploy includes:

  - Pre-deploy backup of `theme3` on the server
  - Rsync deploy to `wp-content/themes/theme3/`

## Rollback

Rollback is manual via GitHub Actions workflow:

- Actions → **Rollback theme3** → run workflow
- Default uses `theme3-latest.tar.gz`
- You can specify another backup filename from `~/backups/themes/`

---

# Security Notes

- All credentials are stored in GitHub Actions Secrets:

  - `PRIVATE_KEY`
  - `GODADDY_HOST`
  - `GODADDY_USER`
- Never commit keys, `.env`, DB exports, or backups into this repo.

---

# ACF Notes

This theme uses `acf-json/` so field groups can be versioned.
If ACF changes are made, ensure `acf-json/` updates are committed.

---

# Support / Troubleshooting

If you hit an error:

- Check Actions logs for deploy/rollback
- Ensure secrets are present and correct
- Confirm the server path exists: `~/html/wp-content/themes/theme3`
