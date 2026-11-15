<?php

namespace App\Concerns;

use Closure;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV downloads shared by the stock ledger and the reports: streamed row by row, prefixed with a UTF-8 BOM so
 * Excel reads non-ASCII names correctly, and with user-entered text that looks like a formula neutralised.
 */
trait StreamsCsv
{
    /**
     * Stream a CSV download. The rows closure runs while streaming and yields one array per row, so large exports
     * never sit in memory.
     *
     * @param  list<string>  $header
     * @param  Closure(): iterable<array<int, mixed>>  $rows
     */
    protected function streamCsv(string $filename, array $header, Closure $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\u{FEFF}");

            fputcsv($output, $header, escape: '');

            foreach ($rows() as $row) {
                fputcsv($output, array_map($this->csvSafe(...), $row), escape: '');
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Stop spreadsheet apps from treating user-entered text as a formula.
     */
    protected function csvSafe(mixed $value): mixed
    {
        return is_string($value) && preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }
}
