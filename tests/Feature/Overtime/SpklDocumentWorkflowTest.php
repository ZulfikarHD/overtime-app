<?php

use App\Actions\Overtime\AttachSpklDocumentAction;
use App\Models\Department;
use App\Models\OperationalCalendar;
use App\Models\OvertimeSubmission;
use App\Models\Section;
use App\Models\SpklDocument;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(AttachSpklDocumentAction::DISK);

    $todayWib = Carbon::now('Asia/Jakarta')->format('Y-m-d');
    OperationalCalendar::firstOrCreate(
        ['calendar_date' => $todayWib],
        ['day_type' => 'HKN', 'is_holiday' => false]
    );
});

function createSubmissionWithSpkl(array $submissionAttrs = [], array $spklAttrs = []): OvertimeSubmission
{
    $dept = Department::factory()->create(['is_active' => true]);
    $section = Section::factory()->create(['department_id' => $dept->id, 'is_active' => true]);
    $tl = User::factory()->teamLeader($section->id, $dept->id)->create();

    $submission = OvertimeSubmission::create(array_merge([
        'submission_code' => 'OT-20260908-SEC-'.uniqid(),
        'submission_date' => '2026-09-08',
        'operational_date' => '2026-09-08',
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $tl->id,
        'status' => 'SUBMITTED',
        'total_hours_cached' => 8.0,
    ], $submissionAttrs));

    $submission->spklDocument()->create(array_merge([
        'status' => 'PENDING',
        'due_date' => Carbon::parse($submission->operational_date)->addWeekdays(2)->toDateString(),
    ], $spklAttrs));

    return $submission->fresh(['spklDocument', 'section', 'department']);
}

test('guest cannot attach or verify spkl documents', function () {
    $submission = createSubmissionWithSpkl();

    $this->post(route('overtime.submissions.spkl.attach', $submission), [])
        ->assertRedirect(route('login'));

    $this->patch(route('overtime.submissions.spkl.verify', $submission))
        ->assertRedirect(route('login'));

    $this->get(route('overtime.submissions.spkl.download', $submission))
        ->assertRedirect(route('login'));
});

test('team leader can attach a valid spkl pdf file', function () {
    $submission = createSubmissionWithSpkl();
    $tl = User::find($submission->submitted_by_user_id);

    $file = UploadedFile::fake()->create('spkl_signed.pdf', 1500, 'application/pdf');

    $response = $this->actingAs($tl)->post(route('overtime.submissions.spkl.attach', $submission), [
        'file' => $file,
        'spkl_number' => 'SPKL/STAMP/2026/09/001',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
    $response->assertSessionHas('success');

    $spkl = $submission->fresh()->spklDocument;
    expect($spkl)->not->toBeNull();
    expect($spkl->status)->toBe('ATTACHED');
    expect($spkl->spkl_number)->toBe('SPKL/STAMP/2026/09/001');
    expect($spkl->file_name)->toBe('spkl_signed.pdf');
    expect($spkl->mime_type)->toBe('application/pdf');
    expect($spkl->attached_by_user_id)->toBe($tl->id);
    expect($spkl->attached_at)->not->toBeNull();

    Storage::disk(AttachSpklDocumentAction::DISK)->assertExists($spkl->file_path);
});

test('team leader can attach spkl by physical number only without file', function () {
    $submission = createSubmissionWithSpkl();
    $tl = User::find($submission->submitted_by_user_id);

    $response = $this->actingAs($tl)->post(route('overtime.submissions.spkl.attach', $submission), [
        'spkl_number' => 'SPKL/PRESS/2026/09/099',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $spkl = $submission->fresh()->spklDocument;
    expect($spkl->status)->toBe('ATTACHED');
    expect($spkl->spkl_number)->toBe('SPKL/PRESS/2026/09/099');
    expect($spkl->file_path)->toBeNull();
});

test('spkl attachment validates file format and size', function () {
    $submission = createSubmissionWithSpkl();
    $tl = User::find($submission->submitted_by_user_id);

    // 1. Neither file nor number provided
    $this->actingAs($tl)->post(route('overtime.submissions.spkl.attach', $submission), [])
        ->assertSessionHasErrors(['file']);

    // 2. Disallowed format (e.g. .exe or .txt)
    $badFile = UploadedFile::fake()->create('bad_file.txt', 50, 'text/plain');
    $this->actingAs($tl)->post(route('overtime.submissions.spkl.attach', $submission), [
        'file' => $badFile,
    ])->assertSessionHasErrors(['file']);

    // 3. Oversized file (> 3072 KB)
    $largeFile = UploadedFile::fake()->create('huge.pdf', 3500, 'application/pdf');
    $this->actingAs($tl)->post(route('overtime.submissions.spkl.attach', $submission), [
        'file' => $largeFile,
    ])->assertSessionHasErrors(['file']);
});

test('re-uploading replaces the previous file and removes it from disk', function () {
    $submission = createSubmissionWithSpkl();
    $tl = User::find($submission->submitted_by_user_id);

    $file1 = UploadedFile::fake()->create('first_doc.pdf', 500, 'application/pdf');
    $this->actingAs($tl)->post(route('overtime.submissions.spkl.attach', $submission), [
        'file' => $file1,
        'spkl_number' => 'SPKL-01',
    ]);

    $spkl1 = $submission->fresh()->spklDocument;
    $firstPath = $spkl1->file_path;
    Storage::disk(AttachSpklDocumentAction::DISK)->assertExists($firstPath);

    // Upload replacement
    $file2 = UploadedFile::fake()->create('second_doc.png', 800, 'image/png');
    $this->actingAs($tl)->post(route('overtime.submissions.spkl.attach', $submission), [
        'file' => $file2,
        'spkl_number' => 'SPKL-02',
    ]);

    $spkl2 = $submission->fresh()->spklDocument;
    expect($spkl2->id)->toBe($spkl1->id); // 1:1 relationship preserved
    expect($spkl2->file_name)->toBe('second_doc.png');
    expect($spkl2->spkl_number)->toBe('SPKL-02');

    // First file removed, second file exists
    Storage::disk(AttachSpklDocumentAction::DISK)->assertMissing($firstPath);
    Storage::disk(AttachSpklDocumentAction::DISK)->assertExists($spkl2->file_path);
});

test('user from different section cannot attach spkl document', function () {
    $submission = createSubmissionWithSpkl();

    // Create another section and another TL
    $otherDept = Department::factory()->create();
    $otherSection = Section::factory()->create(['department_id' => $otherDept->id]);
    $foreignTl = User::factory()->teamLeader($otherSection->id, $otherDept->id)->create();

    $this->actingAs($foreignTl)->post(route('overtime.submissions.spkl.attach', $submission), [
        'spkl_number' => 'SPKL-HACK',
    ])->assertForbidden();
});

test('manager can verify attached spkl document', function () {
    $submission = createSubmissionWithSpkl();
    $tl = User::find($submission->submitted_by_user_id);

    // First attach
    $file = UploadedFile::fake()->create('spkl_valid.pdf', 500, 'application/pdf');
    $this->actingAs($tl)->post(route('overtime.submissions.spkl.attach', $submission), [
        'file' => $file,
    ]);

    // Team Leader cannot verify
    $this->actingAs($tl)->patch(route('overtime.submissions.spkl.verify', $submission))
        ->assertForbidden();

    // Manager for this department can verify
    $manager = User::factory()->manager($submission->department_id)->create();
    $response = $this->actingAs($manager)->patch(route('overtime.submissions.spkl.verify', $submission));

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $spkl = $submission->fresh()->spklDocument;
    expect($spkl->status)->toBe('VERIFIED');
});

test('manager cannot verify spkl if still pending', function () {
    $submission = createSubmissionWithSpkl([], ['status' => 'PENDING']);
    $manager = User::factory()->manager($submission->department_id)->create();

    $this->actingAs($manager)->patch(route('overtime.submissions.spkl.verify', $submission))
        ->assertStatus(422);
});

test('manager from different department cannot verify spkl', function () {
    $submission = createSubmissionWithSpkl([], ['status' => 'ATTACHED']);

    $otherDept = Department::factory()->create();
    $foreignManager = User::factory()->manager($otherDept->id)->create();

    $this->actingAs($foreignManager)->patch(route('overtime.submissions.spkl.verify', $submission))
        ->assertForbidden();
});

test('authorized user can download attached spkl file', function () {
    $submission = createSubmissionWithSpkl();
    $tl = User::find($submission->submitted_by_user_id);

    $file = UploadedFile::fake()->create('spkl_downloadable.pdf', 500, 'application/pdf');
    $this->actingAs($tl)->post(route('overtime.submissions.spkl.attach', $submission), [
        'file' => $file,
    ]);

    // Download as Team Leader
    $response = $this->actingAs($tl)->get(route('overtime.submissions.spkl.download', $submission));
    $response->assertRedirect(); // Redirects to signed URL

    // AJAX request gets JSON signed URL
    $jsonResponse = $this->actingAs($tl)->getJson(route('overtime.submissions.spkl.download', $submission));
    $jsonResponse->assertOk()
        ->assertJsonStructure(['url']);

    // User from different section cannot download
    $otherDept = Department::factory()->create();
    $otherSection = Section::factory()->create(['department_id' => $otherDept->id]);
    $foreignTl = User::factory()->teamLeader($otherSection->id, $otherDept->id)->create();

    $this->actingAs($foreignTl)->get(route('overtime.submissions.spkl.download', $submission))
        ->assertForbidden();
});

test('download returns 404 if no file was uploaded', function () {
    $submission = createSubmissionWithSpkl([], ['status' => 'PENDING', 'file_path' => null]);
    $tl = User::find($submission->submitted_by_user_id);

    $this->actingAs($tl)->get(route('overtime.submissions.spkl.download', $submission))
        ->assertNotFound();
});

test('isDueOverdue returns true only when pending and due date in past', function () {
    $spklOverdue = new SpklDocument([
        'status' => 'PENDING',
        'due_date' => Carbon::now('Asia/Jakarta')->subDays(2)->toDateString(),
    ]);
    expect($spklOverdue->isDueOverdue())->toBeTrue();

    $spklToday = new SpklDocument([
        'status' => 'PENDING',
        'due_date' => Carbon::now('Asia/Jakarta')->toDateString(),
    ]);
    expect($spklToday->isDueOverdue())->toBeFalse();

    $spklFuture = new SpklDocument([
        'status' => 'PENDING',
        'due_date' => Carbon::now('Asia/Jakarta')->addDays(2)->toDateString(),
    ]);
    expect($spklFuture->isDueOverdue())->toBeFalse();

    $spklAttachedOverdue = new SpklDocument([
        'status' => 'ATTACHED',
        'due_date' => Carbon::now('Asia/Jakarta')->subDays(2)->toDateString(),
    ]);
    expect($spklAttachedOverdue->isDueOverdue())->toBeFalse();

    $spklVerifiedOverdue = new SpklDocument([
        'status' => 'VERIFIED',
        'due_date' => Carbon::now('Asia/Jakarta')->subDays(2)->toDateString(),
    ]);
    expect($spklVerifiedOverdue->isDueOverdue())->toBeFalse();
});
