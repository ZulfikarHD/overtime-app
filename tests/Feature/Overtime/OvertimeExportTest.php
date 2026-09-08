<?php

use App\Models\CapexProject;
use App\Models\Department;
use App\Models\Employee;
use App\Models\OperationalCalendar;
use App\Models\OvertimeItem;
use App\Models\OvertimeSubmission;
use App\Models\Section;
use App\Models\SpklDocument;
use App\Models\User;
use App\Services\OvertimeExportService;
use Carbon\Carbon;

function ensureExportCalendar(string $date = '2026-09-08'): void
{
    if (! OperationalCalendar::whereDate('calendar_date', $date)->exists()) {
        OperationalCalendar::create([
            'calendar_date' => $date,
            'day_type' => 'HKN',
            'is_holiday' => false,
        ]);
    }
}

function createExportFixture(array $submissionAttrs = [], array $itemAttrs = [], array $context = []): array
{
    $date = $submissionAttrs['operational_date'] ?? '2026-09-08';
    ensureExportCalendar($date);

    $department = $context['department'] ?? Department::factory()->create([
        'code' => 'DEPT_EXP_'.uniqid(),
        'name' => 'Stamping Export Dept',
        'is_active' => true,
    ]);

    $section = $context['section'] ?? Section::factory()->create([
        'department_id' => $department->id,
        'code' => 'SEC_EXP_'.uniqid(),
        'name' => 'Press Line Export',
        'is_active' => true,
    ]);

    $teamLeader = $context['teamLeader'] ?? User::factory()->teamLeader($section->id, $department->id)->create();
    $manager = $context['manager'] ?? User::factory()->manager($department->id)->create();

    $employee = $context['employee'] ?? Employee::factory()->forDepartmentAndSection($department, $section)->create([
        'full_name' => 'Budi Export Operator',
        'npk' => 'NPK-'.fake()->unique()->numerify('#####'),
        'hourly_rate' => 40000,
        'is_active' => true,
    ]);

    $capex = CapexProject::create([
        'project_code' => 'CAPEX-EXP-'.uniqid(),
        'asset_code' => 'AST-EXP',
        'name' => 'Press Tooling Upgrade',
        'department_id' => $department->id,
        'allocated_labor_hours' => 150,
        'allocated_labor_budget_idr' => 7500000,
        'physical_progress_pct' => 10,
        'status' => 'ACTIVE',
        'start_date' => $date,
        'target_end_date' => Carbon::parse($date)->addMonths(3)->toDateString(),
    ]);

    $submission = OvertimeSubmission::create(array_merge([
        'submission_code' => 'OT-'.str_replace('-', '', $date).'-EXP-'.uniqid(),
        'submission_date' => $date,
        'operational_date' => $date,
        'day_type' => 'HKN',
        'department_id' => $department->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $teamLeader->id,
        'status' => 'SUBMITTED',
        'total_hours_cached' => 4.0,
    ], $submissionAttrs));

    $hoursProd = (float) ($itemAttrs['hours_production'] ?? 2.0);
    $hoursTpm = (float) ($itemAttrs['hours_tpm'] ?? 1.0);
    $hoursProject = (float) ($itemAttrs['hours_project'] ?? 1.0);
    $hoursOthers = (float) ($itemAttrs['hours_others'] ?? 0.0);
    $totalHours = $hoursProd + $hoursTpm + $hoursProject + $hoursOthers;
    $rate = 40000.0;

    $item = OvertimeItem::create(array_merge([
        'overtime_submission_id' => $submission->id,
        'employee_id' => $employee->id,
        'npk_snapshot' => $employee->npk,
        'capex_project_id' => $capex->id,
        'hours_production' => $hoursProd,
        'hours_tpm' => $hoursTpm,
        'hours_project' => $hoursProject,
        'hours_others' => $hoursOthers,
        'hourly_rate_snapshot' => $rate,
        'total_cost_snapshot' => $totalHours * $rate,
        'status' => 'PENDING',
        'task_description' => 'Press machine calibration',
        'rca_category' => 'MACHINE_BREAKDOWN',
        'lock_version' => 1,
    ], $itemAttrs));

    return compact('department', 'section', 'teamLeader', 'manager', 'employee', 'capex', 'submission', 'item');
}

test('manager can export csv scoped strictly to their department with all 19 columns', function () {
    $fixture1 = createExportFixture();
    $manager1 = $fixture1['manager'];

    // Create a second department and submission with a distinct employee name
    $fixture2 = createExportFixture([], [], [
        'employee' => Employee::factory()->create([
            'full_name' => 'Siti Foreign Operator',
            'hourly_rate' => 42000,
            'is_active' => true,
        ]),
    ]);

    $response = $this->actingAs($manager1)->get('/overtime/approvals/export?format=csv');

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $content = $response->streamedContent();

    // Contains UTF-8 BOM
    $bom = chr(0xEF).chr(0xBB).chr(0xBF);
    expect(str_starts_with($content, $bom))->toBeTrue();

    // Check headings
    $cleanContent = substr($content, strlen($bom));
    $lines = explode("\n", trim($cleanContent));
    $headerLine = str_getcsv($lines[0]);

    expect($headerLine)->toBe(OvertimeExportService::HEADINGS);
    expect(count($headerLine))->toBe(19);

    // Manager 1 should only see their department's submission and employee
    expect($content)->toContain($fixture1['submission']->submission_code);
    expect($content)->toContain($fixture1['employee']->full_name);
    expect($content)->toContain($fixture1['department']->name);

    // Must NOT contain other department's data
    expect($content)->not->toContain($fixture2['submission']->submission_code);
    expect($content)->not->toContain($fixture2['employee']->full_name);
});

test('manager cannot export records from another department via query param', function () {
    $fixture1 = createExportFixture();
    $manager1 = $fixture1['manager'];
    $fixture2 = createExportFixture();

    $response = $this->actingAs($manager1)
        ->get('/overtime/approvals/export?department_id='.$fixture2['department']->id);

    $response->assertStatus(403);
});

test('admin can export plant-wide or filtered by department', function () {
    $fixture1 = createExportFixture(['operational_date' => '2026-09-08']);
    $fixture2 = createExportFixture(['operational_date' => '2026-09-08']);

    $admin = User::factory()->admin()->create();

    // 1. Plant-wide export
    $responseAll = $this->actingAs($admin)->get('/overtime/approvals/export?format=csv&date_from=2026-09-01&date_to=2026-09-30');
    $responseAll->assertStatus(200);
    $contentAll = $responseAll->streamedContent();

    expect($contentAll)->toContain($fixture1['submission']->submission_code);
    expect($contentAll)->toContain($fixture2['submission']->submission_code);

    // 2. Department-scoped export for admin
    $responseDept1 = $this->actingAs($admin)->get('/overtime/approvals/export?format=csv&department_id='.$fixture1['department']->id);
    $responseDept1->assertStatus(200);
    $contentDept1 = $responseDept1->streamedContent();

    expect($contentDept1)->toContain($fixture1['submission']->submission_code);
    expect($contentDept1)->not->toContain($fixture2['submission']->submission_code);
});

test('export respects status, section, date range, and spkl filters', function () {
    $fixture = createExportFixture(['operational_date' => '2026-09-05'], ['status' => 'APPROVED']);
    $manager = $fixture['manager'];
    $dept = $fixture['department'];
    $sec = $fixture['section'];

    ensureExportCalendar('2026-09-08');

    // Another submission in same dept but different date and status
    $submission2 = OvertimeSubmission::create([
        'submission_code' => 'OT-20260908-SEC2-999',
        'submission_date' => '2026-09-08',
        'operational_date' => '2026-09-08',
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'submitted_by_user_id' => $fixture['teamLeader']->id,
        'status' => 'SUBMITTED',
        'total_hours_cached' => 3.0,
    ]);

    $item2 = OvertimeItem::create([
        'overtime_submission_id' => $submission2->id,
        'employee_id' => $fixture['employee']->id,
        'npk_snapshot' => $fixture['employee']->npk,
        'hours_production' => 3.0,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 40000,
        'total_cost_snapshot' => 120000,
        'status' => 'PENDING',
        'lock_version' => 1,
    ]);

    // Attach SPKL to submission 2
    SpklDocument::create([
        'overtime_submission_id' => $submission2->id,
        'spkl_number' => 'SPKL-TEST-002',
        'status' => 'ATTACHED',
        'due_date' => '2026-09-10',
    ]);

    // Filter by status=APPROVED and date range
    $response = $this->actingAs($manager)->get('/overtime/approvals/export?status=APPROVED&date_from=2026-09-01&date_to=2026-09-06');
    $response->assertStatus(200);
    $content = $response->streamedContent();

    expect($content)->toContain($fixture['submission']->submission_code);
    expect($content)->not->toContain($submission2->submission_code);

    // Filter by spkl_status=ATTACHED
    $spklResponse = $this->actingAs($manager)->get('/overtime/approvals/export?spkl_status=ATTACHED');
    $spklResponse->assertStatus(200);
    $spklContent = $spklResponse->streamedContent();

    expect($spklContent)->toContain($submission2->submission_code);
    expect($spklContent)->not->toContain($fixture['submission']->submission_code);
});

test('export logs audit trail entry in both export_logs and overtime_item_audits', function () {
    $fixture = createExportFixture();
    $manager = $fixture['manager'];

    $response = $this->actingAs($manager)->get('/overtime/approvals/export?format=csv');
    $response->assertStatus(200);

    // Check export_logs table
    $this->assertDatabaseHas('export_logs', [
        'actor_user_id' => $manager->id,
        'resource_type' => 'overtime_approvals',
        'format' => 'csv',
    ]);

    // Check overtime_item_audits table
    $this->assertDatabaseHas('overtime_item_audits', [
        'action' => 'EXPORT',
        'actor_user_id' => $manager->id,
    ]);
});

test('export supports xlsx format and produces valid openxml spreadsheet', function () {
    $fixture = createExportFixture();
    $manager = $fixture['manager'];

    $response = $this->actingAs($manager)->get('/overtime/approvals/export?format=xlsx');

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    // Binary response file can be inspected as a zip archive
    $filePath = $response->getFile()->getPathname();

    $zip = new ZipArchive;
    $openRes = $zip->open($filePath);
    expect($openRes)->toBeTrue();

    // Verify key OpenXML structure files exist inside the generated .xlsx
    expect($zip->locateName('[Content_Types].xml'))->not->toBeFalse();
    expect($zip->locateName('xl/workbook.xml'))->not->toBeFalse();
    expect($zip->locateName('xl/worksheets/sheet1.xml'))->not->toBeFalse();
    expect($zip->locateName('xl/styles.xml'))->not->toBeFalse();

    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    expect($sheetXml)->toContain($fixture['submission']->submission_code);
    expect($sheetXml)->toContain('submission_code');
    expect($sheetXml)->toContain('spkl_status');

    $zip->close();
});

test('unauthenticated or unauthorized users are forbidden from exporting', function () {
    $fixture = createExportFixture();
    $teamLeader = $fixture['teamLeader'];

    // Unauthenticated
    $this->get('/overtime/approvals/export')
        ->assertRedirect('/login');

    // Team Leader cannot access approvals export
    $this->actingAs($teamLeader)
        ->get('/overtime/approvals/export')
        ->assertStatus(403);
});
