# Public contract

The package namespace is `MitinSany\RouterOS`. This document defines the
public names retained while the implementation is built.

## Api

Public properties: `debug`, `error_no`, `error_str`, `attempts`, `connected`,
`delay`, `port`, `timeout`, and `sock`.

Public methods: `connect()`, `debug()`, `write()`, `encodeLength()`, `read()`,
`parseResponse()`, `disconnect()`, `parseResponseForSmarty()`,
`changeArrayKeyName()`, and `command()`.

## RouterOS

Public properties: `sock`, `hostname`, and `readOnly`.

Public methods: `setTimeout()`, `getAll()`, `set()`, `reboot()`, `fetchUrl()`,
`cancel()`, `move()`, `add()`, `remove()`, `unsetValue()`, `scan()`,
`frequencyMonitor()`, `listen()`, `bandwidthTest()`, and `dispatch()`.

## ConfigurationSynchronizer

Public properties: `vars`, `logs`, `showIgnored`, and `currentContext`.

Public methods: `variable()`, `configureWithArguments()`,
`configureReversedWithArguments()`, `ignoreWithArguments()`,
`passWithArguments()`, `parseFile()`, `replace()`, `error()`, `define()`,
`config()`, `cmd()`, `ignore()`, `pass()`, `section()`, `call()`, `update()`,
and `updateSection()`. It also supports PHP property magic methods.
