<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class Csv
{
    /**
     * @param list<string> $headers
     * @param iterable<array<int, mixed>> $rows
     */
    public static function export(array $headers, iterable $rows, string $filename): StreamedResponse
    {
        return response()->streamDownload(
            function () use ($headers, $rows): void {
                $stream = fopen('php://output', 'w');

                if ($stream === false) {
                    return;
                }

                fputcsv($stream, $headers);

                foreach ($rows as $row) {
                    fputcsv($stream, $row);
                }

                fclose($stream);
            },
            $filename,
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    /**
     * @param list<string> $headers
     * @return list<array<int, string>>
     *
     * @throws InvalidArgumentException when the CSV contents are invalid
     * @throws RuntimeException when the CSV cannot be read
     */
    public static function import(UploadedFile $file, array $headers): array
    {
        $stream = fopen($file->getRealPath(), 'r');

        if ($stream === false) {
            throw new RuntimeException('The CSV file could not be read.');
        }

        try {
            $actualHeaders = fgetcsv($stream);

            $expectedHeaders = array_map(fn(string $header): string => strtolower(rtrim($header, '*')), $headers);
            $actualHeaders = $actualHeaders === false
                ? false
                : array_map(fn(string $header): string => strtolower(rtrim(trim($header), '*')), $actualHeaders);

            if ($actualHeaders === false || $actualHeaders !== $expectedHeaders) {
                throw new CsvImportException('csv.import_errors.invalid_headers');
            }

            $rows = [];
            $line = 0;

            while (($row = fgetcsv($stream)) !== false) {
                $line++;

                if (count($row) !== count($headers)) {
                    throw new CsvImportException('csv.import_errors.invalid_row', ['line' => $line]);
                }

                foreach ($headers as $column => $header) {
                    if (str_ends_with($header, '*') && trim((string) $row[$column]) === '') {
                        $field = rtrim($header, '*');

                        throw new CsvImportException('csv.import_errors.required_field', [
                            'field' => $field,
                            'line' => $line,
                        ]);
                    }
                }

                $rows[] = $row;
            }

            return $rows;
        } finally {
            fclose($stream);
        }
    }
}