# Public contract

The package namespace is `MitinSany\RouterOS`. This document defines the
public names retained while the implementation is built.

## Api

Public properties: `debug`, `error_no`, `error_str`, `attempts`, `connected`,
`delay`, `port`, `timeout`, and `sock`.

Public methods: `connect()`, `debug()`, `write()`, `encodeLength()`, `read()`,
`parseResponse()`, `disconnect()`, `parseResponseForSmarty()`,
`changeArrayKeyName()`, and `command()`.

`connect($host, $user, $password)` sends RouterOS plain-text `/login` and
returns `true` only after `!done`; it returns `false` for a trap, transport
error, or unsuccessful connection, setting `error_str` when a message is
available. `command($path, $attributes)` writes `=key=value` attributes and
returns an array of `!re` records; it returns `false` after `!trap` or a
transport failure. Attribute values preserve embedded equal signs. `read()`
stops after `!done` or `!trap`; with its default argument it returns parsed
records, otherwise it returns raw response sentences.

## RouterOS

Public properties: `sock`, `hostname`, and `readOnly`.

Public methods: `setTimeout()`, `getAll()`, `set()`, `reboot()`, `fetchUrl()`,
`cancel()`, `move()`, `add()`, `remove()`, `unsetValue()`, `scan()`,
`frequencyMonitor()`, `listen()`, `bandwidthTest()`, and `dispatch()`.

`getAll($path)` reads `!re` records from `$path/print` and returns `false` on
`!trap`. Mutating helpers return `true` after `!done`, `false` after a trap or
when `readOnly` is true, and a callback tag when passed a callback. `listen()`
always requires a callback and returns its tag. `dispatch(&$continue)` calls
callbacks as `($router, $success, $reply)`; an `!re` reply has `true` and an
attribute array, `!trap` has `false`, and `!done` has `true` and `null`.
Setting `$continue` to `false` stops further callback invocation.

## ConfigurationSynchronizer

Public properties: `vars`, `logs`, `showIgnored`, and `currentContext`.

Public methods: `variable()`, `configureWithArguments()`,
`configureReversedWithArguments()`, `ignoreWithArguments()`,
`passWithArguments()`, `parseFile()`, `replace()`, `error()`, `define()`,
`config()`, `cmd()`, `ignore()`, `pass()`, `section()`, `call()`, `update()`,
and `updateSection()`. It also supports PHP property magic methods.

Configuration text is line-oriented. Blank lines and `#` comments are ignored.
`define key value` stores a variable; `config /path key=value ...` adds a
configuration entry; `ignore /path key=value ...` excludes matching entries;
and `pass /path key=value ...` permits only matching entries when pass rules
exist for that path. Values may contain `{{variable}}` placeholders. `update()`
calls `set($path, $attributes)` on its router argument in configuration order.
`section($alias, $path, $type)` registers a path and `updateSection()` applies
only entries for that path. Parsing or connection failure returns `false` and
appends a message to `logs`.
