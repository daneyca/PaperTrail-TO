<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminRoleController extends Controller
{
    private const FUTURE_ENHANCEMENT_MESSAGE = 'Dynamic role creation and permission assignment are planned for future implementation. Current PaperTrail access is based on predefined LGU procurement roles.';

    public function index(Request $request): View
    {
        $query = Role::query()->withCount(['users', 'permissions']);

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(fn ($builder) => $builder
                ->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%"));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('type')) {
            $query->where('is_system', $request->string('type')->toString() === 'system');
        }

        return view('admin.roles.index', [
            'roles' => $query->orderByDesc('is_system')->orderBy('name')->paginate(10)->withQueryString(),
            'filters' => $request->only(['search', 'status', 'type']),
            'summary' => [
                'total' => Role::count(),
                'active' => Role::where('status', Role::STATUS_ACTIVE)->count(),
                'system' => Role::where('is_system', true)->count(),
                'permissions' => Permission::count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.roles.create', [
            'role' => new Role(['status' => Role::STATUS_ACTIVE, 'is_system' => false]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        return redirect()
            ->route('admin.roles.index')
            ->with('error', self::FUTURE_ENHANCEMENT_MESSAGE);
    }

    public function edit(Role $role): View
    {
        return view('admin.roles.edit', ['role' => $role]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        return redirect()
            ->route('admin.roles.index')
            ->with('error', self::FUTURE_ENHANCEMENT_MESSAGE);
    }

    public function updateStatus(Role $role): RedirectResponse
    {
        return redirect()
            ->route('admin.roles.index')
            ->with('error', self::FUTURE_ENHANCEMENT_MESSAGE);
    }

    public function permissions(Role $role): View
    {
        return view('admin.roles.permissions', [
            'role' => $role->loadCount('users')->load('permissions'),
            'permissionsByGroup' => Permission::orderBy('group')->orderBy('name')->get()->groupBy('group'),
            'selected' => $role->permissions->pluck('id')->all(),
        ]);
    }

    public function updatePermissions(Request $request, Role $role): RedirectResponse
    {
        return redirect()
            ->route('admin.roles.index')
            ->with('error', self::FUTURE_ENHANCEMENT_MESSAGE);
    }

    private function rules(?Role $role = null): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:80',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('roles', 'code')->ignore($role),
            ],
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($role)],
            'description' => ['nullable', 'string'],
            'dashboard_route' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in([Role::STATUS_ACTIVE, Role::STATUS_INACTIVE])],
            'is_system' => ['nullable', 'boolean'],
        ];
    }
}
