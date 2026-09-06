<?php

use App\Models\Department;
use App\Models\Section;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('handle inertia requests shares flash message bag and toasts correctly', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->withSession([
            'success' => 'Operasi berhasil disimpan.',
            'toast' => [
                'type' => 'success',
                'message' => 'Notifikasi tersampaikan.',
            ],
        ])
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->where('flash.success', 'Operasi berhasil disimpan.')
        ->where('flash.toast.type', 'success')
        ->where('flash.toast.message', 'Notifikasi tersampaikan.')
        ->has('flash')
        ->has('translations')
        ->has('locale')
        ->where('name', config('app.name'))
    );
});

test('authenticated user session shares complete department and section scoping', function () {
    $dept = Department::create([
        'code' => 'DEPT_TEST_ASY',
        'name' => 'Assembly Test Department',
        'cost_center_code' => 'CC-TEST-104',
        'default_hourly_rate' => 45000.00,
        'is_active' => true,
    ]);

    $section = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_TEST_TRIM',
        'name' => 'Trim Line Test',
        'is_active' => true,
    ]);

    $user = User::factory()->teamLeader($section->id, $dept->id)->create([
        'name' => 'Rahmat Hidayat',
        'npk' => 'EMP-77889',
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->where('auth.user.name', 'Rahmat Hidayat')
        ->where('auth.user.npk', 'EMP-77889')
        ->where('auth.user.role', 'team_leader')
        ->where('auth.user.department.name', 'Assembly Test Department')
        ->where('auth.user.section.name', 'Trim Line Test')
    );
});

test('guest layout is applied for login route and unauthenticated users are redirected', function () {
    $guestResponse = $this->get(route('login'));
    $guestResponse->assertOk();
    $guestResponse->assertInertia(fn (Assert $page) => $page
        ->component('auth/Login')
    );

    $protectedResponse = $this->get(route('dashboard'));
    $protectedResponse->assertRedirect(route('login'));
});
