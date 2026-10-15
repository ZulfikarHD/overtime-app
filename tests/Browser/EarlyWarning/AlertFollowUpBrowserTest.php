<?php

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
 * Creates today's Merah alert for the section: a 10 h/day pacing budget with 10 h approved = 100 %.
 */
function followUpBrowserSeedMerahToday(Section $section): string
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
    ]);

    OvertimeItem::factory()
        ->forEmployee(Employee::factory()->create())
        ->approved(User::factory()->admin()->create())
        ->create([
            'overtime_submission_id' => $submission->id,
            'hours_production' => 10.0,
            'hours_tpm' => 0,
            'hours_project' => 0,
            'hours_others' => 0,
        ]);

    return $today->toDateString();
}

function followUpBrowserLogin(string $email): mixed
{
    return visit('/login')
        ->fill('email', $email)
        ->fill('password', 'password')
        ->click('[data-test="login-button"]')
        ->assertPathIs('/dashboard');
}

beforeEach(function () {
    PolicyThreshold::factory()->plantDefault()->create();

    $this->dept = Department::factory()->create(['name' => 'Assembly Followup Dept', 'is_active' => true]);
    $this->pic = Department::factory()->create(['name' => 'PCD Followup Dept', 'is_active' => true]);
    $this->section = Section::factory()->create(['department_id' => $this->dept->id, 'name' => 'Line Followup', 'is_active' => true]);

    User::factory()->manager($this->pic->id)->create(['email' => 'pic.manager@factory.com']);
    User::factory()->manager($this->dept->id)->create(['email' => 'manager.followup@factory.com', 'password' => 'password']);
});

test('the form opens prefilled by the system, tags edits, resets them, then saves a draft and sends it', function () {
    $today = followUpBrowserSeedMerahToday($this->section);

    followUpBrowserLogin('manager.followup@factory.com')
        ->navigate("/early-warning/{$this->section->id}/{$today}")
        ->assertValue('[data-test="followup-where"]', 'Assembly Followup Dept — Line Followup')
        ->assertValue('[data-test="followup-when"]', $today)
        ->assertValue('[data-test="followup-who"]', (string) $this->dept->id)
        ->assertSeeIn('[data-test="followup-tag-what"]', 'System generated')
        ->assertSeeIn('[data-test="followup-tag-where"]', 'System generated')
        ->assertNotPresent('[data-test="followup-tag-why"]')
        ->fill('[data-test="followup-what"]', 'My own description of the problem')
        ->assertSeeIn('[data-test="followup-tag-what"]', 'Edited')
        ->click('[data-test="followup-reset-what"]')
        ->assertSeeIn('[data-test="followup-tag-what"]', 'System generated')
        ->fill('[data-test="followup-why"]', 'Volume produksi naik 20%')
        ->fill('[data-test="followup-how"]', 'Tambah 5 orang shift malam')
        ->select('[data-test="followup-who"]', (string) $this->pic->id)
        ->fill('[data-test="followup-cost"]', '7500000')
        ->assertSeeIn('[data-test="followup-cost-preview"]', 'Rp')
        ->click('[data-test="followup-save-draft"]')
        ->assertSeeIn('[data-test="followup-history"]', 'Draft')
        ->assertSeeIn('[data-test="follow-up-status-pill"]', 'Draft')
        ->assertNotPresent('[data-test="followup-new"]')
        ->click('[data-test="followup-send"]')
        ->assertSeeIn('[data-test="follow-up-status-pill"]', 'Sent')
        ->assertPresent('[data-test="followup-sent-note"]')
        ->assertDisabled('[data-test="followup-what"]')
        ->assertMissing('[data-test="followup-send"]')
        ->assertPresent('[data-test="followup-new"]')
        ->assertNoJavaScriptErrors();

    $followUp = AlertFollowUp::query()->sole();

    expect($followUp->status->value)->toBe('sent')
        ->and($followUp->section_id)->toBe($this->section->id)
        ->and($followUp->alert_date->toDateString())->toBe($today)
        ->and($followUp->generated_payload['what_text'])->toContain('Line Followup')
        ->and($followUp->how_much_cost_idr)->toBe(7500000);
});

test('Follow-up baru opens a fresh suggestion once the previous follow-up was sent', function () {
    $today = followUpBrowserSeedMerahToday($this->section);
    $sent = AlertFollowUp::factory()->forAlert($this->section, $today)->sent()->create(['what_text' => 'First sent follow-up']);

    followUpBrowserLogin('manager.followup@factory.com')
        ->navigate("/early-warning/{$this->section->id}/{$today}")
        ->assertValue('[data-test="followup-what"]', 'First sent follow-up')
        ->assertDisabled('[data-test="followup-what"]')
        ->click('[data-test="followup-new"]')
        ->assertSeeIn('[data-test="followup-tag-what"]', 'System generated')
        ->assertNotPresent('[data-test="followup-sent-note"]')
        ->assertPresent('[data-test="followup-send"]')
        ->click("[data-test=\"followup-history-item-{$sent->id}\"]")
        ->assertValue('[data-test="followup-what"]', 'First sent follow-up')
        ->assertNoJavaScriptErrors();
});

test('sending without the cause and plan shows validation feedback, saves nothing and keeps the form editable', function () {
    $today = followUpBrowserSeedMerahToday($this->section);

    followUpBrowserLogin('manager.followup@factory.com')
        ->navigate("/early-warning/{$this->section->id}/{$today}")
        ->click('[data-test="followup-send"]')
        ->assertSee('Explain the cause (Why) before sending.')
        ->assertSee('Describe the follow-up plan (How) before sending.')
        ->assertPresent('[data-test="followup-send"]')
        ->assertNoJavaScriptErrors();

    expect(AlertFollowUp::query()->count())->toBe(0);
});

test('a date that is not an alert shows no form to fill in', function () {
    $section = Section::factory()->create(['department_id' => $this->dept->id, 'name' => 'Painting Tenang', 'is_active' => true]);
    $today = Carbon::now('Asia/Jakarta')->toDateString();

    followUpBrowserLogin('manager.followup@factory.com')
        ->navigate("/early-warning/{$section->id}/{$today}")
        ->assertPresent('[data-test="alert-not-an-alert"]')
        ->assertSeeIn('[data-test="followup-empty"]', 'No follow-up has been recorded for this alert yet.')
        ->assertNotPresent('[data-test="followup-save-draft"]')
        ->assertNotPresent('[data-test="followup-send"]')
        ->assertNoJavaScriptErrors();
});

test('the dashboard no longer carries the follow-up panel', function () {
    followUpBrowserLogin('manager.followup@factory.com')
        ->click('[data-test="tab-distribution"]')
        ->assertNotPresent('[data-test="followup-panel"]')
        ->assertNoJavaScriptErrors();
});
