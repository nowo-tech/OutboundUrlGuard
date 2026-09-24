# Release

Current stable: **v1.0.1** (2026-09-24).

Maintainers: follow this checklist before creating a tag.

## Pre-release checklist

1. **Update version and docs**
   - Ensure [CHANGELOG.md](CHANGELOG.md) has an entry for the new version (for example `[1.0.0] - YYYY-MM-DD`) and that `[Unreleased]` is empty or updated.
   - Update the compare links at the bottom of [CHANGELOG.md](CHANGELOG.md).
   - Ensure [UPGRADING.md](UPGRADING.md) mentions any behaviour changes.
   - Confirm [SECURITY.md](SECURITY.md) release checklist (12.4.1), including the AI security audit note.

2. **Run quality checks**

   ```bash
   make release-check
   ```

   This runs: `check-no-cursor-coauthor`, `check-open-prs`, `ensure-up`, `composer-sync`, `cs-fix`, `cs-check`, `rector-dry`, `phpstan`, and `test-coverage` (coverage gate included).

3. **Open pull requests**

   `make check-open-prs` must pass: `gh pr list --repo nowo-tech/OutboundUrlGuard --state open` is empty, or every remaining PR has label `hold` or `do-not-merge` and a future `review-by: YYYY-MM-DD` in the body. Conflicted PRs are not allowed without that hold.

4. **Commit** any changes. Ensure the tree is clean and pushed:

   ```bash
   git status
   make check-no-cursor-coauthor
   git push origin main
   ```

## Tag and publish

5. **Create an annotated tag** after at least one commit:

   ```bash
   git tag -a v1.0.1 -m "Release v1.0.1"
   git push origin v1.0.1
   ```

6. **GitHub release**

   [.github/workflows/release.yml](../.github/workflows/release.yml) creates the GitHub Release from the tag message and [CHANGELOG.md](CHANGELOG.md). [.github/workflows/sync-releases.yml](../.github/workflows/sync-releases.yml) backfills missing releases.

7. **Packagist**

   Submit `https://github.com/nowo-tech/OutboundUrlGuard` once on Packagist for `nowo-tech/outbound-url-guard-bundle`. Later tags are picked up automatically.
