<?php

use App\Actions\Overtime\ImportSplExcelAction;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Section;
use App\Models\SplEntry;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

// -------------------------------------------------------
// Authorization
// -------------------------------------------------------

test('guest is redirected to login for SPL routes', function () {
    $this->get(route('overtime.spl.index'))->assertRedirect(route('login'));
    $this->post(route('overtime.spl.import'), [])->assertRedirect(route('login'));
});

test('team_leader cannot access SPL import (gate enforced)', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $leader = User::factory()->teamLeader($section->id, $dept->id)->create();

    $this->actingAs($leader)->get(route('overtime.spl.index'))->assertForbidden();
    $this->actingAs($leader)->post(route('overtime.spl.import'), [])->assertForbidden();
});

test('admin can view SPL import index page', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('overtime.spl.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('overtime/SplImport')
            ->has('entries')
            ->has('current_year')
            ->has('current_month'),
        );
});

// -------------------------------------------------------
// ImportSplExcelAction unit tests
// -------------------------------------------------------

/**
 * Build a minimal SPL Excel file in memory following the template layout.
 * Sheet name = day number (1-31), data starts at row 13.
 */
function makeSplExcel(array $sheetData): string
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->removeSheetByIndex(0); // remove default

    foreach ($sheetData as $sheetName => $rows) {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle((string) $sheetName);

        // Header row at row 11
        $sheet->setCellValue('A11', 'NO');
        $sheet->setCellValue('B11', 'NAMA KARYAWAN');
        $sheet->setCellValue('C11', 'NPK/SAP');
        $sheet->setCellValue('D11', 'KODE HARI (*)');
        $sheet->setCellValue('E11', 'MULAI');
        $sheet->setCellValue('F11', 'SELESAI');
        $sheet->setCellValue('G11', 'TOTAL (JAM)');
        $sheet->setCellValue('H11', 'JENIS PEKERJAAN');
        $sheet->setCellValue('J11', 'TYPE OT');
        $sheet->setCellValue('K11', 'KETERANGAN LEMBUR');
        $sheet->setCellValue('L11', 'Description');
        $sheet->setCellValue('M11', 'Action');

        foreach ($rows as $i => $row) {
            $excelRow = 13 + $i;
            $sheet->setCellValue("A{$excelRow}", $row[0] ?? null); // NO
            $sheet->setCellValue("B{$excelRow}", $row[1] ?? null); // NAMA
            $sheet->setCellValue("C{$excelRow}", $row[2] ?? null); // NPK
            $sheet->setCellValue("D{$excelRow}", $row[3] ?? null); // KODE HARI
            $sheet->setCellValue("E{$excelRow}", $row[4] ?? null); // MULAI
            $sheet->setCellValue("F{$excelRow}", $row[5] ?? null); // SELESAI
            $sheet->setCellValue("G{$excelRow}", $row[6] ?? null); // TOTAL JAM
            $sheet->setCellValue("H{$excelRow}", $row[7] ?? null); // JENIS PEKERJAAN
            $sheet->setCellValue("J{$excelRow}", $row[8] ?? null); // TYPE OT
            $sheet->setCellValue("K{$excelRow}", $row[9] ?? null); // KETERANGAN
        }
    }

    $tmpPath = tempnam(sys_get_temp_dir(), 'spl_test_').'.xlsx';
    $writer = new XlsxWriter($spreadsheet);
    $writer->save($tmpPath);

    return $tmpPath;
}

test('ImportSplExcelAction imports rows correctly', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $employee = Employee::factory()->create([
        'npk' => '6740',
        'full_name' => 'ERVAN AJI',
        'section_id' => $section->id,
        'department_id' => $dept->id,
    ]);
    $admin = User::factory()->admin()->create();

    $tmpPath = makeSplExcel([
        '1' => [
            [1, 'ERVAN AJI', '6740', 'HLR', '07:30', '15:45', null, 'Support MTC', 67, null],
            [2, 'AGIT AGUSTIAN MAHENDRA', '58515', 'HLR', '07:30', '15:45', null, 'Support MTC', 67, null],
        ],
        '2' => [
            [1, 'ERVAN AJI', '6740', 'HKN', '16:30', '20:00', null, 'Pembuatan Rak', 67, null],
        ],
    ]);

    /** @var ImportSplExcelAction $action */
    $action = app(ImportSplExcelAction::class);
    $result = $action->execute($tmpPath, 2026, 9, $admin);

    @unlink($tmpPath);

    expect($result['imported'])->toBe(3);
    expect($result['updated'])->toBe(0);
    expect($result['errors'])->toBeEmpty();

    // Employee resolved correctly
    $entry = SplEntry::where('npk_snapshot', '6740')
        ->whereDate('realization_date', '2026-09-01')
        ->first();
    expect($entry)->not->toBeNull();
    expect($entry->employee_id)->toBe($employee->id);
    expect($entry->day_type)->toBe('HLR');
    expect($entry->type_ot_code)->toBe(67);
});

test('ImportSplExcelAction upserts on duplicate npk+date+start_time', function () {
    $admin = User::factory()->admin()->create();

    $tmpPath = makeSplExcel([
        '5' => [
            [1, 'JOHN DOE', '99999', 'HKN', '16:30', '20:00', null, 'First job', 61, null],
        ],
    ]);

    /** @var ImportSplExcelAction $action */
    $action = app(ImportSplExcelAction::class);
    $action->execute($tmpPath, 2026, 9, $admin);

    // Create updated file — same npk+date+start but different jenis pekerjaan
    $tmpPath2 = makeSplExcel([
        '5' => [
            [1, 'JOHN DOE', '99999', 'HKN', '16:30', '20:00', null, 'Updated job', 61, null],
        ],
    ]);

    $result = $action->execute($tmpPath2, 2026, 9, $admin);

    @unlink($tmpPath);
    @unlink($tmpPath2);

    expect($result['imported'])->toBe(0);
    expect($result['updated'])->toBe(1);

    $this->assertDatabaseHas('spl_entries', [
        'npk_snapshot' => '99999',
        'jenis_pekerjaan' => 'Updated job',
    ]);
    $updated = SplEntry::where('npk_snapshot', '99999')
        ->whereDate('realization_date', '2026-09-05')
        ->first();
    expect($updated)->not->toBeNull();
    expect($updated->jenis_pekerjaan)->toBe('Updated job');
});

test('ImportSplExcelAction skips empty rows gracefully', function () {
    $admin = User::factory()->admin()->create();

    $tmpPath = makeSplExcel([
        '3' => [
            [1, 'VALID NAME', '11111', 'HKN', '16:30', '19:00', null, 'Work', 61, null],
            [2, null, null, null, null, null, null, null, null, null], // empty row
            [3, '', '', '', '07:30', '10:00', null, 'Work 2', 62, null], // empty name+npk
        ],
    ]);

    /** @var ImportSplExcelAction $action */
    $action = app(ImportSplExcelAction::class);
    $result = $action->execute($tmpPath, 2026, 9, $admin);

    @unlink($tmpPath);

    // Only 1 valid row was imported
    expect($result['imported'])->toBe(1);
});

// -------------------------------------------------------
// Controller upload integration
// -------------------------------------------------------

test('admin can upload valid SPL excel and see import summary', function () {
    $admin = User::factory()->admin()->create();

    // Mock the action so the controller test focuses on routing/auth,
    // not on PhpSpreadsheet file-system behaviour in the test env.
    $this->mock(ImportSplExcelAction::class, function ($mock) {
        $mock->shouldReceive('execute')
            ->once()
            ->andReturn(['imported' => 2, 'updated' => 0, 'skipped' => 0, 'errors' => []]);
    });

    $file = UploadedFile::fake()->create('spl_manual_ot.xlsx', 10, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $this->actingAs($admin)
        ->post(route('overtime.spl.import'), [
            'file' => $file,
            'fiscal_year' => 2026,
            'fiscal_month' => 9,
        ])
        ->assertRedirect();
});

test('manager can also upload SPL files', function () {
    $dept = Department::factory()->create();
    Section::factory()->create(['department_id' => $dept->id]);
    $manager = User::factory()->manager($dept->id)->create();

    $this->mock(ImportSplExcelAction::class, function ($mock) {
        $mock->shouldReceive('execute')
            ->once()
            ->andReturn(['imported' => 1, 'updated' => 0, 'skipped' => 0, 'errors' => []]);
    });

    $file = UploadedFile::fake()->create('spl_manual_ot.xlsx', 10, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $this->actingAs($manager)
        ->post(route('overtime.spl.import'), [
            'file' => $file,
            'fiscal_year' => 2026,
            'fiscal_month' => 9,
        ])
        ->assertRedirect();
});

test('spl import rejects non-excel files', function () {
    $admin = User::factory()->admin()->create();
    $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    $this->actingAs($admin)
        ->post(route('overtime.spl.import'), [
            'file' => $file,
            'fiscal_year' => 2026,
            'fiscal_month' => 9,
        ])
        ->assertSessionHasErrors('file');
});

test('spl import requires fiscal_year and fiscal_month', function () {
    $admin = User::factory()->admin()->create();
    $file = UploadedFile::fake()->create('spl.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $this->actingAs($admin)
        ->post(route('overtime.spl.import'), [
            'file' => $file,
            // missing fiscal_year and fiscal_month
        ])
        ->assertSessionHasErrors(['fiscal_year', 'fiscal_month']);
});

// -------------------------------------------------------
// SPL entry deletion
// -------------------------------------------------------

test('admin can delete an SPL entry', function () {
    $admin = User::factory()->admin()->create();
    $entry = SplEntry::factory()->create([
        'imported_by_user_id' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->deleteJson(route('overtime.spl.destroy', $entry))
        ->assertOk();

    $this->assertDatabaseMissing('spl_entries', ['id' => $entry->id]);
});

test('non-admin cannot delete SPL entries', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $manager = User::factory()->manager($dept->id)->create();
    $admin = User::factory()->admin()->create();

    $entry = SplEntry::factory()->create([
        'imported_by_user_id' => $admin->id,
    ]);

    $this->actingAs($manager)
        ->deleteJson(route('overtime.spl.destroy', $entry))
        ->assertForbidden();
});
