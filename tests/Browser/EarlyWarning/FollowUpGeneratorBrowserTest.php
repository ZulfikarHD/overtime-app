<?php

use App\Models\AlertCausePicMapping;
use App\Models\AlertFollowUp;
use App\Models\Department;
use App\Models\Employee;
use App\Models\OvertimeBudget;
use App\Models\OvertimeItem;
use App\Models\OvertimeSubmission;
use App\Models\PolicyThreshold;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Today's Merah alert with excess hours: a 10 h/day pacing budget, 24 h x the day of the month approved from two
 * employees (all Production, Rp 50.000/h) = 240 % month to date, 14 h x the day of the month above the pace.
 */
function generatorBrowserSeedMerahToday(Section $section): string
{
    $today = Carbon::now('Asia/Jakarta')->startOfDay();

    OvertimeBudget::factory()->forSection($section)->create([
        'fiscal_year' => $today->year,
        'fiscal_month' => $today->month,
        'planned_hours' => $today->daysInMonth * 10.0,
    ]);

    $submission = OvertimeSubmission::factory()->create([
        'department_id' => $section->department_id,
        'section_id' => $section->id,
        'operational_date' => $today->toDateString(),
        'submission_date' => $today->toDateString(),
        'day_type' => 'HKN',
    ]);

    foreach (range(1, 2) as $position) {
        OvertimeItem::factory()
            ->forEmployee(Employee::factory()->create(['hourly_rate' => 50000]))
            ->approved(User::factory()->admin()->create())
            ->create([
                'overtime_submission_id' => $submission->id,
                'hours_production' => 12.0 * $today->day,
                'hours_tpm' => 0,
                'hours_project' => 0,
                'hours_others' => 0,
                'hourly_rate_snapshot' => 50000,
                'total_cost_snapshot' => 600000 * $today->day,
            ]);
    }

    return $today->toDateString();
}

function generatorBrowserLogin(string $email): mixed
{
    return visit('/login')
        ->fill('email', $email)
        ->fill('password', 'password')
        ->click('[data-test="login-button"]')
        ->assertPathIs('/dashboard');
}

beforeEach(function () {
    PolicyThreshold::factory()->plantDefault()->create();

    $this->dept = Department::factory()->create(['name' => 'Assembly Generator Dept', 'is_active' => true]);
    $this->section = Section::factory()->create(['department_id' => $this->dept->id, 'name' => 'Line Generator', 'is_active' => true]);

    User::factory()->manager($this->dept->id)->create(['email' => 'manager.generator@factory.com', 'password' => 'password']);
});

test('an alert opens with all seven fields written by the system, edits are tagged and reset, and it can be sent', function () {
    $today = generatorBrowserSeedMerahToday($this->section);
    // Hours above the month-to-date pace: 24 h x D booked against 10 h x D expected on day D of the month.
    $aboveThePace = 14 * Carbon::now('Asia/Jakarta')->day;

    generatorBrowserLogin('manager.generator@factory.com')
        ->navigate("/early-warning/{$this->section->id}/{$today}")
        ->assertValue('[data-test="followup-why"]', 'Category Production holds 100.0% of the overtime hours.')
        ->assertValue('[data-test="followup-hours"]', (string) $aboveThePace)
        ->assertValue('[data-test="followup-cost"]', (string) ($aboveThePace * 50000))
        ->assertValue('[data-test="followup-who"]', (string) $this->dept->id)
        ->assertSeeIn('[data-test="followup-tag-what"]', 'System generated')
        ->assertSeeIn('[data-test="followup-tag-why"]', 'System generated')
        ->assertSeeIn('[data-test="followup-tag-when"]', 'System generated')
        ->assertSeeIn('[data-test="followup-tag-where"]', 'System generated')
        ->assertSeeIn('[data-test="followup-tag-who"]', 'System generated')
        ->assertSeeIn('[data-test="followup-tag-how"]', 'System generated')
        ->assertSeeIn('[data-test="followup-tag-cost"]', 'System generated')
        ->assertSeeIn('[data-test="followup-tag-hours"]', 'System generated')
        ->assertPresent('[data-test="followup-when-hint"]')
        ->fill('[data-test="followup-why"]', 'Ditulis ulang oleh manager')
        ->assertSeeIn('[data-test="followup-tag-why"]', 'Edited')
        ->click('[data-test="followup-reset-why"]')
        ->assertSeeIn('[data-test="followup-tag-why"]', 'System generated')
        ->assertValue('[data-test="followup-why"]', 'Category Production holds 100.0% of the overtime hours.')
        ->click('[data-test="followup-send"]')
        ->assertSeeIn('[data-test="follow-up-status-pill"]', 'Sent')
        ->assertDisabled('[data-test="followup-why"]')
        ->assertNoJavaScriptErrors();

    $followUp = AlertFollowUp::query()->sole();

    expect($followUp->status->value)->toBe('sent')
        ->and($followUp->why_text)->toBe('Category Production holds 100.0% of the overtime hours.')
        ->and($followUp->generated_payload['primary_cause'])->toBe('CATEGORY_DOMINANT')
        ->and($followUp->how_much_cost_idr)->toBe($aboveThePace * 50000);
});

test('an admin picks the PIC department of a cause in Administration and the next suggestion uses it', function () {
    $today = generatorBrowserSeedMerahToday($this->section);
    $pic = Department::factory()->create(['name' => 'Maintenance Generator Dept', 'is_active' => true]);
    User::factory()->manager($pic->id)->create();
    User::factory()->admin()->create(['email' => 'admin.generator@factory.com', 'password' => 'password']);

    generatorBrowserLogin('admin.generator@factory.com')
        ->navigate('/admin/administration?tab=policies')
        ->assertPresent('[data-test="alert-cause-pic-card"]')
        ->assertSee('PIC per Cause')
        ->select('[data-test="cause-pic-CATEGORY_DOMINANT"]', (string) $pic->id)
        ->click('[data-test="cause-pic-save"]')
        ->assertSee('PIC per cause saved. It applies to new suggestions only.')
        ->navigate("/early-warning/{$this->section->id}/{$today}")
        ->assertValue('[data-test="followup-who"]', (string) $pic->id)
        ->assertNoJavaScriptErrors();

    expect(AlertCausePicMapping::query()->where('cause', 'CATEGORY_DOMINANT')->value('pic_department_id'))->toBe($pic->id);
});
