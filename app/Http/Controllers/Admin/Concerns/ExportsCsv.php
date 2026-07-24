<?php

namespace App\Http\Controllers\Admin\Concerns;

use Symfony\Component\HttpFoundation\StreamedResponse;

trait ExportsCsv
{
    /**
     * Stream an array of rows as a downloadable CSV - no package needed,
     * `fputcsv` against the response stream is all a flat report table needs.
     *
     * @param  array<int, string>  $header
     * @param  iterable<int, array<int, mixed>>  $rows
     */
    protected function csvDownload(string $filename, array $header, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, $header);

            foreach ($rows as $row) {
                fputcsv($stream, $row);
            }

            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
