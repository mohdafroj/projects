<?php

namespace App\Modules\AdminFull\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AdminFull\Models\AdminFullConfigToggle;
use App\Modules\AdminFull\Models\AdminFullCustomMemberMaster;
use App\Modules\AdminFull\Models\AdminFullSittingTemplate;
use App\Modules\AdminFull\Models\AdminFullSlotTemplate;
use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\Member;
use App\Modules\Core\Models\Sitting;
use App\Modules\Core\Models\Slot;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\Audit\AuditHash;
use App\Modules\Core\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AdminFullController extends Controller
{
    public function summary(AuditLogger $audit): array
    {
        $audit->log('admin_full.summary.view');

        return [
            'generated_at' => now()->toISOString(),
            'users' => [
                'total' => User::query()->count(),
                'active' => User::query()->where('is_active', true)->count(),
            ],
            'roles' => Role::query()->withCount('permissions')->orderBy('name')->get(),
            'sittings' => [
                'total' => Sitting::query()->count(),
                'planned' => Sitting::query()->where('status', 'planned')->count(),
                'live' => Sitting::query()->where('status', 'live')->count(),
                'closed' => Sitting::query()->where('status', 'closed')->count(),
            ],
            'members' => [
                'roster' => Member::query()->count(),
                'custom_master' => AdminFullCustomMemberMaster::query()->count(),
            ],
            'templates' => [
                'sittings' => AdminFullSittingTemplate::query()->count(),
                'slots' => AdminFullSlotTemplate::query()->count(),
            ],
            'audit' => $this->auditChainSummary(),
        ];
    }

    public function users(AuditLogger $audit): array
    {
        $audit->log('admin_full.users.view');

        return [
            'items' => User::query()
                ->with('roles:id,name,display_name')
                ->orderBy('employee_id')
                ->get()
                ->map(fn (User $user) => $this->userPayload($user)),
        ];
    }

    public function storeUser(Request $request, AuditLogger $audit): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'employee_id' => ['required', 'string', 'max:255', 'unique:users,employee_id'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['nullable', 'string', 'min:8'],
            'section' => ['nullable', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            'language_competencies' => ['array'],
            'language_competencies.*' => ['string', Rule::in($this->languages())],
            'is_active' => ['boolean'],
            'roles' => ['array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        return DB::transaction(function () use ($validated, $audit) {
            $roles = $validated['roles'] ?? [];
            unset($validated['roles']);

            $user = User::query()->create([
                ...$validated,
                'password' => $validated['password'] ?? Str::password(16),
                'is_active' => $validated['is_active'] ?? true,
                'language_competencies' => $validated['language_competencies'] ?? [],
            ]);
            $user->syncRoles($roles);

            $audit->log('admin_full.user.create', $user, [
                'user_id' => $user->id,
                'employee_id' => $user->employee_id,
                'roles' => $roles,
            ]);

            return ['user' => $this->userPayload($user->fresh('roles'))];
        });
    }

    public function updateUser(Request $request, User $user, AuditLogger $audit): array
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'employee_id' => ['sometimes', 'string', 'max:255', Rule::unique('users', 'employee_id')->ignore($user->id)],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
            'section' => ['sometimes', 'nullable', 'string', 'max:255'],
            'designation' => ['sometimes', 'nullable', 'string', 'max:255'],
            'language_competencies' => ['sometimes', 'array'],
            'language_competencies.*' => ['string', Rule::in($this->languages())],
            'is_active' => ['sometimes', 'boolean'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        return DB::transaction(function () use ($validated, $user, $audit) {
            $roles = $validated['roles'] ?? null;
            unset($validated['roles']);

            if (array_key_exists('password', $validated) && blank($validated['password'])) {
                unset($validated['password']);
            }

            $user->fill($validated)->save();
            if ($roles !== null) {
                $user->syncRoles($roles);
            }

            $audit->log('admin_full.user.update', $user, [
                'user_id' => $user->id,
                'employee_id' => $user->employee_id,
                'changed' => array_keys($validated),
                'roles' => $roles,
            ]);

            return ['user' => $this->userPayload($user->fresh('roles'))];
        });
    }

    public function roles(AuditLogger $audit): array
    {
        $audit->log('admin_full.roles.view');

        return [
            'permissions' => Permission::query()->orderBy('name')->get(['id', 'name']),
            'items' => Role::query()
                ->with('permissions:id,name')
                ->withCount('users')
                ->orderBy('name')
                ->get()
                ->map(fn (Role $role) => $this->rolePayload($role)),
        ];
    }

    public function storeRole(Request $request, AuditLogger $audit): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'alpha_dash:ascii', 'unique:roles,name'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        return DB::transaction(function () use ($validated, $audit) {
            $role = Role::query()->create(['name' => $validated['name'], 'guard_name' => 'web']);
            $role->forceFill(['display_name' => $validated['display_name'] ?? Str::headline($validated['name'])])->save();
            $role->syncPermissions($validated['permissions'] ?? []);
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $audit->log('admin_full.role.create', null, [
                'role_id' => $role->id,
                'role' => $role->name,
                'permissions' => $validated['permissions'] ?? [],
            ]);

            return ['role' => $this->rolePayload($role->fresh('permissions'))];
        });
    }

    public function updateRole(Request $request, Role $role, AuditLogger $audit): array
    {
        $validated = $request->validate([
            'display_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        return DB::transaction(function () use ($validated, $role, $audit) {
            if (array_key_exists('display_name', $validated)) {
                $role->forceFill(['display_name' => $validated['display_name']])->save();
            }
            if (array_key_exists('permissions', $validated)) {
                $role->syncPermissions($validated['permissions']);
                app(PermissionRegistrar::class)->forgetCachedPermissions();
            }

            $audit->log('admin_full.role.update', null, [
                'role_id' => $role->id,
                'role' => $role->name,
                'changed' => array_keys($validated),
            ]);

            return ['role' => $this->rolePayload($role->fresh('permissions'))];
        });
    }

    public function sittingTemplates(AuditLogger $audit): array
    {
        $audit->log('admin_full.sitting_templates.view');

        return [
            'items' => AdminFullSittingTemplate::query()->with('slotTemplates')->orderBy('name')->get(),
        ];
    }

    public function storeSittingTemplate(Request $request, AuditLogger $audit): array
    {
        $template = AdminFullSittingTemplate::query()->create($this->validateSittingTemplate($request));
        $audit->log('admin_full.sitting_template.create', $template, ['template_id' => $template->id]);

        return ['template' => $template->fresh('slotTemplates')];
    }

    public function updateSittingTemplate(Request $request, AdminFullSittingTemplate $template, AuditLogger $audit): array
    {
        $template->fill($this->validateSittingTemplate($request, true))->save();
        $audit->log('admin_full.sitting_template.update', $template, ['template_id' => $template->id]);

        return ['template' => $template->fresh('slotTemplates')];
    }

    public function slotTemplates(AuditLogger $audit): array
    {
        $audit->log('admin_full.slot_templates.view');

        return [
            'items' => AdminFullSlotTemplate::query()->with('sittingTemplate:id,name')->orderBy('sitting_template_id')->orderBy('start_offset_ms')->get(),
        ];
    }

    public function storeSlotTemplate(Request $request, AuditLogger $audit): array
    {
        $template = AdminFullSlotTemplate::query()->create($this->validateSlotTemplate($request));
        $audit->log('admin_full.slot_template.create', $template, ['template_id' => $template->id]);

        return ['template' => $template->fresh('sittingTemplate')];
    }

    public function updateSlotTemplate(Request $request, AdminFullSlotTemplate $template, AuditLogger $audit): array
    {
        $template->fill($this->validateSlotTemplate($request, true))->save();
        $audit->log('admin_full.slot_template.update', $template, ['template_id' => $template->id]);

        return ['template' => $template->fresh('sittingTemplate')];
    }

    public function sittings(AuditLogger $audit): array
    {
        $audit->log('admin_full.sittings.view');

        return [
            'items' => Sitting::query()->withCount('slots')->latest('sitting_date')->limit(100)->get(),
        ];
    }

    public function storeSitting(Request $request, AuditLogger $audit): array
    {
        $sitting = Sitting::query()->create($this->validateSitting($request));
        $audit->log('admin_full.sitting.create', $sitting, ['sitting_id' => $sitting->id]);

        return ['sitting' => $sitting->fresh('slots')];
    }

    public function storeSittingFromTemplate(Request $request, AuditLogger $audit): array
    {
        $validated = $request->validate([
            'template_id' => ['required', 'integer', 'exists:admin_full_sitting_templates,id'],
            'session_no' => ['required', 'integer', 'min:1'],
            'sitting_no' => ['required', 'integer', 'min:1'],
            'sitting_date' => ['required', 'date'],
        ]);

        return DB::transaction(function () use ($validated, $audit) {
            $template = AdminFullSittingTemplate::query()->with(['slotTemplates' => fn ($query) => $query->where('is_active', true)->orderBy('start_offset_ms')])
                ->findOrFail($validated['template_id']);

            $sitting = Sitting::query()->create([
                'session_no' => $validated['session_no'],
                'sitting_no' => $validated['sitting_no'],
                'sitting_date' => $validated['sitting_date'],
                'status' => $template->default_status,
            ]);

            foreach ($template->slotTemplates as $index => $slotTemplate) {
                Slot::query()->create([
                    'sitting_id' => $sitting->id,
                    'code' => $slotTemplate->code_prefix.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                    'start_offset_ms' => $slotTemplate->start_offset_ms,
                    'duration_ms' => $slotTemplate->duration_ms,
                    'topic' => $slotTemplate->topic,
                    'status' => 'open',
                ]);
            }

            $audit->log('admin_full.sitting.instantiate_template', $sitting, [
                'sitting_id' => $sitting->id,
                'template_id' => $template->id,
                'slots' => $template->slotTemplates->count(),
            ]);

            return ['sitting' => $sitting->fresh('slots')];
        });
    }

    public function updateSitting(Request $request, Sitting $sitting, AuditLogger $audit): array
    {
        $sitting->fill($this->validateSitting($request, true))->save();
        $audit->log('admin_full.sitting.update', $sitting, ['sitting_id' => $sitting->id]);

        return ['sitting' => $sitting->fresh('slots')];
    }

    public function members(AuditLogger $audit): array
    {
        $audit->log('admin_full.members.view');

        return [
            'items' => Member::query()->orderBy('name_en')->limit(300)->get(),
        ];
    }

    public function storeMember(Request $request, AuditLogger $audit): array
    {
        $member = Member::query()->create($this->validateMember($request));
        $audit->log('admin_full.member.create', $member, ['member_id' => $member->id, 'roster_id' => $member->roster_id]);

        return ['member' => $member];
    }

    public function updateMember(Request $request, Member $member, AuditLogger $audit): array
    {
        $member->fill($this->validateMember($request, true, $member))->save();
        $audit->log('admin_full.member.update', $member, ['member_id' => $member->id, 'roster_id' => $member->roster_id]);

        return ['member' => $member->fresh()];
    }

    public function customMembers(AuditLogger $audit): array
    {
        $audit->log('admin_full.custom_members.view');

        return [
            'items' => AdminFullCustomMemberMaster::query()->with('createdBy:id,name,employee_id')->orderBy('name_en')->get(),
        ];
    }

    public function storeCustomMember(Request $request, AuditLogger $audit): array
    {
        $validated = $this->validateCustomMember($request);
        $member = AdminFullCustomMemberMaster::query()->create([
            ...$validated,
            'created_by_user_id' => $request->user()->id,
        ]);
        $audit->log('admin_full.custom_member.create', $member, ['member_id' => $member->id, 'reference_code' => $member->reference_code]);

        return ['member' => $member->fresh('createdBy')];
    }

    public function updateCustomMember(Request $request, AdminFullCustomMemberMaster $member, AuditLogger $audit): array
    {
        $member->fill($this->validateCustomMember($request, true, $member))->save();
        $audit->log('admin_full.custom_member.update', $member, ['member_id' => $member->id, 'reference_code' => $member->reference_code]);

        return ['member' => $member->fresh('createdBy')];
    }

    public function audit(Request $request, AuditLogger $audit): array
    {
        $validated = $request->validate([
            'action' => ['nullable', 'string', 'max:255'],
            'actor_id' => ['nullable', 'integer'],
            'subject_type' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $audit->log('admin_full.audit.view', null, [
            'filters' => collect($validated)->except('limit')->filter()->all(),
        ]);

        $limit = $validated['limit'] ?? 50;
        $rows = AuditLog::query()
            ->when($validated['action'] ?? null, fn ($query, $action) => $query->where('action', 'like', $action.'%'))
            ->when($validated['actor_id'] ?? null, fn ($query, $actorId) => $query->where('actor_id', $actorId))
            ->when($validated['subject_type'] ?? null, fn ($query, $subjectType) => $query->where('subject_type', $subjectType))
            ->latest('id')
            ->limit($limit)
            ->get();
        $validity = $this->auditValidityMap();

        return [
            'chain' => $this->auditChainSummary(),
            'items' => $rows->map(fn (AuditLog $row) => [
                ...$row->only(['id', 'prev_hash', 'this_hash', 'actor_id', 'actor_role', 'chain_segment', 'action', 'subject_type', 'subject_id', 'payload', 'request_id', 'created_at']),
                'valid' => $validity[$row->id] ?? false,
            ]),
        ];
    }

    public function verifyAudit(AuditLogger $audit): array
    {
        $audit->log('admin_full.audit.verify');

        return $this->auditChainSummary();
    }

    public function config(AuditLogger $audit): array
    {
        $audit->log('admin_full.config.view');
        $this->ensureDefaultConfig();

        return ['items' => AdminFullConfigToggle::query()->orderBy('key')->get()];
    }

    public function updateConfig(Request $request, AuditLogger $audit): array
    {
        $validated = $request->validate([
            'toggles' => ['required', 'array', 'min:1'],
            'toggles.*.key' => ['required', 'string', 'max:255'],
            'toggles.*.enabled' => ['required', 'boolean'],
            'toggles.*.value' => ['nullable', 'array'],
            'toggles.*.description' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['toggles'] as $toggle) {
                AdminFullConfigToggle::query()->updateOrCreate(
                    ['key' => $toggle['key']],
                    [
                        'enabled' => $toggle['enabled'],
                        'value' => $toggle['value'] ?? null,
                        'description' => $toggle['description'] ?? null,
                    ],
                );
            }
        });

        $audit->log('admin_full.config.update', null, [
            'keys' => collect($validated['toggles'])->pluck('key')->values()->all(),
        ]);

        return $this->config($audit);
    }

    private function userPayload(User $user): array
    {
        return [
            ...$user->only(['id', 'name', 'employee_id', 'email', 'section', 'designation', 'language_competencies', 'is_active', 'last_login_at', 'created_at', 'updated_at']),
            'roles' => $user->roles->pluck('name')->values(),
        ];
    }

    private function rolePayload(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'display_name' => $role->display_name,
            'permissions' => $role->permissions->pluck('name')->values(),
            'users_count' => $role->users_count ?? null,
        ];
    }

    private function validateSittingTemplate(Request $request, bool $partial = false): array
    {
        return $request->validate([
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'session_no' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'default_status' => [$partial ? 'sometimes' : 'required', Rule::in(['planned', 'live', 'closed'])],
            'metadata' => ['sometimes', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function validateSlotTemplate(Request $request, bool $partial = false): array
    {
        return $request->validate([
            'sitting_template_id' => ['sometimes', 'nullable', 'integer', 'exists:admin_full_sitting_templates,id'],
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'code_prefix' => [$partial ? 'sometimes' : 'required', 'string', 'max:20'],
            'start_offset_ms' => [$partial ? 'sometimes' : 'required', 'integer', 'min:0'],
            'duration_ms' => [$partial ? 'sometimes' : 'required', 'integer', 'min:1000'],
            'topic' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'lang_roles' => ['sometimes', 'array', 'min:1'],
            'lang_roles.*' => ['string', Rule::in($this->languages())],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function validateSitting(Request $request, bool $partial = false): array
    {
        return $request->validate([
            'session_no' => [$partial ? 'sometimes' : 'required', 'integer', 'min:1'],
            'sitting_no' => [$partial ? 'sometimes' : 'required', 'integer', 'min:1'],
            'sitting_date' => [$partial ? 'sometimes' : 'required', 'date'],
            'status' => [$partial ? 'sometimes' : 'required', Rule::in(['planned', 'live', 'closed'])],
            'started_at' => ['sometimes', 'nullable', 'date'],
            'ended_at' => ['sometimes', 'nullable', 'date'],
        ]);
    }

    private function validateMember(Request $request, bool $partial = false, ?Member $member = null): array
    {
        return $request->validate([
            'roster_id' => [$partial ? 'sometimes' : 'required', 'string', 'max:255', Rule::unique('members', 'roster_id')->ignore($member?->id)],
            'category' => [$partial ? 'sometimes' : 'required', Rule::in(['chair', 'minister', 'member'])],
            'name_en' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'name_hi' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'party' => ['sometimes', 'nullable', 'string', 'max:255'],
            'state_jur' => ['sometimes', 'nullable', 'string', 'max:255'],
            'role_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function validateCustomMember(Request $request, bool $partial = false, ?AdminFullCustomMemberMaster $member = null): array
    {
        return $request->validate([
            'reference_code' => [$partial ? 'sometimes' : 'required', 'string', 'max:255', Rule::unique('admin_full_custom_member_masters', 'reference_code')->ignore($member?->id)],
            'name_en' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'name_hi' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'role_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'state_jur' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function languages(): array
    {
        return ['en', 'hi', 'ta', 'ur', 'bn', 'mr'];
    }

    private function ensureDefaultConfig(): void
    {
        $defaults = [
            'iam.local_enabled' => ['enabled' => true, 'description' => 'Allow local IAM fallback for admin-managed users.'],
            'audit.explorer_enabled' => ['enabled' => true, 'description' => 'Expose audit explorer and hash-chain verification to admins.'],
            'workflow.allow_slot_template_apply' => ['enabled' => true, 'description' => 'Allow planned sittings to be generated from slot templates.'],
            'members.custom_master_enabled' => ['enabled' => true, 'description' => 'Allow reusable custom-member master records.'],
        ];

        foreach ($defaults as $key => $definition) {
            AdminFullConfigToggle::query()->firstOrCreate(['key' => $key], $definition);
        }
    }

    /**
     * @return array{valid: bool, rows: int, latest_hash: ?string, first_mismatch_id: ?int}
     */
    private function auditChainSummary(): array
    {
        $previousHashes = [];
        $latestHash = null;
        $rows = 0;
        $firstMismatchId = null;

        foreach (AuditLog::query()->toBase()->orderBy('id')->cursor() as $row) {
            $payload = is_string($row->payload) ? json_decode($row->payload, true, flags: JSON_THROW_ON_ERROR) : (array) $row->payload;
            $chainSegment = $row->chain_segment ?? 'on_record';
            $previousHash = $previousHashes[$chainSegment] ?? null;
            $expected = hash('sha256', AuditHash::preImage(
                $row->prev_hash,
                $row->actor_id,
                $row->actor_role,
                $row->action,
                $row->subject_type,
                $row->subject_id,
                $payload ?? [],
                $row->created_at,
                $chainSegment,
            ));

            if ($firstMismatchId === null && ($row->prev_hash !== $previousHash || $expected !== $row->this_hash)) {
                $firstMismatchId = $row->id;
            }

            $previousHashes[$chainSegment] = $row->this_hash;
            $latestHash = $row->this_hash;
            $rows++;
        }

        return [
            'valid' => $firstMismatchId === null,
            'rows' => $rows,
            'latest_hash' => $latestHash,
            'first_mismatch_id' => $firstMismatchId,
        ];
    }

    /**
     * @return array<int, bool>
     */
    private function auditValidityMap(): array
    {
        $previousHashes = [];
        $validity = [];

        foreach (AuditLog::query()->toBase()->orderBy('id')->cursor() as $row) {
            $payload = is_string($row->payload) ? json_decode($row->payload, true, flags: JSON_THROW_ON_ERROR) : (array) $row->payload;
            $chainSegment = $row->chain_segment ?? 'on_record';
            $previousHash = $previousHashes[$chainSegment] ?? null;
            $expected = hash('sha256', AuditHash::preImage(
                $row->prev_hash,
                $row->actor_id,
                $row->actor_role,
                $row->action,
                $row->subject_type,
                $row->subject_id,
                $payload ?? [],
                $row->created_at,
                $chainSegment,
            ));

            $validity[$row->id] = $row->prev_hash === $previousHash && $expected === $row->this_hash;
            $previousHashes[$chainSegment] = $row->this_hash;
        }

        return $validity;
    }
}
