<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class DataTransferController extends Controller
{
    public function importSqlForm()
    {
        return view('data-transfer.import-sql');
    }

    public function importSql(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sql_file' => ['required', 'file', 'mimes:sql,txt'],
        ], [
            'sql_file.required' => 'Seleziona un file SQL da importare.',
            'sql_file.mimes' => 'Il file deve essere in formato .sql o .txt.',
        ]);

        $sql = file_get_contents($data['sql_file']->getRealPath());
        if ($sql === false || trim($sql) === '') {
            return back()->with('error', 'File SQL vuoto o non leggibile.');
        }

        $sql = $this->removeUtf8Bom($sql);
        $statements = $this->splitSqlStatements($sql);

        if ($statements === []) {
            return back()->with('error', 'Nessuna query SQL valida trovata nel file.');
        }

        $executed = 0;
        try {
            DB::unprepared('SET FOREIGN_KEY_CHECKS=0;');

            foreach ($statements as $statement) {
                $trimmed = trim($statement);
                if ($trimmed === '') {
                    continue;
                }
                DB::unprepared($trimmed);
                $executed++;
            }
        } catch (Throwable $e) {
            return back()->with('error', 'Import SQL fallito: ' . $e->getMessage());
        } finally {
            try {
                DB::unprepared('SET FOREIGN_KEY_CHECKS=1;');
            } catch (Throwable) {
            }
        }

        return redirect()->route('data-transfer.import.sql.form')
            ->with('success', "Import SQL completato. Query eseguite: {$executed}.");
    }

    public function exportSqlForm()
    {
        return view('data-transfer.export-sql');
    }

    public function exportSql()
    {
        $filename = 'budgetnow-export-' . now()->format('Ymd-His') . '.sql';

        return response()->streamDownload(function () {
            $pdo = DB::connection()->getPdo();

            echo "-- BudgetNow SQL Export\n";
            echo "-- Generated at: " . now()->toDateTimeString() . "\n\n";
            echo "SET FOREIGN_KEY_CHECKS=0;\n\n";

            foreach ($this->baseTables() as $table) {
                $quotedTable = $this->quoteIdentifier($table);
                $create = DB::select('SHOW CREATE TABLE ' . $quotedTable);
                $createArr = (array) ($create[0] ?? []);
                $createSql = (string) ($createArr['Create Table'] ?? array_values($createArr)[1] ?? '');

                echo "-- ----------------------------\n";
                echo "-- Table structure for {$table}\n";
                echo "-- ----------------------------\n";
                echo "DROP TABLE IF EXISTS {$quotedTable};\n";
                echo $createSql . ";\n\n";

                $rows = DB::table($table)->get();
                if ($rows->isEmpty()) {
                    continue;
                }

                echo "-- ----------------------------\n";
                echo "-- Data for {$table}\n";
                echo "-- ----------------------------\n";

                foreach ($rows as $row) {
                    $arr = (array) $row;
                    $columns = array_map(fn ($col) => $this->quoteIdentifier((string) $col), array_keys($arr));
                    $values = array_map(function ($value) use ($pdo) {
                        if ($value === null) {
                            return 'NULL';
                        }
                        if (is_bool($value)) {
                            return $value ? '1' : '0';
                        }
                        if (is_int($value) || is_float($value)) {
                            return (string) $value;
                        }

                        return $pdo->quote((string) $value);
                    }, array_values($arr));

                    echo 'INSERT INTO ' . $quotedTable
                        . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ");\n";
                }

                echo "\n";
            }

            echo "SET FOREIGN_KEY_CHECKS=1;\n";
        }, $filename, [
            'Content-Type' => 'application/sql; charset=UTF-8',
        ]);
    }

    private function baseTables(): array
    {
        $rows = DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");

        $tables = [];
        foreach ($rows as $row) {
            $values = array_values((array) $row);
            if (isset($values[0]) && is_string($values[0])) {
                $tables[] = $values[0];
            }
        }

        sort($tables);

        return $tables;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private function removeUtf8Bom(string $content): string
    {
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            return substr($content, 3);
        }

        return $content;
    }

    private function splitSqlStatements(string $sql): array
    {
        $sql = preg_replace('/^\s*DELIMITER\s+.+$/mi', '', $sql) ?? $sql;

        $statements = [];
        $buffer = '';
        $length = strlen($sql);
        $inSingle = false;
        $inDouble = false;
        $inBacktick = false;
        $inLineComment = false;
        $inBlockComment = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            if ($inLineComment) {
                if ($char === "\n") {
                    $inLineComment = false;
                    $buffer .= $char;
                }
                continue;
            }

            if ($inBlockComment) {
                if ($char === '*' && $next === '/') {
                    $inBlockComment = false;
                    $i++;
                }
                continue;
            }

            if (!$inSingle && !$inDouble && !$inBacktick) {
                if ($char === '-' && $next === '-') {
                    $after = $i + 2 < $length ? $sql[$i + 2] : '';
                    if ($after === ' ' || $after === "\t" || $after === "\n" || $after === "\r") {
                        $inLineComment = true;
                        $i++;
                        continue;
                    }
                }
                if ($char === '#') {
                    $inLineComment = true;
                    continue;
                }
                if ($char === '/' && $next === '*') {
                    $inBlockComment = true;
                    $i++;
                    continue;
                }
            }

            if ($char === "'" && !$inDouble && !$inBacktick) {
                $escaped = $i > 0 && $sql[$i - 1] === '\\';
                if (!$escaped) {
                    $inSingle = !$inSingle;
                }
            } elseif ($char === '"' && !$inSingle && !$inBacktick) {
                $escaped = $i > 0 && $sql[$i - 1] === '\\';
                if (!$escaped) {
                    $inDouble = !$inDouble;
                }
            } elseif ($char === '`' && !$inSingle && !$inDouble) {
                $inBacktick = !$inBacktick;
            }

            if ($char === ';' && !$inSingle && !$inDouble && !$inBacktick) {
                $trimmed = trim($buffer);
                if ($trimmed !== '') {
                    $statements[] = $trimmed;
                }
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $trimmed = trim($buffer);
        if ($trimmed !== '') {
            $statements[] = $trimmed;
        }

        return $statements;
    }
}
