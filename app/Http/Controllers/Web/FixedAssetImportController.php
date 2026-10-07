<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AssetCategory;
use App\Models\Department;
use App\Models\FixedAsset;
use App\Models\Location;
use App\Models\User;
use App\Services\FixedAssetService;
use App\Support\Assets\AssetStatus;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

// Bulk-register fixed assets from an Excel/CSV file. Every row is validated
// first; if any row fails nothing is imported, and the errors are listed by
// row number. Valid files go through FixedAssetService::registerAsset so each
// asset gets its number and "created" history entry like a manual registration.
class FixedAssetImportController extends Controller
{
    private const MAX_ROWS = 1000;

    // Column key => [heading, required, help text, example]
    private const COLUMNS = [
        'name'                    => ['Name', true, 'Asset name.', 'Dell Latitude 5440 Laptop'],
        'category'                => ['Category', false, 'Must match an existing asset category (pick from the list).', null],
        'description'             => ['Description', false, 'Free text.', '16GB RAM, 512GB SSD'],
        'manufacturer'            => ['Manufacturer', false, 'Free text.', 'Dell'],
        'model'                   => ['Model', false, 'Free text.', 'Latitude 5440'],
        'serial_number'           => ['Serial Number', false, 'Must be unique — not already in the register or repeated in the file.', 'SN-123456'],
        'purchase_date'           => ['Purchase Date', false, 'Date as YYYY-MM-DD (e.g. 2025-01-31) or DD/MM/YYYY.', '2025-01-31'],
        'purchase_cost'           => ['Purchase Cost', false, 'Number, no currency symbol (e.g. 1250.00).', 1250],
        'useful_life_years'       => ['Useful Life (years)', false, 'Whole number of years, 1–100. Needed for depreciation.', 5],
        'salvage_value'           => ['Salvage Value', false, 'Number. Value expected at end of useful life.', 100],
        'revaluation_cycle_years' => ['Revaluation Cycle (years)', false, 'Whole number, 1–50. Defaults to 3 if blank.', 3],
        'supplier_name'           => ['Supplier', false, 'Free text.', 'Computer Centre'],
        'warranty_start'          => ['Warranty Start', false, 'Date, same formats as Purchase Date.', '2025-01-31'],
        'warranty_expiry'         => ['Warranty Expiry', false, 'Date, same formats as Purchase Date.', '2028-01-31'],
        'condition'               => ['Condition', false, 'One of: '.'%CONDITIONS%'.'. Defaults to good.', 'good'],
        'department'              => ['Department', false, 'Must match an existing department (pick from the list).', null],
        'location'                => ['Location', false, 'Must match an existing location (pick from the list).', null],
    ];

    private const DATE_COLUMNS = ['purchase_date', 'warranty_start', 'warranty_expiry'];

    private const NUMBER_COLUMNS = ['purchase_cost', 'useful_life_years', 'salvage_value', 'revaluation_cycle_years'];

    public function __construct(private readonly FixedAssetService $service)
    {
    }

    private function authorizeManager(User $user): void
    {
        abort_unless(in_array($user->role, ['admin', 'director', 'stores', 'accounting_dep'], true), 403);
    }

    public function template(Request $request): StreamedResponse
    {
        $this->authorizeManager($request->user());

        $lists = [
            'Category'   => AssetCategory::orderBy('name')->pluck('name')->all(),
            'Condition'  => AssetStatus::CONDITIONS,
            'Department' => Department::orderBy('name')->pluck('name')->all(),
            'Location'   => Location::orderBy('name')->pluck('name')->all(),
        ];

        $book = new Spreadsheet();

        // ── Sheet 1: Assets (the one that gets imported) ─────────────────────
        $sheet = $book->getActiveSheet()->setTitle('Assets');
        $col = 1;
        foreach (self::COLUMNS as $key => [$heading, $required]) {
            $letter = $this->letter($col);
            $sheet->setCellValue("{$letter}1", $heading.($required ? ' *' : ''));
            $example = self::COLUMNS[$key][3];
            if ($key === 'category') {
                // Prefer a category that suits the laptop example.
                $example = collect($lists['Category'])->first(fn ($n) => preg_match('/\b(it|ict|computer|laptop)/i', $n))
                    ?? $lists['Category'][0] ?? null;
            } elseif ($key === 'department') {
                $example = $lists['Department'][0] ?? null;
            } elseif ($key === 'location') {
                $example = $lists['Location'][0] ?? null;
            }
            if ($example !== null) {
                $sheet->setCellValue("{$letter}2", $example);
            }
            if (in_array($key, self::DATE_COLUMNS, true)) {
                $sheet->getStyle("{$letter}2:{$letter}".(self::MAX_ROWS + 1))->getNumberFormat()->setFormatCode('yyyy-mm-dd');
            }
            if (in_array($key, ['purchase_cost', 'salvage_value'], true)) {
                $sheet->getStyle("{$letter}2:{$letter}".(self::MAX_ROWS + 1))->getNumberFormat()->setFormatCode('#,##0.00');
            }
            $sheet->getColumnDimension($letter)->setWidth(max(14, strlen($heading) + 4));
            $col++;
        }
        $lastCol = $this->letter(count(self::COLUMNS));
        $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle("A1:{$lastCol}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F3864');
        $sheet->getStyle("A2:{$lastCol}2")->getFont()->setItalic(true)->getColor()->setRGB('6B7280');
        $sheet->freezePane('A2');

        // ── Hidden "Lists" sheet feeding the drop-downs ──────────────────────
        $listSheet = $book->createSheet()->setTitle('Lists');
        $listCol = 1;
        $ranges = [];
        foreach ($lists as $title => $values) {
            $letter = $this->letter($listCol++);
            $listSheet->setCellValue("{$letter}1", $title);
            foreach (array_values($values) as $i => $value) {
                $listSheet->setCellValue($letter.($i + 2), $value);
            }
            $ranges[$title] = $values ? "Lists!\${$letter}\$2:\${$letter}\$".(count($values) + 1) : null;
        }
        $listSheet->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);

        $dropdowns = ['category' => 'Category', 'condition' => 'Condition', 'department' => 'Department', 'location' => 'Location'];
        foreach ($dropdowns as $key => $title) {
            if (! $ranges[$title]) {
                continue;
            }
            $letter = $this->letter(array_search($key, array_keys(self::COLUMNS), true) + 1);
            $validation = $sheet->getCell("{$letter}2")->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST)
                ->setErrorStyle(DataValidation::STYLE_STOP)
                ->setAllowBlank(true)
                ->setShowDropDown(true)
                ->setShowErrorMessage(true)
                ->setErrorTitle("Invalid {$title}")
                ->setError("Pick a {$title} from the list.")
                ->setFormula1($ranges[$title]);
            $validation->setSqref("{$letter}2:{$letter}".(self::MAX_ROWS + 1));
        }

        // ── Sheet 3: Instructions ────────────────────────────────────────────
        $help = $book->createSheet()->setTitle('Instructions');
        $help->fromArray([['Column', 'Required', 'Format / allowed values', 'Example']]);
        $r = 2;
        foreach (self::COLUMNS as $key => [$heading, $required, $text, $example]) {
            $help->fromArray([[
                $heading,
                $required ? 'Yes' : 'No',
                str_replace('%CONDITIONS%', implode(', ', AssetStatus::CONDITIONS), $text),
                (string) ($example ?? ''),
            ]], null, "A{$r}");
            $r++;
        }
        $r++;
        foreach ([
            'How to use:',
            '1. Fill one asset per row on the "Assets" sheet, starting at row 2 (replace the grey example row).',
            '2. Only Name is required. Leave a cell blank if you don\'t have the value.',
            '3. Asset numbers are created automatically — do not add them.',
            '4. Upload the file on Fixed Assets → Import. Every row is checked first; if any row has a problem nothing is imported and the errors are listed by row number.',
            '5. Up to '.self::MAX_ROWS.' assets per file. Excel (.xlsx) and CSV files with the same headings both work.',
        ] as $line) {
            $help->setCellValue("A{$r}", $line);
            $r++;
        }
        $help->getStyle('A1:D1')->getFont()->setBold(true);
        foreach (['A' => 26, 'B' => 10, 'C' => 70, 'D' => 26] as $c => $w) {
            $help->getColumnDimension($c)->setWidth($w);
        }

        $book->setActiveSheetIndex(0);

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, 'fixed-assets-import-template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $this->authorizeManager($request->user());

        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv,txt|max:5120']);
        $file = $request->file('file');

        try {
            $rows = IOFactory::load($file->getRealPath())->getSheet(0)->toArray(null, true, false, false);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['file' => 'The file could not be read. Use the Excel template or a CSV with the same headings.']);
        }

        [$assets, $errors] = $this->parse($rows);

        if ($errors) {
            throw ValidationException::withMessages(
                collect($errors)->take(50)->mapWithKeys(fn ($msg, $i) => ["rows.{$i}" => $msg])->all()
                + (count($errors) > 50 ? ['rows.50' => '…and '.(count($errors) - 50).' more problems.'] : [])
            );
        }

        $created = DB::transaction(fn () => $assets->map(fn ($attrs) => $this->service->registerAsset($request->user(), $attrs)));

        $first = $created->first()->asset_number;
        $last  = $created->last()->asset_number;
        $range = $first === $last ? $first : "{$first} – {$last}";

        $request->attributes->set('audit_description',
            "Imported {$created->count()} fixed asset(s) from \"{$file->getClientOriginalName()}\" ({$range})");

        return back()->with('success', "Imported {$created->count()} asset(s): {$range}.");
    }

    // Returns [Collection of attribute arrays, list of "Row N: …" errors].
    private function parse(array $rows): array
    {
        $headings = array_shift($rows) ?? [];
        $map = $this->mapHeadings($headings);

        if (! isset($map['name'])) {
            return [collect(), ['The first row must contain the column headings from the template (at least "Name").']];
        }

        $categories  = AssetCategory::pluck('id', 'name')->mapWithKeys(fn ($id, $n) => [mb_strtolower(trim($n)) => $id]);
        $departments = Department::pluck('id', 'name')->mapWithKeys(fn ($id, $n) => [mb_strtolower(trim($n)) => $id]);
        $locations   = Location::pluck('id', 'name')->mapWithKeys(fn ($id, $n) => [mb_strtolower(trim($n)) => $id]);
        $existingSerials = FixedAsset::withTrashed()->whereNotNull('serial_number')->pluck('serial_number')
            ->map(fn ($s) => mb_strtolower(trim($s)))->flip();

        $assets = collect();
        $errors = [];
        $seenSerials = [];

        foreach ($rows as $i => $row) {
            $rowNumber = $i + 2; // heading is row 1
            $cell = fn (string $key) => isset($map[$key]) ? $row[$map[$key]] ?? null : null;

            // Skip fully blank rows (e.g. trailing formatted rows in the template).
            if (collect($map)->every(fn ($index) => trim((string) ($row[$index] ?? '')) === '')) {
                continue;
            }
            if ($assets->count() + count($errors) >= self::MAX_ROWS) {
                $errors[] = 'The file has more than '.self::MAX_ROWS.' assets. Split it into smaller files.';
                break;
            }

            // The template ships with a grey example row; refuse it rather than
            // registering a fake laptop if someone forgets to replace it.
            if (trim((string) $cell('name')) === self::COLUMNS['name'][3] && trim((string) $cell('serial_number')) === self::COLUMNS['serial_number'][3]) {
                $errors[] = "Row {$rowNumber}: this is the template's example row — replace it with a real asset or delete the row.";
                continue;
            }

            $rowErrors = [];
            $attrs = [];

            foreach (array_keys(self::COLUMNS) as $key) {
                $raw = $cell($key);
                $value = is_string($raw) ? trim($raw) : $raw;
                if ($value === '' || $value === null) {
                    continue;
                }

                $heading = self::COLUMNS[$key][0];
                $lookups = ['category' => [$categories, 'asset_category_id'], 'department' => [$departments, 'department_id'], 'location' => [$locations, 'location_id']];

                if (in_array($key, self::DATE_COLUMNS, true)) {
                    if ($date = $this->toDate($value)) {
                        $attrs[$key] = $date;
                    } else {
                        $rowErrors[] = "{$heading} \"{$value}\" is not a valid date (use YYYY-MM-DD)";
                    }
                } elseif (in_array($key, self::NUMBER_COLUMNS, true)) {
                    $number = is_numeric($value) ? $value : str_replace([',', ' ', '$'], '', (string) $value);
                    if (is_numeric($number)) {
                        $attrs[$key] = $number + 0;
                    } else {
                        $rowErrors[] = "{$heading} \"{$value}\" is not a number";
                    }
                } elseif (isset($lookups[$key])) {
                    [$options, $column] = $lookups[$key];
                    if ($id = $options[mb_strtolower((string) $value)] ?? null) {
                        $attrs[$column] = $id;
                    } else {
                        $rowErrors[] = "{$heading} \"{$value}\" doesn't exist";
                    }
                } elseif ($key === 'condition') {
                    $attrs['condition'] = mb_strtolower($value);
                } else {
                    $attrs[$key] = (string) $value;
                }
            }

            $validator = Validator::make($attrs, [
                'name'                    => 'required|string|max:255',
                'manufacturer'            => 'nullable|string|max:255',
                'model'                   => 'nullable|string|max:255',
                'serial_number'           => 'nullable|string|max:255',
                'supplier_name'           => 'nullable|string|max:255',
                'purchase_cost'           => 'nullable|numeric|min:0',
                'useful_life_years'       => 'nullable|integer|min:1|max:100',
                'salvage_value'           => 'nullable|numeric|min:0',
                'revaluation_cycle_years' => 'nullable|integer|min:1|max:50',
                'condition'               => 'nullable|in:'.implode(',', AssetStatus::CONDITIONS),
            ], [
                'name.required'   => 'Name is required',
                'condition.in'    => 'Condition must be one of: '.implode(', ', AssetStatus::CONDITIONS),
                'integer'         => ':attribute must be a whole number',
            ], [
                'useful_life_years' => 'Useful Life', 'revaluation_cycle_years' => 'Revaluation Cycle',
                'purchase_cost' => 'Purchase Cost', 'salvage_value' => 'Salvage Value',
            ]);
            $rowErrors = array_merge($rowErrors, $validator->errors()->all());

            if (! empty($attrs['serial_number'])) {
                $serial = mb_strtolower($attrs['serial_number']);
                if (isset($existingSerials[$serial])) {
                    $rowErrors[] = "Serial number \"{$attrs['serial_number']}\" is already in the register";
                } elseif (isset($seenSerials[$serial])) {
                    $rowErrors[] = "Serial number \"{$attrs['serial_number']}\" is repeated (also on row {$seenSerials[$serial]})";
                }
                $seenSerials[$serial] ??= $rowNumber;
            }

            if (isset($attrs['salvage_value'], $attrs['purchase_cost']) && $attrs['salvage_value'] > $attrs['purchase_cost']) {
                $rowErrors[] = 'Salvage Value is more than Purchase Cost';
            }

            if ($rowErrors) {
                $errors[] = "Row {$rowNumber}: ".implode('; ', array_unique($rowErrors)).'.';
            } else {
                $attrs['revaluation_cycle_years'] ??= 3;
                $assets->push($attrs);
            }
        }

        if (! $errors && $assets->isEmpty()) {
            $errors[] = 'The file has no assets in it — fill the "Assets" sheet from row 2.';
        }

        return [$assets, $errors];
    }

    // Heading text (template or close variants like "serial_number") → column index.
    private function mapHeadings(array $headings): array
    {
        $normalise = fn ($h) => preg_replace('/[^a-z]/', '', mb_strtolower((string) $h));
        $lookup = [];
        foreach (self::COLUMNS as $key => [$heading]) {
            $lookup[$normalise($heading)] = $key;
            $lookup[$normalise($key)] = $key;
        }
        $lookup['usefullife'] = 'useful_life_years';
        $lookup['revaluationcycle'] = 'revaluation_cycle_years';
        $lookup['supplier'] = 'supplier_name';

        $map = [];
        foreach ($headings as $index => $heading) {
            $key = $lookup[$normalise($heading)] ?? null;
            if ($key && ! isset($map[$key])) {
                $map[$key] = $index;
            }
        }

        return $map;
    }

    private function toDate(mixed $value): ?string
    {
        if (is_numeric($value)) {
            // Excel stores dates as serial day numbers.
            return ($value > 0 && $value < 2958466) ? Carbon::instance(ExcelDate::excelToDateTimeObject($value))->toDateString() : null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'Y/m/d', 'd-m-Y', 'Y-m-d H:i:s'] as $format) {
            try {
                $date = Carbon::createFromFormat("!{$format}", (string) $value);
            } catch (\Throwable) {
                continue;
            }
            // Round-trip check rejects overflow like 2025-02-31.
            if ($date && $date->format($format) === (string) $value) {
                return $date->toDateString();
            }
        }

        return null;
    }

    private function letter(int $index): string
    {
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index);
    }
}
