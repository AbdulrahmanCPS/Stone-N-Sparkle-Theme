# Cursor Rules (Stone-N-Sparkle Theme)

**Source of truth:** `.cursor/rules/stone-n-sparkle-theme.mdc` — Cursor loads this automatically.

This file is a quick reference. For full rules (branch workflow, PR checklist, production safety, file hygiene, etc.), see the rule file above or open it in the editor.

## Quick reference
- **Never develop on `main`.** Use `feature/<short-name>` or `fix/<short-name>`.
- Merge to `main` only via PRs. Keep changes minimal and scoped.
- Never commit secrets, `.env`, DB exports, or deployment workflow changes unless asked.
- Before PR: run local checks (homepage, shop/product if relevant, no PHP fatals), re-sync from `origin/main`.

See `.cursor/rules/stone-n-sparkle-theme.mdc` for the complete rules, checklist, and prompt template.
