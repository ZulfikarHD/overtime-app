<?php

namespace App\Http\Controllers\Overtime;

use App\Actions\Overtime\AttachSpklDocumentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Overtime\AttachSpklDocumentRequest;
use App\Models\OvertimeSubmission;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SpklDocumentController extends Controller
{
    public function __construct(
        public AttachSpklDocumentAction $attachSpklDocumentAction,
    ) {}

    /**
     * Attach an SPKL document file or reference number to an overtime submission.
     */
    public function attach(AttachSpklDocumentRequest $request, OvertimeSubmission $submission): RedirectResponse|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $spklDocument = $this->attachSpklDocumentAction->execute(
            $submission,
            [
                'file' => $request->file('file'),
                'spkl_number' => $request->input('spkl_number'),
            ],
            $user->id
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => __('Dokumen SPKL berhasil dilampirkan.'),
                'spkl_document' => $spklDocument,
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Dokumen SPKL berhasil dilampirkan untuk pengajuan :code', [
                'code' => $submission->submission_code,
            ]),
        ]);

        return redirect()->back()->with('success', __('Dokumen SPKL berhasil dilampirkan.'));
    }

    /**
     * Verify an attached SPKL document (Manager/Admin only).
     */
    public function verify(Request $request, OvertimeSubmission $submission): RedirectResponse|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->isAdmin() && ! $user->isManager()) {
            abort(403, __('Hanya Manajer atau Administrator yang dapat memverifikasi dokumen SPKL.'));
        }

        if (! $user->canAccessSection($submission->section_id)) {
            abort(403, __('Anda tidak memiliki akses ke data pengajuan seksi ini.'));
        }

        $spklDocument = $submission->spklDocument;

        if (! $spklDocument) {
            abort(404, __('Dokumen SPKL belum ada untuk pengajuan ini.'));
        }

        if ($spklDocument->status !== 'ATTACHED') {
            abort(422, __('Hanya dokumen SPKL dengan status Terlampir yang dapat diverifikasi.'));
        }

        $spklDocument->update([
            'status' => 'VERIFIED',
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => __('Dokumen SPKL berhasil diverifikasi.'),
                'spkl_document' => $spklDocument->fresh(['attachedBy']),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Dokumen SPKL untuk pengajuan :code berhasil diverifikasi.', [
                'code' => $submission->submission_code,
            ]),
        ]);

        return redirect()->back()->with('success', __('Dokumen SPKL berhasil diverifikasi.'));
    }

    /**
     * Download or retrieve a signed temporary URL for the attached SPKL file.
     */
    public function download(Request $request, OvertimeSubmission $submission): RedirectResponse|JsonResponse|StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->canAccessSection($submission->section_id)) {
            abort(403, __('Anda tidak memiliki akses ke berkas dokumen pengajuan ini.'));
        }

        $spklDocument = $submission->spklDocument;

        if (! $spklDocument || ! $spklDocument->file_path) {
            abort(404, __('Berkas dokumen SPKL belum dilampirkan.'));
        }

        if (! Storage::disk(AttachSpklDocumentAction::DISK)->exists($spklDocument->file_path)) {
            abort(404, __('Berkas dokumen SPKL tidak ditemukan pada penyimpanan fisik.'));
        }

        try {
            $signedUrl = Storage::disk(AttachSpklDocumentAction::DISK)->temporaryUrl(
                $spklDocument->file_path,
                Carbon::now('Asia/Jakarta')->addMinutes(15),
                [
                    'ResponseContentDisposition' => 'attachment; filename="'.addslashes($spklDocument->file_name ?? 'spkl_document.pdf').'"',
                ]
            );

            if ($request->wantsJson()) {
                return response()->json([
                    'url' => $signedUrl,
                ]);
            }

            return redirect()->away($signedUrl);
        } catch (\Throwable) {
            return Storage::disk(AttachSpklDocumentAction::DISK)->download(
                $spklDocument->file_path,
                $spklDocument->file_name ?? 'spkl_document.pdf'
            );
        }
    }
}
