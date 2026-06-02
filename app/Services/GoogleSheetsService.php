<?php

namespace App\Services;

use App\Models\SbcSignup;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleSheetsService
{
    private string $apiKey;
    private string $baseUrl = 'https://sheets.googleapis.com/v4/spreadsheets';

    public function __construct()
    {
        $this->apiKey = config('google.sheets_api_key', '');
    }

    /**
     * Read all rows from a named sheet tab, returned as array of assoc arrays.
     * Cached for 5 minutes.
     */
    public function getSheet(string $spreadsheetId, string $sheetName): array
    {
        $cacheKey = "gsheet_{$spreadsheetId}_{$sheetName}";

        return Cache::remember($cacheKey, 300, function () use ($spreadsheetId, $sheetName) {
            try {
                $range    = urlencode($sheetName);
                $response = Http::get("{$this->baseUrl}/{$spreadsheetId}/values/{$range}", [
                    'key' => $this->apiKey,
                ]);

                if (! $response->successful()) {
                    Log::warning("GoogleSheetsService: {$sheetName} returned {$response->status()}");
                    return ['headers' => [], 'rows' => [], 'error' => 'API error ' . $response->status()];
                }

                $values  = $response->json('values', []);
                if (empty($values)) return ['headers' => [], 'rows' => [], 'error' => null];

                $headers = array_map('trim', $values[0]);
                $rows    = [];

                foreach (array_slice($values, 1) as $row) {
                    $assoc = [];
                    foreach ($headers as $i => $header) {
                        $assoc[$header] = $row[$i] ?? '';
                    }
                    $rows[] = $assoc;
                }

                return ['headers' => $headers, 'rows' => $rows, 'error' => null];
            } catch (\Exception $e) {
                Log::error('GoogleSheetsService: ' . $e->getMessage());
                return ['headers' => [], 'rows' => [], 'error' => $e->getMessage()];
            }
        });
    }

    public function clearCache(string $spreadsheetId, string $sheetName): void
    {
        Cache::forget("gsheet_{$spreadsheetId}_{$sheetName}");
    }

    /**
     * Pull one sheet from Google Sheets and upsert every row into the DB.
     * Returns ['inserted' => int, 'updated' => int, 'error' => string|null]
     */
    public function syncSheetToDb(string $spreadsheetId, string $sheetName): array
    {
        $this->clearCache($spreadsheetId, $sheetName);
        $data = $this->getSheet($spreadsheetId, $sheetName);

        if ($data['error']) return ['inserted' => 0, 'updated' => 0, 'error' => $data['error']];

        $inserted = 0;
        $updated  = 0;
        $now      = now();

        foreach ($data['rows'] as $row) {
            $phone = trim($row['Phone Number'] ?? $row[' Phone Number'] ?? '');
            $date  = trim($row['Date'] ?? '');

            // Try to parse date; skip row if invalid
            try {
                $parsedDate = $date ? \Carbon\Carbon::parse($date)->toDateString() : null;
            } catch (\Exception) {
                $parsedDate = null;
            }

            $attributes = [
                'sheet'        => $sheetName,
                'date'         => $parsedDate,
                'phone_number' => $phone ?: null,
            ];

            $values = [
                'first_name' => trim($row['First Name'] ?? '') ?: null,
                'surname'    => trim($row['Surname'] ?? '') ?: null,
                'age'        => is_numeric($row['Age'] ?? '') ? (int)$row['Age'] : null,
                'sex'        => trim($row['Sex'] ?? '') ?: null,
                'location'   => trim($row['Location'] ?? '') ?: null,
                'synced_at'  => $now,
            ];

            $existing = SbcSignup::where($attributes)->first();

            if ($existing) {
                $existing->update($values);
                $updated++;
            } else {
                SbcSignup::create(array_merge($attributes, $values));
                $inserted++;
            }
        }

        return ['inserted' => $inserted, 'updated' => $updated, 'error' => null];
    }
}
