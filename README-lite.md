# php-procedural-sqlite — Lite Edition

`sqlite-lite.php` is the small, standalone edition of the procedural SQLite compatibility layer. It contains only what typical applications need for connections, CRUD queries, prepared statements, result fetching, error handling, escaping, and transactions.

Application code calls functions such as `sqlite_connect()`, `sqlite_query()`, `sqlite_fetch_assoc()`, and `sqlite_stmt_execute()`. Intern, the library uses PHP's `SQLite3` extension and buffers results to provide mysqli-style behavior such as `sqlite_num_rows()`.

## AI attribution

> **This project is 100% AI-generated.**

All PHP source code and documentation in this repository were generated with [OpenAI Codex](https://developers.openai.com/codex), an AI coding agent by OpenAI. A human supplied the requirements and directed the work; the resulting project files were generated entirely by AI.

## Requirements

- PHP 8.2 or newer
- The PHP `sqlite3` extension
- Write permission for the database directory unless an in-memory database is used

## Installation

```php
require_once __DIR__ . '/sqlite/sqlite-lite.php';
```

Do not load `sqlite.php` and `sqlite-lite.php` together. Both editions intentionally define the same `sqlite_*` names. The Lite Edition does not load the [Full Edition](README.md) and has no additional runtime dependencies.

## Quick start

```php
<?php

require_once __DIR__ . '/sqlite/sqlite-lite.php';
$db = sqlite_connect(__DIR__ . '/app.sqlite');

sqlite_execute($db, 'CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');

$stmt = sqlite_prepare($db, 'INSERT INTO users (name) VALUES (?)');
$name = 'Ada';
sqlite_stmt_bind_param($stmt, 's', $name);
sqlite_stmt_execute($stmt);
sqlite_stmt_close($stmt);

$result = sqlite_query($db, 'SELECT id, name FROM users ORDER BY id');
while ($row = sqlite_fetch_assoc($result)) {
    echo $row['id'] . ': ' . $row['name'] . PHP_EOL;
}

sqlite_free_result($result);
sqlite_close($db);
```

## Scope

The Lite Edition provides 32 public functions for:

- Connections and report modes
- Direct and parameterized CRUD queries
- Prepared statements with reference binding
- Associative and numeric result fetching
- Result dimensions and cleanup
- Error codes and messages
- Insert IDs and changed-row counts
- Transactions, commits, and rollbacks

The Lite Edition omits multi-queries, random-access result cursors, field metadata, object hydration, `bind_result`, savepoints, managed autocommit, BLOB streams, backups, custom SQL functions, collations, authorizers, extension loading, and server/charset compatibility helpers. Use the Full Edition when those features are required.

## Constants

| Group | Constants |
|---|---|
| Fetch modes | `SQLITE_ASSOC`, `SQLITE_NUM`, `SQLITE_BOTH` |
| Report modes | `SQLITE_REPORT_OFF`, `SQLITE_REPORT_ERROR`, `SQLITE_REPORT_STRICT` |
| Transactions | `SQLITE_TRANS_DEFERRED`, `SQLITE_TRANS_IMMEDIATE`, `SQLITE_TRANS_EXCLUSIVE` |

The default report mode is `SQLITE_REPORT_ERROR | SQLITE_REPORT_STRICT`. Native `SQLITE3_OPEN_READONLY`, `SQLITE3_OPEN_READWRITE`, and `SQLITE3_OPEN_CREATE` constants can be passed to `sqlite_connect()`.

## Connections and reporting

### sqlite_report

```php
sqlite_report(int $flags): bool
```

Sets global error handling. `flags` is a bitwise combination of `SQLITE_REPORT_ERROR` and `SQLITE_REPORT_STRICT`; `SQLITE_REPORT_OFF` disables both. Returns `true`; invalid bits throw `ValueError`.

- **Parameters:** `flags` selects warnings and/or strict exceptions; pass `SQLITE_REPORT_OFF` for return-value-only handling.
- **Returns:** Always `true` for valid flags.
- **Errors:** Unknown flag bits throw `ValueError`.

### sqlite_connect

```php
sqlite_connect(string $filename, int $flags = SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE, string $encryptionKey = ''): SQLiteProceduralLiteConnection|false
```

Opens `filename`, or `:memory:` for an in-memory database. `flags` contains native `SQLITE3_OPEN_*` values. `encryptionKey` applies only to suitably compiled builds. Returns an opaque handle, or `false`/throws on failure.

- **Parameters:** `filename` is the database path; `flags` selects read-only, read/write, and create behavior; `encryptionKey` is passed to the native driver.
- **Returns:** `SQLiteProceduralLiteConnection` on success or `false` when strict reporting is disabled.
- **Errors:** Failures update `sqlite_connect_errno()` and `sqlite_connect_error()`.

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
- **Returns:** The last connection error message or `null`.

### sqlite_close

```php
sqlite_close(SQLiteProceduralLiteConnection $connection): bool
```

Closes the connection. An active managed transaction is rolled back first. Repeated closing returns `true`.

- **Parameters:** `connection` is the opaque Lite handle to close.
- **Returns:** `true` when closed/already closed, otherwise `false` in non-strict mode.

## Direct queries

### sqlite_query

```php
sqlite_query(SQLiteProceduralLiteConnection $connection, string $query): SQLiteProceduralLiteResult|bool
```

Executes one SQL statement. Queries return a buffered result handle; statements without result columns return `true`. Errors return `false` or throw according to the report mode.

- **Parameters:** `connection` is open; `query` is one complete SQL statement.
- **Returns:** `SQLiteProceduralLiteResult` for row-producing SQL, `true` for successful non-row SQL, or `false` in non-strict mode.

### sqlite_execute

```php
sqlite_execute(SQLiteProceduralLiteConnection $connection, string $query): bool
```

Executes SQL without exposing results. It is suitable for schema changes and fixed writes and may contain multiple statements. Returns `true` on success.

- **Parameters:** `connection` is open; `query` contains SQL whose rows, if any, are discarded.
- **Returns:** `true` on success or `false` in non-strict mode.

### sqlite_execute_query

```php
sqlite_execute_query(SQLiteProceduralLiteConnection $connection, string $query, ?array $params = null): SQLiteProceduralLiteResult|bool
```

Prepares `query`, binds positional `params`, executes it, and closes the internal statement. Queries return a result; successful writes return `true`. This mirrors `mysqli_execute_query()`.

- **Parameters:** `connection` is open; `query` contains optional positional placeholders; `params` supplies values in order.
- **Returns:** A buffered Lite result for queries, `true` for successful writes, or `false` on error.
- **Errors:** Parameter-count mismatches throw `ArgumentCountError`.

### sqlite_affected_rows

```php
sqlite_affected_rows(SQLiteProceduralLiteConnection $connection): int
```

Returns the number of rows changed by the latest write operation.

- **Parameters:** `connection` is the handle to inspect.
- **Returns:** The native SQLite changed-row count.

### sqlite_insert_id

```php
sqlite_insert_id(SQLiteProceduralLiteConnection $connection): int
```

Returns the row ID generated by the latest insert.

- **Parameters:** `connection` is the handle to inspect.
- **Returns:** The latest SQLite row ID.

### sqlite_real_escape_string

```php
sqlite_real_escape_string(SQLiteProceduralLiteConnection $connection, string $string): string
```

Escapes single quotes for use inside a SQLite string literal. Surrounding quotes are not added.

- **Parameters:** `connection` must be open; `string` is the raw literal value.
- **Returns:** Escaped text without surrounding quote delimiters.
- **Security:** This does not escape identifiers; use parameter binding whenever possible.

```php
$name = sqlite_real_escape_string($db, $name);
$result = sqlite_query($db, "SELECT * FROM users WHERE name = '$name'");
```

Prepared statements remain preferable for untrusted values.

## Prepared statements

### sqlite_prepare

```php
sqlite_prepare(SQLiteProceduralLiteConnection $connection, string $query): SQLiteProceduralLiteStatement|false
```

Prepares one statement. Positional `?` placeholders are recommended for mysqli-style use. Returns a statement handle, or `false`/throws on failure.

- **Parameters:** `connection` is open; `query` is one SQL statement with optional placeholders.
- **Returns:** `SQLiteProceduralLiteStatement` on success or `false` in non-strict mode.

### sqlite_stmt_bind_param

```php
sqlite_stmt_bind_param(SQLiteProceduralLiteStatement $statement, string $types, mixed &$variable, mixed &...$variables): bool
```

Binds variables by reference, reading current values on every execution. `types` contains one character per parameter: `i` integer, `d` float, `s` text, or `b` BLOB. Counts must match. `null` always binds SQL `NULL`.

- **Parameters:** `statement` is prepared; `types` declares every binding; `variable`/`variables` are references in placeholder order.
- **Returns:** `true` after binding.
- **Errors:** Count mismatches throw `ArgumentCountError`; unknown type letters throw `ValueError`.

### sqlite_stmt_execute

```php
sqlite_stmt_execute(SQLiteProceduralLiteStatement $statement, ?array $params = null): bool
```

Executes the statement. Without `params`, every parameter must be bound with `sqlite_stmt_bind_param()`. Otherwise, `params` supplies positional values whose types are inferred. Returns `true` and buffers any result.

- **Parameters:** `statement` is prepared; `params` optionally supplies a one-shot positional value list.
- **Returns:** `true` on success or `false` in non-strict mode.
- **Errors:** Missing bindings report an API error; incorrectly sized arrays throw `ArgumentCountError`.

### sqlite_stmt_get_result

```php
sqlite_stmt_get_result(SQLiteProceduralLiteStatement $statement): SQLiteProceduralLiteResult|false
```

Returns the buffered result from the latest execution, or `false` without result columns.

- **Parameters:** `statement` is an executed Lite statement.
- **Returns:** `SQLiteProceduralLiteResult` or `false`.

### sqlite_stmt_close

```php
sqlite_stmt_close(SQLiteProceduralLiteStatement $statement): bool
```

Releases the current result and native statement. Repeated closing returns `true`.

- **Parameters:** `statement` is the Lite handle to close.
- **Returns:** `true` on success/already closed, otherwise `false` in non-strict mode.

### sqlite_stmt_affected_rows

```php
sqlite_stmt_affected_rows(SQLiteProceduralLiteStatement $statement): int
```

Returns the number of rows changed by the latest execution. Read-only queries report `0`.

- **Parameters:** `statement` is the statement to inspect.
- **Returns:** Changed-row count as an integer.

### sqlite_stmt_insert_id

```php
sqlite_stmt_insert_id(SQLiteProceduralLiteStatement $statement): int
```

Returns the row ID recorded after the latest execution.

- **Parameters:** `statement` is the statement to inspect.
- **Returns:** Last recorded SQLite row ID.

### sqlite_stmt_errno

```php
sqlite_stmt_errno(SQLiteProceduralLiteStatement $statement): int
```

Returns the last stored statement error code, or `0`.

- **Parameters:** `statement` is the statement to inspect.
- **Returns:** Stored integer error code.

### sqlite_stmt_error

```php
sqlite_stmt_error(SQLiteProceduralLiteStatement $statement): string
```

Returns the last stored statement error message, or an empty string.

- **Parameters:** `statement` is the statement to inspect.
- **Returns:** Stored error text or `''`.

## Result sets

### sqlite_fetch_array

```php
sqlite_fetch_array(SQLiteProceduralLiteResult $result, int $mode = SQLITE_BOTH): ?array
```

Fetches the next row using `SQLITE_ASSOC`, `SQLITE_NUM`, or `SQLITE_BOTH`. Returns `null` at the end; invalid modes throw `ValueError`.

- **Parameters:** `result` is a buffered Lite result; `mode` controls associative/numeric keys.
- **Returns:** The next row as an array or `null` when exhausted/freed.

### sqlite_fetch_assoc

```php
sqlite_fetch_assoc(SQLiteProceduralLiteResult $result): ?array
```

Fetches the next row with column names as keys, or `null` at the end. Later duplicate names overwrite earlier values.

- **Parameters:** `result` is a buffered Lite result.
- **Returns:** The next associative row or `null`.

### sqlite_fetch_row

```php
sqlite_fetch_row(SQLiteProceduralLiteResult $result): ?array
```

Fetches the next row as a zero-based numeric array, or `null` at the end.

- **Parameters:** `result` is a buffered Lite result.
- **Returns:** The next numeric row or `null`.

### sqlite_fetch_all

```php
sqlite_fetch_all(SQLiteProceduralLiteResult $result, int $mode = SQLITE_NUM): array
```

Returns every remaining row in the selected fetch mode and advances the cursor to the end.

- **Parameters:** `result` is buffered; `mode` is one of the three fetch constants.
- **Returns:** A list of remaining rows, possibly empty.

### sqlite_num_rows

```php
sqlite_num_rows(SQLiteProceduralLiteResult $result): int
```

Returns the total number of buffered rows, or `0` after the result is freed.

- **Parameters:** `result` is the Lite result to inspect.
- **Returns:** Total buffered row count.

### sqlite_num_fields

```php
sqlite_num_fields(SQLiteProceduralLiteResult $result): int
```

Returns the number of result columns, or `0` after the result is freed.

- **Parameters:** `result` is the Lite result to inspect.
- **Returns:** Result-column count.

### sqlite_free_result

```php
sqlite_free_result(SQLiteProceduralLiteResult $result): void
```

Releases buffered rows and column names. Do not fetch from the handle afterward.

- **Parameters:** `result` is the buffered handle to clear.
- **Returns:** No value.

## Errors

### sqlite_errno

```php
sqlite_errno(SQLiteProceduralLiteConnection $connection): int
```

Returns the last stored connection error code, or `0`.

- **Parameters:** `connection` is the Lite connection to inspect.
- **Returns:** Stored SQLite error code.

### sqlite_error

```php
sqlite_error(SQLiteProceduralLiteConnection $connection): string
```

Returns the last stored connection error message, or an empty string.

- **Parameters:** `connection` is the Lite connection to inspect.
- **Returns:** Stored error text or `''`.

With exceptions:

```php
sqlite_report(SQLITE_REPORT_ERROR | SQLITE_REPORT_STRICT);

try {
    $db = sqlite_connect(__DIR__ . '/app.sqlite');
    sqlite_query($db, 'SELECT * FROM missing_table');
} catch (SQLiteProceduralException $error) {
    printf("SQLite error %d: %s\n", $error->getCode(), $error->getMessage());
}
```

Use `sqlite_report(SQLITE_REPORT_OFF)` to inspect `false`, `sqlite_errno()`, and `sqlite_error()` instead.

## Transactions

### sqlite_begin_transaction

```php
sqlite_begin_transaction(SQLiteProceduralLiteConnection $connection, int $flags = SQLITE_TRANS_DEFERRED, ?string $name = null): bool
```

Starts a transaction. `flags` selects deferred, immediate, or exclusive mode. `name` is accepted for mysqli compatibility but ignored. Starting another managed transaction reports an error.

- **Parameters:** `connection` is open; `flags` is one Lite transaction constant; `name` is an ignored compatibility argument.
- **Returns:** `true` on success or `false` in non-strict mode.
- **Errors:** Invalid flags throw `ValueError`; an active managed transaction reports an API error.

### sqlite_commit

```php
sqlite_commit(SQLiteProceduralLiteConnection $connection, int $flags = 0, ?string $name = null): bool
```

Commits the active transaction. Returns `true` when none is active. `flags` and `name` are ignored compatibility parameters.

- **Parameters:** `connection` owns the transaction; `flags` and `name` are accepted but ignored.
- **Returns:** `true` after commit or when no transaction is active; otherwise `false` in non-strict mode.

### sqlite_rollback

```php
sqlite_rollback(SQLiteProceduralLiteConnection $connection, int $flags = 0, ?string $name = null): bool
```

Rolls back the active transaction. Returns `true` when none is active. `flags` and `name` are ignored compatibility parameters.

- **Parameters:** `connection` owns the transaction; `flags` and `name` are accepted but ignored.
- **Returns:** `true` after rollback or when no transaction is active; otherwise `false` in non-strict mode.

```php
sqlite_begin_transaction($db, SQLITE_TRANS_IMMEDIATE);

try {
    sqlite_execute_query($db, 'UPDATE accounts SET balance = balance - ? WHERE id = ?', [100, 1]);
    sqlite_execute_query($db, 'UPDATE accounts SET balance = balance + ? WHERE id = ?', [100, 2]);
    sqlite_commit($db);
} catch (Throwable $error) {
    sqlite_rollback($db);
    throw $error;
}
```

## Security and behavior

- Bind untrusted values with prepared statements.
- Choose table and column names through a fixed allowlist; identifiers cannot be bound.
- Store database files outside publicly accessible directories.
- Results are fully buffered; limit or paginate very large queries.
- SQLite is embedded and requires no host, username, or password.

## Repository

Source, documentation, and issues: [github.com/dersnyke/php-procedural-sqlite](https://github.com/dersnyke/php-procedural-sqlite)
