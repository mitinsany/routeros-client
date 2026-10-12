# Public contract

The package namespace is `MitinSany\RouterOS`. This document defines the
public names retained while the implementation is built.

## Api

Public properties: `debug`, `errorNo`, `errorString`, `attempts`, `connected`,
`delay`, `port`, `timeout`, and `sock`.

Public methods: `connect()`, `debug()`, `write()`, `encodeLength()`, `read()`,
`parseResponse()`, `disconnect()`, `parseResponseForSmarty()`,
`changeArrayKeyName()`, and `command()`.

`connect($host, $user, $password, $loginMode)` accepts the optional values
`LoginMode::AUTO`, `LoginMode::PLAIN`, and `LoginMode::LEGACY`; omitted mode
means `AUTO`. `PLAIN` sends one modern name/password request. `LEGACY` obtains
a RouterOS MD5 challenge and sends the corresponding response. `AUTO` first
sends the modern request, then uses the same connection for a legacy response
only when RouterOS returns a valid challenge. It returns `false` for a trap,
transport error, invalid challenge, or unsuccessful connection, setting
`errorString` when a message is available. `command($path, $attributes)` writes `=key=value` attributes and
returns an array of `!re` records; it returns `false` after `!trap`, `!fatal`,
or a transport failure. Attribute values preserve embedded equal signs.
`read()` stops after `!done` or terminal `!fatal`; a `!trap` is read through
the following `!done`. With its default argument it returns parsed records,
otherwise it returns raw response sentences. A terminal fatal reply does not
require a following `!done`; its message is preserved in `errorString` by
`connect()` and `command()`.

## RouterOS

Public properties: `sock`, `hostname`, and `readOnly`.

Public methods: `setTimeout()`, `getAll()`, `set()`, `reboot()`, `fetchUrl()`,
`cancel()`, `move()`, `add()`, `remove()`, `unsetValue()`, `scan()`,
`frequencyMonitor()`, `listen()`, `bandwidthTest()`, and `dispatch()`.

`getAll($path)` reads `!re` records from `$path/print` and returns `false` on
`!trap`. Its optional final `$queryWords` array is emitted unchanged after
attributes, preserving RouterOS query-word order. Mutating helpers return `true` after `!done`, `false` after a trap or
when `readOnly` is true, and a callback tag when passed a callback. `listen()`
always requires a callback and returns its tag. `dispatch(&$continue)` calls
callbacks as `($router, $success, $reply)`; an `!re` reply has `true` and an
attribute array, `!trap` has `false`, and `!done` has `true` and `null`.
Setting `$continue` to `false` stops further callback invocation.

## ConfigurationSynchronizer

Public properties: `vars`, `logs`, `errors`, `showIgnored`, and `currentContext`.

Public methods: `variable()`, `configureWithArguments()`,
`configureReversedWithArguments()`, `ignoreWithArguments()`,
`passWithArguments()`, `parseFile()`, `replace()`, `error()`, `define()`,
`config()`, `cmd()`, `ignore()`, `pass()`, `section()`, `readOptions()`, `call()`, `update()`,
`updateSection()`, and `continueOnError()`. It also supports PHP property magic methods.

Configuration text is line-oriented. Blank lines and `#` comments are ignored.
`define key value` stores a variable; `config /path key=value ...` adds a
configuration entry; `ignore /path key=value ...` excludes matching entries;
and `pass /path key=value ...` permits only matching entries when pass rules
exist for that path. Values may contain `{{variable}}` placeholders. `update()`
calls `set($path, $attributes)` on its router argument in configuration order.
`section($alias, $path, $type)` registers a path. `readOptions()` optionally
sets the print `.proplist` and ordered query words for that section; without it,
the synchronizer keeps using `getAll($path)`. `updateSection()` applies
only entries for that path. Parsing or connection failure returns `false` and
appends a message to `logs`.

Mutation failures stop reconciliation by default. `continueOnError(true)`
allows later records and sections to be processed after mutation failures;
`update()` returns `true` in that mode when processing completes, even if
mutations failed. Callers inspect `errors` for those failures. Read failures
still stop reconciliation and return `false`. `logs` and `errors` are reset
at the beginning of each `update()`.

Classes marked `@internal`, including `Protocol\SentenceWords` and the
`Synchronization` helpers, are implementation details outside this public
contract. Callers continue to use `Api`, `RouterOS`, and
`ConfigurationSynchronizer`.
