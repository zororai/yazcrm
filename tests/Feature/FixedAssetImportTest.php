<?php

namespace Tests\Feature;

use App\Models\AssetCategory;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\FixedAsset;
use App\Models\FixedAssetActivityLog;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class FixedAssetImportTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private AssetCategory $laptops;
    private Department $finance;
    private Location $hq;
    private Location $field;

    private const HEADINGS = [
        'Asset Number *', 'Name *', 'Category', 'Description', 'Manufacturer', 'Model', 'Serial Number', 'Purchase Date', 'Purchase Cost',
        'Useful Life (years)', 'Salvage Value', 'Revaluation Cycle (years)', 'Supplier', 'Warranty Start',
        'Warranty Expiry', 'Condition', 'Department', 'Asset Location *', 'Issued Location',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = User::create(['name' => 'Stores', 'email' => 'stores@example.test', 'password' => bcrypt('x'), 'role' => 'stores']);
        $this->laptops = AssetCategory::forceCreate(['name' => 'Laptops']);
        $this->finance = Department::forceCreate(['code' => 'FIN', 'name' => 'Finance']);
        $this->hq      = Location::forceCreate(['name' => 'Head Office']);
        $this->field   = Location::forceCreate(['name' => 'Field Office']);
    }

    private function xlsx(array $rows, array $headings = self::HEADINGS): UploadedFile
    {
        $book = new Spreadsheet();
        $book->getActiveSheet()->fromArray(array_merge([$headings], $rows));
        $path = tempnam(sys_get_temp_dir(), 'fa').'.xlsx';
        (new Xlsx($book))->save($path);

        return new UploadedFile($path, 'assets.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    // Fills a unique Asset Number and the Head Office asset location unless
    // the test sets them (pass '' to leave one blank). An empty array is a blank row.
    private function row(array $values): array
    {
        static $n = 0;
        if ($values !== []) {
            $values += ['Asset Number *' => 'YAZ-'.(++$n), 'Asset Location *' => 'Head Office'];
        }

        return array_map(fn ($h) => $values[$h] ?? null, self::HEADINGS);
    }

    public function test_valid_file_registers_every_asset_with_lookups_dates_and_defaults(): void
    {
        $file = $this->xlsx([
            $this->row([
                'Asset Number *' => 'YAZ/IT/0001', 'Name *' => 'Dell Laptop', 'Category' => 'laptops', 'Serial Number' => 'SN-1',
                'Purchase Date' => ExcelDate::PHPToExcel(new \DateTime('2025-01-31')), // real Excel date cell
                'Purchase Cost' => '1,250.00', 'Useful Life (years)' => 5, 'Salvage Value' => 100,
                'Warranty Expiry' => '31/01/2028', 'Department' => 'Finance',
                'Asset Location *' => 'HEAD OFFICE', 'Issued Location' => 'field office',
            ]),
            $this->row(['Asset Number *' => 'YAZ/FUR/0002', 'Name *' => 'Office Chair', 'Condition' => 'Fair', 'Revaluation Cycle (years)' => 2]),
            $this->row([]), // blank row is ignored
        ]);

        $this->actingAs($this->manager)->from('/fixed-assets')
            ->post('/fixed-assets/import', ['file' => $file])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Imported 2 asset(s): YAZ/IT/0001 – YAZ/FUR/0002.');

        $laptop = FixedAsset::where('name', 'Dell Laptop')->firstOrFail();
        $this->assertSame('YAZ/IT/0001', $laptop->asset_number); // the number from the file is kept
        $this->assertSame($this->laptops->id, $laptop->asset_category_id);
        $this->assertSame($this->finance->id, $laptop->department_id);
        $this->assertSame($this->hq->id, $laptop->home_location_id);
        $this->assertSame($this->field->id, $laptop->location_id);
        $this->assertSame('2025-01-31', $laptop->purchase_date->toDateString());
        $this->assertSame('2028-01-31', $laptop->warranty_expiry->toDateString());
        $this->assertEquals(1250, (float) $laptop->purchase_cost);
        $this->assertSame(3, $laptop->revaluation_cycle_years); // default
        $this->assertSame('good', $laptop->condition);           // default
        $this->assertSame('available', $laptop->status);

        $chair = FixedAsset::where('name', 'Office Chair')->firstOrFail();
        $this->assertSame($this->hq->id, $chair->home_location_id);
        $this->assertNull($chair->location_id); // not issued
        $this->assertSame('fair', $chair->condition);
        $this->assertSame(2, $chair->revaluation_cycle_years);

        $this->assertSame(2, FixedAssetActivityLog::where('action', 'created')->count());
        $this->assertStringContainsString('Imported 2 fixed asset(s) from "assets.xlsx"', AuditLog::latest('id')->value('description'));
    }

    public function test_any_bad_row_blocks_the_whole_import_and_lists_problems_by_row(): void
    {
        FixedAsset::forceCreate(['asset_number' => 'AST-000900', 'name' => 'Existing', 'serial_number' => 'SN-OLD', 'status' => 'available', 'created_by' => $this->manager->id]);

        $file = $this->xlsx([
            $this->row(['Name *' => 'Good row']),
            $this->row(['Name *' => 'Bad date', 'Purchase Date' => '2025-02-31']),
            $this->row(['Category' => 'Vehicles', 'Purchase Cost' => 'ten']),
            $this->row(['Name *' => 'Dup A', 'Serial Number' => 'SN-9']),
            $this->row(['Name *' => 'Dup B', 'Serial Number' => 'sn-9']),
            $this->row(['Name *' => 'Old serial', 'Serial Number' => 'SN-OLD', 'Useful Life (years)' => 2.5, 'Condition' => 'shiny']),
            $this->row(['Asset Number *' => '', 'Name *' => 'No number', 'Asset Location *' => '']),
            $this->row(['Asset Number *' => 'ast-000900', 'Name *' => 'Taken number', 'Asset Location *' => 'Moon Base']),
            $this->row(['Asset Number *' => 'DUP-1', 'Name *' => 'Dup number A']),
            $this->row(['Asset Number *' => 'dup-1', 'Name *' => 'Dup number B']),
        ]);

        $response = $this->actingAs($this->manager)->from('/fixed-assets')->post('/fixed-assets/import', ['file' => $file]);

        $errors = collect(session('errors')->getBag('default')->all());
        $this->assertSame(1, FixedAsset::count()); // only the pre-existing asset
        $response->assertSessionMissing('success');

        $this->assertTrue($errors->contains(fn ($e) => str_starts_with($e, 'Row 3:') && str_contains($e, 'Purchase Date "2025-02-31" is not a valid date')));
        $this->assertTrue($errors->contains(fn ($e) => str_starts_with($e, 'Row 4:') && str_contains($e, 'Name is required')
            && str_contains($e, 'Category "Vehicles" doesn\'t exist') && str_contains($e, 'Purchase Cost "ten" is not a number')));
        $this->assertTrue($errors->contains(fn ($e) => str_starts_with($e, 'Row 6:') && str_contains($e, 'repeated (also on row 5)')));
        $this->assertTrue($errors->contains(fn ($e) => str_starts_with($e, 'Row 7:') && str_contains($e, 'already in the register')
            && str_contains($e, 'Useful Life must be a whole number') && str_contains($e, 'Condition must be one of')));
        $this->assertFalse($errors->contains(fn ($e) => str_starts_with($e, 'Row 2:')));

        $this->assertTrue($errors->contains(fn ($e) => str_starts_with($e, 'Row 8:') && str_contains($e, 'Asset Number is required') && str_contains($e, 'Asset Location is required')));
        $this->assertTrue($errors->contains(fn ($e) => str_starts_with($e, 'Row 9:') && str_contains($e, 'Asset Number "ast-000900" is already in the register')
            && str_contains($e, 'Asset Location "Moon Base" doesn\'t exist')));
        $this->assertTrue($errors->contains(fn ($e) => str_starts_with($e, 'Row 11:') && str_contains($e, 'Asset Number "dup-1" is repeated (also on row 10)')));
    }

    public function test_downloaded_template_can_be_filled_in_and_uploaded(): void
    {
        $response = $this->actingAs($this->manager)->get('/fixed-assets/import-template');
        $response->assertOk();
        $this->assertStringContainsString('fixed-assets-import-template.xlsx', $response->headers->get('Content-Disposition'));

        $path = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
        file_put_contents($path, $response->streamedContent());
        $book = IOFactory::load($path);

        $this->assertSame(['Assets', 'Lists', 'Instructions'], $book->getSheetNames());
        $sheet = $book->getSheetByName('Assets');
        $this->assertSame('Asset Number *', $sheet->getCell('A1')->getValue());
        $this->assertSame('Asset Location *', $sheet->getCell('R1')->getValue());
        $this->assertSame('Issued Location', $sheet->getCell('S1')->getValue());
        $this->assertSame('Laptops', $book->getSheetByName('Lists')->getCell('A2')->getValue());
        $this->assertSame('list', $sheet->getCell('C5')->getDataValidation()->getType()); // category drop-down
        $this->assertSame('list', $sheet->getCell('R5')->getDataValidation()->getType()); // asset location drop-down
        $this->assertSame('list', $sheet->getCell('S5')->getDataValidation()->getType()); // issued location drop-down

        // Uploading it untouched is refused: the example row isn't a real asset.
        $this->actingAs($this->manager)->from('/fixed-assets')
            ->post('/fixed-assets/import', ['file' => new UploadedFile($path, 'untouched.xlsx', null, null, true)])
            ->assertSessionHasErrors(['rows.0' => "Row 2: this is the template's example row — replace it with a real asset or delete the row."]);
        $this->assertSame(0, FixedAsset::count());

        // Replace the example row with a real one, as a user would.
        $sheet->fromArray([$this->row(['Name *' => 'From template', 'Category' => 'Laptops', 'Purchase Date' => '2025-03-01'])], null, 'A2');
        (new Xlsx($book))->save($path);

        $this->actingAs($this->manager)->from('/fixed-assets')
            ->post('/fixed-assets/import', ['file' => new UploadedFile($path, 'filled.xlsx', null, null, true)])
            ->assertSessionHasNoErrors();

        $this->assertSame('2025-03-01', FixedAsset::where('name', 'From template')->firstOrFail()->purchase_date->toDateString());
    }

    public function test_csv_with_template_headings_also_imports(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'fa').'.csv';
        // "Location" on its own is read as the Asset Location.
        file_put_contents($path, "Asset Number,Name,Category,Purchase Date,Purchase Cost,Useful Life,Location\nPRN-1,Printer,Laptops,2024-12-01,450,4,Head Office\n");

        $this->actingAs($this->manager)->from('/fixed-assets')
            ->post('/fixed-assets/import', ['file' => new UploadedFile($path, 'assets.csv', 'text/csv', null, true)])
            ->assertSessionHasNoErrors();

        $printer = FixedAsset::where('name', 'Printer')->firstOrFail();
        $this->assertSame(4, $printer->useful_life_years);
        $this->assertSame('PRN-1', $printer->asset_number);
        $this->assertSame($this->hq->id, $printer->home_location_id);
        $this->assertSame($this->laptops->id, $printer->asset_category_id);
    }

    public function test_only_asset_managers_can_import(): void
    {
        $staff = User::create(['name' => 'Staff', 'email' => 'staff@example.test', 'password' => bcrypt('x'), 'role' => 'staff']);

        $this->actingAs($staff)->get('/fixed-assets/import-template')->assertForbidden();
        $this->actingAs($staff)->post('/fixed-assets/import', ['file' => $this->xlsx([$this->row(['Name *' => 'X'])])])->assertForbidden();
        $this->assertSame(0, FixedAsset::count());
    }
}
