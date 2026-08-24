<?php

/**
 * php-procedural-sqlite
 * =====================
 * Lite Edition — the essential procedural API for PHP's SQLite3 extension.
 *
 * Project:       https://github.com/dersnyke/php-procedural-sqlite
 * Documentation: https://github.com/dersnyke/php-procedural-sqlite/blob/main/README-lite.md
 * Edition:       Lite (32 public sqlite_* functions)
 *
 * This is a standalone edition. Do not include it together with sqlite.php;
 * both editions intentionally provide the same function names.
 */

if (!defined('SQLITE_PROCEDURAL_LITE_VERSION')) {
    if (function_exists('sqlite_connect')) {
        throw new LogicException('A sqlite_* API is already loaded; sqlite.php and sqlite-lite.php cannot be used together.');
    }

    define('SQLITE_PROCEDURAL_LITE_VERSION', '1.0.0');

    if (!defined('SQLITE_ASSOC')) {
        define('SQLITE_ASSOC', SQLITE3_ASSOC);
        define('SQLITE_NUM', SQLITE3_NUM);
        define('SQLITE_BOTH', SQLITE3_BOTH);
        define('SQLITE_REPORT_OFF', 0);
        define('SQLITE_REPORT_ERROR', 1);
        define('SQLITE_REPORT_STRICT', 2);
        define('SQLITE_TRANS_DEFERRED', 0);
        define('SQLITE_TRANS_IMMEDIATE', 1);
        define('SQLITE_TRANS_EXCLUSIVE', 2);
    }

    /** Raised for SQLite errors while SQLITE_REPORT_STRICT is enabled. */
    class SQLiteProceduralException extends RuntimeException
    {
    }

    /** @internal Opaque connection handle. */
    final class SQLiteProceduralLiteConnection
    {
        /** @internal */
        public SQLite3 $native;
        /** @internal */
        public bool $closed = false;
        /** @internal */
        public int $errorCode = 0;
        /** @internal */
        public string $errorMessage = '';
        /** @internal */
        public bool $inTransaction = false;

        /** @internal */
        public function __construct(SQLite3 $native)
        {
            $this->native = $native;
        }
    }

    /** @internal Opaque buffered result handle. */
    final class SQLiteProceduralLiteResult
    {
        /** @internal @var list<list<mixed>> */
        public array $rows;
        /** @internal @var list<string> */
        public array $columns;
        /** @internal */
        public int $cursor = 0;
        /** @internal */
        public bool $freed = false;

        /**
         * @internal
         * @param list<list<mixed>> $rows
         * @param list<string> $columns
         */
        public function __construct(array $rows, array $columns)
        {
            $this->rows = $rows;
            $this->columns = $columns;
        }
    }

    /** @internal Opaque prepared-statement handle. */
    final class SQLiteProceduralLiteStatement
    {
        /** @internal */
        public SQLiteProceduralLiteConnection $connection;
        /** @internal */
        public SQLite3Stmt $native;
        /** @internal */
        public bool $closed = false;
        /** @internal */
        public int $errorCode = 0;
        /** @internal */
        public string $errorMessage = '';
        /** @internal */
        public string $boundTypes = '';
        /** @internal @var array<int,mixed> */
        public array $boundValues = [];
        /** @internal */
        public ?SQLiteProceduralLiteResult $result = null;
        /** @internal */
        public int $affectedRows = 0;
        /** @internal */
        public int $insertId = 0;

        /** @internal */
        public function __construct(SQLiteProceduralLiteConnection $connection, SQLite3Stmt $native)
        {
            $this->connection = $connection;
            $this->native = $native;
        }
    }

    /** @internal */
    final class SQLiteProceduralLiteRuntime
    {
        public static int $reportMode = SQLITE_REPORT_ERROR | SQLITE_REPORT_STRICT;
        public static int $connectErrorCode = 0;
        public static string $connectErrorMessage = '';

        public static function fail(?SQLiteProceduralLiteConnection $connection, string $message, int $code = 1): false
        {
            if ($connection !== null) {
                $connection->errorCode = $code;
                $connection->errorMessage = $message;
            }
            if ((self::$reportMode & SQLITE_REPORT_STRICT) !== 0) {
                throw new SQLiteProceduralException($message, $code);
            }
            if ((self::$reportMode & SQLITE_REPORT_ERROR) !== 0) {
                trigger_error($message, E_USER_WARNING);
            }
            return false;
        }

        public static function statementFail(SQLiteProceduralLiteStatement $statement, string $message, int $code = 1): false
        {
            $statement->errorCode = $code;
            $statement->errorMessage = $message;
            return self::fail($statement->connection, $message, $code);
        }

        public static function nativeFail(SQLiteProceduralLiteConnection $connection): false
        {
            $code = $connection->native->lastExtendedErrorCode() ?: 1;
            return self::fail($connection, $connection->native->lastErrorMsg(), $code);
        }

        public static function statementNativeFail(SQLiteProceduralLiteStatement $statement): false
        {
            $connection = $statement->connection;
            $code = $connection->native->lastExtendedErrorCode() ?: 1;
            return self::statementFail($statement, $connection->native->lastErrorMsg(), $code);
        }

        public static function checkConnection(SQLiteProceduralLiteConnection $connection): bool
        {
            if ($connection->closed) {
                return self::fail($connection, 'SQLite connection is already closed.');
            }
            return true;
        }

        public static function clearError(SQLiteProceduralLiteConnection $connection): void
        {
            $connection->errorCode = 0;
            $connection->errorMessage = '';
        }

        public static function buffer(SQLite3Result $native): SQLiteProceduralLiteResult
        {
            $columns = [];
            for ($index = 0; $index < $native->numColumns(); $index++) {
                $columns[] = $native->columnName($index);
            }

            $rows = [];
            while (($row = $native->fetchArray(SQLITE3_NUM)) !== false) {
                $rows[] = array_values($row);
            }
            $native->finalize();
            return new SQLiteProceduralLiteResult($rows, $columns);
        }

        /** @return array<string,mixed> */
        public static function assoc(SQLiteProceduralLiteResult $result, array $row): array
        {
            $assoc = [];
            foreach ($result->columns as $index => $name) {
                $assoc[$name] = $row[$index] ?? null;
            }
            return $assoc;
        }
    }

    function sqlite_report(int $flags): bool
    {
        if (($flags & ~(SQLITE_REPORT_ERROR | SQLITE_REPORT_STRICT)) !== 0) {
            throw new ValueError('sqlite_report(): Argument #1 ($flags) contains an invalid flag.');
        }
        SQLiteProceduralLiteRuntime::$reportMode = $flags;
        return true;
    }

    function sqlite_connect(
        string $filename,
        int $flags = SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE,
        string $encryptionKey = ''
    ) {
        if (!extension_loaded('sqlite3')) {
            SQLiteProceduralLiteRuntime::$connectErrorCode = 1;
            SQLiteProceduralLiteRuntime::$connectErrorMessage = 'The SQLite3 extension is not loaded.';
            return SQLiteProceduralLiteRuntime::fail(null, SQLiteProceduralLiteRuntime::$connectErrorMessage);
        }

        try {
            $native = new SQLite3($filename, $flags, $encryptionKey);
            $native->enableExceptions(false);
            SQLiteProceduralLiteRuntime::$connectErrorCode = 0;
            SQLiteProceduralLiteRuntime::$connectErrorMessage = '';
            return new SQLiteProceduralLiteConnection($native);
        } catch (Throwable $exception) {
            $code = $exception->getCode() ?: 1;
            SQLiteProceduralLiteRuntime::$connectErrorCode = $code;
            SQLiteProceduralLiteRuntime::$connectErrorMessage = $exception->getMessage();
            return SQLiteProceduralLiteRuntime::fail(null, $exception->getMessage(), $code);
        }
    }

    function sqlite_connect_errno(): int
    {
        return SQLiteProceduralLiteRuntime::$connectErrorCode;
    }

    function sqlite_connect_error(): ?string
    {
        return SQLiteProceduralLiteRuntime::$connectErrorMessage !== ''
            ? SQLiteProceduralLiteRuntime::$connectErrorMessage
            : null;
    }

    function sqlite_close(SQLiteProceduralLiteConnection $connection): bool
    {
        if ($connection->closed) {
            return true;
        }
        if ($connection->inTransaction) {
            @$connection->native->exec('ROLLBACK');
        }
        if (!@$connection->native->close()) {
            return SQLiteProceduralLiteRuntime::nativeFail($connection);
        }
        $connection->closed = true;
        $connection->inTransaction = false;
        return true;
    }

    function sqlite_query(SQLiteProceduralLiteConnection $connection, string $query)
    {
        if (!SQLiteProceduralLiteRuntime::checkConnection($connection)) {
            return false;
        }
        SQLiteProceduralLiteRuntime::clearError($connection);
        $nativeResult = @$connection->native->query($query);
        if ($nativeResult === false) {
            return SQLiteProceduralLiteRuntime::nativeFail($connection);
        }
        if ($nativeResult->numColumns() === 0) {
            $nativeResult->finalize();
            return true;
        }
        return SQLiteProceduralLiteRuntime::buffer($nativeResult);
    }

    function sqlite_execute(SQLiteProceduralLiteConnection $connection, string $query): bool
    {
        if (!SQLiteProceduralLiteRuntime::checkConnection($connection)) {
            return false;
        }
        SQLiteProceduralLiteRuntime::clearError($connection);
        if (!@$connection->native->exec($query)) {
            return SQLiteProceduralLiteRuntime::nativeFail($connection);
        }
        return true;
    }

    function sqlite_execute_query(SQLiteProceduralLiteConnection $connection, string $query, ?array $params = null)
    {
        $statement = sqlite_prepare($connection, $query);
        if ($statement === false) {
            return false;
        }
        try {
            if (!sqlite_stmt_execute($statement, $params)) {
                return false;
            }
            if ($statement->result !== null) {
                $result = $statement->result;
                $statement->result = null;
                return $result;
            }
            return true;
        } finally {
            sqlite_stmt_close($statement);
        }
    }

    function sqlite_prepare(SQLiteProceduralLiteConnection $connection, string $query)
    {
        if (!SQLiteProceduralLiteRuntime::checkConnection($connection)) {
            return false;
        }
        SQLiteProceduralLiteRuntime::clearError($connection);
        $native = @$connection->native->prepare($query);
        if ($native === false) {
            return SQLiteProceduralLiteRuntime::nativeFail($connection);
        }
        return new SQLiteProceduralLiteStatement($connection, $native);
    }

    function sqlite_stmt_bind_param(SQLiteProceduralLiteStatement $statement, string $types, &...$variables): bool
    {
        if ($statement->closed) {
            return SQLiteProceduralLiteRuntime::statementFail($statement, 'SQLite statement is already closed.');
        }
        $expected = $statement->native->paramCount();
        if (strlen($types) !== count($variables) || count($variables) !== $expected) {
            throw new ArgumentCountError(
                sprintf('The number of type characters and variables must match the %d statement parameters.', $expected)
            );
        }
        if ($types !== '' && preg_match('/[^idsb]/', $types)) {
            throw new ValueError('sqlite_stmt_bind_param(): $types may contain only i, d, s, and b.');
        }

        $statement->boundTypes = $types;
        $statement->boundValues = [];
        foreach ($variables as $index => &$variable) {
            $statement->boundValues[$index + 1] =& $variable;
        }
        unset($variable);
        return true;
    }

    function sqlite_stmt_execute(SQLiteProceduralLiteStatement $statement, ?array $params = null): bool
    {
        if ($statement->closed) {
            return SQLiteProceduralLiteRuntime::statementFail($statement, 'SQLite statement is already closed.');
        }

        $statement->errorCode = 0;
        $statement->errorMessage = '';
        $statement->result = null;
        @$statement->native->reset();
        @$statement->native->clear();

        if ($params !== null) {
            if (count($params) !== $statement->native->paramCount()) {
                throw new ArgumentCountError('The number of values in $params must match the statement parameter count.');
            }
            foreach (array_values($params) as $index => $value) {
                $type = match (true) {
                    is_int($value), is_bool($value) => SQLITE3_INTEGER,
                    is_float($value) => SQLITE3_FLOAT,
                    is_null($value) => SQLITE3_NULL,
                    default => SQLITE3_TEXT,
                };
                if (!@$statement->native->bindValue($index + 1, $value, $type)) {
                    return SQLiteProceduralLiteRuntime::statementNativeFail($statement);
                }
            }
        } else {
            $expected = $statement->native->paramCount();
            if (count($statement->boundValues) !== $expected) {
                return SQLiteProceduralLiteRuntime::statementFail(
                    $statement,
                    sprintf('No complete parameter binding supplied: expected %d parameters, got %d.', $expected, count($statement->boundValues))
                );
            }
            foreach ($statement->boundValues as $position => &$value) {
                $typeCharacter = $statement->boundTypes[$position - 1];
                if ($value === null) {
                    $nativeType = SQLITE3_NULL;
                } else {
                    $nativeType = match ($typeCharacter) {
                        'i' => SQLITE3_INTEGER,
                        'd' => SQLITE3_FLOAT,
                        'b' => SQLITE3_BLOB,
                        default => SQLITE3_TEXT,
                    };
                }
                if (!@$statement->native->bindValue($position, $value, $nativeType)) {
                    unset($value);
                    return SQLiteProceduralLiteRuntime::statementNativeFail($statement);
                }
            }
            unset($value);
        }

        $nativeResult = @$statement->native->execute();
        if ($nativeResult === false) {
            return SQLiteProceduralLiteRuntime::statementNativeFail($statement);
        }
        if ($nativeResult->numColumns() > 0) {
            $statement->result = SQLiteProceduralLiteRuntime::buffer($nativeResult);
        } else {
            $nativeResult->finalize();
        }
        $statement->affectedRows = $statement->native->readOnly()
            ? 0
            : $statement->connection->native->changes();
        $statement->insertId = $statement->connection->native->lastInsertRowID();
        return true;
    }

    function sqlite_stmt_get_result(SQLiteProceduralLiteStatement $statement)
    {
        return $statement->result ?? false;
    }

    function sqlite_stmt_close(SQLiteProceduralLiteStatement $statement): bool
    {
        if ($statement->closed) {
            return true;
        }
        if ($statement->result !== null) {
            sqlite_free_result($statement->result);
            $statement->result = null;
        }
        if (!@$statement->native->close()) {
            return SQLiteProceduralLiteRuntime::statementNativeFail($statement);
        }
        $statement->closed = true;
        return true;
    }

    function sqlite_stmt_affected_rows(SQLiteProceduralLiteStatement $statement): int
    {
        return $statement->affectedRows;
    }

    function sqlite_stmt_insert_id(SQLiteProceduralLiteStatement $statement): int
    {
        return $statement->insertId;
    }

    function sqlite_stmt_errno(SQLiteProceduralLiteStatement $statement): int
    {
        return $statement->errorCode;
    }

    function sqlite_stmt_error(SQLiteProceduralLiteStatement $statement): string
    {
        return $statement->errorMessage;
    }

    function sqlite_fetch_array(SQLiteProceduralLiteResult $result, int $mode = SQLITE_BOTH): ?array
    {
        if ($result->freed || $result->cursor >= count($result->rows)) {
            return null;
        }
        if (!in_array($mode, [SQLITE_ASSOC, SQLITE_NUM, SQLITE_BOTH], true)) {
            throw new ValueError('sqlite_fetch_array(): $mode must be SQLITE_ASSOC, SQLITE_NUM, or SQLITE_BOTH.');
        }
        $row = $result->rows[$result->cursor++];
        if ($mode === SQLITE_NUM) {
            return $row;
        }
        $assoc = SQLiteProceduralLiteRuntime::assoc($result, $row);
        return $mode === SQLITE_ASSOC ? $assoc : $row + $assoc;
    }

    function sqlite_fetch_assoc(SQLiteProceduralLiteResult $result): ?array
    {
        return sqlite_fetch_array($result, SQLITE_ASSOC);
    }

    function sqlite_fetch_row(SQLiteProceduralLiteResult $result): ?array
    {
        return sqlite_fetch_array($result, SQLITE_NUM);
    }

    function sqlite_fetch_all(SQLiteProceduralLiteResult $result, int $mode = SQLITE_NUM): array
    {
        $rows = [];
        while (($row = sqlite_fetch_array($result, $mode)) !== null) {
            $rows[] = $row;
        }
        return $rows;
    }

    function sqlite_num_rows(SQLiteProceduralLiteResult $result): int
    {
        return $result->freed ? 0 : count($result->rows);
    }

    function sqlite_num_fields(SQLiteProceduralLiteResult $result): int
    {
        return $result->freed ? 0 : count($result->columns);
    }

    function sqlite_free_result(SQLiteProceduralLiteResult $result): void
    {
        $result->rows = [];
        $result->columns = [];
        $result->freed = true;
    }

    function sqlite_affected_rows(SQLiteProceduralLiteConnection $connection): int
    {
        return $connection->native->changes();
    }

    function sqlite_insert_id(SQLiteProceduralLiteConnection $connection): int
    {
        return $connection->native->lastInsertRowID();
    }

    function sqlite_errno(SQLiteProceduralLiteConnection $connection): int
    {
        return $connection->errorCode;
    }

    function sqlite_error(SQLiteProceduralLiteConnection $connection): string
    {
        return $connection->errorMessage;
    }

    function sqlite_real_escape_string(SQLiteProceduralLiteConnection $connection, string $string): string
    {
        SQLiteProceduralLiteRuntime::checkConnection($connection);
        return SQLite3::escapeString($string);
    }

    function sqlite_begin_transaction(
        SQLiteProceduralLiteConnection $connection,
        int $flags = SQLITE_TRANS_DEFERRED,
        ?string $name = null
    ): bool {
        if (!SQLiteProceduralLiteRuntime::checkConnection($connection)) {
            return false;
        }
        if ($connection->inTransaction) {
            return SQLiteProceduralLiteRuntime::fail($connection, 'A transaction is already active.');
        }
        $kind = match ($flags) {
            SQLITE_TRANS_DEFERRED => 'DEFERRED',
            SQLITE_TRANS_IMMEDIATE => 'IMMEDIATE',
            SQLITE_TRANS_EXCLUSIVE => 'EXCLUSIVE',
            default => throw new ValueError('sqlite_begin_transaction(): invalid transaction flag.'),
        };
        if (!@$connection->native->exec('BEGIN ' . $kind)) {
            return SQLiteProceduralLiteRuntime::nativeFail($connection);
        }
        $connection->inTransaction = true;
        return true;
    }

    function sqlite_commit(SQLiteProceduralLiteConnection $connection, int $flags = 0, ?string $name = null): bool
    {
        if (!SQLiteProceduralLiteRuntime::checkConnection($connection)) {
            return false;
        }
        if (!$connection->inTransaction) {
            return true;
        }
        if (!@$connection->native->exec('COMMIT')) {
            return SQLiteProceduralLiteRuntime::nativeFail($connection);
        }
        $connection->inTransaction = false;
        return true;
    }

    function sqlite_rollback(SQLiteProceduralLiteConnection $connection, int $flags = 0, ?string $name = null): bool
    {
        if (!SQLiteProceduralLiteRuntime::checkConnection($connection)) {
            return false;
        }
        if (!$connection->inTransaction) {
            return true;
        }
        if (!@$connection->native->exec('ROLLBACK')) {
            return SQLiteProceduralLiteRuntime::nativeFail($connection);
        }
        $connection->inTransaction = false;
        return true;
    }
}
