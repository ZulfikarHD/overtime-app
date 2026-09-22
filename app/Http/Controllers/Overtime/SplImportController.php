<?php

namespace App\Http\Controllers\Overtime;

use App\Actions\Overtime\ImportSplExcelAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Overtime\SplImportRequest;
use App\Models\Section;
use App\Models\SplEntry;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SplImportController extends Controller
{
    public function __construct(
        private readonly ImportSplExcelAction $importSplExcelAction,
    ) {}

    /** Allowed sort columns → their DB expression */
    private const SORT_COLUMNS = [
        'date' => 'realization_date',
        'employee' => 'employee_name_snapshot',
        'section' => 'section_name_snapshot',
        'hours' => 'total_hours',
        'ot_type' => 'type_ot_code',
        'day_type' => 'day_type',
    ];

    /**
     * Show the SPL import page.
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        // Validate sort parameters
        $sortBy = $request->input('sort', 'date');
        $sortDir = $request->input('direction', 'desc');

        if (! array_key_exists($sortBy, self::SORT_COLUMNS)) {
            $sortBy = 'date';
        }

        if (! in_array($sortDir, ['asc', 'desc'])) {
            $sortDir = 'desc';
        }

        $dbColumn = self::SORT_COLUMNS[$sortBy];

        // Filters for viewing existing entries
        $query = SplEntry::query()
            ->with([
                'employee:id,npk,full_name',
                'section:id,name,code',
                'importedBy:id,name',
            ])
            ->orderBy($dbColumn, $sortDir);

        // Secondary sort for stable ordering
        if ($sortBy !== 'date') {
            $query->orderByDesc('realization_date');
        }

        if ($sortBy !== 'employee') {
            $query->orderBy('employee_name_snapshot');
        }

        if ($user->isTeamLeader() && $user->section_id) {
            $query->where('section_id', $user->section_id);
        } elseif ($user->isManager() && $user->department_id) {
            $query->where('department_id', $user->department_id);
        }

        if ($request->filled('fiscal_year') && $request->filled('fiscal_month')) {
            $query->forMonth((int) $request->input('fiscal_year'), (int) $request->input('fiscal_month'));
        }

        if ($request->filled('section_id')) {
            $sectionId = (int) $request->input('section_id');
            if ($user->canAccessSection($sectionId)) {
                $query->where('section_id', $sectionId);
            }
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('employee_name_snapshot', 'like', "%{$search}%")
                    ->orWhere('npk_snapshot', 'like', "%{$search}%")
                    ->orWhere('section_name_snapshot', 'like', "%{$search}%");
            });
        }

        $entries = $query->paginate(50)->withQueryString();

        $sectionsQuery = Section::where('is_active', true)->orderBy('name');
        if ($user->isTeamLeader() && $user->section_id) {
            $sectionsQuery->where('id', $user->section_id);
        } elseif ($user->isManager() && $user->department_id) {
            $sectionsQuery->where('department_id', $user->department_id);
        }

        $now = Carbon::now('Asia/Jakarta');

        return Inertia::render('overtime/SplImport', [
            'entries' => $entries,
            'sections' => $sectionsQuery->get(['id', 'code', 'name']),
            'filters' => $request->only(['fiscal_year', 'fiscal_month', 'section_id', 'sort', 'direction', 'search']),
            'current_year' => $now->year,
            'current_month' => $now->month,
        ]);
    }

    /**
     * Handle the SPL Excel file upload and import.
     * Gate: admin / manager only (enforced by SplImportRequest).
     */
    public function import(SplImportRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $file = $request->file('file');
        $fiscalYear = (int) $request->validated('fiscal_year');
        $fiscalMonth = (int) $request->validated('fiscal_month');

        // Store the file temporarily on the local disk.
        // Use Storage::disk('local')->path() to resolve the absolute path — avoids
        // hardcoding storage_path('app/...') which breaks when the disk root is
        // configured to storage_path('app/private').
        $path = $file->storeAs(
            'spl-imports',
            sprintf('spl_%04d%02d_%s.xlsx', $fiscalYear, $fiscalMonth, uniqid()),
            'local',
        );

        if ($path === false) {
            return back()->withErrors(['file' => __('Gagal menyimpan file sementara. Periksa permission storage.')]);
        }

        $absolutePath = Storage::disk('local')->path($path);

        $result = $this->importSplExcelAction->execute(
            $absolutePath,
            $fiscalYear,
            $fiscalMonth,
            $user,
        );

        // Clean up temp file
        @unlink($absolutePath);

        $msg = __(':imported data baru ditambahkan, :updated data diperbarui.', [
            'imported' => $result['imported'],
            'updated' => $result['updated'],
        ]);

        if (! empty($result['errors'])) {
            $msg .= ' '.__(':count baris dilewati (lihat detail).', ['count' => $result['skipped']]);
        }

        Inertia::flash('toast', [
            'type' => empty($result['errors']) ? 'success' : 'warning',
            'message' => $msg,
        ]);

        return redirect()->route('overtime.spl.index', [
            'fiscal_year' => $fiscalYear,
            'fiscal_month' => $fiscalMonth,
        ])->with('import_result', $result);
    }

    /**
     * Delete a specific SPL entry (admin only).
     */
    public function destroy(Request $request, SplEntry $splEntry): JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->isAdmin()) {
            abort(403, __('Hanya administrator yang dapat menghapus data SPL.'));
        }

        $splEntry->delete();

        $successMsg = __('Data SPL berhasil dihapus.');

        if ($request->wantsJson()) {
            return response()->json(['message' => $successMsg]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $successMsg]);

        return back();
    }
}
