# MitinSany RouterOS client

An independently authored PHP client for the MikroTik RouterOS API protocol.

This `php8` branch supports PHP `>=8.0`.
The library exposes the `MitinSany\RouterOS` namespace.

`Api::connect()` supports `LoginMode::AUTO` by default, plus explicit
`LoginMode::PLAIN` for RouterOS 6.43+ and `LoginMode::LEGACY` for challenge-
response authentication on older RouterOS releases.

The supported public surface is recorded in
[docs/public-contract.md](docs/public-contract.md). Protocol behavior and
command helpers are covered by isolated tests.

See [docs/branching.md](docs/branching.md) for Docker verification commands
and the PHP branch policy.

The clean-room technical provenance is described in
[docs/provenance.md](docs/provenance.md).
