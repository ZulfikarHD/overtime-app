<?php

use App\Models\OperationalCalendar;
use App\Models\User;
use App\Services\OperationalCalendarService;

test('admin can navigate to operational calendar tab and inspect monthly grid', function () {
    app(OperationalCalendarService::class)->generateForYear(2026);

    $admin = User::factory()->admin()->create([
        'email' => 'admin.calendar@factory.com',
    ]);

    visit('/login')
        ->fill('email', 'admin.calendar@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->click('Master Data')
        ->assertPathIs('/admin/master-data')
        ->click('[data-test="tab-calendar"]')
        ->assertSee('Operational Calendar')
        ->assertSee('Workdays (HKN)')
        ->assertSee('Holidays / Rest Days (HLR)');
});

test('admin can click a day cell and update classification via slide-in sheet', function () {
    app(OperationalCalendarService::class)->generateForYear(2026);

    $admin = User::factory()->admin()->create([
        'email' => 'admin.sheet@factory.com',
    ]);

    visit('/login')
        ->fill('email', 'admin.sheet@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->navigate('/admin/master-data?tab=calendar&year=2026&month=8')
        ->assertSee('Agustus 2026')
        ->assertSee('17')
        ->click('[data-test="calendar-cell-2026-08-20"]')
        ->assertSee('Edit Day Classification')
        ->click('[data-test="btn-day-type-hlr"]')
        ->fill('#holiday_name', 'Cuti Bersama Plant Shutdown')
        ->fill('#calendar_description', 'Shutdown lini produksi tahunan')
        ->click('[data-test="btn-save-calendar-day"]')
        ->assertSee('Cuti Bersama Plant Shutdown');

    $record = OperationalCalendar::find('2026-08-20');
    expect($record->day_type)->toBe('HLR')
        ->and($record->is_holiday)->toBeTrue()
        ->and($record->holiday_name)->toBe('Cuti Bersama Plant Shutdown');
});

test('admin can open national holidays import dialog and view CSV template format', function () {
    app(OperationalCalendarService::class)->generateForYear(2026);

    $admin = User::factory()->admin()->create([
        'email' => 'admin.dialog@factory.com',
    ]);

    visit('/login')
        ->fill('email', 'admin.dialog@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->navigate('/admin/master-data?tab=calendar&year=2026&month=8')
        ->click('[data-test="btn-import-holidays"]')
        ->assertSee('Import Hari Libur Nasional (CSV)')
        ->assertSee('Standard CSV Template Format')
        ->assertSee('date (YYYY-MM-DD), holiday_name, description')
        ->click('Cancel');
});
