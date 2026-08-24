# SQLite prozedural – Lite

## Einführung

`sqlite-lite.php` ist die reduzierte, eigenständige Ausgabe der prozeduralen SQLite-Kompatibilitätsschicht. Sie stellt nur die Funktionen bereit, die gewöhnliche Anwendungen für Verbindungsaufbau, CRUD-Abfragen, Prepared Statements, Ergebnisausgabe, Fehlerbehandlung und Transaktionen benötigen.

Der Anwendungscode arbeitet ausschließlich mit Funktionen wie `sqlite_connect()`, `sqlite_query()`, `sqlite_fetch_assoc()` und `sqlite_stmt_execute()`. Intern verwendet die Bibliothek PHPs `SQLite3`-Erweiterung und puffert Ergebnismengen, damit mysqli-ähnliche Funktionen wie `sqlite_num_rows()` möglich sind.

## Anforderungen

- PHP 8.2 oder neuer
- aktivierte PHP-Erweiterung `sqlite3`
- Schreibrechte auf dem Verzeichnis der Datenbankdatei, sofern keine In-Memory-Datenbank verwendet wird

## Installation

```php
require_once __DIR__ . '/sqlite/sqlite-lite.php';
```

`sqlite.php` und `sqlite-lite.php` dürfen nicht gemeinsam eingebunden werden. Beide Ausgaben stellen absichtlich dieselben `sqlite_*`-Funktionsnamen bereit. Die Lite-Ausgabe lädt die [Vollversion](README.md) nicht nach und benötigt keine weiteren Dateien.

## Schnellstart

```php
<?php

require_once __DIR__ . '/sqlite/sqlite-lite.php';

$db = sqlite_connect(__DIR__ . '/app.sqlite');

sqlite_execute($db, <<<'SQL'
    CREATE TABLE IF NOT EXISTS users (
        id   INTEGER PRIMARY KEY,
        name TEXT NOT NULL
    )
SQL);

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

## Umfang der Lite-Ausgabe

Enthalten sind 32 öffentliche Funktionen für:

- Verbindung und Report-Modus
- direkte und parametrisierte Abfragen
- Prepared Statements mit Referenzbindung
- assoziative und numerische Ergebnisausgabe
- Zeilen-/Spaltenanzahl und Freigabe von Ergebnissen
- Fehlercode und Fehlermeldung
- Insert-ID und Anzahl geänderter Zeilen
- Transaktionen mit Commit und Rollback

Nicht enthalten sind Multi-Queries, Ergebnis-Cursor, Feldmetadaten, Objekt-Hydrierung, `bind_result`, Savepoints, Autocommit-Verwaltung, BLOB-Streams, Backups, eigene SQL-Funktionen, Collations, Authorizer, Extension-Loading sowie Server-, Client- und Charset-Kompatibilitätsfunktionen. Diese stehen bei Bedarf in der Vollversion `sqlite.php` bereit.

## Vordefinierte Konstanten

### Fetch-Modi

| Konstante | Bedeutung |
|---|---|
| `SQLITE_ASSOC` | Spaltennamen als Array-Schlüssel |
| `SQLITE_NUM` | Numerische Array-Schlüssel |
| `SQLITE_BOTH` | Beide Schlüsselarten |

### Report-Modi

| Konstante | Bedeutung |
|---|---|
| `SQLITE_REPORT_OFF` | Fehler nur über Rückgabewerte melden |
| `SQLITE_REPORT_ERROR` | Fehler zusätzlich als PHP-Warnung melden |
| `SQLITE_REPORT_STRICT` | Fehler als `SQLiteProceduralException` auslösen |

Standardmäßig ist `SQLITE_REPORT_ERROR | SQLITE_REPORT_STRICT` aktiv, entsprechend dem modernen mysqli-Verhalten.

### Transaktionsmodi

| Konstante | Bedeutung |
|---|---|
| `SQLITE_TRANS_DEFERRED` | Sperren erst beim tatsächlichen Zugriff anfordern |
| `SQLITE_TRANS_IMMEDIATE` | Sofort eine reservierte Schreibsperre anfordern |
| `SQLITE_TRANS_EXCLUSIVE` | Sofort eine exklusive Transaktion beginnen |

Für `sqlite_connect()` können außerdem die nativen Konstanten `SQLITE3_OPEN_READONLY`, `SQLITE3_OPEN_READWRITE` und `SQLITE3_OPEN_CREATE` verwendet werden.

## Verbindungen und Report-Modus

### sqlite_report

```php
sqlite_report(int $flags): bool
```

Legt fest, wie SQLite-Fehler gemeldet werden.

**Parameter**

- `flags` — Bitweise Kombination aus `SQLITE_REPORT_ERROR` und `SQLITE_REPORT_STRICT`. `SQLITE_REPORT_OFF` deaktiviert beides.

**Rückgabewerte**

Gibt `true` zurück. Unbekannte Bits lösen `ValueError` aus.

### sqlite_connect

```php
sqlite_connect(
    string $filename,
    int $flags = SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE,
    string $encryptionKey = ''
): SQLiteProceduralLiteConnection|false
```

Öffnet eine SQLite-Datenbank.

**Parameter**

- `filename` — Pfad zur Datenbankdatei oder `:memory:` für eine flüchtige In-Memory-Datenbank.
- `flags` — Öffnungsmodus als Bitmaske nativer `SQLITE3_OPEN_*`-Konstanten.
- `encryptionKey` — Schlüssel für speziell mit Verschlüsselung kompilierte SQLite-Versionen. Die Standard-Erweiterung ignoriert ihn.

**Rückgabewerte**

Gibt ein undurchsichtiges Verbindungshandle zurück. Bei einem Fehler wird abhängig vom Report-Modus `false` zurückgegeben oder eine `SQLiteProceduralException` ausgelöst.

### sqlite_connect_errno

```php
sqlite_connect_errno(): int
```

Gibt den Fehlercode des letzten fehlgeschlagenen Verbindungsversuchs zurück. Ohne Fehler wird `0` geliefert.

### sqlite_connect_error

```php
sqlite_connect_error(): ?string
```

Gibt die Fehlermeldung des letzten fehlgeschlagenen Verbindungsversuchs zurück. Ohne Fehler wird `null` geliefert.

### sqlite_close

```php
sqlite_close(SQLiteProceduralLiteConnection $connection): bool
```

Schließt `connection`. Eine noch aktive, über diese API begonnene Transaktion wird vorher zurückgerollt. Wiederholtes Schließen desselben Handles liefert `true`.

## Direkte Abfragen

### sqlite_query

```php
sqlite_query(
    SQLiteProceduralLiteConnection $connection,
    string $query
): SQLiteProceduralLiteResult|bool
```

Führt genau eine SQL-Anweisung aus.

**Parameter**

- `connection` — Eine geöffnete SQLite-Verbindung.
- `query` — Auszuführender SQL-Text.

**Rückgabewerte**

Abfragen mit Ergebnisspalten liefern ein gepuffertes Resulthandle. Anweisungen ohne Ergebnisspalten liefern `true`. Fehler liefern `false` oder lösen abhängig vom Report-Modus eine Exception aus.

### sqlite_execute

```php
sqlite_execute(SQLiteProceduralLiteConnection $connection, string $query): bool
```

Führt SQL aus, ohne eine Ergebnismenge bereitzustellen. Die Funktion eignet sich für `CREATE`, feste `INSERT`-, `UPDATE`- oder `DELETE`-Anweisungen und andere Befehle, deren Ergebnis nicht gelesen werden muss.

`query` kann mehrere Anweisungen enthalten, deren Ergebnisse jedoch verworfen werden. Bei Erfolg wird `true` geliefert.

### sqlite_execute_query

```php
sqlite_execute_query(
    SQLiteProceduralLiteConnection $connection,
    string $query,
    ?array $params = null
): SQLiteProceduralLiteResult|bool
```

Bereitet `query` vor, bindet `params` positionsbasiert, führt die Anweisung aus und schließt das interne Statement. Diese Kurzform entspricht `mysqli_execute_query()`.

**Parameter**

- `connection` — Eine geöffnete SQLite-Verbindung.
- `query` — Eine einzelne SQL-Anweisung mit optionalen `?`-Platzhaltern.
- `params` — Werte in Platzhalterreihenfolge oder `null`. Die Typen werden aus den PHP-Werten abgeleitet.

**Rückgabewerte**

Eine Abfrage mit Spalten liefert ein Resulthandle. Eine erfolgreiche Anweisung ohne Ergebnisspalten liefert `true`; Fehler liefern `false` oder lösen eine Exception aus.

### sqlite_affected_rows

```php
sqlite_affected_rows(SQLiteProceduralLiteConnection $connection): int
```

Gibt die Zahl der durch die zuletzt ausgeführte schreibende SQL-Anweisung geänderten Zeilen zurück.

### sqlite_insert_id

```php
sqlite_insert_id(SQLiteProceduralLiteConnection $connection): int
```

Gibt die Row-ID der zuletzt auf dieser Verbindung eingefügten Zeile zurück.

### sqlite_real_escape_string

```php
sqlite_real_escape_string(
    SQLiteProceduralLiteConnection $connection,
    string $string
): string
```

Maskiert eine Zeichenkette zur Verwendung innerhalb eines SQLite-Stringliterals. Die Funktion entspricht dem Einsatzzweck von `mysqli_real_escape_string()`.

**Parameter**

- `connection` — Eine geöffnete SQLite-Verbindung.
- `string` — Die zu maskierende Zeichenkette.

**Rückgabewerte**

Gibt die maskierte Zeichenkette zurück. Umschließende einfache Anführungszeichen werden nicht hinzugefügt.

```php
$name = sqlite_real_escape_string($db, $name);
$result = sqlite_query($db, "SELECT * FROM users WHERE name = '$name'");
```

Prepared Statements sind für Werte aus Benutzereingaben weiterhin vorzuziehen, da sie keine manuelle Zusammensetzung von SQL erfordern.

## Prepared Statements

### sqlite_prepare

```php
sqlite_prepare(
    SQLiteProceduralLiteConnection $connection,
    string $query
): SQLiteProceduralLiteStatement|false
```

Bereitet genau eine SQL-Anweisung vor.

**Parameter**

- `connection` — Eine geöffnete Verbindung.
- `query` — SQL mit optionalen Platzhaltern. Für die mysqli-ähnliche Verwendung werden positionsbasierte `?`-Platzhalter empfohlen.

**Rückgabewerte**

Gibt ein Statementhandle zurück. Bei einem Fehler wird `false` geliefert oder eine Exception ausgelöst.

### sqlite_stmt_bind_param

```php
sqlite_stmt_bind_param(
    SQLiteProceduralLiteStatement $statement,
    string $types,
    mixed &$variable,
    mixed &...$variables
): bool
```

Bindet Variablen per Referenz an ein vorbereitetes Statement. Die aktuellen Variablenwerte werden bei jedem Aufruf von `sqlite_stmt_execute()` gelesen.

**Parameter**

- `statement` — Das vorbereitete Statement.
- `types` — Genau ein Typzeichen pro Parameter: `i` für Integer, `d` für Float, `s` für Text oder `b` für BLOB.
- `variable`, `variables` — Variablen in der Reihenfolge der SQL-Platzhalter.

Die Zahl der Typzeichen und Variablen muss der Parameterzahl entsprechen. Der Wert `null` wird unabhängig vom Typzeichen als SQL-`NULL` gebunden.

### sqlite_stmt_execute

```php
sqlite_stmt_execute(
    SQLiteProceduralLiteStatement $statement,
    ?array $params = null
): bool
```

Führt ein vorbereitetes Statement aus.

**Parameter**

- `statement` — Das auszuführende Statement.
- `params` — Optionales positionsbasiertes Wertearray. Ist es `null`, müssen alle Parameter zuvor mit `sqlite_stmt_bind_param()` gebunden worden sein.

**Rückgabewerte**

Gibt bei Erfolg `true` zurück. Eine vorhandene Ergebnismenge wird intern gepuffert und mit `sqlite_stmt_get_result()` abgerufen.

### sqlite_stmt_get_result

```php
sqlite_stmt_get_result(
    SQLiteProceduralLiteStatement $statement
): SQLiteProceduralLiteResult|false
```

Gibt die gepufferte Ergebnismenge der letzten Statement-Ausführung zurück. Statements ohne Ergebnisspalten liefern `false`.

### sqlite_stmt_close

```php
sqlite_stmt_close(SQLiteProceduralLiteStatement $statement): bool
```

Gibt die aktuelle Ergebnismenge und das native Statement frei. Wiederholtes Schließen liefert `true`.

### sqlite_stmt_affected_rows

```php
sqlite_stmt_affected_rows(SQLiteProceduralLiteStatement $statement): int
```

Gibt die Zahl der durch die letzte Statement-Ausführung geänderten Zeilen zurück. Für eine reine Leseabfrage wird `0` geliefert.

### sqlite_stmt_insert_id

```php
sqlite_stmt_insert_id(SQLiteProceduralLiteStatement $statement): int
```

Gibt die nach der letzten Statement-Ausführung ermittelte Row-ID zurück.

### sqlite_stmt_errno

```php
sqlite_stmt_errno(SQLiteProceduralLiteStatement $statement): int
```

Gibt den zuletzt für dieses Statement gespeicherten SQLite-Fehlercode zurück. Ohne Fehler wird `0` geliefert.

### sqlite_stmt_error

```php
sqlite_stmt_error(SQLiteProceduralLiteStatement $statement): string
```

Gibt die zuletzt für dieses Statement gespeicherte Fehlermeldung zurück. Ohne Fehler wird eine leere Zeichenkette geliefert.

## Ergebnismengen

### sqlite_fetch_array

```php
sqlite_fetch_array(
    SQLiteProceduralLiteResult $result,
    int $mode = SQLITE_BOTH
): ?array
```

Liest die nächste Ergebniszeile.

**Parameter**

- `result` — Eine gepufferte Ergebnismenge.
- `mode` — `SQLITE_ASSOC`, `SQLITE_NUM` oder `SQLITE_BOTH`.

**Rückgabewerte**

Gibt die Zeile als Array und am Ende der Ergebnismenge `null` zurück. Ein ungültiger Modus löst `ValueError` aus.

### sqlite_fetch_assoc

```php
sqlite_fetch_assoc(SQLiteProceduralLiteResult $result): ?array
```

Liest die nächste Zeile mit Spaltennamen als Schlüsseln. Am Ende wird `null` geliefert. Bei doppelten Spaltennamen überschreibt der weiter rechts stehende Wert den vorherigen.

### sqlite_fetch_row

```php
sqlite_fetch_row(SQLiteProceduralLiteResult $result): ?array
```

Liest die nächste Zeile als nullbasiert numerisch indiziertes Array. Am Ende wird `null` geliefert.

### sqlite_fetch_all

```php
sqlite_fetch_all(
    SQLiteProceduralLiteResult $result,
    int $mode = SQLITE_NUM
): array
```

Liest alle ab der aktuellen Position verbleibenden Zeilen im angegebenen Fetch-Modus und bewegt den Cursor ans Ende. Eine leere Ergebnismenge liefert ein leeres Array.

### sqlite_num_rows

```php
sqlite_num_rows(SQLiteProceduralLiteResult $result): int
```

Gibt die Gesamtzahl der gepufferten Zeilen zurück, unabhängig von der aktuellen Leseposition. Nach `sqlite_free_result()` wird `0` geliefert.

### sqlite_num_fields

```php
sqlite_num_fields(SQLiteProceduralLiteResult $result): int
```

Gibt die Anzahl der Ergebnisspalten zurück. Nach `sqlite_free_result()` wird `0` geliefert.

### sqlite_free_result

```php
sqlite_free_result(SQLiteProceduralLiteResult $result): void
```

Gibt die gepufferten Zeilen und Spaltennamen frei. Das Handle darf anschließend nicht mehr zum Abrufen von Daten verwendet werden.

## Fehlerbehandlung

### sqlite_errno

```php
sqlite_errno(SQLiteProceduralLiteConnection $connection): int
```

Gibt den zuletzt durch diese API gespeicherten Fehlercode der Verbindung zurück. Ohne Fehler wird `0` geliefert.

### sqlite_error

```php
sqlite_error(SQLiteProceduralLiteConnection $connection): string
```

Gibt die zuletzt durch diese API gespeicherte Fehlermeldung der Verbindung zurück. Ohne Fehler wird eine leere Zeichenkette geliefert.

### Beispiel mit Exceptions

```php
sqlite_report(SQLITE_REPORT_ERROR | SQLITE_REPORT_STRICT);

try {
    $db = sqlite_connect(__DIR__ . '/app.sqlite');
    sqlite_query($db, 'SELECT * FROM nicht_vorhanden');
} catch (SQLiteProceduralException $error) {
    printf("SQLite-Fehler %d: %s\n", $error->getCode(), $error->getMessage());
}
```

### Beispiel mit Rückgabewerten

```php
sqlite_report(SQLITE_REPORT_OFF);

$db = sqlite_connect(__DIR__ . '/app.sqlite');
$result = sqlite_query($db, 'INVALID SQL');

if ($result === false) {
    printf("SQLite-Fehler %d: %s\n", sqlite_errno($db), sqlite_error($db));
}
```

## Transaktionen

### sqlite_begin_transaction

```php
sqlite_begin_transaction(
    SQLiteProceduralLiteConnection $connection,
    int $flags = SQLITE_TRANS_DEFERRED,
    ?string $name = null
): bool
```

Beginnt eine Transaktion.

**Parameter**

- `connection` — Eine geöffnete Verbindung.
- `flags` — `SQLITE_TRANS_DEFERRED`, `SQLITE_TRANS_IMMEDIATE` oder `SQLITE_TRANS_EXCLUSIVE`.
- `name` — Wird zur Signaturkompatibilität mit mysqli akzeptiert, von SQLite jedoch nicht ausgewertet.

**Rückgabewerte**

Gibt bei Erfolg `true` zurück. Ist bereits eine Transaktion aktiv, wird ein Fehler gemeldet.

### sqlite_commit

```php
sqlite_commit(
    SQLiteProceduralLiteConnection $connection,
    int $flags = 0,
    ?string $name = null
): bool
```

Schreibt die aktive Transaktion fest. Ohne aktive Transaktion wird `true` geliefert. `flags` und `name` werden zur mysqli-Kompatibilität akzeptiert, von SQLite aber nicht ausgewertet.

### sqlite_rollback

```php
sqlite_rollback(
    SQLiteProceduralLiteConnection $connection,
    int $flags = 0,
    ?string $name = null
): bool
```

Rollt die aktive Transaktion zurück. Ohne aktive Transaktion wird `true` geliefert. `flags` und `name` werden zur mysqli-Kompatibilität akzeptiert, von SQLite aber nicht ausgewertet.

### Transaktionsbeispiel

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

## Sicherheit und Verhalten

- Werte aus Benutzereingaben über Prepared Statements binden.
- Tabellen- und Spaltennamen können nicht als SQL-Parameter gebunden werden und müssen aus einer festen Allowlist stammen.
- Datenbankdateien möglichst außerhalb öffentlich erreichbarer Webverzeichnisse speichern.
- Ergebnismengen werden vollständig im PHP-Arbeitsspeicher gepuffert. Sehr große Abfragen sollten deshalb mit `LIMIT` oder einer geeigneten Seitennavigation begrenzt werden.
- SQLite arbeitet eingebettet und benötigt keine Angaben für Host, Benutzer oder Passwort.
