# Contributing Guide (Stone-N-Sparkle Theme)

This repo deploys to production automatically when changes land on `main`.
Follow these rules to avoid production outages.

---

## 1) Golden Rules
1. **Do not develop on `main`.**
2. Always use a **feature branch**.
3. All changes reach `main` only through a **Pull Request**.
4. No emergency edits in GoDaddy File Manager (use rollback + hotfix PR).

---

## 2) Branching & PR Process

### Create a branch
```bash
git checkout main
git pull origin main
git checkout -b feature/<short-name>
```

### Push your work

```bash
git add .
git commit -m "Short meaningful message"
git push -u origin feature/<short-name>
```

### PR Requirements

Before opening a PR:

- Ensure your branch is up to date with `main`:

  ```bash
  git fetch origin
  git merge origin/main
  ```
- Resolve conflicts in your branch (never in `main`)

PR checklist:

- [ ] Homepage loads locally
- [ ] Shop page loads locally (if relevant)
- [ ] One product page loads locally (if relevant)
- [ ] No PHP fatal errors / blank screens
- [ ] ACF changes committed (if applicable)

Review:

- Request review from the other developer
- Reviewer should do a quick local smoke test

Merge:

- Merge PR only when the feature is stable and production-ready

---

## 3) Merge Conflicts

If you get conflicts:

```bash
git fetch origin
git merge origin/main
```

Resolve in Cursor, then:

```bash
git add .
git commit -m "Resolve conflicts with main"
git push
```

---

## 4) Production Safety & Rollback

### If production breaks after a merge

1. **Stop merging more PRs**
2. Run GitHub Action: **Rollback theme3**
3. Confirm production is stable
4. Fix forward using a `fix/...` branch and PR

Rollback workflow:

- Actions → "Rollback theme3 on GoDaddy (Managed WP)"
- Leave default `theme3-latest.tar.gz` or choose a specific backup

---

## 5) File Hygiene

Do not commit:

- `wp-config.php`
- `.env` / `.env.*`
- `*.wpress`, `*.sql`, `*.zip` backups/exports
- `wp-content/uploads/` (media)
- private keys or credentials

If you suspect secrets were committed:

- Stop
- Rotate the secret
- Rewrite Git history if needed

---

## 6) Communication Protocol (Parallel Work)

To avoid stepping on each other:

- Announce when editing shared core files (e.g. `functions.php`, `header.php`, `footer.php`)
- Prefer smaller PRs
- Don't run multiple large refactors in parallel

---

## 7) Cursor Agent Notes

If you are using Cursor AI:

- Always work on a feature branch
- Keep diffs minimal and scoped to the task
- Do not modify deployment workflows unless explicitly instructed
- Never add secrets to code; use GitHub Secrets only
