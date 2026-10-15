<?php

use App\Enums\AlertCause;
use App\Models\AlertCausePicMapping;
use App\Models\Department;
use App\Models\Employee;
use App\Models\MlForecastInput;
use App\Models\MlTrainingData;
use App\Models\OvertimeBudget;
use App\Models\OvertimeItem;
use App\Models\OvertimePlan;
use App\Models\OvertimePlanItem;
use App\Models\OvertimeSubmission;
use App\Models\PolicyThreshold;
use App\Models\Section;
use App\Models\User;
use App\Services\EarlyWarningFollowUpGenerator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/*
 * Today = Wed 14 Oct 2026. The index is month-to-date. A 31 h October budget expects 1 h per calendar day (12 h by
 * 12 Oct, 13 h by 13 Oct). The standard streak books 24 h on Mon 12 Oct and 24 h on Tue 13 Oct: 200 % and 369.2 %,
 * both Merah with the default 70 / 90 limits, while every earlier day has no hours and stays Hijau. The alert under
 * test is Tue 13 Oct (48 h booked, 35 h above the 13 h expected); Mon 12 Oct is its streak partner.
 * Category totals are split evenly by default so no category dominates unless a test asks for it.
 */

beforeEach(function () {
    app()->setLocale('en');
    $this->travelTo(Carbon::parse('2026-10-14 10:00:00', 'Asia/Jakarta'));

    PolicyThreshold::factory()->plantDefault()->create();

    $this->dept = Department::factory()->create(['is_active' => true]);
    $this->section = Section::factory()->create(['department_id' => $this->dept->id]);
    $this->manager = User::factory()->manager($this->dept->id)->create();
    $this->generator = app(EarlyWarningFollowUpGenerator::class);

    OvertimeBudget::factory()->forSection($this->section)->create([
        'fiscal_year' => 2026,
        'fiscal_month' => 10,
        'planned_hours' => 31.0,
    ]);
});

/**
 * Approved overtime of one employee on one day. `$hours` = [production, tpm, project, others].
 *
 * @param  array{0: float, 1: float, 2: float, 3: float}|null  $hours  null splits $total evenly
 */
function ewgenDay(Section $section, string $date, string $npk, ?array $hours = null, float $total = 0.0, float $rate = 50000.0, string $dayType = 'HKN'): void
{
    $hours ??= [$total / 4, $total / 4, $total / 4, $total / 4];
    $employee = Employee::query()->where('npk', $npk)->first()
        ?? Employee::factory()->create(['npk' => $npk, 'hourly_rate' => $rate]);

    $submission = OvertimeSubmission::factory()->create([
        'department_id' => $section->department_id,
        'section_id' => $section->id,
        'operational_date' => $date,
        'submission_date' => $date,
        'day_type' => $dayType,
    ]);

    OvertimeItem::factory()
        ->forEmployee($employee)
        ->approved(User::factory()->admin()->create())
        ->create([
            'overtime_submission_id' => $submission->id,
            'hours_production' => $hours[0],
            'hours_tpm' => $hours[1],
            'hours_project' => $hours[2],
            'hours_others' => $hours[3],
            'hourly_rate_snapshot' => $rate,
            'total_cost_snapshot' => round(array_sum($hours) * $rate, 2),
        ]);
}

/**
 * The standard two-day alert: both days Merah, 24 h/day from two employees, evenly split categories.
 */
function ewgenStreak(Section $section, float $perEmployee = 12.0): void
{
    foreach (['2026-10-12', '2026-10-13'] as $date) {
        ewgenDay($section, $date, 'EMP-A', total: $perEmployee);
        ewgenDay($section, $date, 'EMP-B', total: $perEmployee);
    }
}

function ewgenVolume(int $previousVolume, int $currentVolume, int $manpower = 800): void
{
    MlTrainingData::factory()->create(['year' => 2026, 'month' => 9, 'production_volume' => $previousVolume, 'man_power' => 800]);
    MlTrainingData::factory()->create(['year' => 2026, 'month' => 10, 'production_volume' => $currentVolume, 'man_power' => $manpower]);
}

function ewgenGenerate(): array
{
    return test()->generator->generate(test()->manager, test()->section, Carbon::parse('2026-10-13', 'Asia/Jakarta'));
}

test('a date that is not an alert has no suggestion', function () {
    ewgenDay($this->section, '2026-10-13', 'EMP-A', total: 3.0);

    expect($this->generator->generate($this->manager, $this->section, Carbon::parse('2026-10-13', 'Asia/Jakarta')))->toBeNull();
});

test('what states the zone, the index, the distance above the limit and the streak', function () {
    ewgenStreak($this->section);

    $suggestion = ewgenGenerate();

    expect($suggestion['what'])->toBe("Month-to-date index {$this->section->name} 369.2% (Red), 279.2% above the 90.0% limit, 2 consecutive days.")
        ->and($suggestion['where'])->toBe($this->dept->name.' — '.$this->section->name)
        ->and($suggestion['when_date'])->toBe('2026-10-13')
        ->and($suggestion['when_text'])->toBe('12–13 Oct 2026 (2 consecutive working days)');
});

test('manpower shortage is detected from volume up and manpower flat, with headcount and cost from the excess hours', function () {
    ewgenVolume(2000, 2400, 800);
    ewgenStreak($this->section, 24.0);

    $suggestion = ewgenGenerate();

    // 96 h booked by 13 Oct against 13 h expected = 83 h above the pace; / 7 days = 11.86 h/day; x 7 days = 83 h;
    // 11.86 / 8 h shift = 2 people; 83 h x Rp 50.000.
    expect($suggestion['primary_cause'])->toBe('MANPOWER_SHORTAGE')
        ->and($suggestion['supporting_causes'])->toBe([])
        ->and($suggestion['why'])->toBe('Production volume +20.0% (vs September 2026) while manpower is 0.0% (vs September 2026 (plant-wide)).')
        ->and($suggestion['how'])->toBe('Add 2 people for 7 working days to cover the excess of 11.9 hours per day (8 hours per shift). Estimated need: 83.0 overtime hours (Rp 4.150.000).')
        ->and($suggestion['excess_hours_per_day'])->toBe(11.86)
        ->and($suggestion['suggested_headcount'])->toBe(2)
        ->and($suggestion['how_much_hours'])->toBe(83.0)
        ->and($suggestion['how_much_cost_idr'])->toBe(4150000)
        ->and($suggestion['cost_note'])->toBeNull();
});

test('manpower shortage evidence says when the figures are forecast data and not actuals', function () {
    MlForecastInput::factory()->create(['year' => 2026, 'month' => 9, 'production_volume' => 2000, 'man_power' => 800]);
    MlForecastInput::factory()->create(['year' => 2026, 'month' => 10, 'production_volume' => 2400, 'man_power' => 800]);
    ewgenStreak($this->section, 24.0);

    $suggestion = ewgenGenerate();

    expect($suggestion['primary_cause'])->toBe('MANPOWER_SHORTAGE')
        ->and($suggestion['why'])->toBe('Production volume +20.0% (vs September 2026, forecast data) while manpower is 0.0% (vs September 2026 (plant-wide), forecast data).');
});

test('manpower shortage is skipped when volume or manpower is unknown, flat volume, or manpower grew', function (callable $seed) {
    $seed();
    ewgenStreak($this->section);

    expect(ewgenGenerate()['primary_cause'])->toBe('UNKNOWN');
})->with([
    'no monthly data at all' => [fn () => null],
    'previous volume is zero (N/A)' => [fn () => ewgenVolume(0, 2400)],
    'volume below the threshold' => [fn () => ewgenVolume(2000, 2100)],
    'manpower grew with the volume' => [fn () => ewgenVolume(2000, 2400, 900)],
]);

test('one dominant category is detected with its share', function () {
    foreach (['2026-10-12', '2026-10-13'] as $date) {
        ewgenDay($this->section, $date, 'EMP-A', [10.0, 2.5, 0.0, 0.0]);
        ewgenDay($this->section, $date, 'EMP-B', [10.0, 2.5, 0.0, 0.0]);
    }

    $suggestion = ewgenGenerate();

    expect($suggestion['primary_cause'])->toBe('CATEGORY_DOMINANT')
        ->and($suggestion['why'])->toBe('Category Production holds 80.0% of the overtime hours.')
        ->and($suggestion['how'])->toStartWith('Review the workload and scheduling of the Production category (80.0% of the overtime hours).');
});

test('overtime concentrated on the top three employees is detected with their NPK', function () {
    foreach (['2026-10-12', '2026-10-13'] as $date) {
        foreach (['EMP-A' => 6.0, 'EMP-B' => 5.0, 'EMP-C' => 4.0, 'EMP-D' => 1.0] as $npk => $total) {
            ewgenDay($this->section, $date, $npk, total: $total);
        }
    }

    $suggestion = ewgenGenerate();

    expect($suggestion['primary_cause'])->toBe('EMPLOYEE_CONCENTRATION')
        ->and($suggestion['why'])->toBe('The top 3 employees (EMP-A, EMP-B, EMP-C) hold 93.8% of the overtime hours.')
        ->and($suggestion['how'])->toStartWith('Redistribute the work across more employees; the top 3 employees hold 93.8% of the hours.');
});

test('concentration needs at least as many contributors as the top-N', function () {
    ewgenDay($this->section, '2026-10-12', 'EMP-A', total: 9.0);
    ewgenDay($this->section, '2026-10-13', 'EMP-A', total: 9.0);

    expect(ewgenGenerate()['primary_cause'])->toBe('UNKNOWN');
});

test('weekend and holiday overtime is detected from the HLR share of the period', function () {
    ewgenDay($this->section, '2026-10-10', 'EMP-A', total: 4.0, dayType: 'HLR');
    ewgenDay($this->section, '2026-10-10', 'EMP-B', total: 4.0, dayType: 'HLR');
    foreach (['2026-10-12', '2026-10-13'] as $date) {
        ewgenDay($this->section, $date, 'EMP-A', total: 4.0);
        ewgenDay($this->section, $date, 'EMP-B', total: 4.0);
    }

    $suggestion = ewgenGenerate();

    expect($suggestion['primary_cause'])->toBe('HOLIDAY_OVERTIME')
        ->and($suggestion['why'])->toBe('33.3% of the overtime hours were worked on weekends and holidays.')
        ->and($suggestion['how'])->toStartWith('Review weekend and holiday scheduling (33.3% of the hours fall on non-working days).');
});

test('overtime above the published plan is detected, a draft plan is ignored', function () {
    ewgenStreak($this->section, 4.0);

    $plan = OvertimePlan::factory()->create(['section_id' => $this->section->id, 'department_id' => $this->dept->id, 'fiscal_year' => 2026, 'fiscal_month' => 10]);
    OvertimePlanItem::factory()->create(['overtime_plan_id' => $plan->id, 'plan_date' => '2026-10-12', 'hours_production' => 6, 'hours_tpm' => 0, 'hours_project' => 0, 'hours_others' => 0]);

    expect(ewgenGenerate()['primary_cause'])->toBe('UNKNOWN');

    $plan->update(['status' => 'PUBLISHED']);

    $suggestion = ewgenGenerate();

    // 2 days x 8 h = 16 h actual in the period against 6 h planned.
    expect($suggestion['primary_cause'])->toBe('UNPLANNED_OVERTIME')
        ->and($suggestion['why'])->toBe('Actual overtime of 16.0 hours against 6.0 hours in the approved plan (+166.7%).')
        ->and($suggestion['how'])->toStartWith('Align the next overtime plan with the real demand (plan 6.0 hours, actual 16.0 hours).');
});

test('nothing detected gives the UNKNOWN cause instead of a guess', function () {
    ewgenStreak($this->section);

    $suggestion = ewgenGenerate();

    expect($suggestion['primary_cause'])->toBe('UNKNOWN')
        ->and($suggestion['supporting_causes'])->toBe([])
        ->and($suggestion['why'])->toBe('Cause not yet identified from the available data.')
        ->and($suggestion['how'])->toStartWith('Investigate the cause in this section.')
        ->and($suggestion['suggested_headcount'])->toBeNull();
});

test('the highest score is primary and the next two are supporting; the catalog order breaks a tie', function () {
    ewgenVolume(2000, 2400, 800);

    // Three people, everything in Production: category share 100 % and top-3 share 100 % tie at 1.0; manpower scores 0.1667.
    foreach (['2026-10-12', '2026-10-13'] as $date) {
        foreach (['EMP-A', 'EMP-B', 'EMP-C'] as $npk) {
            ewgenDay($this->section, $date, $npk, [8.0, 0.0, 0.0, 0.0]);
        }
    }

    $suggestion = ewgenGenerate();

    expect($suggestion['primary_cause'])->toBe('CATEGORY_DOMINANT')
        ->and($suggestion['supporting_causes'])->toBe(['EMPLOYEE_CONCENTRATION', 'MANPOWER_SHORTAGE'])
        ->and($suggestion['why'])->toBe('Category Production holds 100.0% of the overtime hours; The top 3 employees (EMP-A, EMP-B, EMP-C) hold 100.0% of the overtime hours; Production volume +20.0% (vs September 2026) while manpower is 0.0% (vs September 2026 (plant-wide)).')
        ->and(collect($suggestion['evidence'])->pluck('cause')->all())->toBe(['CATEGORY_DOMINANT', 'EMPLOYEE_CONCENTRATION', 'MANPOWER_SHORTAGE'])
        ->and($suggestion['evidence'][0]['values'])->toMatchArray(['category' => 'production', 'share_pct' => 100.0]);
});

test('every threshold comes from the configuration', function () {
    foreach (['2026-10-12', '2026-10-13'] as $date) {
        ewgenDay($this->section, $date, 'EMP-A', [10.0, 2.5, 0.0, 0.0]);
        ewgenDay($this->section, $date, 'EMP-B', [10.0, 2.5, 0.0, 0.0]);
    }

    config(['early_warning.thresholds.category_dominant_pct' => 90]);

    expect(ewgenGenerate()['primary_cause'])->toBe('UNKNOWN');
});

test('headcount is at least one and hours and cost are never negative when the month is below its pace', function () {
    ewgenVolume(2000, 2400, 800);
    // 10 h booked by 13 Oct against 13 h expected = 76.9 %: Kuning, but not above the pace.
    foreach (['2026-10-12', '2026-10-13'] as $date) {
        ewgenDay($this->section, $date, 'EMP-A', total: 2.5);
        ewgenDay($this->section, $date, 'EMP-B', total: 2.5);
    }

    $suggestion = ewgenGenerate();

    expect($suggestion['primary_cause'])->toBe('MANPOWER_SHORTAGE')
        ->and($suggestion['excess_hours_per_day'])->toBe(0.0)
        ->and($suggestion['suggested_headcount'])->toBe(1)
        ->and($suggestion['how_much_hours'])->toBe(0.0)
        ->and($suggestion['how_much_cost_idr'])->toBe(0)
        ->and($suggestion['how'])->toBe('Add 1 people for 7 working days to cover the excess of 0.0 hours per day (8 hours per shift).');
});

test('the cost falls back to the average hourly rate of the active employees of the section, then to zero with a note', function () {
    // Approved history carries no cost, so the employees of the section decide.
    foreach (['2026-10-12', '2026-10-13'] as $date) {
        foreach (['EMP-A', 'EMP-B'] as $npk) {
            ewgenDay($this->section, $date, $npk, total: 12.0, rate: 0.0);
        }
    }

    $withoutBasis = ewgenGenerate();

    expect($withoutBasis['how_much_hours'])->toBe(35.0)
        ->and($withoutBasis['how_much_cost_idr'])->toBe(0)
        ->and($withoutBasis['cost_note'])->toBe('Cost estimate not available yet');

    Employee::factory()->create(['section_id' => $this->section->id, 'department_id' => $this->dept->id, 'hourly_rate' => 40000]);
    Employee::factory()->create(['section_id' => $this->section->id, 'department_id' => $this->dept->id, 'hourly_rate' => null]);
    Employee::factory()->inactive()->create(['section_id' => $this->section->id, 'department_id' => $this->dept->id, 'hourly_rate' => 999999]);
    $this->dept->update(['default_hourly_rate' => 45000]);

    $withFallback = ewgenGenerate();

    expect($withFallback['how_much_cost_idr'])->toBe((int) round(35.0 * 42500))
        ->and($withFallback['cost_note'])->toBeNull();
});

test('who follows the admin mapping and falls back to the own department when the mapping cannot be used', function () {
    foreach (['2026-10-12', '2026-10-13'] as $date) {
        ewgenDay($this->section, $date, 'EMP-A', [10.0, 2.5, 0.0, 0.0]);
        ewgenDay($this->section, $date, 'EMP-B', [10.0, 2.5, 0.0, 0.0]);
    }

    $pic = Department::factory()->create(['is_active' => true]);
    User::factory()->manager($pic->id)->create();

    // No mapping row and no configured default for this cause: the own department.
    expect(ewgenGenerate()['who_department_id'])->toBe($this->dept->id);

    $mapping = AlertCausePicMapping::factory()->forCause(AlertCause::CategoryDominant)->create(['pic_department_id' => $pic->id]);
    expect(ewgenGenerate()['who_department_id'])->toBe($pic->id);

    $mapping->update(['is_active' => false]);
    expect(ewgenGenerate()['who_department_id'])->toBe($this->dept->id);

    $mapping->update(['is_active' => true]);
    $pic->update(['is_active' => false]);
    expect(ewgenGenerate()['who_department_id'])->toBe($this->dept->id);

    $pic->update(['is_active' => true]);
    $mapping->update(['pic_department_id' => null]);
    expect(ewgenGenerate()['who_department_id'])->toBe($this->dept->id);
});

test('who skips a mapped department without active recipients when the own department has some', function () {
    foreach (['2026-10-12', '2026-10-13'] as $date) {
        ewgenDay($this->section, $date, 'EMP-A', [10.0, 2.5, 0.0, 0.0]);
        ewgenDay($this->section, $date, 'EMP-B', [10.0, 2.5, 0.0, 0.0]);
    }

    $emptyPic = Department::factory()->create(['is_active' => true]);
    AlertCausePicMapping::factory()->forCause(AlertCause::CategoryDominant)->create(['pic_department_id' => $emptyPic->id]);

    expect(ewgenGenerate()['who_department_id'])->toBe($this->dept->id);

    // Nobody to notify anywhere: the mapped department is still proposed and the send validation explains it.
    $this->manager->update(['is_active' => false]);

    expect(ewgenGenerate()['who_department_id'])->toBe($emptyPic->id);
});

test('the configured default PIC applies until an admin saves a mapping for the cause', function () {
    ewgenVolume(2000, 2400, 800);
    ewgenStreak($this->section);

    $hcm = Department::factory()->create(['code' => 'DEPT_HCM', 'is_active' => true]);
    User::factory()->manager($hcm->id)->create();

    expect(ewgenGenerate()['who_department_id'])->toBe($hcm->id);

    AlertCausePicMapping::factory()->forCause(AlertCause::ManpowerShortage)->create(['pic_department_id' => null]);

    expect(ewgenGenerate()['who_department_id'])->toBe($this->dept->id);
});

test('the text follows the application locale in Indonesian and English', function () {
    ewgenVolume(2000, 2400, 800);
    ewgenStreak($this->section, 24.0);

    app()->setLocale('id');
    $indonesian = ewgenGenerate();

    app()->setLocale('en');
    $english = ewgenGenerate();

    expect($indonesian['what'])->toContain('Indeks bulan berjalan')->toContain('738,5%')
        ->and($indonesian['when_text'])->toBe('12–13 Okt 2026 (2 hari kerja berturut-turut)')
        ->and($indonesian['why'])->toContain('Volume produksi +20,0% (vs September 2026)')
        ->and($indonesian['how'])->toContain('Tambah 2 orang selama 7 hari kerja')->toContain('Rp 4.150.000')
        ->and($english['what'])->toContain('Month-to-date index')->toContain('738.5%')
        ->and($english['when_text'])->toBe('12–13 Oct 2026 (2 consecutive working days)');
});

test('the same input always gives the same output', function () {
    ewgenVolume(2000, 2400, 800);
    ewgenStreak($this->section);

    expect(ewgenGenerate())->toBe(ewgenGenerate());
});

test('generation never reads another department or an operator', function () {
    ewgenStreak($this->section);

    $other = User::factory()->manager(Department::factory()->create()->id)->create();
    $operator = User::factory()->user()->create(['department_id' => $this->dept->id]);

    expect(fn () => $this->generator->generate($other, $this->section, Carbon::parse('2026-10-13', 'Asia/Jakarta')))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => $this->generator->generate($operator, $this->section, Carbon::parse('2026-10-13', 'Asia/Jakarta')))
        ->toThrow(AuthorizationException::class);
});

test('generation runs in a constant number of queries however much data exists', function () {
    ewgenVolume(2000, 2400, 800);
    ewgenStreak($this->section);

    DB::flushQueryLog();
    DB::enableQueryLog();
    ewgenGenerate();
    $small = count(DB::getQueryLog());

    // Older history outside the judged period: more rows, same result.
    foreach (['2026-10-01', '2026-10-02', '2026-10-05'] as $date) {
        foreach (['EMP-A', 'EMP-B', 'EMP-C', 'EMP-D'] as $npk) {
            ewgenDay($this->section, $date, $npk, total: 3.0);
        }
    }

    DB::flushQueryLog();
    ewgenGenerate();
    $large = count(DB::getQueryLog());
    DB::disableQueryLog();

    // The first run also loads the section's department; after that, more rows must not mean more queries.
    expect($large)->toBeLessThanOrEqual($small)->and($large)->toBeLessThanOrEqual(20);
});
