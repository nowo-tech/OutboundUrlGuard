# Contributing

Contributions are welcome. Please open an issue or a pull request on the [GitHub repository](https://github.com/nowo-tech/OutboundUrlGuard) (Composer package: `nowo-tech/outbound-url-guard-bundle`).

- Follow PSR-12 and the project's PHP-CS-Fixer configuration.
- Add tests for new behaviour. Tests live under `tests/Unit/` (isolated) and `tests/Integration/` (container). Run all with `make test`, or only `composer test-unit` / `composer test-integration`.
- Keep documentation (README, docs/) in sync with code changes.
- Merge method: squash merge on GitHub unless a maintainer asks for a merge commit.

Run quality checks locally:

```bash
make install
make cs-check
make test
make phpstan
make rector-dry
```

## Code of Conduct

This project follows the [Contributor Covenant Code of Conduct](../CODE_OF_CONDUCT.md). By participating, you are expected to uphold it. Please report unacceptable behavior to **hectorfranco@nowo.tech**.

## Git hooks (REQ-GIT-001)

Do **not** add `Co-authored-by: Cursor` or `cursoragent@cursor.com` trailers to commit messages.

```bash
make setup-hooks
make check-no-cursor-coauthor
```

`make setup-hooks` installs `.githooks/commit-msg` (and sets `core.hooksPath` to `.githooks`). Run it once per clone before your first commit.
If CI fails because trailers are already on the remote, see [GITHUB_CI.md](GITHUB_CI.md) and run `make strip-cursor-coauthor-from-history` before `git push --force-with-lease`.

## Cursor Agent

Submit prompts with **Ctrl + Enter** (macOS: **⌘ + Enter**). Enter inserts a newline. In user `settings.json` set:

```json
{
  "cursor.composer.queueMessageDefaultBehavior": "queue"
}
```

`queue` waits until the current agent turn finishes. Do not use `steer` as the default.
