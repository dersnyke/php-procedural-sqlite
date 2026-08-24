# SQLite prozedural

## Einführung

`sqlite.php` stellt eine prozedurale, an `mysqli` angelehnte API für SQLite bereit. Der aufrufende Code verwendet ausschließlich Funktionen wie `sqlite_connect()`, `sqlite_query()`, `sqlite_fetch_assoc()` und `sqlite_stmt_execute()`. Intern verwendet die Bibliothek die PHP-Erweiterung `SQLite3`.

Für Anwendungen, die nur CRUD, Prepared Statements und Transaktionen benötigen, steht außerdem die eigenständige [Lite-Ausgabe](README-lite.md) mit 32 statt 90 öffentlichen Funktionen bereit. Beide Ausgaben dürfen wegen ihrer identischen Funktionsnamen nicht gleichzeitig eingebunden werden.

Die API puffert Ergebnismengen vollständig. Dadurch stehen mysqli-typische Funktionen wie `sqlite_num_rows()` und `sqlite_data_seek()` auch für SQLite zur Verfügung. Verbindungs-, Statement- und Result-Handles sind undurchsichtige Werte; ihre internen Klassen gehören nicht zur öffentlichen API.

## KI-Attribution

> **Dieses Projekt ist zu 100 % KI-generiert.**

Der gesamte PHP-Quellcode und die vollständige Dokumentation dieses Repositorys wurden mit [OpenAI Codex](https://developers.openai.com/codex), einem KI-Coding-Agenten von OpenAI, erzeugt. Die Anforderungen und die fachliche Steuerung wurden von einem Menschen vorgegeben; die daraus entstandenen Projektdateien wurden vollständig durch die KI generiert.

*All source code and documentation in this repository were generated entirely with OpenAI Codex.*

## Anforderungen

- PHP 8.2 oder neuer
- aktivierte PHP-Erweiterung `sqlite3`
- Schreibrechte auf dem Verzeichnis der Datenbankdatei, sofern keine reine In-Memory-Datenbank verwendet wird

## Installation

```php
require_once __DIR__ . '/sqlite/sqlite.php';
```

Die Datei soll mit `require_once` eingebunden werden. Es sind weder Composer noch weitere Dateien erforderlich.

## Schnellstart

```php
<?php

require_once __DIR__ . '/sqlite/sqlite.php';

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

$result = sqlite_query($db, 'SELECT id, name FROM users ORDER BY id');
while ($row = sqlite_fetch_assoc($result)) {
    echo $row['id'] . ': ' . $row['name'] . PHP_EOL;
}

sqlite_free_result($result);
sqlite_stmt_close($stmt);
sqlite_close($db);
```

Benutzereingaben sollen bevorzugt über Prepared Statements gebunden werden. `sqlite_real_escape_string()` ist nur für Fälle vorgesehen, in denen Parameterbindung technisch nicht möglich ist.

## Vordefinierte Konstanten

### Fetch-Modi

| Konstante | Beschreibung |
|---|---|
| `SQLITE_ASSOC` | Ergebniszeile mit Spaltennamen als Schlüssel |
| `SQLITE_NUM` | Ergebniszeile mit numerischen Schlüsseln |
| `SQLITE_BOTH` | Beide Schlüsselarten |

### Report-Modi

| Konstante | Beschreibung |
|---|---|
| `SQLITE_REPORT_OFF` | Keine Warnungen oder Exceptions |
| `SQLITE_REPORT_ERROR` | Fehler als PHP-Warnung melden |
| `SQLITE_REPORT_STRICT` | Fehler als `SQLiteProceduralException` auslösen |

Der Standard ist `SQLITE_REPORT_ERROR | SQLITE_REPORT_STRICT`, entsprechend dem modernen mysqli-Verhalten.

### Transaktionsmodi

| Konstante | Beschreibung |
|---|---|
| `SQLITE_TRANS_DEFERRED` | Sperren erst beim ersten Zugriff anfordern |
| `SQLITE_TRANS_IMMEDIATE` | Sofort eine reservierte Schreibsperre anfordern |
| `SQLITE_TRANS_EXCLUSIVE` | Sofort eine exklusive Transaktion beginnen |

### SQLite-Datentypen

`SQLITE_TYPE_INTEGER`, `SQLITE_TYPE_FLOAT`, `SQLITE_TYPE_TEXT`, `SQLITE_TYPE_BLOB` und `SQLITE_TYPE_NULL` entsprechen den nativen `SQLITE3_*`-Typkonstanten.

Die nativen Konstanten `SQLITE3_OPEN_READONLY`, `SQLITE3_OPEN_READWRITE`, `SQLITE3_OPEN_CREATE`, `SQLITE3_DETERMINISTIC` und weitere Konstanten der `SQLite3`-Erweiterung können direkt verwendet werden.

## Unterschiede zu mysqli

- SQLite arbeitet mit einem Dateinamen statt mit Host, Benutzer, Passwort und Server-Datenbank.
- SQLite kennt nur UTF-8 und UTF-16; diese API liefert als Verbindungszeichensatz immer `UTF-8`.
- Ergebnismengen werden vollständig im Arbeitsspeicher gepuffert.
- `sqlite_multi_query()` trennt normale SQL-Anweisungen an Semikolons außerhalb von Strings und Kommentaren. Trigger-Definitionen mit Semikolons im `BEGIN … END`-Block sollten als einzelne Anweisung mit `sqlite_execute()` ausgeführt werden.
- `sqlite_commit()` und `sqlite_rollback()` akzeptieren die mysqli-kompatiblen Parameter `flags` und `name`; SQLite wertet sie nicht aus.
- Metadaten wie Ursprungstabelle, Feldlänge und Zeichensatznummer existieren in SQLite nicht. Die entsprechenden Felder der von `sqlite_fetch_field*()` gelieferten Objekte enthalten neutrale Werte.
- `sqlite_thread_id()` liefert `0`, da SQLite keine Server-Thread-ID besitzt.

## Verbindungen und Fehlerbehandlung

### sqlite_report

```php
sqlite_report(int $flags): bool
```

Legt den globalen Fehlermodus fest. `flags` ist eine bitweise Kombination aus `SQLITE_REPORT_ERROR` und `SQLITE_REPORT_STRICT`; `SQLITE_REPORT_OFF` deaktiviert beide. Gibt immer `true` zurück. Ungültige Bits lösen `ValueError` aus.

### sqlite_connect

```php
sqlite_connect(
    string $filename,
    int $flags = SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE,
    string $encryptionKey = ''
): SQLiteProceduralConnection|false
```

Öffnet eine SQLite-Datenbank.

**Parameter**

- `filename` — Pfad zur Datenbankdatei oder `:memory:` für eine flüchtige In-Memory-Datenbank.
- `flags` — Bitmaske aus `SQLITE3_OPEN_READONLY`, `SQLITE3_OPEN_READWRITE` und `SQLITE3_OPEN_CREATE`.
- `encryptionKey` — Schlüssel für entsprechend kompilierte SQLite-Erweiterungen; die Standard-Erweiterung ignoriert ihn.

**Rückgabewerte**

Gibt ein Verbindungshandle zurück. Bei einem Fehler wird abhängig vom Report-Modus `false` zurückgegeben oder eine Exception ausgelöst.

### sqlite_connect_errno

```php
sqlite_connect_errno(): int
```

Gibt den Fehlercode des letzten fehlgeschlagenen Verbindungsversuchs zurück, andernfalls `0`.

### sqlite_connect_error

```php
sqlite_connect_error(): ?string
```

Gibt die Fehlermeldung des letzten fehlgeschlagenen Verbindungsversuchs oder `null` zurück.

### sqlite_close

```php
sqlite_close(SQLiteProceduralConnection $connection): bool
```

Schließt `connection`. Eine noch aktive Transaktion wird vorher zurückgerollt. Wiederholtes Schließen desselben Handles liefert `true`.

### sqlite_errno

```php
sqlite_errno(SQLiteProceduralConnection $connection): int
```

Gibt den zuletzt durch diese API gespeicherten SQLite-Fehlercode der Verbindung zurück.

### sqlite_error

```php
sqlite_error(SQLiteProceduralConnection $connection): string
```

Gibt die letzte Fehlermeldung der Verbindung oder eine leere Zeichenkette zurück.

### sqlite_sqlstate

```php
sqlite_sqlstate(SQLiteProceduralConnection $connection): string
```

Gibt `00000` bei fehlerfreiem Zustand und andernfalls den allgemeinen SQLSTATE `HY000` zurück.

### sqlite_error_list

```php
sqlite_error_list(SQLiteProceduralConnection $connection): array
```

Gibt entweder ein leeres Array oder einen Eintrag mit `errno`, `sqlstate` und `error` für den letzten Fehler zurück.

### sqlite_ping

```php
sqlite_ping(SQLiteProceduralConnection $connection): bool
```

Prüft mit `SELECT 1`, ob die Verbindung noch verwendbar ist.

## Abfragen

### sqlite_query

```php
sqlite_query(SQLiteProceduralConnection $connection, string $query): SQLiteProceduralResult|bool
```

Führt genau eine SQL-Anweisung aus. Abfragen mit Spalten liefern ein gepuffertes Resulthandle; Anweisungen ohne Ergebnisspalten liefern `true`. Fehler liefern `false` oder lösen gemäß Report-Modus eine Exception aus.

### sqlite_execute

```php
sqlite_execute(SQLiteProceduralConnection $connection, string $query): bool
```

Führt eine oder mehrere SQL-Anweisungen ohne abrufbare Ergebnismenge aus. Geeignet für Schemaänderungen und feste SQL-Anweisungen. Gibt bei Erfolg `true` zurück.

### sqlite_execute_query

```php
sqlite_execute_query(
    SQLiteProceduralConnection $connection,
    string $query,
    ?array $params = null
): SQLiteProceduralResult|bool
```

Bereitet `query` vor, bindet die Werte aus `params` positionsbasiert, führt die Anweisung aus und schließt das interne Statement. Abfragen mit Spalten liefern ein Resulthandle, Anweisungen ohne Ergebnisspalten `true`. Diese Kurzform entspricht `mysqli_execute_query()`.

### sqlite_query_single

```php
sqlite_query_single(
    SQLiteProceduralConnection $connection,
    string $query,
    bool $entireRow = false
): mixed
```

Liefert bei `entireRow = false` den Wert der ersten Spalte der ersten Zeile. Bei `true` wird die erste Zeile als assoziatives Array geliefert. Eine leere Ergebnismenge liefert `null` beziehungsweise ein leeres Array; ein Fehler liefert `false` oder löst eine Exception aus.

### sqlite_multi_query

```php
sqlite_multi_query(SQLiteProceduralConnection $connection, string $query): bool
```

Führt mehrere durch Semikolon getrennte Anweisungen aus und speichert deren Ergebnisse in der Verbindung. Das erste Ergebnis ist anschließend aktuell. Strings, quoted identifiers sowie Zeilen- und Blockkommentare werden beim Trennen berücksichtigt. Gibt bei vollständigem Erfolg `true` zurück.

### sqlite_store_result

```php
sqlite_store_result(SQLiteProceduralConnection $connection): SQLiteProceduralResult|false
```

Gibt die Ergebnismenge der aktuellen Multi-Query-Anweisung zurück. Für Anweisungen ohne Ergebnismenge wird `false` geliefert.

### sqlite_more_results

```php
sqlite_more_results(SQLiteProceduralConnection $connection): bool
```

Gibt an, ob nach dem aktuellen Multi-Query-Ergebnis ein weiteres Ergebnis vorhanden ist.

### sqlite_next_result

```php
sqlite_next_result(SQLiteProceduralConnection $connection): bool
```

Wechselt zum nächsten Multi-Query-Ergebnis. Gibt `false` zurück, wenn kein weiteres Ergebnis existiert.

### sqlite_affected_rows

```php
sqlite_affected_rows(SQLiteProceduralConnection $connection): int
```

Gibt die Zahl der von der zuletzt ausgeführten `INSERT`-, `UPDATE`- oder `DELETE`-Anweisung geänderten Zeilen zurück.

### sqlite_insert_id

```php
sqlite_insert_id(SQLiteProceduralConnection $connection): int
```

Gibt die Row-ID der zuletzt eingefügten Zeile dieser Verbindung zurück.

### sqlite_real_escape_string

```php
sqlite_real_escape_string(SQLiteProceduralConnection $connection, string $string): string
```

Maskiert einfache Anführungszeichen für ein SQLite-Stringliteral. Die Funktion fügt keine umschließenden Anführungszeichen hinzu. Prepared Statements sind vorzuziehen.

## Ergebnismengen

### sqlite_fetch_array

```php
sqlite_fetch_array(SQLiteProceduralResult $result, int $mode = SQLITE_BOTH): ?array
```

Liest die nächste Zeile. `mode` ist `SQLITE_ASSOC`, `SQLITE_NUM` oder `SQLITE_BOTH`. Gibt am Ende der Ergebnismenge `null` zurück.

### sqlite_fetch_assoc

```php
sqlite_fetch_assoc(SQLiteProceduralResult $result): ?array
```

Liest die nächste Zeile als assoziatives Array oder liefert am Ende `null`. Bei doppelten Spaltennamen überschreibt der weiter rechts stehende Wert den vorherigen.

### sqlite_fetch_row

```php
sqlite_fetch_row(SQLiteProceduralResult $result): ?array
```

Liest die nächste Zeile als numerisch indiziertes Array oder liefert am Ende `null`.

### sqlite_fetch_object

```php
sqlite_fetch_object(
    SQLiteProceduralResult $result,
    string $class = stdClass::class,
    array $constructorArgs = []
): ?object
```

Liest die nächste Zeile in die Eigenschaften einer Instanz von `class`. Die Spaltenwerte werden vor dem Aufruf des Konstruktors gesetzt. `constructorArgs` enthält dessen Argumente. Am Ende wird `null` geliefert.

### sqlite_fetch_all

```php
sqlite_fetch_all(SQLiteProceduralResult $result, int $mode = SQLITE_NUM): array
```

Liest alle noch nicht abgerufenen Zeilen im angegebenen Fetch-Modus und bewegt den Cursor ans Ende.

### sqlite_fetch_column

```php
sqlite_fetch_column(SQLiteProceduralResult $result, int $column = 0): mixed
```

Liest aus der nächsten Zeile die durch den nullbasierten Index `column` bezeichnete Spalte. Gibt am Ende `null` zurück; ein ungültiger Index löst `ValueError` aus.

### sqlite_fetch_lengths

```php
sqlite_fetch_lengths(SQLiteProceduralResult $result): array|false
```

Gibt die Bytelänge jeder Spalte der zuletzt gelesenen Zeile zurück. Vor dem ersten Fetch wird `false` geliefert; `NULL` hat die Länge `0`.

### sqlite_num_rows

```php
sqlite_num_rows(SQLiteProceduralResult $result): int
```

Gibt die Gesamtzahl der gepufferten Zeilen zurück, unabhängig von der aktuellen Cursorposition.

### sqlite_num_fields

```php
sqlite_num_fields(SQLiteProceduralResult $result): int
```

Gibt die Anzahl der Spalten der Ergebnismenge zurück.

### sqlite_data_seek

```php
sqlite_data_seek(SQLiteProceduralResult $result, int $offset): bool
```

Setzt den Zeilencursor auf den nullbasierten Index `offset`. Ein Index außerhalb einer vorhandenen Zeile löst `ValueError` aus.

### sqlite_field_seek

```php
sqlite_field_seek(SQLiteProceduralResult $result, int $index): bool
```

Setzt den internen Feldcursor auf den nullbasierten Spaltenindex `index`.

### sqlite_field_tell

```php
sqlite_field_tell(SQLiteProceduralResult $result): int
```

Gibt die aktuelle Position des Feldcursors zurück.

### sqlite_fetch_field

```php
sqlite_fetch_field(SQLiteProceduralResult $result): object|false
```

Gibt ein Metadatenobjekt für das Feld an der aktuellen Feldcursorposition zurück und erhöht den Cursor. Nach dem letzten Feld wird `false` geliefert. Das Objekt enthält mysqli-ähnliche Eigenschaften wie `name`, `type`, `table`, `flags` und `length`.

### sqlite_fetch_field_direct

```php
sqlite_fetch_field_direct(SQLiteProceduralResult $result, int $index): object
```

Gibt das Metadatenobjekt der durch `index` bezeichneten Spalte zurück. Ein ungültiger Index löst `ValueError` aus.

### sqlite_fetch_fields

```php
sqlite_fetch_fields(SQLiteProceduralResult $result): array
```

Gibt die Metadatenobjekte aller Spalten zurück.

### sqlite_free_result

```php
sqlite_free_result(SQLiteProceduralResult $result): void
```

Gibt den Speicher der gepufferten Ergebnismenge frei. Das Handle darf danach nicht mehr zum Abrufen von Daten verwendet werden.

## Prepared Statements

### sqlite_prepare

```php
sqlite_prepare(SQLiteProceduralConnection $connection, string $query): SQLiteProceduralStatement|false
```

Bereitet genau eine SQL-Anweisung vor und gibt ein Statementhandle zurück. Parameter können als `?`, `?NNN`, `:name`, `@name` oder `$name` geschrieben werden; für die mysqli-ähnliche Bindung sind positionsbasierte `?`-Parameter empfohlen.

### sqlite_stmt_bind_param

```php
sqlite_stmt_bind_param(
    SQLiteProceduralStatement $statement,
    string $types,
    mixed &$variable,
    mixed &...$variables
): bool
```

Bindet Variablen per Referenz. Ihre Werte werden erst bei jedem `sqlite_stmt_execute()` gelesen.

**Parameter**

- `statement` — Das vorbereitete Statement.
- `types` — Ein Zeichen pro Parameter: `i` für Integer, `d` für Float, `s` für Text und `b` für BLOB.
- `variable`, `variables` — Zu bindende Variablen in Parameterreihenfolge. Die Anzahl muss exakt der Parameterzahl entsprechen.

`null` wird unabhängig vom Typzeichen als SQL-`NULL` gebunden.

### sqlite_stmt_send_long_data

```php
sqlite_stmt_send_long_data(SQLiteProceduralStatement $statement, int $paramNum, string $data): bool
```

Hängt `data` an einen mit `b` deklarierten Parameter an. `paramNum` ist nullbasiert. Mehrere Aufrufe vor dem Ausführen werden zusammengefügt; nach dem Ausführen wird der Puffer geleert.

### sqlite_stmt_execute

```php
sqlite_stmt_execute(SQLiteProceduralStatement $statement, ?array $params = null): bool
```

Führt das Statement aus. Ohne `params` werden die zuvor per Referenz gebundenen Variablen gelesen; bei parametrisiertem SQL muss die Bindung vollständig sein. Alternativ bindet `params` einmalig ein positionsbasiertes Wertearray; die Typen werden aus den PHP-Werten abgeleitet. Eine Ergebnismenge wird intern gepuffert. Gibt bei Erfolg `true` zurück.

### sqlite_stmt_execute_query

```php
sqlite_stmt_execute_query(SQLiteProceduralStatement $statement, ?array $params = null): SQLiteProceduralResult|false
```

Kurzform aus `sqlite_stmt_execute()` und `sqlite_stmt_get_result()`. Für ein erfolgreiches Statement ohne Ergebnisspalten wird `false` geliefert.

### sqlite_stmt_get_result

```php
sqlite_stmt_get_result(SQLiteProceduralStatement $statement): SQLiteProceduralResult|false
```

Gibt die gepufferte Ergebnismenge des zuletzt ausgeführten Statements zurück. Statements ohne Ergebnisspalten liefern `false`.

### sqlite_stmt_bind_result

```php
sqlite_stmt_bind_result(SQLiteProceduralStatement $statement, mixed &$variable, mixed &...$variables): bool
```

Bindet für jede Ergebnisspalte eine Ausgabevariable per Referenz. Das Statement muss bereits ausgeführt worden sein. Die Anzahl der Variablen muss der Spaltenzahl entsprechen.

### sqlite_stmt_fetch

```php
sqlite_stmt_fetch(SQLiteProceduralStatement $statement): ?bool
```

Überträgt die nächste Zeile in die mit `sqlite_stmt_bind_result()` gebundenen Variablen. Gibt `true` für eine gelesene Zeile und `null` am Ende zurück.

### sqlite_stmt_store_result

```php
sqlite_stmt_store_result(SQLiteProceduralStatement $statement): bool
```

Gibt `true` zurück, wenn das zuletzt ausgeführte Statement eine gepufferte Ergebnismenge besitzt. Das Puffern selbst erfolgt bereits beim Ausführen.

### sqlite_stmt_result_metadata

```php
sqlite_stmt_result_metadata(SQLiteProceduralStatement $statement): SQLiteProceduralResult|false
```

Gibt nach dem Ausführen ein zeilenloses Resulthandle mit den Spaltenmetadaten zurück. Ohne Ergebnisspalten wird `false` geliefert.

### sqlite_stmt_data_seek

```php
sqlite_stmt_data_seek(SQLiteProceduralStatement $statement, int $offset): void
```

Setzt den Cursor der Statement-Ergebnismenge auf die vorhandene Zeile `offset`. Ohne Ergebnis oder bei ungültigem Index wird `ValueError` ausgelöst.

### sqlite_stmt_reset

```php
sqlite_stmt_reset(SQLiteProceduralStatement $statement): bool
```

Setzt Ausführungszustand, Ergebnis und Long-Data-Puffer zurück. Per Referenz gebundene Variablen bleiben gebunden.

### sqlite_stmt_free_result

```php
sqlite_stmt_free_result(SQLiteProceduralStatement $statement): void
```

Gibt die aktuelle gepufferte Ergebnismenge des Statements frei.

### sqlite_stmt_close

```php
sqlite_stmt_close(SQLiteProceduralStatement $statement): bool
```

Gibt Ergebnis und natives Statement frei. Wiederholtes Schließen liefert `true`.

### sqlite_stmt_param_count

```php
sqlite_stmt_param_count(SQLiteProceduralStatement $statement): int
```

Gibt die Anzahl der SQL-Parameter des vorbereiteten Statements zurück.

### sqlite_stmt_field_count

```php
sqlite_stmt_field_count(SQLiteProceduralStatement $statement): int
```

Gibt nach dem Ausführen die Anzahl der Ergebnisspalten zurück, andernfalls `0`.

### sqlite_stmt_num_rows

```php
sqlite_stmt_num_rows(SQLiteProceduralStatement $statement): int
```

Gibt die Anzahl der gepufferten Ergebniszeilen des Statements zurück.

### sqlite_stmt_affected_rows

```php
sqlite_stmt_affected_rows(SQLiteProceduralStatement $statement): int
```

Gibt die Zahl der durch die letzte Ausführung geänderten Zeilen zurück.

### sqlite_stmt_insert_id

```php
sqlite_stmt_insert_id(SQLiteProceduralStatement $statement): int
```

Gibt die nach der letzten Ausführung ermittelte Row-ID zurück.

### sqlite_stmt_errno

```php
sqlite_stmt_errno(SQLiteProceduralStatement $statement): int
```

Gibt den letzten gespeicherten Fehlercode des Statements zurück.

### sqlite_stmt_error

```php
sqlite_stmt_error(SQLiteProceduralStatement $statement): string
```

Gibt die letzte gespeicherte Fehlermeldung des Statements zurück.

### sqlite_stmt_sqlstate

```php
sqlite_stmt_sqlstate(SQLiteProceduralStatement $statement): string
```

Gibt `00000` bei fehlerfreiem Zustand und andernfalls `HY000` zurück.

### sqlite_stmt_readonly

```php
sqlite_stmt_readonly(SQLiteProceduralStatement $statement): bool
```

Gibt `true` zurück, wenn das Statement die Datenbank nicht verändert.

### sqlite_stmt_sql

```php
sqlite_stmt_sql(SQLiteProceduralStatement $statement, bool $expand = false): string
```

Gibt den SQL-Text des Statements zurück. Bei `expand = true` versucht SQLite, gebundene Werte in den zurückgegebenen Text einzusetzen. Der expandierte Text darf nicht zur erneuten Ausführung oder Protokollierung von Geheimnissen verwendet werden.

## Transaktionen

### sqlite_begin_transaction

```php
sqlite_begin_transaction(
    SQLiteProceduralConnection $connection,
    int $flags = SQLITE_TRANS_DEFERRED,
    ?string $name = null
): bool
```

Beginnt eine Transaktion im durch `flags` bestimmten Modus. `name` wird aus mysqli-Kompatibilitätsgründen akzeptiert, von SQLite aber ignoriert. Ist bereits eine Transaktion aktiv, wird ein Fehler gemeldet.

### sqlite_commit

```php
sqlite_commit(SQLiteProceduralConnection $connection, int $flags = 0, ?string $name = null): bool
```

Schreibt die aktive Transaktion fest. Ohne aktive Transaktion wird `true` geliefert. `flags` und `name` werden nur aus Kompatibilitätsgründen akzeptiert.

### sqlite_rollback

```php
sqlite_rollback(SQLiteProceduralConnection $connection, int $flags = 0, ?string $name = null): bool
```

Rollt die aktive Transaktion zurück. Ohne aktive Transaktion wird `true` geliefert. `flags` und `name` werden nur aus Kompatibilitätsgründen akzeptiert.

### sqlite_autocommit

```php
sqlite_autocommit(SQLiteProceduralConnection $connection, bool $enable): bool
```

Schaltet den Autocommit-Modus um. Beim Deaktivieren wird sofort eine verzögerte Transaktion begonnen. Beim Aktivieren wird eine aktive Transaktion festgeschrieben.

### sqlite_get_autocommit

```php
sqlite_get_autocommit(SQLiteProceduralConnection $connection): bool
```

Gibt den durch `sqlite_autocommit()` verwalteten Autocommit-Status zurück.

### sqlite_savepoint

```php
sqlite_savepoint(SQLiteProceduralConnection $connection, string $name): bool
```

Erstellt einen Savepoint. `name` muss mit einem Buchstaben oder Unterstrich beginnen und darf danach nur Buchstaben, Ziffern und Unterstriche enthalten.

### sqlite_release_savepoint

```php
sqlite_release_savepoint(SQLiteProceduralConnection $connection, string $name): bool
```

Gibt den benannten Savepoint frei. Für `name` gelten dieselben Regeln wie bei `sqlite_savepoint()`.

### sqlite_rollback_to_savepoint

```php
sqlite_rollback_to_savepoint(SQLiteProceduralConnection $connection, string $name): bool
```

Rollt Änderungen bis zum benannten Savepoint zurück, ohne ihn freizugeben.

## SQLite-spezifische Funktionen

### sqlite_busy_timeout

```php
sqlite_busy_timeout(SQLiteProceduralConnection $connection, int $milliseconds): bool
```

Legt fest, wie lange SQLite bei einer gesperrten Datenbank erneut versucht zuzugreifen. `milliseconds` muss mindestens `0` sein; `0` deaktiviert das Warten.

### sqlite_backup

```php
sqlite_backup(
    SQLiteProceduralConnection $source,
    SQLiteProceduralConnection $destination,
    string $sourceDatabase = 'main',
    string $destinationDatabase = 'main'
): bool
```

Kopiert eine geöffnete Quelldatenbank in eine geöffnete Zieldatenbank. Die Datenbanknamen beziehen sich auf `main`, `temp` oder mit `ATTACH` eingebundene Schemas.

### sqlite_create_function

```php
sqlite_create_function(
    SQLiteProceduralConnection $connection,
    string $name,
    callable $callback,
    int $argCount = -1,
    int $flags = 0
): bool
```

Registriert eine skalare SQL-Funktion. `callback` erhält die SQL-Argumente, `argCount` legt deren Anzahl fest (`-1` bedeutet variabel), und `flags` kann beispielsweise `SQLITE3_DETERMINISTIC` enthalten.

### sqlite_create_aggregate

```php
sqlite_create_aggregate(
    SQLiteProceduralConnection $connection,
    string $name,
    callable $stepCallback,
    callable $finalCallback,
    int $argCount = -1
): bool
```

Registriert eine Aggregatfunktion. `stepCallback` verarbeitet jede Zeile und `finalCallback` erzeugt den Endwert. Die Callback-Signaturen entsprechen `SQLite3::createAggregate()`.

### sqlite_create_collation

```php
sqlite_create_collation(SQLiteProceduralConnection $connection, string $name, callable $callback): bool
```

Registriert eine Sortierfunktion. `callback` erhält zwei Zeichenketten und muss einen Wert kleiner, gleich oder größer `0` zurückgeben.

### sqlite_set_authorizer

```php
sqlite_set_authorizer(SQLiteProceduralConnection $connection, ?callable $callback): bool
```

Registriert einen SQLite-Authorizer oder entfernt ihn mit `null`. Der Callback muss einen der nativen Rückgabecodes `SQLite3::OK`, `SQLite3::DENY` oder `SQLite3::IGNORE` liefern.

### sqlite_open_blob

```php
sqlite_open_blob(
    SQLiteProceduralConnection $connection,
    string $table,
    string $column,
    int $rowId,
    string $database = 'main',
    int $flags = SQLITE3_OPEN_READONLY
): resource|false
```

Öffnet eine BLOB-Spalte als Stream. `table`, `column` und `rowId` bestimmen die Zelle; `flags` ist `SQLITE3_OPEN_READONLY` oder `SQLITE3_OPEN_READWRITE`. Der Stream wird mit den normalen PHP-Streamfunktionen gelesen, geschrieben und geschlossen.

### sqlite_load_extension

```php
sqlite_load_extension(SQLiteProceduralConnection $connection, string $name): bool
```

Lädt eine SQLite-Laufzeiterweiterung. Dies funktioniert nur, wenn PHP und SQLite das Laden von Erweiterungen erlauben und `sqlite3.extension_dir` passend konfiguriert ist. Erweiterungsnamen dürfen niemals ungeprüft aus Benutzereingaben stammen.

### sqlite_enable_extended_result_codes

```php
sqlite_enable_extended_result_codes(SQLiteProceduralConnection $connection, bool $enable = true): bool
```

Aktiviert oder deaktiviert erweiterte SQLite-Fehlercodes für die Verbindung.

## Versions- und Verbindungsinformationen

### sqlite_client_info

```php
sqlite_client_info(): string
```

Gibt Namen und Version dieser Kompatibilitätsbibliothek zurück, beispielsweise `sqlite-procedural/1.0.0`.

### sqlite_get_client_info

```php
sqlite_get_client_info(): string
```

mysqli-kompatibler Alias von `sqlite_client_info()`.

### sqlite_client_version

```php
sqlite_client_version(): int
```

Gibt die numerische Bibliotheksversion im Format `Hauptversion * 10000 + Nebenversion * 100 + Patchversion` zurück. Version 1.0.0 entspricht `10000`.

### sqlite_get_client_version

```php
sqlite_get_client_version(): int
```

mysqli-kompatibler Alias von `sqlite_client_version()`.

### sqlite_server_info

```php
sqlite_server_info(SQLiteProceduralConnection $connection): string
```

Gibt die Versionszeichenkette der verwendeten SQLite-Bibliothek zurück. Das Verbindungshandle dient der mysqli-ähnlichen Signatur.

### sqlite_get_server_info

```php
sqlite_get_server_info(SQLiteProceduralConnection $connection): string
```

mysqli-kompatibler Alias von `sqlite_server_info()`.

### sqlite_server_version

```php
sqlite_server_version(SQLiteProceduralConnection $connection): int
```

Gibt die numerische Version der verwendeten SQLite-Bibliothek zurück.

### sqlite_get_server_version

```php
sqlite_get_server_version(SQLiteProceduralConnection $connection): int
```

mysqli-kompatibler Alias von `sqlite_server_version()`.

### sqlite_character_set_name

```php
sqlite_character_set_name(SQLiteProceduralConnection $connection): string
```

Gibt immer `UTF-8` zurück.

### sqlite_get_charset

```php
sqlite_get_charset(SQLiteProceduralConnection $connection): object
```

Gibt ein mysqli-ähnliches Zeichensatzobjekt zurück. Es enthält unter anderem `charset = 'UTF-8'`, `collation = 'BINARY'`, `min_length = 1` und `max_length = 4`.

### sqlite_set_charset

```php
sqlite_set_charset(SQLiteProceduralConnection $connection, string $charset): bool
```

Akzeptiert `utf8`, `utf-8` und `utf_8` und liefert dann `true`. Andere Werte erzeugen einen Fehler, weil SQLite den Verbindungszeichensatz nicht wie MySQL umschalten kann.

### sqlite_thread_id

```php
sqlite_thread_id(SQLiteProceduralConnection $connection): int
```

Gibt immer `0` zurück, weil SQLite eingebettet arbeitet und keine Server-Thread-ID besitzt.

## Fehlerbehandlung – Beispiel

```php
sqlite_report(SQLITE_REPORT_ERROR | SQLITE_REPORT_STRICT);

try {
    $db = sqlite_connect(__DIR__ . '/app.sqlite');
    sqlite_query($db, 'SELECT * FROM nicht_vorhanden');
} catch (SQLiteProceduralException $error) {
    echo $error->getCode() . ': ' . $error->getMessage();
}
```

Für Code, der Fehler ausdrücklich über Rückgabewerte auswertet:

```php
sqlite_report(SQLITE_REPORT_OFF);

$db = sqlite_connect(__DIR__ . '/app.sqlite');
$result = sqlite_query($db, 'INVALID SQL');

if ($result === false) {
    printf("SQLite-Fehler %d: %s\n", sqlite_errno($db), sqlite_error($db));
}
```

## Sicherheitshinweise

- SQL-Werte über Prepared Statements binden; Tabellen- und Spaltennamen über eine feste Allowlist auswählen.
- Datenbankdateien außerhalb öffentlich erreichbarer Webverzeichnisse speichern.
- `sqlite_load_extension()` nur mit fest konfigurierten, vertrauenswürdigen Erweiterungen verwenden.
- Für konkurrierende Schreibzugriffe einen sinnvollen `sqlite_busy_timeout()` setzen und Transaktionen kurz halten.
- BLOB-Streams und alle Handles nach Gebrauch schließen.
