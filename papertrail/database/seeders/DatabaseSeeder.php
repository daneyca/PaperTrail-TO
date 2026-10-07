<?php

namespace Database\Seeders;

use App\Models\Office;
use App\Models\Permission;
use App\Models\BudgetReview;
use App\Models\DocumentRoutingHistory;
use App\Models\ProcurementDocument;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $workflowOfficeCodes = ['SYSADMIN', 'OMM', 'MBO', 'MACCO', 'BACSEC', 'BAC'];
        $finalHeadOfficeCodes = ['MO', 'MBO', 'Accounting', 'MEO', 'MPDO', 'HRMO', 'LEGISLATIVE', 'MAO', 'MENRO', 'RHU', 'MCR', 'MTO', 'MSWDO', 'MDDRMO', 'PNP', 'BFP', 'ASSESSOR'];

        $offices = [
            ['SYSADMIN', 'System Admin', Office::TYPE_SYSTEM],
            ['OMM', "Mayor's Office", Office::TYPE_APPROVING],
            ['MBO', 'Municipal Budget Office', Office::TYPE_END_USER],
            ['MACCO', 'Accounting Office', Office::TYPE_FINANCE],
            ['BACSEC', 'BAC Secretariat', Office::TYPE_PROCUREMENT],
            ['MO', 'Office of the Municipal Mayor', Office::TYPE_END_USER],
            ['BAC', 'Bids and Awards Comittee', Office::TYPE_END_USER],
            ['Accounting', 'Municipal Accounting Office', Office::TYPE_END_USER],
            ['MEO', 'Municipal Engineering Office', Office::TYPE_END_USER],
            ['MPDO', 'Municipal Planning and Development Office', Office::TYPE_END_USER],
            ['HRMO', 'Human Resource Management Office', Office::TYPE_END_USER],
            ['LEGISLATIVE', 'Municipal Legislative Office', Office::TYPE_LEGISLATIVE],
            ['MAO', 'Municipal Agriculture Office', Office::TYPE_END_USER],
            ['MENRO', 'Municipal Environment and Natural Resources Office', Office::TYPE_END_USER],
            ['RHU', 'Rural Health Unit', Office::TYPE_END_USER],
            ['MCR', 'Municipal Civil Registrar', Office::TYPE_END_USER],
            ['MTO', 'Municipal Treasurers Office', Office::TYPE_END_USER],
            ['MSWDO', 'Municipal Social Welfare and Development Office', Office::TYPE_END_USER],
            ['MDDRMO', 'Municipal Disaster Risk Reduction and Management Office', Office::TYPE_END_USER],
            ['PNP', 'Municipal Police Station - PNP', Office::TYPE_END_USER],
            ['BFP', 'Municipal Fire Station - BFP', Office::TYPE_END_USER],
            ['ASSESSOR', 'Assessors Office', Office::TYPE_END_USER],
        ];

        foreach ($offices as [$code, $name, $type]) {
            Office::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'type' => $type,
                    'description' => null,
                    'status' => Office::STATUS_ACTIVE,
                    'is_requesting_office' => in_array($type, [Office::TYPE_END_USER, Office::TYPE_LEGISLATIVE], true) && $code !== 'BAC',
                ],
            );
        }

        Office::whereIn('type', [Office::TYPE_END_USER, Office::TYPE_LEGISLATIVE])
            ->whereNotIn('code', $finalHeadOfficeCodes)
            ->whereNotIn('code', $workflowOfficeCodes)
            ->update(['status' => Office::STATUS_INACTIVE]);

        $roleSeeds = [
            ['admin', User::ROLE_ADMIN, 'admin.dashboard', 'Full system administration and configuration access.'],
            ['head_office', User::ROLE_HEAD_OFFICE, 'head-office.dashboard', 'Submits and monitors procurement documents for assigned office.'],
            ['budget_officer', User::ROLE_BUDGET, 'budget.dashboard', 'Reviews budget availability and budget-related document requirements.'],
            ['accounting_officer', User::ROLE_ACCOUNTING, 'accounting.dashboard', 'Reviews accounting requirements and validates accounting-related document details.'],
            ['bac_secretariat', User::ROLE_BAC_SECRETARIAT, 'bac-secretariat.dashboard', 'Receives, records, routes, and monitors procurement documents.'],
            ['bac_member', User::ROLE_BAC_MEMBER, 'bac-member.dashboard', 'Reviews assigned procurement documents and BAC-related actions.'],
            ['bac_chair', User::ROLE_BAC_CHAIR, 'bac-chair.dashboard', 'Confirms BAC-level reviews and actions.'],
            ['bac_vice_chairperson', User::ROLE_BAC_VICE_CHAIRPERSON, 'bac-chair.dashboard', 'Reviews and signs BAC documents using the BAC Chair workspace.'],
            ['approving_authority', User::ROLE_APPROVING_AUTHORITY, 'approving-authority.dashboard', 'Final authorized approval role for procurement documents.'],
            ['pr_numbering_staff', User::ROLE_PR_NUMBERING, 'pr-numbering.dashboard', 'Assigns official LGU Purchase Request numbers before BAC Secretariat validation.'],
        ];

        foreach ($roleSeeds as [$code, $name, $dashboardRoute, $description]) {
            Role::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'dashboard_route' => $dashboardRoute,
                    'description' => $description,
                    'status' => Role::STATUS_ACTIVE,
                    'is_system' => true,
                ],
            );
        }

        $permissionSeeds = [
            'Administration' => ['admin.dashboard.view', 'admin.users.manage', 'admin.offices.manage', 'admin.roles.manage', 'admin.settings.manage'],
            'Dashboard' => ['dashboard.view'],
            'User/Profile' => ['profile.view', 'profile.update'],
            'Notifications' => ['notifications.view', 'notifications.manage'],
            'Documents' => ['documents.view.own', 'documents.view.assigned', 'documents.view.all', 'documents.track', 'documents.submit', 'documents.edit.own', 'documents.upload', 'documents.return', 'documents.comment', 'documents.approve'],
            'PPMP' => ['ppmp.submit', 'ppmp.view', 'ppmp.review', 'ppmp.approve'],
            'APP' => ['app.view', 'app.consolidate', 'app.approve'],
            'Purchase Request' => ['pr.submit', 'pr.view', 'pr.review', 'pr.approve'],
            'PR Numbering' => ['pr-number.view', 'pr-number.assign', 'pr-number.return'],
            'BAC Resolution' => ['bac.resolution.view', 'bac.resolution.create', 'bac.resolution.update', 'bac.resolution.submit', 'bac.resolution.review', 'bac.resolution.confirm', 'bac.resolution.return', 'bac.resolution.forward_to_hope'],
            'Purchase Order' => ['po.view', 'po.prepare', 'po.approve'],
            'Workflow' => ['workflow.route', 'workflow.update_status', 'workflow.view_history'],
            'Review' => ['review.budget', 'review.accounting', 'review.bac'],
            'Electronic Signature' => ['esignature.use'],
            'Reports' => ['reports.view', 'reports.admin', 'reports.procurement', 'reports.budget', 'reports.accounting', 'reports.approval'],
            'Audit Trail' => ['audit.view'],
        ];

        foreach ($permissionSeeds as $group => $keys) {
            foreach ($keys as $key) {
                Permission::updateOrCreate(
                    ['key' => $key],
                    [
                        'name' => str($key)->replace(['.', '_'], ' ')->title()->toString(),
                        'group' => $group,
                        'description' => null,
                    ],
                );
            }
        }

        $assignments = [
            'admin' => Permission::pluck('key')->all(),
            'head_office' => ['dashboard.view', 'profile.view', 'profile.update', 'notifications.view', 'documents.view.own', 'documents.track', 'documents.submit', 'documents.edit.own', 'documents.upload', 'documents.comment', 'ppmp.submit', 'ppmp.view', 'pr.submit', 'pr.view'],
            'budget_officer' => ['dashboard.view', 'profile.view', 'profile.update', 'notifications.view', 'documents.view.assigned', 'documents.track', 'documents.return', 'documents.comment', 'review.budget', 'reports.budget'],
            'accounting_officer' => ['dashboard.view', 'profile.view', 'profile.update', 'notifications.view', 'documents.view.assigned', 'documents.track', 'documents.return', 'documents.comment', 'review.accounting', 'reports.accounting'],
            'bac_secretariat' => ['dashboard.view', 'profile.view', 'profile.update', 'notifications.view', 'documents.view.all', 'documents.track', 'documents.return', 'documents.comment', 'workflow.route', 'workflow.update_status', 'workflow.view_history', 'ppmp.review', 'app.view', 'app.consolidate', 'pr.view', 'pr.review', 'bac.resolution.view', 'bac.resolution.create', 'bac.resolution.update', 'bac.resolution.submit', 'po.view', 'po.prepare', 'reports.procurement', 'audit.view'],
            'bac_member' => ['dashboard.view', 'profile.view', 'profile.update', 'notifications.view', 'documents.view.assigned', 'documents.track', 'documents.comment', 'documents.return', 'review.bac', 'ppmp.review', 'pr.review'],
            'bac_chair' => ['dashboard.view', 'profile.view', 'profile.update', 'notifications.view', 'documents.view.assigned', 'documents.track', 'documents.comment', 'documents.return', 'review.bac', 'workflow.update_status', 'workflow.view_history', 'ppmp.review', 'app.approve', 'pr.review', 'bac.resolution.view', 'bac.resolution.review', 'bac.resolution.confirm', 'bac.resolution.return', 'bac.resolution.forward_to_hope', 'esignature.use', 'reports.procurement'],
            'bac_vice_chairperson' => ['dashboard.view', 'profile.view', 'profile.update', 'notifications.view', 'documents.view.assigned', 'documents.track', 'documents.comment', 'documents.return', 'review.bac', 'workflow.update_status', 'workflow.view_history', 'ppmp.review', 'app.approve', 'pr.review', 'bac.resolution.view', 'bac.resolution.review', 'bac.resolution.confirm', 'bac.resolution.return', 'bac.resolution.forward_to_hope', 'esignature.use', 'reports.procurement'],
            'approving_authority' => ['dashboard.view', 'profile.view', 'profile.update', 'notifications.view', 'documents.view.assigned', 'documents.track', 'documents.comment', 'documents.return', 'documents.approve', 'workflow.update_status', 'workflow.view_history', 'ppmp.approve', 'app.approve', 'pr.approve', 'po.approve', 'esignature.use', 'reports.approval'],
            'pr_numbering_staff' => ['dashboard.view', 'profile.view', 'profile.update', 'notifications.view', 'documents.track', 'pr-number.view', 'pr-number.assign', 'pr-number.return'],
        ];

        foreach ($assignments as $roleCode => $permissionKeys) {
            $role = Role::where('code', $roleCode)->first();
            $permissionIds = Permission::whereIn('key', $permissionKeys)->pluck('id')->all();
            $role?->permissions()->sync($permissionIds);
        }

        $settingSeeds = [
            ['system.name', 'general', 'System Name', 'PaperTrail', 'text', null, 'Public system name shown across authenticated pages.', true, true, 10],
            ['system.full_title', 'general', 'Full System Title', 'PaperTrail: AI-Assisted Procurement Management and Information System', 'textarea', null, 'Official capstone system title.', true, false, 20],
            ['system.environment_label', 'general', 'Environment Label', 'Capstone Prototype', 'text', null, 'Short label for the current deployment environment.', false, false, 30],
            ['system.timezone', 'general', 'Timezone', 'Asia/Manila', 'text', null, 'Default timezone used for administrative displays.', false, false, 40],
            ['lgu.name', 'lgu', 'LGU Name', 'Municipality of Tomas Oppus', 'text', null, 'Name used in reports and system headers.', true, false, 10],
            ['lgu.province', 'lgu', 'Province', 'Southern Leyte', 'text', null, 'Province or local government area.', true, false, 20],
            ['lgu.address', 'lgu', 'Office Address', 'Municipal Hall, Tomas Oppus, Southern Leyte', 'textarea', null, 'Primary LGU office address.', true, false, 30],
            ['lgu.contact_email', 'lgu', 'Contact Email', 'procurement@example.gov.ph', 'email', null, 'Administrative contact email for report footers.', false, false, 40],
            ['lgu.contact_number', 'lgu', 'Contact Number', '', 'text', null, 'Administrative contact number.', false, false, 50],
            ['lgu.logo_path', 'lgu', 'Logo Path', '', 'text', null, 'Stored logo path used by the upload control.', false, true, 60],
            ['security.max_failed_attempts', 'security', 'Maximum Failed Login Attempts', '5', 'number', null, 'Reference value for future account lockout behavior.', false, false, 10],
            ['security.session_lifetime_minutes', 'security', 'Session Lifetime Minutes', '120', 'number', null, 'Reference session lifetime for authenticated users.', false, false, 20],
            ['security.password_reset_default', 'security', 'Default Reset Password', 'password123', 'text', null, 'Default password used by the current fixed-account reset flow.', false, false, 30],
            ['security.maintenance_login_message', 'security', 'Login Notice', 'Use your assigned pre-registered User ID to access PaperTrail.', 'textarea', null, 'Optional notice for administrators to display in future login messaging.', false, false, 40],
            ['reports.default_format', 'reports', 'Default Export Format', 'csv', 'select', [['value' => 'csv', 'label' => 'CSV']], 'Default report export format for current admin reports.', false, false, 10],
            ['reports.include_lgu_header', 'reports', 'Include LGU Header', '1', 'boolean', null, 'Include LGU information in report print views.', false, false, 20],
            ['reports.records_per_page', 'reports', 'Records Per Page', '10', 'number', null, 'Reference page size for future report pagination configuration.', false, false, 30],
            ['uploads.max_file_size_mb', 'uploads', 'Maximum File Size (MB)', '10', 'number', null, 'Reserved setting for future document uploads.', false, false, 10],
            ['uploads.allowed_file_types', 'uploads', 'Allowed File Types', 'pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'text', null, 'Reserved file type list for future upload validation.', false, false, 20],
            ['procurement.default_tracking_prefix', 'procurement', 'Default Tracking Prefix', 'PT', 'text', null, 'Reserved prefix for future procurement tracking numbers.', false, false, 10],
            ['procurement.fiscal_year', 'procurement', 'Fiscal Year', now()->format('Y'), 'number', null, 'Reference fiscal year for future procurement records.', false, false, 20],
            ['maintenance.mode', 'maintenance', 'Maintenance Mode', '0', 'boolean', null, 'Administrative flag reserved for future maintenance controls.', false, false, 10],
            ['maintenance.notice', 'maintenance', 'Maintenance Notice', '', 'textarea', null, 'Optional internal maintenance notice.', false, false, 20],
            ['maintenance.backup_reminder', 'maintenance', 'Backup Reminder', 'Review database backup procedures before production deployment.', 'textarea', null, 'Administrative reminder for capstone/prototype maintenance.', false, false, 30],
        ];

        foreach ($settingSeeds as [$key, $group, $label, $value, $type, $options, $description, $isPublic, $isLocked, $sortOrder]) {
            $setting = SystemSetting::firstOrNew(['key' => $key]);

            if (! $setting->exists) {
                $setting->value = $value;
            }

            $setting->fill([
                'group' => $group,
                'label' => $label,
                'type' => $type,
                'options' => $options,
                'description' => $description,
                'is_public' => $isPublic,
                'is_locked' => $isLocked,
                'sort_order' => $sortOrder,
            ])->save();
        }

        $password = Hash::make('password123');

        $workflowUsers = [
            ['ADMIN-001', 'System Administrator', 'SYSADMIN', User::ROLE_ADMIN],
            ['HOPE-001', 'Head of the Procuring Entity', 'MO', User::ROLE_APPROVING_AUTHORITY],
            ['BUDGET-001', 'Budget Office', 'MBO', User::ROLE_BUDGET],
            ['ACCOUNTING-001', 'Accounting Office', 'MACCO', User::ROLE_ACCOUNTING],
            ['BACSEC-001', 'BAC Secretariat', 'BACSEC', User::ROLE_BAC_SECRETARIAT],
            ['BACMEM-001', 'BAC Member', 'BAC', User::ROLE_BAC_MEMBER],
            ['BACMEM-002', 'BAC Member', 'BAC', User::ROLE_BAC_MEMBER],
            ['BACMEM-003', 'BAC Member', 'BAC', User::ROLE_BAC_MEMBER],
            ['BACCHAIR-001', 'BAC Chair', 'BAC', User::ROLE_BAC_CHAIR],
            ['BACVICE-001', 'BAC Vice Chairperson', 'BAC', User::ROLE_BAC_VICE_CHAIRPERSON],
            ['PRNO-001', 'PR Numbering Staff', 'MEO', User::ROLE_PR_NUMBERING],
        ];

        foreach ($workflowUsers as [$userId, $name, $officeCode, $role]) {
            $office = Office::where('code', $officeCode)->first();
            $assignedRole = Role::where('name', $role)->first();
            $existingUser = User::where('user_id', $userId)->first();

            User::updateOrCreate(
                ['user_id' => $userId],
                [
                    'name' => $name,
                    'office_id' => $office?->id,
                    'office' => $office?->name,
                    'role_id' => $assignedRole?->id,
                    'role' => $role,
                    'password' => $existingUser?->password ?? $password,
                    'status' => User::STATUS_ACTIVE,
                ],
            );
        }

        User::where('role', User::ROLE_PR_NUMBERING)
            ->where('user_id', '!=', 'PRNO-001')
            ->update(['status' => User::STATUS_INACTIVE]);

        $finalHeadOfficeUsers = [
            ['MO-001', 'Office of the Municipal Mayor', 'MO'],
            ['MBO-001', 'Municipal Budget Office', 'MBO'],
            ['ACC-001', 'Municipal Accounting Office', 'Accounting'],
            ['MEO-001', 'Municipal Engineering Office', 'MEO'],
            ['MPDO-001', 'Municipal Planning and Development Office', 'MPDO'],
            ['HRMO-001', 'Human Resource Management Office', 'HRMO'],
            ['LEGISLATIVE-0011', 'Municipal Legislative Office', 'LEGISLATIVE'],
            ['MAO-001', 'Municipal Agriculture Office', 'MAO'],
            ['MENRO-001', 'Municipal Environment and Natural Resources Office', 'MENRO'],
            ['RHU-001', 'Rural Health Unit', 'RHU'],
            ['MCR-001', 'Municipal Civil Registrar', 'MCR'],
            ['MTO-001', 'Municipal Treasurers Office', 'MTO'],
            ['MSDWO-001', 'Municipal Social Welfare and Development Office', 'MSWDO'],
            ['MDRRMO-001', 'Municipal Disaster Risk Reduction and Management Office', 'MDDRMO'],
            ['PNP-001', 'Municipal Police Station - PNP', 'PNP'],
            ['BFP-001', 'Municipal Fire Station - BFP', 'BFP'],
            ['ASSESSOR-001', 'Assessors Office', 'ASSESSOR'],
        ];

        $finalHeadOfficeUserIds = collect($finalHeadOfficeUsers)->pluck(0)->all();
        $headOfficeRole = Role::where('name', User::ROLE_HEAD_OFFICE)->first();

        foreach ($finalHeadOfficeUsers as [$userId, $name, $officeCode]) {
            $existing = User::where('user_id', $userId)->first();

            if ($existing && $existing->role !== User::ROLE_HEAD_OFFICE) {
                $this->command?->warn("Skipped {$userId}: existing account role is {$existing->role}, not Head of Office / End User.");
                continue;
            }

            $office = Office::where('code', $officeCode)->first();

            User::updateOrCreate(
                ['user_id' => $userId],
                [
                    'name' => $name,
                    'office_id' => $office?->id,
                    'office' => $office?->name,
                    'role_id' => $headOfficeRole?->id,
                    'role' => User::ROLE_HEAD_OFFICE,
                    'password' => $existing?->password ?? $password,
                    'status' => User::STATUS_ACTIVE,
                ],
            );
        }

        User::where('role', User::ROLE_HEAD_OFFICE)
            ->whereNotIn('user_id', $finalHeadOfficeUserIds)
            ->update(['status' => User::STATUS_INACTIVE]);

        User::whereNull('office_id')
            ->whereNotNull('office')
            ->get()
            ->each(function (User $user) {
                $office = Office::where('name', $user->office)->first();

                if ($office) {
                    $user->update(['office_id' => $office->id]);
                }
            });

        User::whereNull('role_id')
            ->whereNotNull('role')
            ->get()
            ->each(function (User $user) {
                $role = Role::where('name', $user->role)
                    ->orWhere('code', $user->role)
                    ->first();

                if ($role) {
                    $user->update(['role_id' => $role->id]);
                }
            });

        $demoDocumentTrackingNumbers = [
            'PR-2026-MEO-0001',
            'PR-2026-RHU-0001',
            'PR-2026-MDRRMO-0001',
            'PR-2026-MSWDO-0001',
        ];

        $seedDemoDocuments = filter_var(env('SEED_DEMO_DOCUMENTS', false), FILTER_VALIDATE_BOOLEAN);

        if (class_exists(ProcurementDocument::class) && ! $seedDemoDocuments) {
            ProcurementDocument::whereIn('tracking_number', $demoDocumentTrackingNumbers)
                ->get()
                ->each(function (ProcurementDocument $document) {
                    $document->budgetReviews()->delete();
                    $document->routingHistories()->delete();
                    $document->delete();
                });
        }

        if (class_exists(ProcurementDocument::class) && $seedDemoDocuments) {
            $budgetOffice = Office::where('code', 'MBO')->first();
            $budgetOfficer = User::where('user_id', 'BUDGET-001')->first();

            $samples = [
                ['PR-2026-MEO-0001', 'Purchase Request', 'Road maintenance materials and supplies', 'MEO', 'MEO-001', ProcurementDocument::STATUS_PENDING_BUDGET_REVIEW, 'normal', 285000.00],
                ['PR-2026-RHU-0001', 'Purchase Request', 'Medical supplies for rural health services', 'RHU', 'RHU-001', ProcurementDocument::STATUS_PENDING_BUDGET_REVIEW, 'high', 162500.00],
                ['PR-2026-MDRRMO-0001', 'Purchase Request', 'Disaster response equipment replenishment', 'MDDRMO', 'MDRRMO-001', ProcurementDocument::STATUS_UNDER_BUDGET_REVIEW, 'urgent', 418750.00],
                ['PR-2026-MSWDO-0001', 'Purchase Request', 'Social welfare assistance supplies', 'MSWDO', 'MSDWO-001', ProcurementDocument::STATUS_PENDING_BUDGET_REVIEW, 'normal', 98750.00],
            ];

            foreach ($samples as [$tracking, $type, $title, $officeCode, $userId, $status, $priority, $amount]) {
                $office = Office::where('code', $officeCode)->first();
                $submitter = User::where('user_id', $userId)->first();

                $document = ProcurementDocument::updateOrCreate(
                    ['tracking_number' => $tracking],
                    [
                        'document_type' => $type,
                        'title' => $title,
                        'description' => 'Demo procurement document for Budget Officer pending review testing.',
                        'fiscal_year' => 2026,
                        'submitting_office_id' => $office?->id,
                        'submitted_by_user_id' => $submitter?->id,
                        'current_office_id' => $budgetOffice?->id,
                        'assigned_to_user_id' => $status === ProcurementDocument::STATUS_UNDER_BUDGET_REVIEW ? $budgetOfficer?->id : null,
                        'status' => $status,
                        'stage' => ProcurementDocument::STAGE_BUDGET_REVIEW,
                        'priority' => $priority,
                        'total_amount' => $amount,
                        'submitted_at' => now()->subDays(rand(2, 9)),
                        'budget_status' => $status === ProcurementDocument::STATUS_UNDER_BUDGET_REVIEW ? BudgetReview::STATUS_UNDER_REVIEW : BudgetReview::STATUS_PENDING,
                        'budget_review_started_at' => $status === ProcurementDocument::STATUS_UNDER_BUDGET_REVIEW ? now()->subDay() : null,
                    ],
                );

                if ($status === ProcurementDocument::STATUS_UNDER_BUDGET_REVIEW) {
                    BudgetReview::updateOrCreate(
                        [
                            'procurement_document_id' => $document->id,
                            'review_status' => BudgetReview::STATUS_UNDER_REVIEW,
                        ],
                        [
                            'reviewed_by_user_id' => $budgetOfficer?->id,
                            'requested_amount' => $amount,
                            'started_at' => $document->budget_review_started_at,
                        ],
                    );
                }

                DocumentRoutingHistory::updateOrCreate(
                    [
                        'procurement_document_id' => $document->id,
                        'action' => 'Routed to Budget Office',
                    ],
                    [
                        'action_by_user_id' => $submitter?->id,
                        'from_office_id' => $office?->id,
                        'to_office_id' => $budgetOffice?->id,
                        'status_from' => 'submitted',
                        'status_to' => $status,
                        'comments' => 'Demo document routed to Budget Office.',
                        'action_at' => $document->submitted_at ?? now(),
                    ],
                );
            }
        }

        $this->call(DocumentTemplateSeeder::class);
    }
}
