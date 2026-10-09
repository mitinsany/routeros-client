# Contribution rules

Keep RouterOS protocol fixes on `php5` first, then transfer reviewed commits
to `php7` and `php8`. Preserve wire-level behavior across branches.

Do not add application integration, database access, licensing checks, worker
logic, deployment configuration, or credentials to this package. The package
contains only RouterOS protocol and configuration-synchronization code.

Run the Docker checks documented in `docs/branching.md` before publishing.
Use the isolated Composer container for dependency installation.
