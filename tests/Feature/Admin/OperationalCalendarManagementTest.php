<?php

use App\Models\OperationalCalendar;
use App\Models\User;
use App\Services\OperationalCalendarService;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

test('guest is redirected to login when accessing operational calendar on master data hub', function () {
    $this->get(route('admin.master-data', ['tab' => 'calendar']))->assertRedirect(route('login'));
});

test('non-admin users are forbidden from accessing operational calendar admin routes', function () {
    $manager = User::factory()->manager()->create();
    $teamLeader = User::factory()->teamLeader()->create();
    $operator = User::factory()->user()->create();

    $this->actingAs($manager)->get(route('admin.master-data', ['tab' => 'calendar']))->assertForbidden();
    $this->actingAs($teamLeader)->get(route('admin.master-data', ['tab' => 'calendar']))->assertForbidden();
    $this->actingAs($operator)->get(route('admin.master-data', ['tab' => 'calendar']))->assertForbidden();

    $this->actingAs($manager)->put(route('admin.calendar.update', '2026-08-17'), ['day_type' => 'HLR'])->assertForbidden();
    $this->actingAs($teamLeader)->post(route('admin.calendar.import.preview'))->assertForbidden();
    $this->actingAs($operator)->post(route('admin.calendar.import'))->assertForbidden();
});

test('admin can access operational calendar on master data hub with monthly days and statistics', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('admin.master-data', [
        'tab' => 'calendar',
        'year' => 2026,
        'month' => 8,
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/MasterData')
        ->where('activeTab', 'calendar')
        ->where('calendar.year', 2026)
        ->where('calendar.month', 8)
        ->has('calendar.days', 31)
        ->has('calendar.stats')
        ->where('calendar.stats.total_days', 31)
        ->where('calendar.days.16.calendar_date', '2026-08-17')
    );
});

test('operational calendar service generates full year with weekend as HLR and weekdays as HKN', function () {
    $service = app(OperationalCalendarService::class);
    $count = $service->generateForYear(2026);

    expect($count)->toBe(365);

    // Test a normal weekday (e.g. 2026-01-05 is a Monday)
    $monday = OperationalCalendar::find('2026-01-05');
    expect($monday)->not->toBeNull()
        ->and($monday->day_type)->toBe('HKN')
        ->and($monday->is_holiday)->toBeFalse();

    // Test a weekend (e.g. 2026-01-04 is a Sunday)
    $sunday = OperationalCalendar::find('2026-01-04');
    expect($sunday)->not->toBeNull()
        ->and($sunday->day_type)->toBe('HLR')
        ->and($sunday->is_holiday)->toBeTrue();

    // Test a national holiday (2026-01-01 New Year)
    $newYear = OperationalCalendar::find('2026-01-01');
    expect($newYear)->not->toBeNull()
        ->and($newYear->day_type)->toBe('HLR')
        ->and($newYear->is_holiday)->toBeTrue()
        ->and($newYear->holiday_name)->toBe('Tahun Baru 2026 Masehi');
});

test('seed calendar artisan command seeds specified fiscal year or current and next year', function () {
    $this->artisan('app:seed-calendar 2026')
        ->assertSuccessful();

    expect(OperationalCalendar::whereYear('calendar_date', 2026)->count())->toBe(365);

    $this->artisan('app:seed-calendar')
        ->assertSuccessful();

    $curYear = now()->year;
    $nextYear = $curYear + 1;

    expect(OperationalCalendar::whereYear('calendar_date', $curYear)->count())->toBeGreaterThanOrEqual(365)
        ->and(OperationalCalendar::whereYear('calendar_date', $nextYear)->count())->toBeGreaterThanOrEqual(365);
});

test('admin can update a calendar day type and holiday description', function () {
    $admin = User::factory()->admin()->create();

    // Ensure date exists
    $service = app(OperationalCalendarService::class);
    $service->generateForYear(2026);

    $targetDate = '2026-10-15'; // A Thursday
    $day = OperationalCalendar::find($targetDate);
    expect($day->day_type)->toBe('HKN');

    $response = $this->actingAs($admin)->put(route('admin.calendar.update', $targetDate), [
        'day_type' => 'HLR',
        'is_holiday' => true,
        'holiday_name' => 'Hari Libur Khusus Pabrik',
        'description' => 'Maintenance tahunan lini stamping',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $updated = OperationalCalendar::find($targetDate);
    expect($updated->day_type)->toBe('HLR')
        ->and($updated->is_holiday)->toBeTrue()
        ->and($updated->holiday_name)->toBe('Hari Libur Khusus Pabrik')
        ->and($updated->description)->toBe('Maintenance tahunan lini stamping');
});

test('admin can toggle an HLR date back to HKN workday', function () {
    $admin = User::factory()->admin()->create();

    $service = app(OperationalCalendarService::class);
    $service->generateForYear(2026);

    $targetDate = '2026-08-17'; // Originally National Holiday HLR
    $response = $this->actingAs($admin)->put(route('admin.calendar.update', $targetDate), [
        'day_type' => 'HKN',
        'description' => 'Shift lembur nasional khusus',
    ]);

    $response->assertRedirect();

    $updated = OperationalCalendar::find($targetDate);
    expect($updated->day_type)->toBe('HKN')
        ->and($updated->is_holiday)->toBeFalse()
        ->and($updated->holiday_name)->toBeNull();
});

test('calendar update validates required day_type and valid options', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->put(route('admin.calendar.update', '2026-08-17'), [
        'day_type' => 'INVALID_TYPE',
    ]);

    $response->assertSessionHasErrors('day_type');
});

test('admin can preview valid and invalid national holiday rows from CSV', function () {
    $admin = User::factory()->admin()->create();

    $csvContent = <<<'CSV'
date,holiday_name,description
2026-09-01,Hari Apresiasi Karyawan,Acara internal pabrik
invalid-date,Hari Rusak,Format tanggal salah
2026-09-01,Duplikat Tanggal,Duplikat baris
,Tanpa Tanggal,
2026-09-02,,Tanpa Nama Hari Libur
CSV;

    $file = UploadedFile::fake()->createWithContent('holidays.csv', $csvContent);

    $response = $this->actingAs($admin)->postJson(route('admin.calendar.import.preview'), [
        'file' => $file,
    ]);

    $response->assertOk();
    $data = $response->json();

    expect($data['total'])->toBe(5)
        ->and($data['valid_count'])->toBe(1)
        ->and($data['error_count'])->toBe(4)
        ->and($data['rows'][0]['is_valid'])->toBeTrue()
        ->and($data['rows'][1]['is_valid'])->toBeFalse()
        ->and($data['rows'][2]['is_valid'])->toBeFalse();
});

test('admin can import validated national holidays from CSV into operational calendar', function () {
    $admin = User::factory()->admin()->create();

    $rows = [
        [
            'date' => '2026-11-10',
            'holiday_name' => 'Hari Pahlawan',
            'description' => 'Hari Libur Nasional Hari Pahlawan',
            'is_valid' => true,
        ],
        [
            'date' => '2026-11-25',
            'holiday_name' => 'Hari Guru Nasional',
            'description' => 'Peringatan Hari Guru',
            'is_valid' => true,
        ],
    ];

    $response = $this->actingAs($admin)->post(route('admin.calendar.import'), [
        'rows' => $rows,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $day1 = OperationalCalendar::find('2026-11-10');
    expect($day1)->not->toBeNull()
        ->and($day1->day_type)->toBe('HLR')
        ->and($day1->is_holiday)->toBeTrue()
        ->and($day1->holiday_name)->toBe('Hari Pahlawan');

    $day2 = OperationalCalendar::find('2026-11-25');
    expect($day2)->not->toBeNull()
        ->and($day2->day_type)->toBe('HLR')
        ->and($day2->holiday_name)->toBe('Hari Guru Nasional');
});

test('admin can download national holidays CSV template', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('admin.calendar.template'));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    expect($response->getContent())->toContain('date,holiday_name,description');
});

test('authenticated user can query calendar day classification API', function () {
    $user = User::factory()->create();

    $service = app(OperationalCalendarService::class);
    $service->generateForYear(2026);

    // Test holiday (2026-08-17)
    $response = $this->actingAs($user)->getJson(route('api.calendar.show', '2026-08-17'));

    $response->assertOk();
    $response->assertJson([
        'date' => '2026-08-17',
        'day_type' => 'HLR',
        'is_holiday' => true,
        'holiday_name' => 'Hari Kemerdekaan RI ke-81',
    ]);

    // Test normal workday (2026-08-18 Tuesday)
    $responseWorkday = $this->actingAs($user)->getJson(route('api.calendar.show', '2026-08-18'));
    $responseWorkday->assertOk();
    $responseWorkday->assertJson([
        'date' => '2026-08-18',
        'day_type' => 'HKN',
        'is_holiday' => false,
    ]);
});

test('calendar API returns 422 for invalid date strings', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson(route('api.calendar.show', 'invalid-date-format'));

    $response->assertStatus(422);
    $response->assertJsonStructure(['message']);
});

test('unauthenticated users cannot access calendar API', function () {
    $response = $this->getJson(route('api.calendar.show', '2026-08-17'));
    $response->assertUnauthorized();
});
