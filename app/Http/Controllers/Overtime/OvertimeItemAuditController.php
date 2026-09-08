<?php

namespace App\Http\Controllers\Overtime;

use App\Http\Controllers\Controller;
use App\Models\OvertimeItem;
use App\Models\OvertimeItemAudit;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OvertimeItemAuditController extends Controller
{
    /**
     * Retrieve the chronological immutable audit trail for a specific overtime item.
     */
    public function index(Request $request, OvertimeItem $item): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $item->loadMissing([
            'employee:id,npk,full_name,job_position',
            'capexProject:id,project_code,name',
            'submission.department:id,name,code',
            'submission.section:id,name,code',
        ]);

        // Scoping: Manager can only access items within their own department
        if ($user->isManager() && $user->department_id !== null) {
            if ($item->submission?->department_id !== $user->department_id) {
                abort(403, __('Anda tidak memiliki akses ke riwayat audit item departemen ini.'));
            }
        }

        $audits = OvertimeItemAudit::where('overtime_item_id', $item->id)
            ->with('actor:id,name,npk,role')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'item' => [
                'id' => $item->id,
                'submission_id' => $item->overtime_submission_id,
                'submission_code' => $item->submission?->submission_code,
                'operational_date' => $item->submission?->operational_date instanceof DateTimeInterface
                    ? $item->submission->operational_date->format('Y-m-d')
                    : (string) $item->submission?->operational_date,
                'day_type' => $item->submission?->day_type,
                'department' => $item->submission?->department,
                'section' => $item->submission?->section,
                'employee' => $item->employee,
                'npk_snapshot' => $item->npk_snapshot,
                'status' => $item->status,
                'total_hours' => $item->total_hours,
                'hours_production' => $item->hours_production,
                'hours_tpm' => $item->hours_tpm,
                'hours_project' => $item->hours_project,
                'hours_others' => $item->hours_others,
                'total_cost_snapshot' => $item->total_cost_snapshot,
                'rejection_reason' => $item->rejection_reason,
                'lock_version' => $item->lock_version,
                'capex_project' => $item->capexProject,
            ],
            'audits' => $audits->map(fn (OvertimeItemAudit $audit) => [
                'id' => $audit->id,
                'action' => $audit->action,
                'actor' => $audit->actor ? [
                    'id' => $audit->actor->id,
                    'name' => $audit->actor->name,
                    'npk' => $audit->actor->npk,
                    'role' => $audit->actor->role,
                ] : null,
                'previous_state' => $audit->previous_state,
                'new_state' => $audit->new_state,
                'notes' => $audit->notes,
                'ip_address' => $audit->ip_address,
                'created_at' => $audit->created_at?->toIso8601String(),
                'created_at_human' => $audit->created_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i:s').' WIB',
            ]),
        ]);
    }
}
