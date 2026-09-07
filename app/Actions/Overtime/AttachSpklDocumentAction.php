<?php

namespace App\Actions\Overtime;

use App\Models\OvertimeSubmission;
use App\Models\SpklDocument;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttachSpklDocumentAction
{
    /**
     * Disk identifier for private SPKL document storage.
     */
    public const DISK = 'spkl-private';

    /**
     * Attach an SPKL document file or physical reference number to a submission.
     * Replaces previous file on private disk if a new file is uploaded.
     *
     * @param  array{file?: UploadedFile|null, spkl_number?: string|null}  $data
     */
    public function execute(OvertimeSubmission $submission, array $data, int $userId): SpklDocument
    {
        return DB::transaction(function () use ($submission, $data, $userId) {
            /** @var SpklDocument $spklDocument */
            $spklDocument = $submission->spklDocument()->firstOrCreate(
                ['overtime_submission_id' => $submission->id],
                [
                    'status' => 'PENDING',
                    'due_date' => Carbon::parse($submission->operational_date)->addWeekdays(2)->toDateString(),
                ]
            );

            /** @var UploadedFile|null $file */
            $file = $data['file'] ?? null;
            $spklNumber = $data['spkl_number'] ?? null;

            $updateData = [];

            if (filled($spklNumber)) {
                $updateData['spkl_number'] = trim((string) $spklNumber);
            }

            if ($file instanceof UploadedFile && $file->isValid()) {
                // Delete previous file from private disk if exists
                if ($spklDocument->file_path && Storage::disk(self::DISK)->exists($spklDocument->file_path)) {
                    Storage::disk(self::DISK)->delete($spklDocument->file_path);
                }

                $extension = $file->getClientOriginalExtension() ?: 'bin';
                $safeBaseName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
                $fileNameToStore = sprintf(
                    '%d_%s_%s.%s',
                    $submission->id,
                    now()->format('YmdHis'),
                    Str::random(8),
                    $extension
                );

                $storedPath = $file->storeAs(
                    'submissions/'.$submission->id,
                    $fileNameToStore,
                    self::DISK
                );

                $updateData['file_path'] = $storedPath;
                $updateData['file_name'] = $file->getClientOriginalName();
                $updateData['file_size_bytes'] = $file->getSize();
                $updateData['mime_type'] = $file->getClientMimeType() ?: $file->getMimeType();
            }

            // Always transition to ATTACHED if currently PENDING
            if ($spklDocument->status === 'PENDING') {
                $updateData['status'] = 'ATTACHED';
            }

            $updateData['attached_at'] = Carbon::now('Asia/Jakarta');
            $updateData['attached_by_user_id'] = $userId;

            $spklDocument->update($updateData);

            return $spklDocument->fresh(['attachedBy', 'overtimeSubmission']);
        });
    }
}
