<?php

use App\Enums\AlertCause;
use App\Models\AlertCausePicMapping;
use App\Models\Department;
use App\Models\PolicyThreshold;
use App\Models\User;
use Database\Seeders\AlertCausePicMappingSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    app()->setLocale('en');
    PolicyThreshold::factory()->plantDefault()->create();

    $this->admin = User::factory()->admin()->create();
    $this->pic = Department::factory()->create(['is_active' => true]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array{mappings: list<array<string, mixed>>}
 */
function causePayload(array $overrides = []): array
{
    return [
        'mappings' => [
            array_merge(['cause' => 'MANPOWER_SHORTAGE', 'pic_department_id' => test()->pic->id, 'is_active' => true], $overrides),
        ],
    ];
}

test('guests are sent to login and non-admins are forbidden', function () {
    $this->put(route('admin.alert-cause-mappings.update'), causePayload())->assertRedirect(route('login'));

    foreach ([User::factory()->manager()->create(), User::factory()->teamLeader()->create(), User::factory()->user()->create()] as $user) {
        $this->actingAs($user)
            ->put(route('admin.alert-cause-mappings.update'), causePayload())
            ->assertForbidden();
    }

    expect(AlertCausePicMapping::query()->count())->toBe(0);
});

test('an admin creates and then updates the mapping of a cause', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.alert-cause-mappings.update'), causePayload())
        ->assertRedirect(route('admin.administration', ['tab' => 'policies']))
        ->assertSessionHas('success');

    $mapping = AlertCausePicMapping::query()->sole();
    expect($mapping->cause)->toBe(AlertCause::ManpowerShortage)
        ->and($mapping->pic_department_id)->toBe($this->pic->id)
        ->and($mapping->is_active)->toBeTrue();

    $this->actingAs($this->admin)
        ->put(route('admin.alert-cause-mappings.update'), causePayload(['pic_department_id' => null, 'is_active' => false]))
        ->assertSessionHasNoErrors();

    $mapping->refresh();
    expect(AlertCausePicMapping::query()->count())->toBe(1)
        ->and($mapping->pic_department_id)->toBeNull()
        ->and($mapping->is_active)->toBeFalse();
});

test('every cause is saved at once and the card lists them all', function () {
    $payload = ['mappings' => collect(AlertCause::cases())->map(fn (AlertCause $cause): array => [
        'cause' => $cause->value,
        'pic_department_id' => $this->pic->id,
        'is_active' => true,
    ])->all()];

    $this->actingAs($this->admin)->put(route('admin.alert-cause-mappings.update'), $payload)->assertSessionHasNoErrors();

    expect(AlertCausePicMapping::query()->count())->toBe(count(AlertCause::cases()));

    $this->actingAs($this->admin)
        ->get(route('admin.administration', ['tab' => 'policies']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Administration')
            ->has('causeMappings', count(AlertCause::cases()))
            ->where('causeMappings.0.cause', 'MANPOWER_SHORTAGE')
            ->where('causeMappings.0.label', 'Manpower shortage')
            ->where('causeMappings.0.pic_department_id', $this->pic->id)
            ->where('causeMappings.0.is_default', false));
});

test('a cause without a saved row shows the configured default department', function () {
    $hcm = Department::factory()->create(['code' => 'DEPT_HCM', 'is_active' => true]);

    $this->actingAs($this->admin)
        ->get(route('admin.administration', ['tab' => 'policies']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('causeMappings.0.pic_department_id', $hcm->id)
            ->where('causeMappings.0.is_default', true)
            ->where('causeMappings.1.pic_department_id', null)
            ->where('causeMappings.1.is_default', true));
});

test('invalid mappings are rejected and nothing is saved', function (callable $mappings, string $errorKey) {
    $this->actingAs($this->admin)
        ->put(route('admin.alert-cause-mappings.update'), ['mappings' => $mappings()])
        ->assertSessionHasErrors($errorKey);

    expect(AlertCausePicMapping::query()->count())->toBe(0);
})->with([
    'unknown cause' => [fn () => [['cause' => 'MAGIC', 'pic_department_id' => null, 'is_active' => true]], 'mappings.0.cause'],
    'duplicate cause' => [fn () => [
        ['cause' => 'UNKNOWN', 'pic_department_id' => null, 'is_active' => true],
        ['cause' => 'UNKNOWN', 'pic_department_id' => null, 'is_active' => true],
    ], 'mappings.0.cause'],
    'department that does not exist' => [fn () => [['cause' => 'UNKNOWN', 'pic_department_id' => 999999, 'is_active' => true]], 'mappings.0.pic_department_id'],
    'missing active flag' => [fn () => [['cause' => 'UNKNOWN', 'pic_department_id' => null]], 'mappings.0.is_active'],
    'no mappings' => [fn () => [], 'mappings'],
]);

test('an inactive department cannot be the PIC', function () {
    $inactive = Department::factory()->create(['is_active' => false]);

    $this->actingAs($this->admin)
        ->put(route('admin.alert-cause-mappings.update'), causePayload(['pic_department_id' => $inactive->id]))
        ->assertSessionHasErrors('mappings.0.pic_department_id');
});

test('the seeder fills one row per cause, uses the default department when it exists and never overwrites an admin choice', function () {
    $hcm = Department::factory()->create(['code' => 'DEPT_HCM', 'is_active' => true]);

    $this->seed(AlertCausePicMappingSeeder::class);

    expect(AlertCausePicMapping::query()->count())->toBe(count(AlertCause::cases()))
        ->and(AlertCausePicMapping::query()->where('cause', 'MANPOWER_SHORTAGE')->value('pic_department_id'))->toBe($hcm->id)
        ->and(AlertCausePicMapping::query()->where('cause', 'UNKNOWN')->value('pic_department_id'))->toBeNull();

    AlertCausePicMapping::query()->where('cause', 'MANPOWER_SHORTAGE')->update(['pic_department_id' => $this->pic->id]);
    $this->seed(AlertCausePicMappingSeeder::class);

    expect(AlertCausePicMapping::query()->count())->toBe(count(AlertCause::cases()))
        ->and(AlertCausePicMapping::query()->where('cause', 'MANPOWER_SHORTAGE')->value('pic_department_id'))->toBe($this->pic->id);
});
