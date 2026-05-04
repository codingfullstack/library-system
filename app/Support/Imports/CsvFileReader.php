<?php

namespace App\Support\Imports;

use Illuminate\Http\UploadedFile;
use RuntimeException;

class CsvFileReader
{
    /**
     * @return array{headers: array<int, string>, rows: array<int, array<string, string|null>>}
     */
    public function read(UploadedFile $file): array
    {
        $path = $file->getRealPath();

        if (! $path || ! is_readable($path)) {
            throw new RuntimeException('Nepavyko perskaityti importo failo.');
        }

        $handle = fopen($path, 'rb');

        if (! $handle) {
            throw new RuntimeException('Nepavyko atidaryti importo failo.');
        }

        $firstLine = fgets($handle);

        if ($firstLine === false) {
            fclose($handle);

            throw new RuntimeException('Importo failas tuscias.');
        }

        $firstLine = $this->stripBom($firstLine);

        if ($this->isSeparatorHintLine($firstLine)) {
            $delimiter = $this->detectDelimiterFromSeparatorHint($firstLine);
            $headerLine = fgets($handle);

            if ($headerLine === false) {
                fclose($handle);

                throw new RuntimeException('Nepavyko nuskaityti stulpeliu antrastes.');
            }

            $headers = str_getcsv($this->stripBom($headerLine), $delimiter);
        } else {
            $delimiter = $this->detectDelimiter($firstLine);
            rewind($handle);
            $headers = fgetcsv($handle, 0, $delimiter);
        }

        if ($headers === false || $headers === [null] || $headers === []) {
            fclose($handle);

            throw new RuntimeException('Nepavyko nuskaityti stulpeliu antrastes.');
        }

        $headers = array_map(fn ($value) => $this->normalizeHeader((string) $value), $headers);
        $rows = [];

        while (($line = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($line === [null] || $line === []) {
                continue;
            }

            $line = array_pad($line, count($headers), null);
            $row = [];
            $hasValue = false;

            foreach ($headers as $index => $header) {
                $value = isset($line[$index]) ? trim((string) $line[$index]) : null;
                $value = $value === '' ? null : $value;
                $row[$header] = $value;
                $hasValue = $hasValue || $value !== null;
            }

            if ($hasValue) {
                $rows[] = $row;
            }
        }

        fclose($handle);

        return [
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    private function detectDelimiter(string $line): string
    {
        $candidates = [
            ';' => substr_count($line, ';'),
            ',' => substr_count($line, ','),
            "\t" => substr_count($line, "\t"),
        ];

        arsort($candidates);

        $delimiter = array_key_first($candidates);

        return $candidates[$delimiter] > 0 ? $delimiter : ',';
    }

    private function isSeparatorHintLine(string $line): bool
    {
        return preg_match('/^\s*sep\s*=\s*.\s*$/i', trim($line)) === 1;
    }

    private function detectDelimiterFromSeparatorHint(string $line): string
    {
        if (preg_match('/^\s*sep\s*=\s*(.)\s*$/i', trim($line), $matches) === 1) {
            return $matches[1];
        }

        return ';';
    }

    private function normalizeHeader(string $header): string
    {
        $header = $this->stripBom($header);
        $header = mb_strtolower(trim($header));
        $header = str_replace([' ', '-'], '_', $header);

        return preg_replace('/[^a-z0-9_]/', '', $header) ?? $header;
    }

    private function stripBom(string $value): string
    {
        return preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
    }
}
