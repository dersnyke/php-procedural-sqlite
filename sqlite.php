<?php

/**
 * php-procedural-sqlite
 * =====================
 * Full Edition — a procedural, mysqli-inspired API for PHP's SQLite3 extension.
 *
 * Project:       https://github.com/dersnyke/php-procedural-sqlite
 * Documentation: https://github.com/dersnyke/php-procedural-sqlite#readme
 * Edition:       Full (90 public sqlite_* functions)
 *
 * Include this file with require_once and use only the sqlite_* functions.
 * The handle classes below are internal implementation details and expose no
 * public database methods.
 */

if (!defined('SQLITE_PROCEDURAL_VERSION')) {
    if (function_exists('sqlite_connect')) {
        throw new LogicException('A sqlite_* API is already loaded; sqlite.php and sqlite-lite.php cannot be used together.');
    }

    define('SQLITE_PROCEDURAL_VERSION', '1.0.0');

    define('SQLITE_ASSOC', SQLITE3_ASSOC);
    define('SQLITE_NUM', SQLITE3_NUM);
    define('SQLITE_BOTH', SQLITE3_BOTH);

    define('SQLITE_REPORT_OFF', 0);
    define('SQLITE_REPORT_ERROR', 1);
    define('SQLITE_REPORT_STRICT', 2);

    define('SQLITE_TRANS_DEFERRED', 0);
    define('SQLITE_TRANS_IMMEDIATE', 1);
    define('SQLITE_TRANS_EXCLUSIVE', 2);

    define('SQLITE_TYPE_INTEGER', SQLITE3_INTEGER);
    define('SQLITE_TYPE_FLOAT', SQLITE3_FLOAT);
    define('SQLITE_TYPE_TEXT', SQLITE3_TEXT);
    define('SQLITE_TYPE_BLOB', SQLITE3_BLOB);
    define('SQLITE_TYPE_NULL', SQLITE3_NULL);

    /** Raised when SQLITE_REPORT_STRICT is enabled. */
    class SQLiteProceduralException extends RuntimeException
    {
    }

    /** @internal Opaque connection handle. */
    final class SQLiteProceduralConnection
    {
        /** @internal */
        public SQLite3 $native;
        /** @internal */
        public string $filename;
        /** @internal */
        public bool $closed = false;
        /** @internal */
        public int $errorCode = 0;
        /** @internal */
        public string $errorMessage = '';
        /** @internal */
        public bool $autoCommit = true;
        /** @internal */
        public bool $inTransaction = false;
        /** @internal @var list<SQLiteProceduralResult|bool> */
        public array $multiResults = [];
        /** @internal */
        public int $multiIndex = 0;

        /** @internal */
        public function __construct(SQLite3 $native, string $filename)
        {
            $this->native = $native;
            $this->filename = $filename;
        }
    }

    /** @internal Opaque buffered result handle. */
    final class SQLiteProceduralResult
    {
        /** @internal @var list<list<mixed>> */
        public array $rows;
        /** @internal @var list<string> */
        public array $columns;
        /** @internal @var list<int> */
        public array $types;
        /** @internal */
        public int $cursor = 0;
        /** @internal */
        public int $fieldCursor = 0;
        /** @internal @var list<mixed>|null */
        public ?array $lastRow = null;
        /** @internal */
        public bool $freed = false;

        /** @internal
         * @param list<list<mixed>> $rows
         * @param list<string> $columns
         * @param list<int> $types
         */
        public function __construct(array $rows, array $columns, array $types)
        {
            $this->rows = $rows;
            $this->columns = $columns;
            $this->types = $types;
        }
    }

    /** @internal Opaque prepared-statement handle. */
    final class SQLiteProceduralStatement
    {
        /** @internal */
        public SQLiteProceduralConnection $connection;
        /** @internal */
        public SQLite3Stmt $native;
        /** @internal */
        public string $sql;
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
        /** @internal @var array<int,string> */
        public array $longData = [];
        /** @internal @var array<int,mixed> */
        public array $boundResults = [];
        /** @internal */
        public ?SQLiteProceduralResult $result = null;
        /** @internal */
        public int $affectedRows = 0;
        /** @internal */
        public int $insertId = 0;

        /** @internal */
        public function __construct(SQLiteProceduralConnection $connection, SQLite3Stmt $native, string $sql)
        {
            $this->connection = $connection;
            $this->native = $native;
            $this->sql = $sql;
        }
    }

    /** @internal */
    final class SQLiteProceduralRuntime
    {
        public static int $reportMode = SQLITE_REPORT_ERROR | SQLITE_REPORT_STRICT;
        public static int $connectErrorCode = 0;
        public static string $connectErrorMessage = '';

        public static function fail(?SQLiteProceduralConnection $connection, string $message, int $code = 1): false
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

        public static function stmtFail(SQLiteProceduralStatement $statement, string $message, int $code = 1): false
        {
            $statement->errorCode = $code;
            $statement->errorMessage = $message;
            return self::fail($statement->connection, $message, $code);
        }

        public static function clearError(SQLiteProceduralConnection $connection): void
        {
            $connection->errorCode = 0;
            $connection->errorMessage = '';
        }

        public static function checkConnection(SQLiteProceduralConnection $connection): bool
        {
            if ($connection->closed) {
                return self::fail($connection, 'SQLite connection is already closed.');
            }
            return true;
        }

        public static function ensureTransaction(SQLiteProceduralConnection $connection): bool
        {
            if (!$connection->autoCommit && !$connection->inTransaction) {
                if (!@$connection->native->exec('BEGIN DEFERRED')) {
                    return self::nativeFail($connection);
                }
                $connection->inTransaction = true;
            }
            return true;
        }

        public static function nativeFail(SQLiteProceduralConnection $connection): false
        {
            $code = $connection->native->lastExtendedErrorCode();
            $message = $connection->native->lastErrorMsg();
            return self::fail($connection, $message, $code ?: 1);
        }

        public static function statementNativeFail(SQLiteProceduralStatement $statement): false
        {
            $connection = $statement->connection;
            $code = $connection->native->lastExtendedErrorCode();
            $message = $connection->native->lastErrorMsg();
            return self::stmtFail($statement, $message, $code ?: 1);
        }

        public static function buffer(SQLite3Result $native): SQLiteProceduralResult
        {
            $columnCount = $native->numColumns();
            $columns = [];
            $types = [];
            for ($i = 0; $i < $columnCount; $i++) {
                $columns[] = $native->columnName($i);
                $types[] = $native->columnType($i);
            }

            $rows = [];
            while (($row = $native->fetchArray(SQLITE3_NUM)) !== false) {
                $rows[] = array_values($row);
                if (count($rows) === 1) {
                    for ($i = 0; $i < $columnCount; $i++) {
                        $types[$i] = $native->columnType($i);
                    }
                }
            }
            $native->finalize();
            return new SQLiteProceduralResult($rows, $columns, $types);
        }

        /** @return array<string,mixed> */
        public static function assoc(SQLiteProceduralResult $result, array $row): array
        {
            $assoc = [];
            foreach ($result->columns as $index => $name) {
                $assoc[$name] = $row[$index] ?? null;
            }
            return $assoc;
        }

        public static function fieldObject(SQLiteProceduralResult $result, int $index): object
        {
            return (object) [
                'name' => $result->columns[$index],
                'orgname' => $result->columns[$index],
                'table' => '',
                'orgtable' => '',
                'def' => '',
                'db' => '',
                'catalog' => 'main',
                'max_length' => 0,
                'length' => 0,
                'charsetnr' => 65001,
                'flags' => 0,
                'type' => $result->types[$index] ?? SQLITE3_NULL,
                'decimals' => 0,
            ];
        }

        /** @return list<string> */
        public static function splitSql(string $sql): array
        {
            $statements = [];
            $buffer = '';
            $length = strlen($sql);
            $quote = null;
            $lineComment = false;
            $blockComment = false;

            for ($i = 0; $i < $length; $i++) {
                $character = $sql[$i];
                $next = $i + 1 < $length ? $sql[$i + 1] : '';

                if ($lineComment) {
                    $buffer .= $character;
                    if ($character === "\n") {
                        $lineComment = false;
                    }
                    continue;
                }
                if ($blockComment) {
                    $buffer .= $character;
                    if ($character === '*' && $next === '/') {
                        $buffer .= '/';
                        $i++;
                        $blockComment = false;
                    }
                    continue;
                }
                if ($quote !== null) {
                    $buffer .= $character;
                    if ($quote === ']' && $character === ']') {
                        $quote = null;
                    } elseif ($character === $quote) {
                        if (($quote === "'" || $quote === '"' || $quote === '`') && $next === $quote) {
                            $buffer .= $next;
                            $i++;
                        } else {
                            $quote = null;
                        }
                    }
                    continue;
                }

                if ($character === '-' && $next === '-') {
                    $buffer .= '--';
                    $i++;
                    $lineComment = true;
                    continue;
                }
                if ($character === '/' && $next === '*') {
                    $buffer .= '/*';
                    $i++;
                    $blockComment = true;
                    continue;
                }
                if ($character === "'" || $character === '"' || $character === '`') {
                    $quote = $character;
                    $buffer .= $character;
                    continue;
                }
                if ($character === '[') {
                    $quote = ']';
                    $buffer .= $character;
                    continue;
                }
                if ($character === ';') {
                    $statement = trim($buffer);
                    if ($statement !== '') {
                        $statements[] = $statement;
                    }
                    $buffer = '';
                    continue;
                }
                $buffer .= $character;
            }

            $statement = trim($buffer);
            if ($statement !== '') {
                $statements[] = $statement;
            }
            return $statements;
        }
    }

    function sqlite_report(int $flags): bool
    {
        if (($flags & ~(SQLITE_REPORT_ERROR | SQLITE_REPORT_STRICT)) !== 0) {
            throw new ValueError('sqlite_report(): Argument #1 ($flags) contains an invalid flag.');
        }
        SQLiteProceduralRuntime::$reportMode = $flags;
        return true;
    }

    function sqlite_connect(
        string $filename,
        int $flags = SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE,
        string $encryptionKey = ''
    ) {
        if (!extension_loaded('sqlite3')) {
            SQLiteProceduralRuntime::$connectErrorCode = 1;
            SQLiteProceduralRuntime::$connectErrorMessage = 'The SQLite3 extension is not loaded.';
            return SQLiteProceduralRuntime::fail(null, SQLiteProceduralRuntime::$connectErrorMessage);
        }

        try {
            $native = new SQLite3($filename, $flags, $encryptionKey);
            $native->enableExceptions(false);
            $connection = new SQLiteProceduralConnection($native, $filename);
            SQLiteProceduralRuntime::$connectErrorCode = 0;
            SQLiteProceduralRuntime::$connectErrorMessage = '';
            return $connection;
        } catch (Throwable $exception) {
            SQLiteProceduralRuntime::$connectErrorCode = $exception->getCode() ?: 1;
            SQLiteProceduralRuntime::$connectErrorMessage = $exception->getMessage();
            return SQLiteProceduralRuntime::fail(null, $exception->getMessage(), $exception->getCode() ?: 1);
        }
    }

    function sqlite_connect_errno(): int
    {
        return SQLiteProceduralRuntime::$connectErrorCode;
    }

    function sqlite_connect_error(): ?string
    {
        return SQLiteProceduralRuntime::$connectErrorMessage !== ''
            ? SQLiteProceduralRuntime::$connectErrorMessage
            : null;
    }

    function sqlite_close(SQLiteProceduralConnection $connection): bool
    {
        if ($connection->closed) {
            return true;
        }
        if ($connection->inTransaction) {
            @$connection->native->exec('ROLLBACK');
        }
        $ok = @$connection->native->close();
        if (!$ok) {
            return SQLiteProceduralRuntime::nativeFail($connection);
        }
        $connection->closed = true;
        return true;
    }

    function sqlite_query(SQLiteProceduralConnection $connection, string $query)
    {
        if (!SQLiteProceduralRuntime::checkConnection($connection)
            || !SQLiteProceduralRuntime::ensureTransaction($connection)) {
            return false;
        }
        SQLiteProceduralRuntime::clearError($connection);
        $nativeResult = @$connection->native->query($query);
        if ($nativeResult === false) {
            return SQLiteProceduralRuntime::nativeFail($connection);
        }
        if ($nativeResult->numColumns() === 0) {
            $nativeResult->finalize();
            return true;
        }
        return SQLiteProceduralRuntime::buffer($nativeResult);
    }

    function sqlite_execute(SQLiteProceduralConnection $connection, string $query): bool
    {
        if (!SQLiteProceduralRuntime::checkConnection($connection)
            || !SQLiteProceduralRuntime::ensureTransaction($connection)) {
            return false;
        }
        SQLiteProceduralRuntime::clearError($connection);
        if (!@$connection->native->exec($query)) {
            return SQLiteProceduralRuntime::nativeFail($connection);
        }
        return true;
    }

    function sqlite_execute_query(SQLiteProceduralConnection $connection, string $query, ?array $params = null)
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

    function sqlite_query_single(SQLiteProceduralConnection $connection, string $query, bool $entireRow = false)
    {
        if (!SQLiteProceduralRuntime::checkConnection($connection)
            || !SQLiteProceduralRuntime::ensureTransaction($connection)) {
            return false;
        }
        SQLiteProceduralRuntime::clearError($connection);
        $value = @$connection->native->querySingle($query, $entireRow);
        if ($connection->native->lastErrorCode() !== 0) {
            return SQLiteProceduralRuntime::nativeFail($connection);
        }
        return $value;
    }

    function sqlite_multi_query(SQLiteProceduralConnection $connection, string $query): bool
    {
        if (!SQLiteProceduralRuntime::checkConnection($connection)
            || !SQLiteProceduralRuntime::ensureTransaction($connection)) {
            return false;
        }
        SQLiteProceduralRuntime::clearError($connection);
        $connection->multiResults = [];
        $connection->multiIndex = 0;
        $statements = SQLiteProceduralRuntime::splitSql($query);
        if ($statements === []) {
            return SQLiteProceduralRuntime::fail($connection, 'The multi-query string contains no SQL statement.');
        }
        foreach ($statements as $statement) {
            $result = sqlite_query($connection, $statement);
            if ($result === false) {
                return false;
            }
            $connection->multiResults[] = $result;
        }
        return true;
    }

    function sqlite_store_result(SQLiteProceduralConnection $connection)
    {
        if (!isset($connection->multiResults[$connection->multiIndex])) {
            return false;
        }
        $result = $connection->multiResults[$connection->multiIndex];
        return $result instanceof SQLiteProceduralResult ? $result : false;
    }

    function sqlite_more_results(SQLiteProceduralConnection $connection): bool
    {
        return isset($connection->multiResults[$connection->multiIndex + 1]);
    }

    function sqlite_next_result(SQLiteProceduralConnection $connection): bool
    {
        if (!sqlite_more_results($connection)) {
            return false;
        }
        $connection->multiIndex++;
        return true;
    }

    function sqlite_prepare(SQLiteProceduralConnection $connection, string $query)
    {
        if (!SQLiteProceduralRuntime::checkConnection($connection)) {
            return false;
        }
        SQLiteProceduralRuntime::clearError($connection);
        $native = @$connection->native->prepare($query);
        if ($native === false) {
            return SQLiteProceduralRuntime::nativeFail($connection);
        }
        return new SQLiteProceduralStatement($connection, $native, $query);
    }

    function sqlite_stmt_bind_param(SQLiteProceduralStatement $statement, string $types, &...$variables): bool
    {
        if ($statement->closed) {
            return SQLiteProceduralRuntime::stmtFail($statement, 'SQLite statement is already closed.');
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

    function sqlite_stmt_send_long_data(SQLiteProceduralStatement $statement, int $paramNum, string $data): bool
    {
        if ($paramNum < 0 || $paramNum >= strlen($statement->boundTypes)) {
            throw new ValueError('sqlite_stmt_send_long_data(): $paramNum is out of range.');
        }
        if ($statement->boundTypes[$paramNum] !== 'b') {
            return SQLiteProceduralRuntime::stmtFail($statement, 'Long data can only be sent to a b parameter.');
        }
        $statement->longData[$paramNum + 1] = ($statement->longData[$paramNum + 1] ?? '') . $data;
        return true;
    }

    function sqlite_stmt_execute(SQLiteProceduralStatement $statement, ?array $params = null): bool
    {
        if ($statement->closed) {
            return SQLiteProceduralRuntime::stmtFail($statement, 'SQLite statement is already closed.');
        }
        if (!SQLiteProceduralRuntime::ensureTransaction($statement->connection)) {
            return false;
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
                    return SQLiteProceduralRuntime::statementNativeFail($statement);
                }
            }
        } else {
            $expected = $statement->native->paramCount();
            if (count($statement->boundValues) !== $expected) {
                return SQLiteProceduralRuntime::stmtFail(
                    $statement,
                    sprintf('No complete parameter binding supplied: expected %d parameters, got %d.', $expected, count($statement->boundValues))
                );
            }
            foreach ($statement->boundValues as $position => &$value) {
                $typeCharacter = $statement->boundTypes[$position - 1];
                $boundValue = $statement->longData[$position] ?? $value;
                if ($boundValue === null) {
                    $nativeType = SQLITE3_NULL;
                } else {
                    $nativeType = match ($typeCharacter) {
                        'i' => SQLITE3_INTEGER,
                        'd' => SQLITE3_FLOAT,
                        'b' => SQLITE3_BLOB,
                        default => SQLITE3_TEXT,
                    };
                }
                if (!@$statement->native->bindValue($position, $boundValue, $nativeType)) {
                    unset($value);
                    return SQLiteProceduralRuntime::statementNativeFail($statement);
                }
            }
            unset($value);
        }

        $nativeResult = @$statement->native->execute();
        if ($nativeResult === false) {
            return SQLiteProceduralRuntime::statementNativeFail($statement);
        }
        if ($nativeResult->numColumns() > 0) {
            $statement->result = SQLiteProceduralRuntime::buffer($nativeResult);
        } else {
            $nativeResult->finalize();
        }
        $statement->affectedRows = $statement->connection->native->changes();
        $statement->insertId = $statement->connection->native->lastInsertRowID();
        $statement->longData = [];
        return true;
    }

    function sqlite_stmt_execute_query(SQLiteProceduralStatement $statement, ?array $params = null)
    {
        if (!sqlite_stmt_execute($statement, $params)) {
            return false;
        }
        return sqlite_stmt_get_result($statement);
    }

    function sqlite_stmt_get_result(SQLiteProceduralStatement $statement)
    {
        return $statement->result ?? false;
    }

    function sqlite_stmt_bind_result(SQLiteProceduralStatement $statement, &...$variables): bool
    {
        $columnCount = $statement->result !== null ? count($statement->result->columns) : 0;
        if (count($variables) !== $columnCount) {
            throw new ArgumentCountError(sprintf('The number of variables must match the %d result columns.', $columnCount));
        }
        $statement->boundResults = [];
        foreach ($variables as $index => &$variable) {
            $statement->boundResults[$index] =& $variable;
        }
        unset($variable);
        return true;
    }

    function sqlite_stmt_fetch(SQLiteProceduralStatement $statement): ?bool
    {
        if ($statement->result === null || $statement->result->cursor >= count($statement->result->rows)) {
            return null;
        }
        $row = $statement->result->rows[$statement->result->cursor++];
        $statement->result->lastRow = $row;
        foreach ($statement->boundResults as $index => &$variable) {
            $variable = $row[$index] ?? null;
        }
        unset($variable);
        return true;
    }

    function sqlite_stmt_store_result(SQLiteProceduralStatement $statement): bool
    {
        return $statement->result !== null;
    }

    function sqlite_stmt_result_metadata(SQLiteProceduralStatement $statement)
    {
        if ($statement->result === null) {
            return false;
        }
        return new SQLiteProceduralResult([], $statement->result->columns, $statement->result->types);
    }

    function sqlite_stmt_data_seek(SQLiteProceduralStatement $statement, int $offset): void
    {
        if ($statement->result === null) {
            throw new ValueError('The statement has no result set.');
        }
        sqlite_data_seek($statement->result, $offset);
    }

    function sqlite_stmt_reset(SQLiteProceduralStatement $statement): bool
    {
        $statement->result = null;
        $statement->longData = [];
        return @$statement->native->reset();
    }

    function sqlite_stmt_free_result(SQLiteProceduralStatement $statement): void
    {
        if ($statement->result !== null) {
            sqlite_free_result($statement->result);
            $statement->result = null;
        }
    }

    function sqlite_stmt_close(SQLiteProceduralStatement $statement): bool
    {
        if ($statement->closed) {
            return true;
        }
        sqlite_stmt_free_result($statement);
        $ok = @$statement->native->close();
        if (!$ok) {
            return SQLiteProceduralRuntime::statementNativeFail($statement);
        }
        $statement->closed = true;
        return true;
    }

    function sqlite_stmt_param_count(SQLiteProceduralStatement $statement): int
    {
        return $statement->native->paramCount();
    }

    function sqlite_stmt_field_count(SQLiteProceduralStatement $statement): int
    {
        return $statement->result !== null ? count($statement->result->columns) : 0;
    }

    function sqlite_stmt_num_rows(SQLiteProceduralStatement $statement): int
    {
        return $statement->result !== null ? count($statement->result->rows) : 0;
    }

    function sqlite_stmt_affected_rows(SQLiteProceduralStatement $statement): int
    {
        return $statement->affectedRows;
    }

    function sqlite_stmt_insert_id(SQLiteProceduralStatement $statement): int
    {
        return $statement->insertId;
    }

    function sqlite_stmt_errno(SQLiteProceduralStatement $statement): int
    {
        return $statement->errorCode;
    }

    function sqlite_stmt_error(SQLiteProceduralStatement $statement): string
    {
        return $statement->errorMessage;
    }

    function sqlite_stmt_sqlstate(SQLiteProceduralStatement $statement): string
    {
        return $statement->errorCode === 0 ? '00000' : 'HY000';
    }

    function sqlite_stmt_readonly(SQLiteProceduralStatement $statement): bool
    {
        return $statement->native->readOnly();
    }

    function sqlite_stmt_sql(SQLiteProceduralStatement $statement, bool $expand = false): string
    {
        return $statement->native->getSQL($expand);
    }

    function sqlite_fetch_array(SQLiteProceduralResult $result, int $mode = SQLITE_BOTH)
    {
        if ($result->freed || $result->cursor >= count($result->rows)) {
            return null;
        }
        if (!in_array($mode, [SQLITE_ASSOC, SQLITE_NUM, SQLITE_BOTH], true)) {
            throw new ValueError('sqlite_fetch_array(): $mode must be SQLITE_ASSOC, SQLITE_NUM, or SQLITE_BOTH.');
        }
        $row = $result->rows[$result->cursor++];
        $result->lastRow = $row;
        if ($mode === SQLITE_NUM) {
            return $row;
        }
        $assoc = SQLiteProceduralRuntime::assoc($result, $row);
        return $mode === SQLITE_ASSOC ? $assoc : $row + $assoc;
    }

    function sqlite_fetch_assoc(SQLiteProceduralResult $result): ?array
    {
        $row = sqlite_fetch_array($result, SQLITE_ASSOC);
        return $row === null ? null : $row;
    }

    function sqlite_fetch_row(SQLiteProceduralResult $result): ?array
    {
        $row = sqlite_fetch_array($result, SQLITE_NUM);
        return $row === null ? null : $row;
    }

    function sqlite_fetch_object(SQLiteProceduralResult $result, string $class = stdClass::class, array $constructorArgs = []): ?object
    {
        $row = sqlite_fetch_assoc($result);
        if ($row === null) {
            return null;
        }
        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();
        $object = $constructor === null
            ? $reflection->newInstance()
            : $reflection->newInstanceWithoutConstructor();
        foreach ($row as $property => $value) {
            $object->{$property} = $value;
        }
        if ($constructor !== null) {
            $constructor->invokeArgs($object, $constructorArgs);
        }
        return $object;
    }

    function sqlite_fetch_all(SQLiteProceduralResult $result, int $mode = SQLITE_NUM): array
    {
        $rows = [];
        while (($row = sqlite_fetch_array($result, $mode)) !== null) {
            $rows[] = $row;
        }
        return $rows;
    }

    function sqlite_fetch_column(SQLiteProceduralResult $result, int $column = 0)
    {
        if ($column < 0 || $column >= count($result->columns)) {
            throw new ValueError('sqlite_fetch_column(): $column is out of range.');
        }
        $row = sqlite_fetch_row($result);
        return $row === null ? null : $row[$column];
    }

    function sqlite_fetch_lengths(SQLiteProceduralResult $result)
    {
        if ($result->lastRow === null) {
            return false;
        }
        return array_map(
            static fn($value): int => $value === null ? 0 : strlen((string) $value),
            $result->lastRow
        );
    }

    function sqlite_num_rows(SQLiteProceduralResult $result): int
    {
        return $result->freed ? 0 : count($result->rows);
    }

    function sqlite_num_fields(SQLiteProceduralResult $result): int
    {
        return $result->freed ? 0 : count($result->columns);
    }

    function sqlite_data_seek(SQLiteProceduralResult $result, int $offset): bool
    {
        if ($offset < 0 || $offset >= count($result->rows)) {
            throw new ValueError('sqlite_data_seek(): $offset must identify an existing row.');
        }
        $result->cursor = $offset;
        $result->lastRow = null;
        return true;
    }

    function sqlite_field_seek(SQLiteProceduralResult $result, int $index): bool
    {
        if ($index < 0 || $index >= count($result->columns)) {
            throw new ValueError('sqlite_field_seek(): $index is out of range.');
        }
        $result->fieldCursor = $index;
        return true;
    }

    function sqlite_field_tell(SQLiteProceduralResult $result): int
    {
        return $result->fieldCursor;
    }

    function sqlite_fetch_field(SQLiteProceduralResult $result)
    {
        if ($result->fieldCursor >= count($result->columns)) {
            return false;
        }
        return SQLiteProceduralRuntime::fieldObject($result, $result->fieldCursor++);
    }

    function sqlite_fetch_field_direct(SQLiteProceduralResult $result, int $index): object
    {
        if ($index < 0 || $index >= count($result->columns)) {
            throw new ValueError('sqlite_fetch_field_direct(): $index is out of range.');
        }
        return SQLiteProceduralRuntime::fieldObject($result, $index);
    }

    function sqlite_fetch_fields(SQLiteProceduralResult $result): array
    {
        $fields = [];
        for ($i = 0, $count = count($result->columns); $i < $count; $i++) {
            $fields[] = SQLiteProceduralRuntime::fieldObject($result, $i);
        }
        return $fields;
    }

    function sqlite_free_result(SQLiteProceduralResult $result): void
    {
        $result->rows = [];
        $result->columns = [];
        $result->types = [];
        $result->lastRow = null;
        $result->freed = true;
    }

    function sqlite_affected_rows(SQLiteProceduralConnection $connection): int
    {
        return $connection->native->changes();
    }

    function sqlite_insert_id(SQLiteProceduralConnection $connection): int
    {
        return $connection->native->lastInsertRowID();
    }

    function sqlite_errno(SQLiteProceduralConnection $connection): int
    {
        return $connection->errorCode;
    }

    function sqlite_error(SQLiteProceduralConnection $connection): string
    {
        return $connection->errorMessage;
    }

    function sqlite_sqlstate(SQLiteProceduralConnection $connection): string
    {
        return $connection->errorCode === 0 ? '00000' : 'HY000';
    }

    function sqlite_error_list(SQLiteProceduralConnection $connection): array
    {
        if ($connection->errorCode === 0) {
            return [];
        }
        return [['errno' => $connection->errorCode, 'sqlstate' => 'HY000', 'error' => $connection->errorMessage]];
    }

    function sqlite_real_escape_string(SQLiteProceduralConnection $connection, string $string): string
    {
        SQLiteProceduralRuntime::checkConnection($connection);
        return SQLite3::escapeString($string);
    }

    function sqlite_begin_transaction(SQLiteProceduralConnection $connection, int $flags = SQLITE_TRANS_DEFERRED, ?string $name = null): bool
    {
        if (!SQLiteProceduralRuntime::checkConnection($connection)) {
            return false;
        }
        if ($connection->inTransaction) {
            return SQLiteProceduralRuntime::fail($connection, 'A transaction is already active.');
        }
        $kind = match ($flags) {
            SQLITE_TRANS_DEFERRED => 'DEFERRED',
            SQLITE_TRANS_IMMEDIATE => 'IMMEDIATE',
            SQLITE_TRANS_EXCLUSIVE => 'EXCLUSIVE',
            default => throw new ValueError('sqlite_begin_transaction(): invalid transaction flag.'),
        };
        if (!@$connection->native->exec('BEGIN ' . $kind)) {
            return SQLiteProceduralRuntime::nativeFail($connection);
        }
        $connection->inTransaction = true;
        return true;
    }

    function sqlite_commit(SQLiteProceduralConnection $connection, int $flags = 0, ?string $name = null): bool
    {
        if (!SQLiteProceduralRuntime::checkConnection($connection)) {
            return false;
        }
        if (!$connection->inTransaction) {
            return true;
        }
        if (!@$connection->native->exec('COMMIT')) {
            return SQLiteProceduralRuntime::nativeFail($connection);
        }
        $connection->inTransaction = false;
        return true;
    }

    function sqlite_rollback(SQLiteProceduralConnection $connection, int $flags = 0, ?string $name = null): bool
    {
        if (!SQLiteProceduralRuntime::checkConnection($connection)) {
            return false;
        }
        if (!$connection->inTransaction) {
            return true;
        }
        if (!@$connection->native->exec('ROLLBACK')) {
            return SQLiteProceduralRuntime::nativeFail($connection);
        }
        $connection->inTransaction = false;
        return true;
    }

    function sqlite_autocommit(SQLiteProceduralConnection $connection, bool $enable): bool
    {
        if ($connection->autoCommit === $enable) {
            return true;
        }
        if ($enable && $connection->inTransaction && !sqlite_commit($connection)) {
            return false;
        }
        $connection->autoCommit = $enable;
        if (!$enable) {
            return SQLiteProceduralRuntime::ensureTransaction($connection);
        }
        return true;
    }

    function sqlite_get_autocommit(SQLiteProceduralConnection $connection): bool
    {
        return $connection->autoCommit;
    }

    function sqlite_savepoint(SQLiteProceduralConnection $connection, string $name): bool
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $name)) {
            throw new ValueError('sqlite_savepoint(): $name is not a valid savepoint identifier.');
        }
        return sqlite_execute($connection, 'SAVEPOINT "' . $name . '"');
    }

    function sqlite_release_savepoint(SQLiteProceduralConnection $connection, string $name): bool
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $name)) {
            throw new ValueError('sqlite_release_savepoint(): $name is not a valid savepoint identifier.');
        }
        return sqlite_execute($connection, 'RELEASE SAVEPOINT "' . $name . '"');
    }

    function sqlite_rollback_to_savepoint(SQLiteProceduralConnection $connection, string $name): bool
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $name)) {
            throw new ValueError('sqlite_rollback_to_savepoint(): $name is not a valid savepoint identifier.');
        }
        return sqlite_execute($connection, 'ROLLBACK TO SAVEPOINT "' . $name . '"');
    }

    function sqlite_busy_timeout(SQLiteProceduralConnection $connection, int $milliseconds): bool
    {
        if ($milliseconds < 0) {
            throw new ValueError('sqlite_busy_timeout(): $milliseconds must be greater than or equal to zero.');
        }
        return $connection->native->busyTimeout($milliseconds);
    }

    function sqlite_ping(SQLiteProceduralConnection $connection): bool
    {
        if ($connection->closed) {
            return false;
        }
        return @$connection->native->querySingle('SELECT 1') === 1;
    }

    function sqlite_backup(SQLiteProceduralConnection $source, SQLiteProceduralConnection $destination, string $sourceDatabase = 'main', string $destinationDatabase = 'main'): bool
    {
        if (!SQLiteProceduralRuntime::checkConnection($source)
            || !SQLiteProceduralRuntime::checkConnection($destination)) {
            return false;
        }
        if (!@$source->native->backup($destination->native, $sourceDatabase, $destinationDatabase)) {
            return SQLiteProceduralRuntime::nativeFail($source);
        }
        return true;
    }

    function sqlite_create_function(SQLiteProceduralConnection $connection, string $name, callable $callback, int $argCount = -1, int $flags = 0): bool
    {
        return $connection->native->createFunction($name, $callback, $argCount, $flags);
    }

    function sqlite_create_aggregate(SQLiteProceduralConnection $connection, string $name, callable $stepCallback, callable $finalCallback, int $argCount = -1): bool
    {
        return $connection->native->createAggregate($name, $stepCallback, $finalCallback, $argCount);
    }

    function sqlite_create_collation(SQLiteProceduralConnection $connection, string $name, callable $callback): bool
    {
        return $connection->native->createCollation($name, $callback);
    }

    function sqlite_set_authorizer(SQLiteProceduralConnection $connection, ?callable $callback): bool
    {
        if (!method_exists($connection->native, 'setAuthorizer')) {
            return SQLiteProceduralRuntime::fail($connection, 'This PHP SQLite3 build does not support authorizers.');
        }
        return $connection->native->setAuthorizer($callback);
    }

    function sqlite_open_blob(SQLiteProceduralConnection $connection, string $table, string $column, int $rowId, string $database = 'main', int $flags = SQLITE3_OPEN_READONLY)
    {
        $stream = @$connection->native->openBlob($table, $column, $rowId, $database, $flags);
        return $stream === false ? SQLiteProceduralRuntime::nativeFail($connection) : $stream;
    }

    function sqlite_load_extension(SQLiteProceduralConnection $connection, string $name): bool
    {
        if (!@$connection->native->loadExtension($name)) {
            return SQLiteProceduralRuntime::nativeFail($connection);
        }
        return true;
    }

    function sqlite_enable_extended_result_codes(SQLiteProceduralConnection $connection, bool $enable = true): bool
    {
        if (!method_exists($connection->native, 'enableExtendedResultCodes')) {
            return SQLiteProceduralRuntime::fail($connection, 'This PHP SQLite3 build does not support extended result codes.');
        }
        return $connection->native->enableExtendedResultCodes($enable);
    }

    function sqlite_client_info(): string
    {
        return 'sqlite-procedural/' . SQLITE_PROCEDURAL_VERSION;
    }

    function sqlite_get_client_info(): string
    {
        return sqlite_client_info();
    }

    function sqlite_client_version(): int
    {
        return 10000;
    }

    function sqlite_get_client_version(): int
    {
        return sqlite_client_version();
    }

    function sqlite_server_info(SQLiteProceduralConnection $connection): string
    {
        return SQLite3::version()['versionString'];
    }

    function sqlite_get_server_info(SQLiteProceduralConnection $connection): string
    {
        return sqlite_server_info($connection);
    }

    function sqlite_server_version(SQLiteProceduralConnection $connection): int
    {
        return SQLite3::version()['versionNumber'];
    }

    function sqlite_get_server_version(SQLiteProceduralConnection $connection): int
    {
        return sqlite_server_version($connection);
    }

    function sqlite_character_set_name(SQLiteProceduralConnection $connection): string
    {
        return 'UTF-8';
    }

    function sqlite_get_charset(SQLiteProceduralConnection $connection): object
    {
        return (object) [
            'charset' => 'UTF-8',
            'collation' => 'BINARY',
            'dir' => '',
            'min_length' => 1,
            'max_length' => 4,
            'number' => 65001,
            'state' => 1,
            'comment' => 'SQLite UTF-8 database encoding',
        ];
    }

    function sqlite_set_charset(SQLiteProceduralConnection $connection, string $charset): bool
    {
        if (!SQLiteProceduralRuntime::checkConnection($connection)) {
            return false;
        }
        $normalized = strtolower(str_replace(['-', '_'], '', $charset));
        if ($normalized !== 'utf8') {
            return SQLiteProceduralRuntime::fail($connection, 'SQLite connections use UTF-8; another connection charset cannot be selected.');
        }
        return true;
    }

    function sqlite_thread_id(SQLiteProceduralConnection $connection): int
    {
        return 0;
    }
}
