# php-procedural-sqlite

A procedural, mysqli-inspired API for PHP's SQLite3 extension. Application code uses functions such as `sqlite_connect()`, `sqlite_query()`, `sqlite_fetch_assoc()`, and `sqlite_stmt_execute()` without calling object methods.

This is the **Full Edition**, providing 90 public functions. Applications that only require CRUD, prepared statements, and basic transactions can use the standalone [Lite Edition](README-lite.md), which provides 32 functions. The editions must not be loaded together because they intentionally use the same function names.

Results are fully buffered to support mysqli-style features such as `sqlite_num_rows()` and `sqlite_data_seek()`. Connection, statement, and result handles are opaque values; their internal classes are not public API.

## AI attribution

> **This project is 100% AI-generated.**

All PHP source code and documentation in this repository were generated with [OpenAI Codex](https://developers.openai.com/codex), an AI coding agent by OpenAI. A human supplied the requirements and directed the work; the resulting project files were generated entirely by AI.

## Requirements

- PHP 8.2 or newer
- The PHP `sqlite3` extension
- Write permission for the database directory unless an in-memory database is used

## Installation

```php
require_once __DIR__ . '/sqlite/sqlite.php';
```

Use `require_once`. Composer and additional runtime files are not required.

## Quick start

```php
<?php

require_once __DIR__ . '/sqlite/sqlite.php';
$db = sqlite_connect(__DIR__ . '/app.sqlite');

sqlite_execute($db, 'CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');

$stmt = sqlite_prepare($db, 'INSERT INTO users (name) VALUES (?)');
$name = 'Ada';
sqlite_stmt_bind_param($stmt, 's', $name);
sqlite_stmt_execute($stmt);

$result = sqlite_query($db, 'SELECT id, name FROM users ORDER BY id');
while ($row = sqlite_fetch_assoc($result)) {
    echo $row['id'] . ': ' . $row['name'] . PHP_EOL;
}

sqlite_free_result($result);
sqlite_stmt_close($stmt);
sqlite_close($db);
```

Bind untrusted values with prepared statements. Use `sqlite_real_escape_string()` only where binding is technically impossible.

## Constants

| Group | Constants |
|---|---|
| Fetch modes | `SQLITE_ASSOC`, `SQLITE_NUM`, `SQLITE_BOTH` |
| Report modes | `SQLITE_REPORT_OFF`, `SQLITE_REPORT_ERROR`, `SQLITE_REPORT_STRICT` |
| Transactions | `SQLITE_TRANS_DEFERRED`, `SQLITE_TRANS_IMMEDIATE`, `SQLITE_TRANS_EXCLUSIVE` |
| Value types | `SQLITE_TYPE_INTEGER`, `SQLITE_TYPE_FLOAT`, `SQLITE_TYPE_TEXT`, `SQLITE_TYPE_BLOB`, `SQLITE_TYPE_NULL` |

The default report mode is `SQLITE_REPORT_ERROR | SQLITE_REPORT_STRICT`, matching modern mysqli behavior. Native constants such as `SQLITE3_OPEN_READONLY`, `SQLITE3_OPEN_READWRITE`, `SQLITE3_OPEN_CREATE`, and `SQLITE3_DETERMINISTIC` can be used directly.

## Differences from mysqli

- SQLite opens a filename instead of connecting to a database server.
- SQLite has no selectable connection charset; this API reports `UTF-8`.
- Result sets are fully buffered in PHP memory.
- `sqlite_multi_query()` splits ordinary statements at semicolons outside strings and comments. Execute trigger definitions containing semicolons with `sqlite_execute()` as one statement.
- Compatibility parameters unsupported by SQLite are accepted but ignored where documented.
- Unsupported MySQL field metadata contains neutral values.
- `sqlite_thread_id()` returns `0` because SQLite has no server thread ID.

## Connections and errors

### sqlite_report

```php
sqlite_report(int $flags): bool
```

Sets the global report mode. `flags` is a bitwise combination of the report constants. Returns `true`; invalid bits throw `ValueError`.

- **Parameters:** `flags` selects warnings and/or strict exceptions; pass `SQLITE_REPORT_OFF` for return-value-only handling.
- **Returns:** Always `true` for a valid flag set.
- **Errors:** Throws `ValueError` if unknown flag bits are present.

### sqlite_connect

```php
sqlite_connect(string $filename, int $flags = SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE, string $encryptionKey = ''): SQLiteProceduralConnection|false
```

Opens `filename`, or `:memory:`. `flags` contains native `SQLITE3_OPEN_*` values. `encryptionKey` applies only to suitably compiled builds. Returns an opaque connection handle, or `false`/throws on failure.

- **Parameters:** `filename` is the database path; `flags` selects read-only, read/write, and create behavior; `encryptionKey` is passed to the native driver.
- **Returns:** `SQLiteProceduralConnection` on success or `false` when strict reporting is disabled.
- **Errors:** Connection failures update `sqlite_connect_errno()` and `sqlite_connect_error()` and follow the active report mode.

### sqlite_connect_errno

```php
sqlite_connect_errno(): int
```

Returns the error code from the latest failed connection attempt, or `0`.

- **Parameters:** None.
- **Returns:** The last connection error code as an integer.

### sqlite_connect_error

```php
sqlite_connect_error(): ?string
```

Returns the message from the latest failed connection attempt, or `null`.

- **Parameters:** None.
- **Returns:** The last connection error message, or `null` when no connection error is stored.

### sqlite_close

```php
sqlite_close(SQLiteProceduralConnection $connection): bool
```

Closes the connection. A managed transaction is rolled back first. Repeated closing returns `true`.

- **Parameters:** `connection` is the opaque handle to close.
- **Returns:** `true` when the handle is closed or was already closed; otherwise `false` in non-strict mode.

### sqlite_errno

```php
sqlite_errno(SQLiteProceduralConnection $connection): int
```

Returns the last stored connection error code, or `0`.

- **Parameters:** `connection` is the connection to inspect.
- **Returns:** The stored SQLite error code.

### sqlite_error

```php
sqlite_error(SQLiteProceduralConnection $connection): string
```

Returns the last stored connection error message, or an empty string.

- **Parameters:** `connection` is the connection to inspect.
- **Returns:** The stored error text or `''`.

### sqlite_sqlstate

```php
sqlite_sqlstate(SQLiteProceduralConnection $connection): string
```

Returns `00000` without an error and the generic SQLSTATE `HY000` otherwise.

- **Parameters:** `connection` is the connection to inspect.
- **Returns:** A five-character SQLSTATE string.

### sqlite_error_list

```php
sqlite_error_list(SQLiteProceduralConnection $connection): array
```

Returns an empty array or one entry containing `errno`, `sqlstate`, and `error`.

- **Parameters:** `connection` is the connection to inspect.
- **Returns:** `[]` or a one-element list containing the last error details.

### sqlite_ping

```php
sqlite_ping(SQLiteProceduralConnection $connection): bool
```

Checks whether the connection remains usable by executing `SELECT 1`.

- **Parameters:** `connection` is the handle to test.
- **Returns:** `true` when the probe succeeds, otherwise `false`.

## Queries

### sqlite_query

```php
sqlite_query(SQLiteProceduralConnection $connection, string $query): SQLiteProceduralResult|bool
```

Executes one statement. Queries return a buffered result; statements without result columns return `true`.

- **Parameters:** `connection` is an open handle; `query` is one complete SQL statement.
- **Returns:** `SQLiteProceduralResult` for row-producing SQL, `true` for successful non-row SQL, or `false` in non-strict error mode.
- **Errors:** SQLite errors are copied to the connection and processed by `sqlite_report()`.

### sqlite_execute

```php
sqlite_execute(SQLiteProceduralConnection $connection, string $query): bool
```

Executes one or more statements without exposing results. Returns `true` on success.

- **Parameters:** `connection` is an open handle; `query` contains SQL whose rows, if any, are intentionally discarded.
- **Returns:** `true` on success or `false` in non-strict error mode.

### sqlite_execute_query

```php
sqlite_execute_query(SQLiteProceduralConnection $connection, string $query, ?array $params = null): SQLiteProceduralResult|bool
```

Prepares `query`, binds positional `params`, executes, and closes the internal statement. Queries return a result; writes return `true`.

- **Parameters:** `connection` is an open handle; `query` contains optional positional placeholders; `params` supplies values in placeholder order.
- **Returns:** A buffered result for row-producing SQL, `true` for successful writes, or `false` on an error in non-strict mode.
- **Errors:** A parameter-count mismatch throws `ArgumentCountError`.

### sqlite_query_single

```php
sqlite_query_single(SQLiteProceduralConnection $connection, string $query, bool $entireRow = false): mixed
```

Returns the first column of the first row. With `entireRow = true`, returns the first row as an associative array.

- **Parameters:** `connection` is an open handle; `query` is one SQL query; `entireRow` selects scalar versus associative-row output.
- **Returns:** The first scalar value, the first associative row, an empty-result value (`null` or `[]`), or `false` on error.

### sqlite_multi_query

```php
sqlite_multi_query(SQLiteProceduralConnection $connection, string $query): bool
```

Executes semicolon-separated statements and stores their results. Strings, quoted identifiers, and comments are respected while splitting.

- **Parameters:** `connection` is an open handle; `query` contains multiple ordinary SQL statements.
- **Returns:** `true` if every statement succeeds, otherwise `false` in non-strict mode.
- **Notes:** Trigger bodies containing internal semicolons are not supported by the splitter; execute those with `sqlite_execute()`.

### sqlite_store_result

```php
sqlite_store_result(SQLiteProceduralConnection $connection): SQLiteProceduralResult|false
```

Returns the current multi-query result, or `false` when the current statement has no result columns.

- **Parameters:** `connection` is the handle used by the latest `sqlite_multi_query()`.
- **Returns:** The current `SQLiteProceduralResult`, or `false` for a non-row statement or missing result.

### sqlite_more_results

```php
sqlite_more_results(SQLiteProceduralConnection $connection): bool
```

Returns whether another multi-query result is available.

- **Parameters:** `connection` is the multi-query connection.
- **Returns:** `true` when `sqlite_next_result()` can advance.

### sqlite_next_result

```php
sqlite_next_result(SQLiteProceduralConnection $connection): bool
```

Advances to the next multi-query result, or returns `false` at the end.

- **Parameters:** `connection` is the multi-query connection.
- **Returns:** `true` after advancing or `false` when already at the final result.

### sqlite_affected_rows

```php
sqlite_affected_rows(SQLiteProceduralConnection $connection): int
```

Returns the number of rows changed by the latest write operation.

- **Parameters:** `connection` is the connection to inspect.
- **Returns:** The native SQLite changed-row count.

### sqlite_insert_id

```php
sqlite_insert_id(SQLiteProceduralConnection $connection): int
```

Returns the row ID generated by the latest insert.

- **Parameters:** `connection` is the connection to inspect.
- **Returns:** The latest SQLite row ID as an integer.

### sqlite_real_escape_string

```php
sqlite_real_escape_string(SQLiteProceduralConnection $connection, string $string): string
```

Escapes single quotes inside a SQLite string literal. It does not add surrounding quotes. Prefer prepared statements.

- **Parameters:** `connection` must be open; `string` is the raw value to escape.
- **Returns:** The escaped string without quote delimiters.
- **Security:** This is for literal values only, not identifiers. Parameter binding is safer.

## Result sets

### sqlite_fetch_array

```php
sqlite_fetch_array(SQLiteProceduralResult $result, int $mode = SQLITE_BOTH): ?array
```

Fetches the next row using `SQLITE_ASSOC`, `SQLITE_NUM`, or `SQLITE_BOTH`. Returns `null` at the end.

- **Parameters:** `result` is a buffered result handle; `mode` controls array keys.
- **Returns:** The next row as an array or `null` when exhausted/freed.
- **Errors:** Invalid modes throw `ValueError`.

### sqlite_fetch_assoc

```php
sqlite_fetch_assoc(SQLiteProceduralResult $result): ?array
```

Fetches the next row with column names as keys, or `null` at the end. Later duplicate names overwrite earlier values.

- **Parameters:** `result` is a buffered result handle.
- **Returns:** The next associative row or `null`.

### sqlite_fetch_row

```php
sqlite_fetch_row(SQLiteProceduralResult $result): ?array
```

Fetches the next row as a zero-based numeric array, or `null`.

- **Parameters:** `result` is a buffered result handle.
- **Returns:** The next numeric row or `null`.

### sqlite_fetch_object

```php
sqlite_fetch_object(SQLiteProceduralResult $result, string $class = stdClass::class, array $constructorArgs = []): ?object
```

Hydrates the next row into `class`. Properties are assigned before its constructor receives `constructorArgs`.

- **Parameters:** `result` is the result handle; `class` is an instantiable class name; `constructorArgs` is passed to its constructor.
- **Returns:** A hydrated object or `null` at the end.
- **Errors:** Reflection, property-type, and constructor errors propagate normally.

### sqlite_fetch_all

```php
sqlite_fetch_all(SQLiteProceduralResult $result, int $mode = SQLITE_NUM): array
```

Returns all remaining rows in the requested mode and advances the cursor to the end.

- **Parameters:** `result` is a buffered result; `mode` is one of the three fetch constants.
- **Returns:** A list of remaining rows, possibly empty.

### sqlite_fetch_column

```php
sqlite_fetch_column(SQLiteProceduralResult $result, int $column = 0): mixed
```

Returns zero-based `column` from the next row, or `null` at the end. Invalid indexes throw `ValueError`.

- **Parameters:** `result` is a buffered result; `column` is a zero-based column index.
- **Returns:** The selected value or `null` when no row remains.

### sqlite_fetch_lengths

```php
sqlite_fetch_lengths(SQLiteProceduralResult $result): array|false
```

Returns byte lengths for the last fetched row, or `false` before fetching. `NULL` has length `0`.

- **Parameters:** `result` is the result whose most recently fetched row is inspected.
- **Returns:** A numeric list of byte lengths, or `false` when no row has been fetched.

### sqlite_num_rows

```php
sqlite_num_rows(SQLiteProceduralResult $result): int
```

Returns the total number of buffered rows.

- **Parameters:** `result` is a buffered result handle.
- **Returns:** Total row count, or `0` after freeing the result.

### sqlite_num_fields

```php
sqlite_num_fields(SQLiteProceduralResult $result): int
```

Returns the number of result columns.

- **Parameters:** `result` is a buffered result handle.
- **Returns:** Column count, or `0` after freeing the result.

### sqlite_data_seek

```php
sqlite_data_seek(SQLiteProceduralResult $result, int $offset): bool
```

Moves the cursor to existing zero-based row `offset`; invalid offsets throw `ValueError`.

- **Parameters:** `result` is the result to reposition; `offset` identifies an existing buffered row.
- **Returns:** `true` after repositioning.

### sqlite_field_seek

```php
sqlite_field_seek(SQLiteProceduralResult $result, int $index): bool
```

Moves the field cursor to zero-based column `index`; invalid indexes throw `ValueError`.

- **Parameters:** `result` is the result to reposition; `index` identifies an existing column.
- **Returns:** `true` after repositioning.

### sqlite_field_tell

```php
sqlite_field_tell(SQLiteProceduralResult $result): int
```

Returns the current field-cursor position.

- **Parameters:** `result` is the result whose field cursor is inspected.
- **Returns:** The zero-based cursor position.

### sqlite_fetch_field

```php
sqlite_fetch_field(SQLiteProceduralResult $result): object|false
```

Returns mysqli-style metadata for the current field and advances the cursor, or `false` at the end.

- **Parameters:** `result` contains the field metadata.
- **Returns:** An object with properties including `name`, `type`, `table`, `flags`, and `length`, or `false`.
- **Notes:** Unsupported MySQL-specific properties contain neutral values.

### sqlite_fetch_field_direct

```php
sqlite_fetch_field_direct(SQLiteProceduralResult $result, int $index): object
```

Returns metadata for zero-based column `index`; invalid indexes throw `ValueError`.

- **Parameters:** `result` contains metadata; `index` identifies an existing column.
- **Returns:** One field metadata object.

### sqlite_fetch_fields

```php
sqlite_fetch_fields(SQLiteProceduralResult $result): array
```

Returns metadata objects for all columns.

- **Parameters:** `result` contains the field metadata.
- **Returns:** A list of metadata objects in column order.

### sqlite_free_result

```php
sqlite_free_result(SQLiteProceduralResult $result): void
```

Releases buffered rows and metadata. Do not use the handle afterward.

- **Parameters:** `result` is the buffered handle to clear.
- **Returns:** No value.

## Prepared statements

### sqlite_prepare

```php
sqlite_prepare(SQLiteProceduralConnection $connection, string $query): SQLiteProceduralStatement|false
```

Prepares one statement. SQLite parameter forms are supported; positional `?` parameters are recommended.

- **Parameters:** `connection` is an open handle; `query` is one SQL statement with optional placeholders.
- **Returns:** `SQLiteProceduralStatement` on success or `false` in non-strict mode.

### sqlite_stmt_bind_param

```php
sqlite_stmt_bind_param(SQLiteProceduralStatement $statement, string $types, mixed &$variable, mixed &...$variables): bool
```

Binds variables by reference. `types` contains `i` (integer), `d` (float), `s` (text), or `b` (BLOB), one per parameter. `null` binds SQL `NULL`.

- **Parameters:** `statement` is prepared; `types` declares each binding; `variable`/`variables` are references in placeholder order.
- **Returns:** `true` after binding.
- **Errors:** Count mismatches throw `ArgumentCountError`; unsupported type letters throw `ValueError`.

### sqlite_stmt_send_long_data

```php
sqlite_stmt_send_long_data(SQLiteProceduralStatement $statement, int $paramNum, string $data): bool
```

Appends `data` to zero-based `b` parameter `paramNum`. Repeated calls concatenate data until execution.

- **Parameters:** `statement` has a previous `b` binding; `paramNum` is zero-based; `data` is the next binary chunk.
- **Returns:** `true` on success or `false` in non-strict mode.
- **Errors:** Invalid indexes throw `ValueError`; non-BLOB targets report a statement error.

### sqlite_stmt_execute

```php
sqlite_stmt_execute(SQLiteProceduralStatement $statement, ?array $params = null): bool
```

Executes the statement. Without `params`, all parameters must be bound. Otherwise, positional PHP values are bound with inferred types.

- **Parameters:** `statement` is prepared; `params` optionally supplies a one-shot positional value list.
- **Returns:** `true` on success or `false` in non-strict mode. Result rows are buffered on the statement.
- **Errors:** Incomplete or incorrectly sized parameter lists report an error or throw `ArgumentCountError`.

### sqlite_stmt_execute_query

```php
sqlite_stmt_execute_query(SQLiteProceduralStatement $statement, ?array $params = null): SQLiteProceduralResult|false
```

Combines statement execution with `sqlite_stmt_get_result()`. Successful statements without result columns return `false`.

- **Parameters:** `statement` is prepared; `params` follows `sqlite_stmt_execute()` semantics.
- **Returns:** A buffered result, or `false` for non-row SQL/failure.

### sqlite_stmt_get_result

```php
sqlite_stmt_get_result(SQLiteProceduralStatement $statement): SQLiteProceduralResult|false
```

Returns the buffered result from the latest execution, or `false` without result columns.

- **Parameters:** `statement` is an executed statement.
- **Returns:** `SQLiteProceduralResult` or `false`.

### sqlite_stmt_bind_result

```php
sqlite_stmt_bind_result(SQLiteProceduralStatement $statement, mixed &$variable, mixed &...$variables): bool
```

Binds one output variable by reference per result column. The statement must already be executed.

- **Parameters:** `statement` has a buffered result; `variable`/`variables` are output references in column order.
- **Returns:** `true` after binding.
- **Errors:** A variable-count mismatch throws `ArgumentCountError`.

### sqlite_stmt_fetch

```php
sqlite_stmt_fetch(SQLiteProceduralStatement $statement): ?bool
```

Copies the next row into bound result variables. Returns `true` for a row and `null` at the end.

- **Parameters:** `statement` has an executed result and bound output variables.
- **Returns:** `true` for a fetched row or `null` when exhausted.

### sqlite_stmt_store_result

```php
sqlite_stmt_store_result(SQLiteProceduralStatement $statement): bool
```

Returns whether the latest execution has a buffered result. Buffering occurs automatically.

- **Parameters:** `statement` is the statement to inspect.
- **Returns:** `true` when a result exists, otherwise `false`.

### sqlite_stmt_result_metadata

```php
sqlite_stmt_result_metadata(SQLiteProceduralStatement $statement): SQLiteProceduralResult|false
```

Returns a rowless handle containing result metadata, or `false` without result columns.

- **Parameters:** `statement` must have been executed.
- **Returns:** A metadata-only `SQLiteProceduralResult` or `false`.

### sqlite_stmt_data_seek

```php
sqlite_stmt_data_seek(SQLiteProceduralStatement $statement, int $offset): void
```

Moves the statement-result cursor to existing row `offset`. Invalid state or offsets throw `ValueError`.

- **Parameters:** `statement` has a buffered result; `offset` is an existing zero-based row.
- **Returns:** No value.

### sqlite_stmt_reset

```php
sqlite_stmt_reset(SQLiteProceduralStatement $statement): bool
```

Resets execution state, results, and long-data buffers. Reference bindings remain.

- **Parameters:** `statement` is the handle to reset.
- **Returns:** The native reset result as `bool`.

### sqlite_stmt_free_result

```php
sqlite_stmt_free_result(SQLiteProceduralStatement $statement): void
```

Releases the current buffered statement result.

- **Parameters:** `statement` owns the result to release.
- **Returns:** No value.

### sqlite_stmt_close

```php
sqlite_stmt_close(SQLiteProceduralStatement $statement): bool
```

Releases the result and native statement. Repeated closing returns `true`.

- **Parameters:** `statement` is the handle to close.
- **Returns:** `true` on success or when already closed; otherwise `false` in non-strict mode.

### sqlite_stmt_param_count

```php
sqlite_stmt_param_count(SQLiteProceduralStatement $statement): int
```

Returns the number of SQL parameters.

- **Parameters:** `statement` is prepared.
- **Returns:** Native parameter count as an integer.

### sqlite_stmt_field_count

```php
sqlite_stmt_field_count(SQLiteProceduralStatement $statement): int
```

Returns the result-column count after execution, or `0`.

- **Parameters:** `statement` is the statement to inspect.
- **Returns:** Buffered result-column count.

### sqlite_stmt_num_rows

```php
sqlite_stmt_num_rows(SQLiteProceduralStatement $statement): int
```

Returns the number of buffered result rows.

- **Parameters:** `statement` is the statement to inspect.
- **Returns:** Buffered row count, or `0` without a result.

### sqlite_stmt_affected_rows

```php
sqlite_stmt_affected_rows(SQLiteProceduralStatement $statement): int
```

Returns the number of rows changed by the latest execution.

- **Parameters:** `statement` is the statement to inspect.
- **Returns:** Changed-row count; read-only statements report `0`.

### sqlite_stmt_insert_id

```php
sqlite_stmt_insert_id(SQLiteProceduralStatement $statement): int
```

Returns the row ID recorded after the latest execution.

- **Parameters:** `statement` is the statement to inspect.
- **Returns:** Last recorded SQLite row ID.

### sqlite_stmt_errno

```php
sqlite_stmt_errno(SQLiteProceduralStatement $statement): int
```

Returns the last stored statement error code, or `0`.

- **Parameters:** `statement` is the statement to inspect.
- **Returns:** Stored integer error code.

### sqlite_stmt_error

```php
sqlite_stmt_error(SQLiteProceduralStatement $statement): string
```

Returns the last stored statement error message, or an empty string.

- **Parameters:** `statement` is the statement to inspect.
- **Returns:** Stored error text or `''`.

### sqlite_stmt_sqlstate

```php
sqlite_stmt_sqlstate(SQLiteProceduralStatement $statement): string
```

Returns `00000` without an error and `HY000` otherwise.

- **Parameters:** `statement` is the statement to inspect.
- **Returns:** A five-character SQLSTATE string.

### sqlite_stmt_readonly

```php
sqlite_stmt_readonly(SQLiteProceduralStatement $statement): bool
```

Returns whether the statement cannot modify the database.

- **Parameters:** `statement` is prepared.
- **Returns:** `true` for read-only SQL, otherwise `false`.

### sqlite_stmt_sql

```php
sqlite_stmt_sql(SQLiteProceduralStatement $statement, bool $expand = false): string
```

Returns statement SQL. With `expand = true`, bound values are substituted where possible; expanded SQL can expose secrets.

- **Parameters:** `statement` is prepared; `expand` requests substituted bound values.
- **Returns:** Original or expanded SQL text.
- **Security:** Treat expanded SQL as sensitive data and do not use it as a new query.

## Transactions

### sqlite_begin_transaction

```php
sqlite_begin_transaction(SQLiteProceduralConnection $connection, int $flags = SQLITE_TRANS_DEFERRED, ?string $name = null): bool
```

Starts a transaction selected by `flags`. `name` is accepted for mysqli compatibility but ignored.

- **Parameters:** `connection` is open; `flags` is one transaction-mode constant; `name` is an ignored compatibility argument.
- **Returns:** `true` on success or `false` in non-strict mode.
- **Errors:** An invalid flag throws `ValueError`; an already managed transaction reports an error.

### sqlite_commit

```php
sqlite_commit(SQLiteProceduralConnection $connection, int $flags = 0, ?string $name = null): bool
```

Commits the active transaction. Returns `true` when none is active. Compatibility parameters are ignored.

- **Parameters:** `connection` owns the transaction; `flags` and `name` are accepted but ignored.
- **Returns:** `true` after commit or when no transaction is active; otherwise `false` in non-strict mode.

### sqlite_rollback

```php
sqlite_rollback(SQLiteProceduralConnection $connection, int $flags = 0, ?string $name = null): bool
```

Rolls back the active transaction. Returns `true` when none is active. Compatibility parameters are ignored.

- **Parameters:** `connection` owns the transaction; `flags` and `name` are accepted but ignored.
- **Returns:** `true` after rollback or when no transaction is active; otherwise `false` in non-strict mode.

### sqlite_autocommit

```php
sqlite_autocommit(SQLiteProceduralConnection $connection, bool $enable): bool
```

Changes managed autocommit. Disabling starts a deferred transaction; enabling commits the active transaction.

- **Parameters:** `connection` is open; `enable` selects autocommit state.
- **Returns:** `true` on success, including no-op state changes, or `false` in non-strict mode.

### sqlite_get_autocommit

```php
sqlite_get_autocommit(SQLiteProceduralConnection $connection): bool
```

Returns the managed autocommit state.

- **Parameters:** `connection` is the handle to inspect.
- **Returns:** `true` when managed autocommit is enabled.

### sqlite_savepoint

```php
sqlite_savepoint(SQLiteProceduralConnection $connection, string $name): bool
```

Creates a savepoint. Names must start with a letter/underscore and contain only letters, digits, and underscores.

- **Parameters:** `connection` is open; `name` is the validated savepoint identifier.
- **Returns:** `true` on success or `false` in non-strict mode.
- **Errors:** Invalid identifiers throw `ValueError`.

### sqlite_release_savepoint

```php
sqlite_release_savepoint(SQLiteProceduralConnection $connection, string $name): bool
```

Releases the named savepoint. The same identifier restrictions apply.

- **Parameters:** `connection` is open; `name` identifies the savepoint.
- **Returns:** `true` on success or `false` in non-strict mode.

### sqlite_rollback_to_savepoint

```php
sqlite_rollback_to_savepoint(SQLiteProceduralConnection $connection, string $name): bool
```

Rolls back to the named savepoint without releasing it.

- **Parameters:** `connection` is open; `name` identifies the savepoint.
- **Returns:** `true` on success or `false` in non-strict mode.

## SQLite-specific functions

### sqlite_busy_timeout

```php
sqlite_busy_timeout(SQLiteProceduralConnection $connection, int $milliseconds): bool
```

Sets the lock retry duration. `milliseconds` must be non-negative; `0` disables waiting.

- **Parameters:** `connection` is open; `milliseconds` sets the native busy timeout.
- **Returns:** The native operation result.
- **Errors:** Negative values throw `ValueError`.

### sqlite_backup

```php
sqlite_backup(SQLiteProceduralConnection $source, SQLiteProceduralConnection $destination, string $sourceDatabase = 'main', string $destinationDatabase = 'main'): bool
```

Copies an open source database to an open destination. Database names select `main`, `temp`, or attached schemas.

- **Parameters:** `source` and `destination` are open handles; the database-name strings select schemas on each handle.
- **Returns:** `true` on success or `false` in non-strict mode.

### sqlite_create_function

```php
sqlite_create_function(SQLiteProceduralConnection $connection, string $name, callable $callback, int $argCount = -1, int $flags = 0): bool
```

Registers a scalar SQL function. `argCount = -1` allows variable arguments; `flags` may contain `SQLITE3_DETERMINISTIC`.

- **Parameters:** `connection` owns the registration; `name` is the SQL name; `callback` implements it; `argCount` constrains arity; `flags` sets SQLite options.
- **Returns:** The native registration result.

### sqlite_create_aggregate

```php
sqlite_create_aggregate(SQLiteProceduralConnection $connection, string $name, callable $stepCallback, callable $finalCallback, int $argCount = -1): bool
```

Registers an aggregate. The callbacks follow `SQLite3::createAggregate()` semantics.

- **Parameters:** `connection` owns the registration; `name` is the SQL name; `stepCallback` processes rows; `finalCallback` produces the result; `argCount` constrains arity.
- **Returns:** The native registration result.

### sqlite_create_collation

```php
sqlite_create_collation(SQLiteProceduralConnection $connection, string $name, callable $callback): bool
```

Registers a collation callback that compares two strings and returns an integer.

- **Parameters:** `connection` owns the collation; `name` is used after `COLLATE`; `callback` compares two strings.
- **Returns:** The native registration result.

### sqlite_set_authorizer

```php
sqlite_set_authorizer(SQLiteProceduralConnection $connection, ?callable $callback): bool
```

Registers an authorizer, or removes it with `null`. Return `SQLite3::OK`, `SQLite3::DENY`, or `SQLite3::IGNORE`.

- **Parameters:** `connection` owns the authorizer; `callback` handles authorization events or `null` removes it.
- **Returns:** The native registration result.
- **Compatibility:** Reports an API error when the current PHP SQLite3 build lacks authorizer support.

### sqlite_open_blob

```php
sqlite_open_blob(SQLiteProceduralConnection $connection, string $table, string $column, int $rowId, string $database = 'main', int $flags = SQLITE3_OPEN_READONLY): resource|false
```

Opens one BLOB cell as a PHP stream. `flags` is `SQLITE3_OPEN_READONLY` or `SQLITE3_OPEN_READWRITE`.

- **Parameters:** `connection` is open; `table`, `column`, and `rowId` identify the cell; `database` selects the schema; `flags` selects access.
- **Returns:** A PHP stream resource or `false` in non-strict error mode. Close the resource with `fclose()`.

### sqlite_load_extension

```php
sqlite_load_extension(SQLiteProceduralConnection $connection, string $name): bool
```

Loads a trusted SQLite runtime extension when PHP and SQLite permit it. Never use untrusted names.

- **Parameters:** `connection` is open; `name` identifies an extension allowed by `sqlite3.extension_dir`.
- **Returns:** `true` on success or `false` in non-strict mode.
- **Security:** Extension loading executes native code and must never use user input.

### sqlite_enable_extended_result_codes

```php
sqlite_enable_extended_result_codes(SQLiteProceduralConnection $connection, bool $enable = true): bool
```

Enables or disables extended SQLite result codes.

- **Parameters:** `connection` is open; `enable` selects extended codes.
- **Returns:** The native operation result. Unsupported PHP builds report an API error.

## Version and connection information

### sqlite_client_info

```php
sqlite_client_info(): string
```

Returns this library's name and version, such as `sqlite-procedural/1.0.0`.

- **Parameters:** None.
- **Returns:** A library identification string.

### sqlite_get_client_info

```php
sqlite_get_client_info(): string
```

mysqli-compatible alias of `sqlite_client_info()`.

- **Parameters:** None.
- **Returns:** The same string as `sqlite_client_info()`.

### sqlite_client_version

```php
sqlite_client_version(): int
```

Returns the numeric library version. Version 1.0.0 is `10000`.

- **Parameters:** None.
- **Returns:** `major * 10000 + minor * 100 + patch`.

### sqlite_get_client_version

```php
sqlite_get_client_version(): int
```

mysqli-compatible alias of `sqlite_client_version()`.

- **Parameters:** None.
- **Returns:** The same integer as `sqlite_client_version()`.

### sqlite_server_info

```php
sqlite_server_info(SQLiteProceduralConnection $connection): string
```

Returns the linked SQLite library's version string.

- **Parameters:** `connection` is accepted for mysqli-style consistency.
- **Returns:** SQLite's `versionString`.

### sqlite_get_server_info

```php
sqlite_get_server_info(SQLiteProceduralConnection $connection): string
```

mysqli-compatible alias of `sqlite_server_info()`.

- **Parameters:** `connection` is an open connection handle.
- **Returns:** The same string as `sqlite_server_info()`.

### sqlite_server_version

```php
sqlite_server_version(SQLiteProceduralConnection $connection): int
```

Returns the linked SQLite library's numeric version.

- **Parameters:** `connection` is accepted for mysqli-style consistency.
- **Returns:** SQLite's numeric `versionNumber`.

### sqlite_get_server_version

```php
sqlite_get_server_version(SQLiteProceduralConnection $connection): int
```

mysqli-compatible alias of `sqlite_server_version()`.

- **Parameters:** `connection` is an open connection handle.
- **Returns:** The same integer as `sqlite_server_version()`.

### sqlite_character_set_name

```php
sqlite_character_set_name(SQLiteProceduralConnection $connection): string
```

Returns `UTF-8`.

- **Parameters:** `connection` is accepted for mysqli-style consistency.
- **Returns:** The literal string `UTF-8`.

### sqlite_get_charset

```php
sqlite_get_charset(SQLiteProceduralConnection $connection): object
```

Returns mysqli-style UTF-8 charset metadata.

- **Parameters:** `connection` is an open handle.
- **Returns:** An object containing `charset`, `collation`, `min_length`, `max_length`, and related compatibility fields.

### sqlite_set_charset

```php
sqlite_set_charset(SQLiteProceduralConnection $connection, string $charset): bool
```

Accepts `utf8`, `utf-8`, or `utf_8`. Other values report an error because SQLite cannot switch connection charset.

- **Parameters:** `connection` is open; `charset` is the requested encoding name.
- **Returns:** `true` for a UTF-8 spelling or `false` in non-strict mode for unsupported values.

### sqlite_thread_id

```php
sqlite_thread_id(SQLiteProceduralConnection $connection): int
```

Returns `0` because embedded SQLite has no server thread ID.

- **Parameters:** `connection` is accepted for mysqli-style consistency.
- **Returns:** Always `0`.

## Error handling

```php
sqlite_report(SQLITE_REPORT_ERROR | SQLITE_REPORT_STRICT);

try {
    $db = sqlite_connect(__DIR__ . '/app.sqlite');
    sqlite_query($db, 'SELECT * FROM missing_table');
} catch (SQLiteProceduralException $error) {
    echo $error->getCode() . ': ' . $error->getMessage();
}
```

Use `sqlite_report(SQLITE_REPORT_OFF)` to inspect `false`, `sqlite_errno()`, and `sqlite_error()` instead.

## Security

- Bind values with prepared statements; select identifiers through a fixed allowlist.
- Store database files outside publicly accessible directories.
- Load only fixed, trusted SQLite extensions.
- Keep write transactions short and set an appropriate busy timeout.
- Close BLOB streams and handles after use.

## Repository

Source, documentation, and issues: [github.com/dersnyke/php-procedural-sqlite](https://github.com/dersnyke/php-procedural-sqlite)
