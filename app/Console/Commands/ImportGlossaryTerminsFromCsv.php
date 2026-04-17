<?php

namespace App\Console\Commands;

use App\Models\Termin;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportGlossaryTerminsFromCsv extends Command
{
    protected $signature = 'termins:import-glossary
                            {csv? : Path to CSV or Google Sheets HTML; default: database/data/glossary.html (project path or /var/www/html/… in Docker)}
                            {--language=ru : language_code for imported rows}';

    protected $description = 'Delete all termins and re-import glossary from CSV or Google Sheets HTML. CSV: comma or semicolon; 2 cols (termin;description) or 3 cols with empty first (;termin;description) or glossary (cols B+C). Default: database/data/glossary.html';

    public function handle(): int
    {
        $given = $this->argument('csv');
        $path = $this->resolveCsvPath(($given !== null && $given !== '') ? (string) $given : 'database/data/glossary.html');
        if ($path === null) {
            $this->error('File not readable: '.$this->argument('csv'));

            return self::FAILURE;
        }

        $language = (string) $this->option('language');

        $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        $rows = match ($ext) {
            'html', 'htm' => $this->readGoogleSheetsHtmlRows($path),
            'csv' => $this->readCsvRows($path),
            default => [],
        };
        if ($rows === []) {
            $this->warn('No data rows found (use .csv, .html, or .htm).');

            return self::FAILURE;
        }

        $this->info('Deleting existing termins…');
        DB::transaction(function () use ($rows, $language) {
            Termin::query()->delete();
            $n = 0;
            foreach ($rows as $row) {
                [$termin, $description] = $this->terminDescriptionFromCsvRow($row);
                if ($termin === '') {
                    continue;
                }
                Termin::create([
                    'termin' => Termin::uniqueName($termin),
                    'description' => $description,
                    'language_code' => $language,
                ]);
                $n++;
            }
            $this->info("Imported {$n} termins.");
        });

        return self::SUCCESS;
    }

    /**
     * Artisan runs in Docker with the repo at /var/www/html; host paths like /Users/... are not visible.
     * Accept project-relative paths and fall back to database/data/{basename} when the given path is missing.
     */
    private function resolveCsvPath(string $path): ?string
    {
        if ($path !== '' && is_readable($path)) {
            return $path;
        }

        $trimmed = ltrim($path, '/');
        if ($trimmed !== '' && is_readable(base_path($trimmed))) {
            return base_path($trimmed);
        }

        if ($path !== '' && ! str_contains($path, '/') && ! str_contains($path, '\\')) {
            $inData = base_path('database/data/'.$path);
            if (is_readable($inData)) {
                return $inData;
            }
        }

        if (preg_match('#/([^/]+)$#', $path, $m)) {
            $byName = base_path('database/data/'.$m[1]);
            if (is_readable($byName)) {
                return $byName;
            }
        }

        return null;
    }

    /**
     * @return list<array<int, string|null>>
     */
    private function readCsvRows(string $path): array
    {
        $delimiter = $this->detectCsvDelimiter($path);
        $fp = fopen($path, 'rb');
        if ($fp === false) {
            return [];
        }
        $rows = [];
        $first = true;
        while (($data = fgetcsv($fp, 0, $delimiter, '"', '\\')) !== false) {
            if ($first) {
                $first = false;
                if (isset($data[0]) && str_starts_with($data[0], "\xEF\xBB\xBF")) {
                    $data[0] = substr($data[0], 3);
                }

                continue;
            }
            $rows[] = $data;
        }
        fclose($fp);

        return $rows;
    }

    /**
     * Detect `;` vs `,` from the first line (supports UTF-8 BOM).
     */
    private function detectCsvDelimiter(string $path): string
    {
        $fp = fopen($path, 'rb');
        if ($fp === false) {
            return ',';
        }
        $line = fgets($fp);
        fclose($fp);
        if ($line === false || $line === '') {
            return ',';
        }
        if (str_starts_with($line, "\xEF\xBB\xBF")) {
            $line = substr($line, 3);
        }
        $byComma = str_getcsv($line, ',', '"', '\\');
        $bySemi = str_getcsv($line, ';', '"', '\\');

        return count($bySemi) > count($byComma) ? ';' : ',';
    }

    /**
     * @param  array<int, string|null>  $row
     * @return array{0: string, 1: string} [termin, description]
     */
    private function terminDescriptionFromCsvRow(array $row): array
    {
        $n = count($row);
        if ($n >= 3 && trim((string) ($row[0] ?? '')) === '') {
            return [
                trim((string) ($row[1] ?? '')),
                (string) ($row[2] ?? ''),
            ];
        }
        if ($n >= 3) {
            return [
                trim((string) ($row[1] ?? '')),
                (string) ($row[2] ?? ''),
            ];
        }

        return [
            trim((string) ($row[0] ?? '')),
            (string) ($row[1] ?? ''),
        ];
    }

    /**
     * Google Sheets → Download → Web page: table.waffle, one freeze column between A and B.
     * Same mapping as CSV: column B = termin, C = description (HTML allowed in C).
     *
     * @return list<array<int, string|null>>
     */
    private function readGoogleSheetsHtmlRows(string $path): array
    {
        $html = file_get_contents($path);
        if ($html === false || $html === '') {
            return [];
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument;
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $out = [];
        foreach ($xpath->query('//tbody/tr') as $tr) {
            if (! $tr instanceof DOMElement) {
                continue;
            }
            $tds = [];
            foreach ($tr->getElementsByTagName('td') as $td) {
                if (str_contains($td->getAttribute('class'), 'freezebar')) {
                    continue;
                }
                $tds[] = $td;
            }
            if (count($tds) < 3) {
                continue;
            }
            $colA = $this->tdPlainText($tds[0]);
            if (str_contains($colA, 'Термин') && str_contains($colA, 'кратко')) {
                continue;
            }
            $termin = $this->tdPlainText($tds[1]);
            $description = $this->tdInnerHtml($tds[2]);
            if ($termin === '') {
                continue;
            }
            $out[] = [null, $termin, $description];
        }

        return $out;
    }

    private function tdPlainText(DOMElement $td): string
    {
        $text = $td->textContent ?? '';

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    private function tdInnerHtml(DOMElement $td): string
    {
        $doc = $td->ownerDocument;
        if ($doc === null) {
            return '';
        }
        $html = '';
        foreach ($td->childNodes as $child) {
            $html .= $doc->saveHTML($child);
        }

        return trim($html);
    }
}
