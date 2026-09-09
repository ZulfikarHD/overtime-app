<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class EmployeeReportService
{
    /**
     * Search employees by NPK or full name scoped to the user's role and hierarchy.
     *
     * @return array<int, array{
     *     id: int,
     *     npk: string,
     *     name: string,
     *     job_position: string,
     *     department_name: string,
     *     section_name: string,
     *     is_active: bool
     * }>
     */
    public function search(User $user, string $query): array
    {
        $trimmed = trim($query);

        if (mb_strlen($trimmed) < 3) {
            return [];
        }

        if ($user->isUser()) {
            abort(403, 'Akses ditolak.');
        }

        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $trimmed);

        $queryBuilder = Employee::query()
            ->with([
                'department:id,name,code',
                'section:id,name,code',
            ])
            ->where(function (Builder $builder) use ($escaped): void {
                $builder->where('npk', 'LIKE', '%'.$escaped.'%')
                    ->orWhere('full_name', 'LIKE', '%'.$escaped.'%');
            });

        $this->applyRoleScope($queryBuilder, $user);

        return $queryBuilder
            ->orderBy('full_name', 'asc')
            ->limit(10)
            ->get()
            ->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'npk' => $employee->npk,
                'name' => $employee->full_name,
                'job_position' => $employee->job_position,
                'department_name' => $employee->department?->name ?? '-',
                'section_name' => $employee->section?->name ?? '-',
                'is_active' => (bool) $employee->is_active,
            ])
            ->values()
            ->all();
    }

    /**
     * Retrieve the scoped roster of employees for the dossier hub index view.
     *
     * @param  array{department_id?: int|string|null, section_id?: int|string|null, search?: string|null}  $filters
     * @return array<int, array{
     *     id: int,
     *     npk: string,
     *     full_name: string,
     *     job_position: string,
     *     is_active: bool,
     *     department: array{id: int, code: string, name: string}|null,
     *     section: array{id: int, code: string, name: string}|null
     * }>
     */
    public function getRoster(User $user, array $filters = []): array
    {
        if ($user->isUser()) {
            abort(403, 'Akses ditolak.');
        }

        $queryBuilder = Employee::query()
            ->with([
                'department:id,name,code',
                'section:id,name,code',
            ]);

        $this->applyRoleScope($queryBuilder, $user);

        if (! empty($filters['department_id']) && $user->isAdmin()) {
            $queryBuilder->where('department_id', (int) $filters['department_id']);
        }

        if (! empty($filters['section_id']) && ($user->isAdmin() || $user->isManager())) {
            $queryBuilder->where('section_id', (int) $filters['section_id']);
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
            $queryBuilder->where(function (Builder $builder) use ($escaped): void {
                $builder->where('npk', 'LIKE', '%'.$escaped.'%')
                    ->orWhere('full_name', 'LIKE', '%'.$escaped.'%');
            });
        }

        return $queryBuilder
            ->orderBy('full_name', 'asc')
            ->limit(100)
            ->get()
            ->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'npk' => $employee->npk,
                'full_name' => $employee->full_name,
                'job_position' => $employee->job_position,
                'is_active' => (bool) $employee->is_active,
                'department' => $employee->department ? [
                    'id' => $employee->department->id,
                    'code' => $employee->department->code,
                    'name' => $employee->department->name,
                ] : null,
                'section' => $employee->section ? [
                    'id' => $employee->section->id,
                    'code' => $employee->section->code,
                    'name' => $employee->section->name,
                ] : null,
            ])
            ->values()
            ->all();
    }

    /**
     * Authorize and retrieve employee dossier data.
     *
     * @return array{
     *     id: int,
     *     npk: string,
     *     full_name: string,
     *     job_position: string,
     *     hourly_rate: float,
     *     is_active: bool,
     *     department: array{id: int, code: string, name: string}|null,
     *     section: array{id: int, code: string, name: string}|null
     * }
     */
    public function getEmployeeDossier(User $user, string $npk): array
    {
        $employee = Employee::query()
            ->with([
                'department:id,code,name,default_hourly_rate',
                'section:id,department_id,code,name',
            ])
            ->where('npk', $npk)
            ->firstOrFail();

        $this->authorizeDossierAccess($user, $employee);

        return [
            'id' => $employee->id,
            'npk' => $employee->npk,
            'full_name' => $employee->full_name,
            'job_position' => $employee->job_position,
            'hourly_rate' => (float) $employee->effective_hourly_rate,
            'is_active' => (bool) $employee->is_active,
            'department' => $employee->department ? [
                'id' => $employee->department->id,
                'code' => $employee->department->code,
                'name' => $employee->department->name,
            ] : null,
            'section' => $employee->section ? [
                'id' => $employee->section->id,
                'code' => $employee->section->code,
                'name' => $employee->section->name,
            ] : null,
        ];
    }

    /**
     * Check if a user is authorized to view a specific employee's dossier.
     */
    public function authorizeDossierAccess(User $user, Employee $employee): void
    {
        if ($user->isAdmin()) {
            return;
        }

        if ($user->isUser()) {
            if ($user->npk !== $employee->npk) {
                abort(403, 'Akses ditolak.');
            }

            return;
        }

        if ($user->isManager()) {
            if (! $user->department_id || (int) $employee->department_id !== (int) $user->department_id) {
                abort(403, 'Akses ditolak. Karyawan berada di luar departemen Anda.');
            }

            return;
        }

        if ($user->isTeamLeader()) {
            if (! $user->section_id || (int) $employee->section_id !== (int) $user->section_id) {
                abort(403, 'Akses ditolak. Karyawan berada di luar seksi Anda.');
            }

            return;
        }

        abort(403, 'Akses ditolak.');
    }

    /**
     * Apply role-based scoping to the employee query builder.
     */
    protected function applyRoleScope(Builder $builder, User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }

        if ($user->isManager()) {
            if ($user->department_id) {
                $builder->where('department_id', $user->department_id);
            } else {
                $builder->whereRaw('1 = 0');
            }

            return;
        }

        if ($user->isTeamLeader()) {
            if ($user->section_id) {
                $builder->where('section_id', $user->section_id);
            } else {
                $builder->whereRaw('1 = 0');
            }

            return;
        }

        $builder->whereRaw('1 = 0');
    }
}
