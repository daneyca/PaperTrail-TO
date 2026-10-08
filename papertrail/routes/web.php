<?php

use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminOfficeController;
use App\Http\Controllers\Admin\AdminRoleController;
use App\Http\Controllers\Admin\AdminAuditTrailController;
use App\Http\Controllers\Admin\AdminReportController;
use App\Http\Controllers\Admin\AdminSettingsController;
use App\Http\Controllers\Admin\RuleConfigurationController;
use App\Http\Controllers\Admin\SvpChainController as AdminSvpChainController;
use App\Http\Controllers\Accounting\AccountingReportController;
use App\Http\Controllers\Accounting\PendingAccountingReviewController as AccountingPendingReviewController;
use App\Http\Controllers\Accounting\ReviewedDocumentsController as AccountingReviewedDocumentsController;
use App\Http\Controllers\Accounting\ReturnedDocumentsController as AccountingReturnedDocumentsController;
use App\Http\Controllers\AiDelayRiskController;
use App\Http\Controllers\AiDocumentCompletenessController;
use App\Http\Controllers\AiDocumentVerificationController;
use App\Http\Controllers\AiMetadataExtractionController;
use App\Http\Controllers\AiRouteValidationController;
use App\Http\Controllers\AiSummaryDashboardController;
use App\Http\Controllers\AnnualProcurementPlanController;
use App\Http\Controllers\Auth\CaptchaController;
use App\Http\Controllers\Auth\EmailVerificationCodeController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\BacSecretariat\AnnualProcurementPlanController as BacAnnualProcurementPlanController;
use App\Http\Controllers\BacSecretariat\AuditTrailController as BacAuditTrailController;
use App\Http\Controllers\BacSecretariat\DocumentRoutingController as BacDocumentRoutingController;
use App\Http\Controllers\BacSecretariat\IncomingDocumentsController as BacIncomingDocumentsController;
use App\Http\Controllers\BacSecretariat\PpmpReviewController as BacPpmpReviewController;
use App\Http\Controllers\BacSecretariat\PurchaseRequestController as BacPurchaseRequestController;
use App\Http\Controllers\BacSecretariat\PurchaseOrderController as BacPurchaseOrderController;
use App\Http\Controllers\BacSecretariat\BacResolutionController as BacResolutionController;
use App\Http\Controllers\BacSecretariat\RfqController as BacRfqController;
use App\Http\Controllers\BacSecretariat\AbstractController as BacAbstractController;
use App\Http\Controllers\BacSecretariat\ReportController as BacReportController;
use App\Http\Controllers\BacSecretariat\SupplementalAppController as BacSupplementalAppController;
use App\Http\Controllers\BacSecretariat\SvpMonitoringController as BacSvpMonitoringController;
use App\Http\Controllers\BacSecretariat\SvpPostingController as BacSvpPostingController;
use App\Http\Controllers\BacMember\DocumentsForReviewController as BacMemberReviewController;
use App\Http\Controllers\BacMember\ReviewedDocumentsController as BacMemberReviewedController;
use App\Http\Controllers\BacMember\BacDeliberationController as BacMemberDeliberationController;
use App\Http\Controllers\BacChair\BacApprovalController as BacChairApprovalController;
use App\Http\Controllers\BacChair\DocumentConfirmationController as BacChairConfirmationController;
use App\Http\Controllers\BacChair\ReviewedDocumentsController as BacChairReviewedController;
use App\Http\Controllers\BacChair\BacResolutionController as BacChairResolutionController;
use App\Http\Controllers\BacChair\ReportController as BacChairReportController;
use App\Http\Controllers\ApprovingAuthority\PendingApprovalController as ApprovingAuthorityPendingController;
use App\Http\Controllers\ApprovingAuthority\ApprovedDocumentsController as ApprovingAuthorityApprovedController;
use App\Http\Controllers\ApprovingAuthority\ReturnedDocumentsController as ApprovingAuthorityReturnedController;
use App\Http\Controllers\ApprovingAuthority\ReportController as ApprovingAuthorityReportController;
use App\Http\Controllers\Budget\BudgetReportController;
use App\Http\Controllers\Budget\PendingBudgetReviewController;
use App\Http\Controllers\Budget\ReviewedDocumentsController;
use App\Http\Controllers\Budget\ReturnedDocumentsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardEventController;
use App\Http\Controllers\DocumentAttachmentController;
use App\Http\Controllers\DocumentSubmenuController;
use App\Http\Controllers\DocumentTemplateController;
use App\Http\Controllers\DocumentTemplateImportController;
use App\Http\Controllers\EmailActivityController;
use App\Http\Controllers\HeadOffice\MyDocumentController as HeadOfficeDocumentController;
use App\Http\Controllers\HeadOffice\AbstractController as HeadOfficeAbstractController;
use App\Http\Controllers\HeadOffice\InspectionAcceptanceController as HeadOfficeInspectionAcceptanceController;
use App\Http\Controllers\HeadOffice\PpmpController as HeadOfficePpmpController;
use App\Http\Controllers\HeadOffice\PurchaseRequestController as HeadOfficePurchaseRequestController;
use App\Http\Controllers\HeadOffice\PurchaseOrderController as HeadOfficePurchaseOrderController;
use App\Http\Controllers\HeadOffice\ReceivedBacResolutionController as HeadOfficeReceivedBacResolutionController;
use App\Http\Controllers\HeadOffice\ReturnedDocumentController as HeadOfficeReturnedDocumentController;
use App\Http\Controllers\HeadOffice\RfqController as HeadOfficeRfqController;
use App\Http\Controllers\HeadOffice\SupplementalAppController as HeadOfficeSupplementalAppController;
use App\Http\Controllers\HeadOffice\SvpTrackingController as HeadOfficeSvpTrackingController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PpmpController as OfficialPpmpController;
use App\Http\Controllers\ProcurementChatbotController;
use App\Http\Controllers\PrNumbering\PrNumberingController;
use App\Http\Controllers\ElectronicSignatureController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfileSignatureController;
use App\Http\Controllers\SignatureRequestController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
})->name('health');

Route::get('/', function () {
    if (Auth::check()) {
        $lastAuthenticatedUrl = session('last_authenticated_url');
        $noCacheHeaders = [
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ];

        if (is_string($lastAuthenticatedUrl) && str_starts_with($lastAuthenticatedUrl, url('/'))) {
            return redirect()
                ->to($lastAuthenticatedUrl)
                ->withHeaders($noCacheHeaders);
        }

        return redirect()
            ->route(request()->user()->dashboardRoute())
            ->withHeaders($noCacheHeaders);
    }

    return view('pages.landing');
});

Route::middleware(['guest', 'no.cache'])->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
    Route::get('/captcha', [CaptchaController::class, 'image'])->name('captcha.image');
    Route::get('/captcha/refresh', [CaptchaController::class, 'refresh'])->name('captcha.refresh');
    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('password.update');
});

Route::middleware(['no.cache', 'auth', 'password.changed', 'remember.page'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::post('/switch-account', [LoginController::class, 'switchAccount'])->name('switch-account');
    Route::post('/session/timeout', [LoginController::class, 'timeout'])->name('session.timeout');
    Route::post('/session/heartbeat', function () {
        request()->session()->put('last_activity_ping_at', now()->timestamp);

        return response()->noContent();
    })->middleware('throttle:30,1')->name('session.heartbeat');
    Route::post('/email/verification-code/send', [EmailVerificationCodeController::class, 'send'])
        ->middleware('throttle:6,1')
        ->name('verification.code.send');
    Route::post('/email/verification-code/verify', [EmailVerificationCodeController::class, 'verify'])
        ->middleware('throttle:10,1')
        ->name('verification.code.verify');
    Route::get('/dashboard', [DashboardController::class, 'redirect'])->name('dashboard');
    Route::get('/dashboard-events', [DashboardEventController::class, 'index'])->name('dashboard-events.index');
    Route::post('/dashboard-events', [DashboardEventController::class, 'store'])->name('dashboard-events.store');
    Route::patch('/dashboard-events/{event}', [DashboardEventController::class, 'update'])->name('dashboard-events.update');
    Route::delete('/dashboard-events/{event}', [DashboardEventController::class, 'destroy'])->name('dashboard-events.destroy');
    Route::get('/email-activity', [EmailActivityController::class, 'index'])->name('email-activity.index');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::delete('/notifications/clear-read', [NotificationController::class, 'clearRead'])->name('notifications.clear-read');
    Route::get('/notifications/{notification}', [NotificationController::class, 'show'])->name('notifications.show');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
    Route::delete('/profile/photo', [ProfileController::class, 'destroyPhoto'])->name('profile.photo.destroy');
    Route::get('/profile/signature', [ProfileSignatureController::class, 'edit'])->name('profile.signature.edit');
    Route::post('/profile/signature', [ProfileSignatureController::class, 'update'])->name('profile.signature.update');
    Route::delete('/profile/signature/image', [ProfileSignatureController::class, 'destroyImage'])->name('profile.signature.image.destroy');
    Route::get('/profile/signature/image/{user}', [ProfileSignatureController::class, 'showImage'])->name('profile.signature.image.show');

    Route::get('/ai-document-verification', [AiDocumentVerificationController::class, 'index'])
        ->name('ai-document-verification.index');
    Route::post('/ai-document-verification/upload', [AiDocumentVerificationController::class, 'upload'])
        ->name('ai-document-verification.upload');
    Route::post('/ai/document-completeness/check', [AiDocumentCompletenessController::class, 'check'])
        ->middleware('throttle:12,1')
        ->name('ai.document-completeness.check');
    Route::post('/ai/route-validation/check', [AiRouteValidationController::class, 'check'])
        ->middleware('throttle:12,1')
        ->name('ai.route-validation.check');
    Route::post('/ai/delay-risk/analyze/{documentType}/{documentId}', [AiDelayRiskController::class, 'analyze'])
        ->middleware('throttle:12,1')
        ->name('ai.delay-risk.analyze');
    Route::post('/ai/metadata/extract/{attachment}', [AiMetadataExtractionController::class, 'extract'])
        ->middleware('throttle:10,1')
        ->name('ai.metadata.extract');
    Route::post('/ai/metadata/{metadata}/accept', [AiMetadataExtractionController::class, 'accept'])
        ->middleware('throttle:20,1')
        ->name('ai.metadata.accept');
    Route::post('/ai/metadata/{metadata}/reject', [AiMetadataExtractionController::class, 'reject'])
        ->middleware('throttle:20,1')
        ->name('ai.metadata.reject');

    Route::get('/assistant', [ProcurementChatbotController::class, 'index'])->name('assistant.index');
    Route::post('/assistant/message', [ProcurementChatbotController::class, 'send'])
        ->middleware('throttle:20,1')
        ->name('assistant.message');
    Route::get('/assistant/history/{conversation}', [ProcurementChatbotController::class, 'history'])
        ->name('assistant.history');

    Route::get('/document-templates/{documentType}/download', [DocumentTemplateController::class, 'download'])
        ->where('documentType', '[A-Za-z0-9_-]+')
        ->name('document-templates.download');
    Route::get('/document-template-imports/{documentType}/upload', [DocumentTemplateImportController::class, 'uploadForm'])
        ->where('documentType', '[A-Za-z0-9_-]+')
        ->name('document-template-imports.upload-form');
    Route::post('/document-template-imports/{documentType}/upload', [DocumentTemplateImportController::class, 'upload'])
        ->where('documentType', '[A-Za-z0-9_-]+')
        ->name('document-template-imports.upload');

    Route::middleware('role:head-office')->prefix('head-office')->name('head-office.')->group(function () {
        Route::get('/ppmps/menu', [DocumentSubmenuController::class, 'headOfficePpmp'])->name('ppmps.menu');
        Route::get('/purchase-requests/menu', [DocumentSubmenuController::class, 'headOfficePurchaseRequest'])->name('purchase-requests.menu');
        Route::get('/my-documents/menu', [DocumentSubmenuController::class, 'headOfficeMyDocuments'])->name('my-documents.menu');
        Route::get('/returned-documents/menu', [DocumentSubmenuController::class, 'headOfficeReturnedDocuments'])->name('returned-documents.menu');
        Route::get('/received-bac-resolutions/menu', [DocumentSubmenuController::class, 'headOfficeReceivedBacResolutions'])->name('resolutions.menu');
        Route::get('/rfqs/menu', [DocumentSubmenuController::class, 'headOfficeRfq'])->name('rfqs.menu');
        Route::get('/abstracts/menu', [DocumentSubmenuController::class, 'headOfficeAbstract'])->name('abstracts.menu');
        Route::get('/purchase-orders/menu', [DocumentSubmenuController::class, 'headOfficePurchaseOrder'])->name('purchase-orders.menu');
        Route::get('/inspection-acceptance/menu', [DocumentSubmenuController::class, 'headOfficeInspectionAcceptance'])->name('inspection.menu');
    });

    Route::middleware('role:bac-secretariat')->prefix('bac-secretariat')->name('bac-secretariat.')->group(function () {
        Route::get('/incoming-documents/menu', [DocumentSubmenuController::class, 'bacSecretariatIncomingDocuments'])->name('incoming.menu');
        Route::get('/document-routing/menu', [DocumentSubmenuController::class, 'bacSecretariatDocumentRouting'])->name('routing.menu');
        Route::get('/ppmp-review/menu', [DocumentSubmenuController::class, 'bacSecretariatPpmpReview'])->name('ppmp.menu');
        Route::get('/apps/menu', [DocumentSubmenuController::class, 'bacSecretariatApp'])->name('apps.menu');
        Route::get('/supplemental-apps/menu', [DocumentSubmenuController::class, 'bacSecretariatSupplementalApp'])->name('supplemental-apps.menu');
        Route::get('/purchase-requests/menu', [DocumentSubmenuController::class, 'bacSecretariatPurchaseRequest'])->name('pr.menu');
        Route::get('/rfqs/menu', [DocumentSubmenuController::class, 'bacSecretariatRfq'])->name('rfqs.menu');
        Route::get('/abstracts/menu', [DocumentSubmenuController::class, 'bacSecretariatAbstract'])->name('abstracts.menu');
        Route::get('/bac-resolutions/menu', [DocumentSubmenuController::class, 'bacSecretariatBacResolution'])->name('resolutions.menu');
        Route::get('/purchase-orders/menu', [DocumentSubmenuController::class, 'bacSecretariatPurchaseOrder'])->name('purchase-orders.menu');
        Route::get('/competitive-bidding/menu', [DocumentSubmenuController::class, 'bacSecretariatCompetitiveBidding'])->name('competitive-bidding.menu');
        Route::get('/competitive-bidding/checklist-requirements/technical', [DocumentSubmenuController::class, 'bacSecretariatTechnicalChecklist'])->name('competitive-bidding.checklist.technical');
        Route::get('/competitive-bidding/checklist-requirements/financial', [DocumentSubmenuController::class, 'bacSecretariatFinancialChecklist'])->name('competitive-bidding.checklist.financial');
        Route::get('/competitive-bidding/abstract-of-bids/as-read', [BacAbstractController::class, 'createBidsAsRead'])->name('competitive-bidding.abstract-bids.as-read');
        Route::get('/competitive-bidding/abstract-of-bids/as-calculated', [BacAbstractController::class, 'createBidsAsCalculated'])->name('competitive-bidding.abstract-bids.as-calculated');
        Route::get('/competitive-bidding/bid-evaluation', [DocumentSubmenuController::class, 'bacSecretariatBidEvaluation'])->name('competitive-bidding.bid-evaluation');
        Route::get('/competitive-bidding/post-qualification-evaluation', [DocumentSubmenuController::class, 'bacSecretariatPostQualificationEvaluation'])->name('competitive-bidding.post-qualification-evaluation');
    });

    Route::get('/budget/review/menu', [DocumentSubmenuController::class, 'budgetReview'])
        ->middleware('role:budget')
        ->name('budget.review.menu');

    Route::get('/accounting/review/menu', [DocumentSubmenuController::class, 'accountingReview'])
        ->middleware('role:accounting')
        ->name('accounting.review.menu');

    Route::get('/pr-numbering/menu', [DocumentSubmenuController::class, 'prNumbering'])
        ->middleware('role:pr-numbering')
        ->name('pr-numbering.menu');

    Route::get('/bac-member/reviews/menu', [DocumentSubmenuController::class, 'bacMemberReviews'])
        ->middleware('role:bac-member')
        ->name('bac-member.reviews.menu');

    Route::get('/bac-chair/reviews/menu', [DocumentSubmenuController::class, 'bacChairReviews'])
        ->middleware('role:bac-chair')
        ->name('bac-chair.reviews.menu');

    Route::get('/approving-authority/approvals/menu', [DocumentSubmenuController::class, 'approvingAuthorityApprovals'])
        ->middleware('role:approving-authority')
        ->name('approving-authority.approvals.menu');

    Route::get('/admin/management/menu', [DocumentSubmenuController::class, 'adminManagement'])
        ->middleware('role:admin')
        ->name('admin.management.menu');

    Route::post('/e-signatures/{documentType}/{documentId}/send-code', [ElectronicSignatureController::class, 'sendCode'])->name('e-signatures.send-code');
    Route::post('/e-signatures/{documentType}/{documentId}/sign', [ElectronicSignatureController::class, 'sign'])->name('e-signatures.sign');
    Route::post('/e-signatures/{documentType}/{documentId}/decline', [ElectronicSignatureController::class, 'decline'])->name('e-signatures.decline');
    Route::get('/e-signatures/{signature}/image', [ElectronicSignatureController::class, 'image'])->name('e-signatures.image');
    Route::get('/e-signatures/{signature}/certificate', [ElectronicSignatureController::class, 'certificate'])->name('e-signatures.certificate');

    Route::post('/documents/{documentType}/{documentId}/attachments', [DocumentAttachmentController::class, 'store'])
        ->where(['documentType' => '[A-Za-z0-9_-]+', 'documentId' => '[0-9]+'])
        ->name('document-attachments.store');
    Route::get('/document-attachments/{attachment}/download', [DocumentAttachmentController::class, 'download'])
        ->name('document-attachments.download');
    Route::get('/document-attachments/{attachment}/view', [DocumentAttachmentController::class, 'view'])
        ->name('document-attachments.view');
    Route::patch('/document-attachments/{attachment}', [DocumentAttachmentController::class, 'update'])
        ->name('document-attachments.update');
    Route::delete('/document-attachments/{attachment}', [DocumentAttachmentController::class, 'destroy'])
        ->name('document-attachments.destroy');

    Route::prefix('signature-requests')->name('signature-requests.')->group(function () {
        Route::get('/', [SignatureRequestController::class, 'index'])->name('index');
        Route::get('/{signatureRequest}', [SignatureRequestController::class, 'show'])->name('show');
        Route::post('/{signatureRequest}/send-code', [SignatureRequestController::class, 'sendCode'])->name('send-code');
        Route::post('/{signatureRequest}/sign', [SignatureRequestController::class, 'sign'])->name('sign');
        Route::post('/{signatureRequest}/decline', [SignatureRequestController::class, 'decline'])->name('decline');
    });

    Route::middleware('requesting.office')->prefix('annual-procurement-plans')->name('annual-procurement-plans.')->group(function () {
        Route::get('/', [AnnualProcurementPlanController::class, 'index'])->name('index');
        Route::get('/create', [AnnualProcurementPlanController::class, 'create'])->name('create');
        Route::post('/', [AnnualProcurementPlanController::class, 'store'])->name('store');
        Route::get('/{annualProcurementPlan}', [AnnualProcurementPlanController::class, 'show'])->name('show');
        Route::get('/{annualProcurementPlan}/edit', [AnnualProcurementPlanController::class, 'edit'])->name('edit');
        Route::match(['put', 'patch'], '/{annualProcurementPlan}', [AnnualProcurementPlanController::class, 'update'])->name('update');
        Route::delete('/{annualProcurementPlan}', [AnnualProcurementPlanController::class, 'destroy'])->name('destroy');
        Route::get('/{annualProcurementPlan}/print', [AnnualProcurementPlanController::class, 'print'])->name('print');
    });

    Route::middleware('requesting.office')->prefix('ppmps')->name('ppmps.')->group(function () {
        Route::get('/', [OfficialPpmpController::class, 'index'])->name('index');
        Route::get('/create', [OfficialPpmpController::class, 'create'])->name('create');
        Route::post('/', [OfficialPpmpController::class, 'store'])->name('store');
        Route::get('/{ppmp}', [OfficialPpmpController::class, 'show'])->name('show');
        Route::get('/{ppmp}/edit', [OfficialPpmpController::class, 'edit'])->name('edit');
        Route::post('/{ppmp}/submit', [OfficialPpmpController::class, 'submit'])->name('submit');
        Route::match(['put', 'patch'], '/{ppmp}', [OfficialPpmpController::class, 'update'])->name('update');
        Route::delete('/{ppmp}', [OfficialPpmpController::class, 'destroy'])->name('destroy');
        Route::get('/{ppmp}/print', [OfficialPpmpController::class, 'print'])->name('print');
    });

    Route::get('/admin/dashboard', [DashboardController::class, 'admin'])
        ->middleware('role:admin')
        ->name('admin.dashboard');
    Route::middleware('role:admin')->prefix('admin/ai-summary')->name('admin.ai-summary.')->group(function () {
        Route::get('/', [AiSummaryDashboardController::class, 'index'])->name('index');
        Route::post('/generate', [AiSummaryDashboardController::class, 'generate'])->name('generate');
        Route::get('/export', [AiSummaryDashboardController::class, 'export'])->name('export');
    });
    Route::middleware('role:admin')->prefix('admin/users')->name('admin.users.')->group(function () {
        Route::get('/', [AdminUserController::class, 'index'])->name('index');
        Route::get('/create', [AdminUserController::class, 'create'])->name('create');
        Route::post('/', [AdminUserController::class, 'store'])->name('store');
        Route::get('/{user}/edit', [AdminUserController::class, 'edit'])->name('edit');
        Route::match(['put', 'patch'], '/{user}', [AdminUserController::class, 'update'])->name('update');
        Route::patch('/{user}/status', [AdminUserController::class, 'updateStatus'])->name('status');
        Route::patch('/{user}/reset-password', [AdminUserController::class, 'resetPassword'])->name('reset-password');
    });
    Route::middleware('role:admin')->prefix('admin/offices')->name('admin.offices.')->group(function () {
        Route::get('/', [AdminOfficeController::class, 'index'])->name('index');
        Route::get('/create', [AdminOfficeController::class, 'create'])->name('create');
        Route::post('/', [AdminOfficeController::class, 'store'])->name('store');
        Route::get('/{office}', [AdminOfficeController::class, 'show'])->name('show');
        Route::get('/{office}/edit', [AdminOfficeController::class, 'edit'])->name('edit');
        Route::match(['put', 'patch'], '/{office}', [AdminOfficeController::class, 'update'])->name('update');
        Route::patch('/{office}/status', [AdminOfficeController::class, 'updateStatus'])->name('status');
    });
    Route::middleware('role:admin')->prefix('admin/roles')->name('admin.roles.')->group(function () {
        Route::get('/', [AdminRoleController::class, 'index'])->name('index');
        Route::get('/create', [AdminRoleController::class, 'create'])->name('create');
        Route::post('/', [AdminRoleController::class, 'store'])->name('store');
        Route::get('/{role}/edit', [AdminRoleController::class, 'edit'])->name('edit');
        Route::match(['put', 'patch'], '/{role}', [AdminRoleController::class, 'update'])->name('update');
        Route::patch('/{role}/status', [AdminRoleController::class, 'updateStatus'])->name('status');
        Route::get('/{role}/permissions', [AdminRoleController::class, 'permissions'])->name('permissions');
        Route::match(['put', 'patch'], '/{role}/permissions', [AdminRoleController::class, 'updatePermissions'])->name('permissions.update');
    });
    Route::middleware('role:admin')->prefix('admin/document-templates')->name('admin.document-templates.')->group(function () {
        Route::get('/', [DocumentTemplateController::class, 'index'])->name('index');
        Route::post('/', [DocumentTemplateController::class, 'store'])->name('store');
        Route::patch('/{documentTemplate}', [DocumentTemplateController::class, 'update'])->name('update');
        Route::delete('/{documentTemplate}', [DocumentTemplateController::class, 'destroy'])->name('destroy');
    });
    Route::middleware(['role:admin', 'permission:audit.view'])->prefix('admin/audit-trail')->name('admin.audit.')->group(function () {
        Route::get('/', [AdminAuditTrailController::class, 'index'])->name('index');
        Route::get('/export/csv', [AdminAuditTrailController::class, 'exportCsv'])->name('export');
        Route::get('/print', [AdminAuditTrailController::class, 'print'])->name('print');
        Route::get('/{auditLog}', [AdminAuditTrailController::class, 'show'])->name('show');
    });
    Route::middleware(['role:admin', 'permission:reports.admin'])->prefix('admin/reports')->name('admin.reports.')->group(function () {
        Route::get('/', [AdminReportController::class, 'index'])->name('index');
        Route::get('/users', [AdminReportController::class, 'users'])->name('users');
        Route::get('/offices', [AdminReportController::class, 'offices'])->name('offices');
        Route::get('/roles', [AdminReportController::class, 'roles'])->name('roles');
        Route::get('/audit-trail', [AdminReportController::class, 'audit'])->name('audit');
        Route::get('/access', [AdminReportController::class, 'access'])->name('access');
        Route::get('/system-summary', [AdminReportController::class, 'systemSummary'])->name('system-summary');
        Route::get('/export/{type}', [AdminReportController::class, 'export'])->name('export');
        Route::get('/print/{type}', [AdminReportController::class, 'print'])->name('print');
    });
    Route::middleware(['role:admin', 'permission:admin.settings.manage'])->prefix('admin/settings')->name('admin.settings.')->group(function () {
        Route::get('/', [AdminSettingsController::class, 'index'])->name('index');
        Route::post('/logo', [AdminSettingsController::class, 'uploadLogo'])->name('logo');
        Route::get('/email', [AdminSettingsController::class, 'email'])->name('email');
        Route::post('/email/test', [AdminSettingsController::class, 'sendTestEmail'])->name('email.test');
        Route::get('/ai-rules', [RuleConfigurationController::class, 'aiRules'])->name('ai-rules.index');
        Route::post('/ai-rules', [RuleConfigurationController::class, 'storeAiRule'])->name('ai-rules.store');
        Route::patch('/ai-rules/{rule}', [RuleConfigurationController::class, 'updateAiRule'])->name('ai-rules.update');
        Route::delete('/ai-rules/{rule}', [RuleConfigurationController::class, 'destroyAiRule'])->name('ai-rules.destroy');
        Route::get('/document-requirements', [RuleConfigurationController::class, 'documentRequirements'])->name('document-requirements.index');
        Route::post('/document-requirements', [RuleConfigurationController::class, 'storeDocumentRequirement'])->name('document-requirements.store');
        Route::patch('/document-requirements/{requirement}', [RuleConfigurationController::class, 'updateDocumentRequirement'])->name('document-requirements.update');
        Route::delete('/document-requirements/{requirement}', [RuleConfigurationController::class, 'destroyDocumentRequirement'])->name('document-requirements.destroy');
        Route::get('/routing-rules', [RuleConfigurationController::class, 'routingRules'])->name('routing-rules.index');
        Route::post('/routing-rules', [RuleConfigurationController::class, 'storeRoutingRule'])->name('routing-rules.store');
        Route::patch('/routing-rules/{rule}', [RuleConfigurationController::class, 'updateRoutingRule'])->name('routing-rules.update');
        Route::delete('/routing-rules/{rule}', [RuleConfigurationController::class, 'destroyRoutingRule'])->name('routing-rules.destroy');
        Route::get('/delay-thresholds', [RuleConfigurationController::class, 'delayThresholds'])->name('delay-thresholds.index');
        Route::post('/delay-thresholds', [RuleConfigurationController::class, 'storeDelayThreshold'])->name('delay-thresholds.store');
        Route::patch('/delay-thresholds/{threshold}', [RuleConfigurationController::class, 'updateDelayThreshold'])->name('delay-thresholds.update');
        Route::delete('/delay-thresholds/{threshold}', [RuleConfigurationController::class, 'destroyDelayThreshold'])->name('delay-thresholds.destroy');
        Route::get('/notification-templates', [RuleConfigurationController::class, 'notificationTemplates'])->name('notification-templates.index');
        Route::post('/notification-templates', [RuleConfigurationController::class, 'storeNotificationTemplate'])->name('notification-templates.store');
        Route::patch('/notification-templates/{template}', [RuleConfigurationController::class, 'updateNotificationTemplate'])->name('notification-templates.update');
        Route::delete('/notification-templates/{template}', [RuleConfigurationController::class, 'destroyNotificationTemplate'])->name('notification-templates.destroy');
        Route::get('/{group}', [AdminSettingsController::class, 'group'])->name('group');
        Route::match(['put', 'patch'], '/{group}', [AdminSettingsController::class, 'update'])->name('update');
    });
    Route::middleware('role:admin')->prefix('admin/svp-chains')->name('admin.svp-chains.')->group(function () {
        Route::get('/', [AdminSvpChainController::class, 'index'])->name('index');
        Route::get('/{chain}', [AdminSvpChainController::class, 'show'])->name('show');
    });

    Route::get('/head-office/dashboard', [DashboardController::class, 'headOffice'])
        ->middleware('role:head-office')
        ->name('head-office.dashboard');
    Route::middleware('role:head-office')->prefix('head-office/my-documents')->name('head-office.documents.')->group(function () {
        Route::get('/', [HeadOfficeDocumentController::class, 'index'])->name('index');
        Route::get('/{document}', [HeadOfficeDocumentController::class, 'show'])->name('show');
    });
    Route::middleware('role:head-office')->prefix('head-office/returned-documents')->name('head-office.returned.')->group(function () {
        Route::get('/', [HeadOfficeReturnedDocumentController::class, 'index'])->name('index');
        Route::get('/{document}', [HeadOfficeReturnedDocumentController::class, 'show'])->name('show');
    });
    Route::middleware(['role:head-office', 'requesting.office', 'permission:ppmp.view'])->prefix('head-office/ppmp')->name('head-office.ppmp.')->group(function () {
        Route::get('/', [HeadOfficePpmpController::class, 'index'])->name('index');
        Route::get('/create', [HeadOfficePpmpController::class, 'create'])
            ->middleware(['permission:ppmp.submit', 'permission:documents.submit'])
            ->name('create');
        Route::post('/', [HeadOfficePpmpController::class, 'store'])
            ->middleware(['permission:ppmp.submit', 'permission:documents.submit'])
            ->name('store');
        Route::get('/{document}', [HeadOfficePpmpController::class, 'show'])->name('show');
        Route::get('/{document}/print', [HeadOfficePpmpController::class, 'print'])->name('print');
        Route::get('/{document}/edit', [HeadOfficePpmpController::class, 'edit'])
            ->middleware('permission:documents.edit.own')
            ->name('edit');
        Route::patch('/{document}', [HeadOfficePpmpController::class, 'update'])
            ->middleware('permission:documents.edit.own')
            ->name('update');
        Route::delete('/{document}', [HeadOfficePpmpController::class, 'destroy'])
            ->middleware('permission:documents.edit.own')
            ->name('destroy');
        Route::post('/{document}/submit', [HeadOfficePpmpController::class, 'submit'])
            ->middleware(['permission:ppmp.submit', 'permission:documents.submit'])
            ->name('submit');
        Route::post('/{document}/attachments', [HeadOfficePpmpController::class, 'storeAttachment'])
            ->middleware('permission:documents.upload')
            ->name('attachments.store');
        Route::delete('/{document}/attachments/{attachment}', [HeadOfficePpmpController::class, 'destroyAttachment'])
            ->middleware('permission:documents.upload')
            ->name('attachments.destroy');
    });
    Route::middleware(['role:head-office', 'requesting.office', 'permission:pr.view'])->prefix('head-office/purchase-requests')->name('head-office.pr.')->group(function () {
        Route::get('/', [HeadOfficePurchaseRequestController::class, 'index'])->name('index');
        Route::get('/create', [HeadOfficePurchaseRequestController::class, 'create'])
            ->middleware(['permission:pr.submit', 'permission:documents.submit'])
            ->name('create');
        Route::post('/', [HeadOfficePurchaseRequestController::class, 'store'])
            ->middleware(['permission:pr.submit', 'permission:documents.submit'])
            ->name('store');
        Route::get('/{document}', [HeadOfficePurchaseRequestController::class, 'show'])->name('show');
        Route::get('/{document}/edit', [HeadOfficePurchaseRequestController::class, 'edit'])
            ->middleware('permission:documents.edit.own')
            ->name('edit');
        Route::patch('/{document}', [HeadOfficePurchaseRequestController::class, 'update'])
            ->middleware('permission:documents.edit.own')
            ->name('update');
        Route::post('/{document}/submit', [HeadOfficePurchaseRequestController::class, 'submit'])
            ->middleware(['permission:pr.submit', 'permission:documents.submit'])
            ->name('submit');
        Route::get('/{document}/print', [HeadOfficePurchaseRequestController::class, 'print'])->name('print');
    });
    Route::middleware('role:head-office')->prefix('head-office/received-bac-resolutions')->name('head-office.resolutions.')->group(function () {
        Route::get('/', [HeadOfficeReceivedBacResolutionController::class, 'index'])->name('index');
        Route::get('/{resolution}', [HeadOfficeReceivedBacResolutionController::class, 'show'])->name('show');
        Route::patch('/{resolution}/acknowledge', [HeadOfficeReceivedBacResolutionController::class, 'acknowledge'])->name('acknowledge');
    });
    Route::middleware('role:head-office')->prefix('head-office/supplemental-apps')->name('head-office.supplemental-apps.')->group(function () {
        Route::get('/{supplementalApp}', [HeadOfficeSupplementalAppController::class, 'show'])->name('show');
        Route::get('/{supplementalApp}/print', [HeadOfficeSupplementalAppController::class, 'print'])->name('print');
    });
    Route::middleware(['role:head-office', 'requesting.office'])->prefix('head-office/rfqs')->name('head-office.rfqs.')->group(function () {
        Route::get('/', [HeadOfficeRfqController::class, 'index'])->name('index');
        Route::get('/create', [HeadOfficeRfqController::class, 'create'])->name('create');
        Route::post('/', [HeadOfficeRfqController::class, 'store'])->name('store');
        Route::get('/{rfq}', [HeadOfficeRfqController::class, 'show'])->name('show');
        Route::get('/{rfq}/edit', [HeadOfficeRfqController::class, 'edit'])->name('edit');
        Route::patch('/{rfq}', [HeadOfficeRfqController::class, 'update'])->name('update');
        Route::get('/{rfq}/print', [HeadOfficeRfqController::class, 'print'])->name('print');
        Route::patch('/{rfq}/submit', [HeadOfficeRfqController::class, 'submit'])->name('submit');
    });
    Route::middleware(['role:head-office', 'requesting.office'])->prefix('head-office/abstracts')->name('head-office.abstracts.')->group(function () {
        Route::get('/', [HeadOfficeAbstractController::class, 'index'])->name('index');
        Route::get('/create', [HeadOfficeAbstractController::class, 'create'])->name('create');
        Route::post('/', [HeadOfficeAbstractController::class, 'store'])->name('store');
        Route::get('/{abstract}', [HeadOfficeAbstractController::class, 'show'])->name('show');
        Route::get('/{abstract}/edit', [HeadOfficeAbstractController::class, 'edit'])->name('edit');
        Route::patch('/{abstract}', [HeadOfficeAbstractController::class, 'update'])->name('update');
        Route::get('/{abstract}/print', [HeadOfficeAbstractController::class, 'print'])->name('print');
        Route::patch('/{abstract}/submit', [HeadOfficeAbstractController::class, 'submit'])->name('submit');
    });
    Route::middleware(['role:head-office', 'requesting.office'])->prefix('head-office/purchase-orders')->name('head-office.purchase-orders.')->group(function () {
        Route::get('/', [HeadOfficePurchaseOrderController::class, 'index'])->name('index');
        Route::get('/create', [HeadOfficePurchaseOrderController::class, 'create'])->name('create');
        Route::post('/', [HeadOfficePurchaseOrderController::class, 'store'])->name('store');
        Route::get('/{purchaseOrder}', [HeadOfficePurchaseOrderController::class, 'show'])->name('show');
        Route::get('/{purchaseOrder}/edit', [HeadOfficePurchaseOrderController::class, 'edit'])->name('edit');
        Route::patch('/{purchaseOrder}', [HeadOfficePurchaseOrderController::class, 'update'])->name('update');
        Route::get('/{purchaseOrder}/print', [HeadOfficePurchaseOrderController::class, 'print'])->name('print');
        Route::patch('/{purchaseOrder}/submit', [HeadOfficePurchaseOrderController::class, 'submit'])->name('submit');
    });
    Route::middleware(['role:head-office', 'requesting.office'])->prefix('head-office/inspection-acceptance')->name('head-office.inspection.')->group(function () {
        Route::get('/', [HeadOfficeInspectionAcceptanceController::class, 'index'])->name('index');
        Route::get('/{purchaseOrder}', [HeadOfficeInspectionAcceptanceController::class, 'show'])->name('show');
        Route::post('/{purchaseOrder}', [HeadOfficeInspectionAcceptanceController::class, 'store'])->name('store');
        Route::patch('/{purchaseOrder}/accept', [HeadOfficeInspectionAcceptanceController::class, 'accept'])->name('accept');
        Route::patch('/{purchaseOrder}/complete', [HeadOfficeInspectionAcceptanceController::class, 'complete'])->name('complete');
    });
    Route::middleware('role:head-office')->prefix('head-office/svp-tracking')->name('head-office.svp-tracking.')->group(function () {
        Route::get('/', [HeadOfficeSvpTrackingController::class, 'index'])->name('index');
        Route::get('/mock-preview', [HeadOfficeSvpTrackingController::class, 'mockPreview'])->name('mock-preview');
        Route::get('/{chain}', [HeadOfficeSvpTrackingController::class, 'show'])->name('show');
    });
    Route::get('/pr-numbering/dashboard', [DashboardController::class, 'prNumbering'])
        ->middleware('role:pr-numbering')
        ->name('pr-numbering.dashboard');
    Route::middleware(['role:pr-numbering', 'permission:pr-number.view', 'permission:documents.track'])->prefix('pr-numbering')->name('pr-numbering.')->group(function () {
        Route::get('/pending', [PrNumberingController::class, 'index'])->name('pending.index');
        Route::get('/pending/{document}', [PrNumberingController::class, 'show'])->name('pending.show');
        Route::patch('/pending/{document}/assign', [PrNumberingController::class, 'assign'])
            ->middleware('permission:pr-number.assign')
            ->name('pending.assign');
        Route::patch('/pending/{document}/return', [PrNumberingController::class, 'return'])
            ->middleware('permission:pr-number.return')
            ->name('pending.return');
        Route::get('/assigned', [PrNumberingController::class, 'assigned'])->name('assigned.index');
    });
    Route::get('/budget/dashboard', [DashboardController::class, 'budget'])
        ->middleware('role:budget')
        ->name('budget.dashboard');
    Route::middleware(['role:budget', 'permission:review.budget', 'permission:documents.view.assigned', 'permission:documents.track', 'permission:documents.return', 'permission:documents.comment'])->prefix('budget/pending-review')->name('budget.pending-review.')->group(function () {
        Route::get('/', [PendingBudgetReviewController::class, 'index'])->name('index');
        Route::get('/{document}', [PendingBudgetReviewController::class, 'show'])->name('show');
        Route::patch('/{document}/start', [PendingBudgetReviewController::class, 'start'])->name('start');
        Route::patch('/{document}/return', [PendingBudgetReviewController::class, 'return'])->name('return');
        Route::patch('/{document}/mark-available', [PendingBudgetReviewController::class, 'markAvailable'])->name('mark-available');
    });
    Route::middleware(['role:budget', 'permission:review.budget', 'permission:documents.view.assigned', 'permission:documents.track'])->prefix('budget/reviewed-documents')->name('budget.reviewed.')->group(function () {
        Route::get('/', [ReviewedDocumentsController::class, 'index'])->name('index');
        Route::get('/{document}', [ReviewedDocumentsController::class, 'show'])->name('show');
    });
    Route::middleware(['role:budget', 'permission:review.budget', 'permission:documents.view.assigned', 'permission:documents.track'])->prefix('budget/returned-documents')->name('budget.returned.')->group(function () {
        Route::get('/', [ReturnedDocumentsController::class, 'index'])->name('index');
        Route::get('/{document}', [ReturnedDocumentsController::class, 'show'])->name('show');
    });
    Route::middleware(['role:budget', 'permission:review.budget', 'permission:documents.view.assigned', 'permission:documents.track', 'permission:reports.budget'])->prefix('budget/reports')->name('budget.reports.')->group(function () {
        Route::get('/', [BudgetReportController::class, 'index'])->name('index');
        Route::get('/review-summary', [BudgetReportController::class, 'reviewSummary'])->name('review-summary');
        Route::get('/export', [BudgetReportController::class, 'export'])->name('export');
        Route::get('/print', [BudgetReportController::class, 'print'])->name('print');
    });
    Route::get('/accounting/dashboard', [DashboardController::class, 'accounting'])
        ->middleware('role:accounting')
        ->name('accounting.dashboard');
    Route::middleware(['role:accounting', 'permission:review.accounting', 'permission:documents.view.assigned', 'permission:documents.track', 'permission:documents.return', 'permission:documents.comment'])->prefix('accounting/pending-review')->name('accounting.pending-review.')->group(function () {
        Route::get('/', [AccountingPendingReviewController::class, 'index'])->name('index');
        Route::get('/{document}', [AccountingPendingReviewController::class, 'show'])->name('show');
        Route::patch('/{document}/start', [AccountingPendingReviewController::class, 'start'])->name('start');
        Route::patch('/{document}/return', [AccountingPendingReviewController::class, 'return'])->name('return');
        Route::patch('/{document}/verify', [AccountingPendingReviewController::class, 'verify'])->name('verify');
    });
    Route::middleware(['role:accounting', 'permission:review.accounting', 'permission:documents.view.assigned', 'permission:documents.track'])->prefix('accounting/reviewed-documents')->name('accounting.reviewed.')->group(function () {
        Route::get('/', [AccountingReviewedDocumentsController::class, 'index'])->name('index');
        Route::get('/{document}', [AccountingReviewedDocumentsController::class, 'show'])->name('show');
    });
    Route::middleware(['role:accounting', 'permission:review.accounting', 'permission:documents.view.assigned', 'permission:documents.track'])->prefix('accounting/returned-documents')->name('accounting.returned.')->group(function () {
        Route::get('/', [AccountingReturnedDocumentsController::class, 'index'])->name('index');
        Route::get('/{document}', [AccountingReturnedDocumentsController::class, 'show'])->name('show');
    });
    Route::middleware(['role:accounting', 'permission:review.accounting', 'permission:documents.track', 'permission:reports.accounting'])->prefix('accounting/reports')->name('accounting.reports.')->group(function () {
        Route::get('/', [AccountingReportController::class, 'index'])->name('index');
        Route::get('/summary', [AccountingReportController::class, 'summary'])->name('summary');
        Route::get('/export', [AccountingReportController::class, 'export'])->name('export');
        Route::get('/print', [AccountingReportController::class, 'print'])->name('print');
    });
    Route::get('/bac-secretariat/dashboard', [DashboardController::class, 'bacSecretariat'])
        ->middleware('role:bac-secretariat')
        ->name('bac-secretariat.dashboard');
    Route::middleware(['role:bac-secretariat', 'permission:documents.view.all', 'permission:documents.track', 'permission:documents.return', 'permission:documents.comment', 'permission:workflow.route', 'permission:workflow.update_status', 'permission:workflow.view_history'])->prefix('bac-secretariat/incoming-documents')->name('bac-secretariat.incoming.')->group(function () {
        Route::get('/', [BacIncomingDocumentsController::class, 'index'])->name('index');
        Route::get('/{document}', [BacIncomingDocumentsController::class, 'show'])->name('show');
        Route::patch('/{document}/acknowledge', [BacIncomingDocumentsController::class, 'acknowledge'])->name('acknowledge');
        Route::patch('/{document}/start-review', [BacIncomingDocumentsController::class, 'startReview'])->name('start-review');
        Route::patch('/{document}/return', [BacIncomingDocumentsController::class, 'return'])->name('return');
        Route::patch('/{document}/mark-ready', [BacIncomingDocumentsController::class, 'markReady'])->name('mark-ready');
        Route::patch('/{document}/accept-ppmp', [BacIncomingDocumentsController::class, 'acceptPpmpForAppConsolidation'])->name('accept-ppmp');
    });
    Route::middleware(['role:bac-secretariat', 'permission:documents.view.all', 'permission:documents.track', 'permission:documents.return', 'permission:documents.comment', 'permission:workflow.route', 'permission:workflow.update_status', 'permission:workflow.view_history'])->prefix('bac-secretariat/document-routing')->name('bac-secretariat.routing.')->group(function () {
        Route::get('/', [BacDocumentRoutingController::class, 'index'])->name('index');
        Route::get('/{document}', [BacDocumentRoutingController::class, 'show'])->name('show');
        Route::patch('/{document}/route', [BacDocumentRoutingController::class, 'route'])->name('route');
        Route::patch('/{document}/return', [BacDocumentRoutingController::class, 'return'])->name('return');
    });
    Route::middleware(['role:bac-secretariat', 'permission:documents.view.all', 'permission:documents.track', 'permission:workflow.view_history'])->prefix('bac-secretariat/svp-monitoring')->name('bac-secretariat.svp-monitoring.')->group(function () {
        Route::get('/', [BacSvpMonitoringController::class, 'index'])->name('index');
        Route::patch('/{chain}/complete-posting', [BacSvpMonitoringController::class, 'completePosting'])->name('complete-posting');
        Route::get('/{chain}', [BacSvpMonitoringController::class, 'show'])->name('show');
    });
    Route::middleware(['role:bac-secretariat', 'permission:documents.view.all', 'permission:documents.track', 'permission:workflow.view_history'])->prefix('bac-secretariat/svp-posting')->name('bac-secretariat.svp-posting.')->group(function () {
        Route::get('/pending', [BacSvpPostingController::class, 'pending'])->name('pending');
        Route::get('/create/{chain?}', [BacSvpPostingController::class, 'create'])->name('create');
        Route::post('/', [BacSvpPostingController::class, 'store'])->name('store');
        Route::get('/posted-records', [BacSvpPostingController::class, 'posted'])->name('posted');
        Route::get('/history', [BacSvpPostingController::class, 'history'])->name('history');
        Route::get('/records/{postingRecord}', [BacSvpPostingController::class, 'show'])->name('show');
    });
    Route::middleware(['role:bac-secretariat', 'permission:ppmp.review', 'permission:documents.view.all', 'permission:documents.track', 'permission:documents.return', 'permission:documents.comment', 'permission:workflow.update_status'])->prefix('bac-secretariat/ppmp-review')->name('bac-secretariat.ppmp.')->group(function () {
        Route::get('/', [BacPpmpReviewController::class, 'index'])->name('index');
        Route::get('/{document}', [BacPpmpReviewController::class, 'show'])->name('show');
        Route::get('/{document}/print', [BacPpmpReviewController::class, 'print'])->name('print');
        Route::patch('/{document}/start', [BacPpmpReviewController::class, 'start'])->name('start');
        Route::patch('/{document}/return', [BacPpmpReviewController::class, 'return'])->name('return');
        Route::patch('/{document}/accept', [BacPpmpReviewController::class, 'accept'])->name('accept');
    });
    Route::middleware(['role:bac-secretariat,approving-authority,bac-chair,admin'])->prefix('bac-secretariat/app')->name('bac-secretariat.app.')->group(function () {
        Route::get('/', [BacAnnualProcurementPlanController::class, 'index'])->name('index');
        Route::get('/create', [BacAnnualProcurementPlanController::class, 'create'])->name('create');
        Route::post('/', [BacAnnualProcurementPlanController::class, 'store'])->name('store');
        Route::post('/ppmp/{document}/add-to-app', [BacAnnualProcurementPlanController::class, 'addPpmpToApp'])->name('add-ppmp');
        Route::get('/{app}/edit', [BacAnnualProcurementPlanController::class, 'edit'])->name('edit');
        Route::patch('/{app}', [BacAnnualProcurementPlanController::class, 'update'])->name('update');
        Route::get('/{app}/print', [BacAnnualProcurementPlanController::class, 'print'])->name('print');
        Route::patch('/{app}/submit', [BacAnnualProcurementPlanController::class, 'submit'])->name('submit');
        Route::patch('/{app}/approve', [BacAnnualProcurementPlanController::class, 'approve'])->name('approve');
        Route::patch('/{app}/return', [BacAnnualProcurementPlanController::class, 'returnApp'])->name('return');
        Route::post('/{app}/import-ppmp', [BacAnnualProcurementPlanController::class, 'importPpmp'])->name('import-ppmp');
        Route::delete('/{app}/ppmp/{document}', [BacAnnualProcurementPlanController::class, 'removePpmp'])->name('remove-ppmp');
        Route::delete('/{app}', [BacAnnualProcurementPlanController::class, 'destroy'])->name('destroy');
        Route::get('/{app}', [BacAnnualProcurementPlanController::class, 'show'])->name('show');
    });
    Route::redirect('/bac-secretariat/app-consolidation', '/bac-secretariat/app')->middleware('role:bac-secretariat');
    Route::middleware(['role:bac-secretariat', 'permission:app.view', 'permission:app.consolidate', 'permission:documents.view.all', 'permission:documents.track', 'permission:workflow.update_status', 'permission:workflow.view_history'])->prefix('bac-secretariat/supplemental-apps')->name('bac-secretariat.supplemental-apps.')->group(function () {
        Route::get('/', [BacSupplementalAppController::class, 'index'])->name('index');
        Route::get('/create', [BacSupplementalAppController::class, 'create'])->name('create');
        Route::get('/create-from-pr/{document}', [BacSupplementalAppController::class, 'createFromPr'])->name('create-from-pr');
        Route::post('/', [BacSupplementalAppController::class, 'store'])->name('store');
        Route::get('/{supplementalApp}', [BacSupplementalAppController::class, 'show'])->name('show');
        Route::get('/{supplementalApp}/edit', [BacSupplementalAppController::class, 'edit'])->name('edit');
        Route::patch('/{supplementalApp}', [BacSupplementalAppController::class, 'update'])->name('update');
        Route::patch('/{supplementalApp}/submit', [BacSupplementalAppController::class, 'submit'])->name('submit');
        Route::patch('/{supplementalApp}/accept', [BacSupplementalAppController::class, 'accept'])->name('accept');
        Route::get('/{supplementalApp}/print', [BacSupplementalAppController::class, 'print'])->name('print');
    });
    Route::middleware(['role:bac-secretariat', 'permission:pr.view', 'permission:pr.review', 'permission:workflow.route', 'permission:workflow.update_status', 'permission:documents.view.all', 'permission:documents.track', 'permission:documents.return', 'permission:documents.comment'])->prefix('bac-secretariat/purchase-requests')->name('bac-secretariat.pr.')->group(function () {
        Route::get('/', [BacPurchaseRequestController::class, 'index'])->name('index');
        Route::get('/{document}/print', [BacPurchaseRequestController::class, 'print'])->name('print');
        Route::get('/{document}', [BacPurchaseRequestController::class, 'show'])->name('show');
        Route::patch('/{document}/acknowledge', [BacPurchaseRequestController::class, 'acknowledge'])->name('acknowledge');
        Route::patch('/{document}/start-validation', [BacPurchaseRequestController::class, 'startValidation'])->name('start-validation');
        Route::patch('/{document}/return', [BacPurchaseRequestController::class, 'return'])->name('return');
        Route::patch('/{document}/route-budget', [BacPurchaseRequestController::class, 'routeBudget'])->name('route-budget');
    });
    Route::middleware(['role:bac-secretariat', 'permission:bac.resolution.view', 'permission:documents.track', 'permission:workflow.update_status'])->prefix('bac-secretariat/bac-resolutions')->name('bac-secretariat.resolutions.')->group(function () {
        Route::get('/', [BacResolutionController::class, 'index'])->name('index');
        Route::get('/create', [BacResolutionController::class, 'create'])->middleware('permission:bac.resolution.create')->name('create');
        Route::post('/', [BacResolutionController::class, 'store'])->middleware('permission:bac.resolution.create')->name('store');
        Route::get('/{resolution}', [BacResolutionController::class, 'show'])->name('show');
        Route::get('/{resolution}/edit', [BacResolutionController::class, 'edit'])->middleware('permission:bac.resolution.update')->name('edit');
        Route::patch('/{resolution}', [BacResolutionController::class, 'update'])->middleware('permission:bac.resolution.update')->name('update');
        Route::patch('/{resolution}/submit', [BacResolutionController::class, 'submit'])->middleware('permission:bac.resolution.submit')->name('submit');
        Route::patch('/{resolution}/return-to-office', [BacResolutionController::class, 'returnToOffice'])->name('return-office');
        Route::get('/{resolution}/print', [BacResolutionController::class, 'print'])->name('print');
    });
    Route::middleware(['role:bac-secretariat'])->prefix('bac-secretariat/rfqs')->name('bac-secretariat.rfqs.')->group(function () {
        Route::get('/', [BacRfqController::class, 'index'])->name('index');
        Route::get('/create', [BacRfqController::class, 'create'])->name('create');
        Route::post('/', [BacRfqController::class, 'store'])->name('store');
        Route::get('/{rfq}', [BacRfqController::class, 'show'])->name('show');
        Route::get('/{rfq}/edit', [BacRfqController::class, 'edit'])->name('edit');
        Route::patch('/{rfq}', [BacRfqController::class, 'update'])->name('update');
        Route::get('/{rfq}/print', [BacRfqController::class, 'print'])->name('print');
        Route::patch('/{rfq}/submit', [BacRfqController::class, 'submit'])->name('submit');
    });
    Route::middleware(['role:bac-secretariat'])->prefix('bac-secretariat/abstracts')->name('bac-secretariat.abstracts.')->group(function () {
        Route::get('/', [BacAbstractController::class, 'index'])->name('index');
        Route::get('/create', [BacAbstractController::class, 'create'])->name('create');
        Route::post('/', [BacAbstractController::class, 'store'])->name('store');
        Route::get('/{abstract}', [BacAbstractController::class, 'show'])->name('show');
        Route::get('/{abstract}/edit', [BacAbstractController::class, 'edit'])->name('edit');
        Route::patch('/{abstract}', [BacAbstractController::class, 'update'])->name('update');
        Route::get('/{abstract}/print', [BacAbstractController::class, 'print'])->name('print');
        Route::patch('/{abstract}/submit', [BacAbstractController::class, 'submit'])->name('submit');
    });
    Route::middleware(['role:bac-secretariat', 'permission:po.view', 'permission:po.prepare', 'permission:workflow.update_status', 'permission:documents.track', 'permission:documents.comment'])->prefix('bac-secretariat/purchase-orders')->name('bac-secretariat.purchase-orders.')->group(function () {
        Route::get('/', [BacPurchaseOrderController::class, 'index'])->name('index');
        Route::get('/create', [BacPurchaseOrderController::class, 'create'])->name('create');
        Route::post('/', [BacPurchaseOrderController::class, 'store'])->name('store');
        Route::get('/{purchaseOrder}', [BacPurchaseOrderController::class, 'show'])->name('show');
        Route::get('/{purchaseOrder}/edit', [BacPurchaseOrderController::class, 'edit'])->name('edit');
        Route::patch('/{purchaseOrder}', [BacPurchaseOrderController::class, 'update'])->name('update');
        Route::get('/{purchaseOrder}/print', [BacPurchaseOrderController::class, 'print'])->name('print');
        Route::patch('/{purchaseOrder}/submit', [BacPurchaseOrderController::class, 'submit'])->name('submit');
    });
    Route::middleware(['role:bac-secretariat', 'permission:audit.view', 'permission:workflow.view_history', 'permission:documents.track'])->prefix('bac-secretariat/audit-trail')->name('bac-secretariat.audit.')->group(function () {
        Route::get('/', [BacAuditTrailController::class, 'index'])->name('index');
        Route::get('/export', [BacAuditTrailController::class, 'export'])->name('export');
        Route::get('/{auditLog}', [BacAuditTrailController::class, 'show'])->name('show');
    });
    Route::middleware(['role:bac-secretariat', 'permission:reports.procurement', 'permission:documents.view.all', 'permission:documents.track', 'permission:workflow.view_history'])->prefix('bac-secretariat/reports')->name('bac-secretariat.reports.')->group(function () {
        Route::get('/', [BacReportController::class, 'index'])->name('index');
        Route::get('/export', [BacReportController::class, 'export'])->name('export');
        Route::get('/print', [BacReportController::class, 'print'])->name('print');
    });
    Route::get('/bac-member/dashboard', [DashboardController::class, 'bacMember'])
        ->middleware('role:bac-member')
        ->name('bac-member.dashboard');
    Route::middleware(['role:bac-member', 'permission:review.bac', 'permission:documents.view.assigned', 'permission:documents.track', 'permission:documents.comment', 'permission:documents.return'])->prefix('bac-member/documents-for-review')->name('bac-member.review.')->group(function () {
        Route::get('/', [BacMemberReviewController::class, 'index'])->name('index');
        Route::get('/{document}', [BacMemberReviewController::class, 'show'])->name('show');
        Route::patch('/{document}/start', [BacMemberReviewController::class, 'start'])->name('start');
        Route::patch('/{document}/return', [BacMemberReviewController::class, 'return'])->name('return');
        Route::patch('/{document}/endorse', [BacMemberReviewController::class, 'endorse'])->name('endorse');
    });
    Route::middleware(['role:bac-member', 'permission:review.bac', 'permission:documents.view.assigned', 'permission:documents.track', 'permission:documents.comment'])->prefix('bac-member/reviewed-documents')->name('bac-member.reviewed.')->group(function () {
        Route::get('/', [BacMemberReviewedController::class, 'index'])->name('index');
        Route::get('/{document}', [BacMemberReviewedController::class, 'show'])->name('show');
    });
    Route::middleware(['role:bac-member', 'permission:review.bac', 'permission:documents.view.assigned', 'permission:documents.track', 'permission:documents.comment'])->prefix('bac-member/deliberations')->name('bac-member.deliberations.')->group(function () {
        Route::get('/', [BacMemberDeliberationController::class, 'index'])->name('index');
        Route::get('/{deliberation}/document', [BacMemberDeliberationController::class, 'document'])->name('document');
        Route::get('/{deliberation}', [BacMemberDeliberationController::class, 'show'])->name('show');
        Route::post('/{deliberation}/comments', [BacMemberDeliberationController::class, 'storeComment'])->name('comments.store');
        Route::patch('/{deliberation}/recommendation', [BacMemberDeliberationController::class, 'updateRecommendation'])->name('recommendation');
    });
    Route::get('/bac-chair/dashboard', [DashboardController::class, 'bacChair'])
        ->middleware('role:bac-chair')
        ->name('bac-chair.dashboard');
    Route::middleware(['role:bac-chair', 'permission:review.bac', 'permission:documents.view.assigned', 'permission:documents.track'])->prefix('bac-chair/approvals')->name('bac-chair.approvals.')->group(function () {
        Route::get('/', [BacChairApprovalController::class, 'index'])->name('index');
        Route::get('/{document}', [BacChairApprovalController::class, 'show'])->name('show');
        Route::patch('/{document}/start', [BacChairApprovalController::class, 'start'])
            ->middleware(['permission:documents.comment', 'permission:workflow.update_status'])
            ->name('start');
        Route::patch('/{document}/return', [BacChairApprovalController::class, 'return'])
            ->middleware(['permission:documents.comment', 'permission:documents.return', 'permission:workflow.update_status'])
            ->name('return');
        Route::patch('/{document}/confirm', [BacChairApprovalController::class, 'confirm'])
            ->middleware(['permission:documents.comment', 'permission:workflow.update_status'])
            ->name('confirm');
    });
    Route::middleware(['role:bac-chair', 'permission:review.bac', 'permission:documents.view.assigned', 'permission:documents.track'])->prefix('bac-chair/documents-for-confirmation')->name('bac-chair.confirmation.')->group(function () {
        Route::get('/', [BacChairConfirmationController::class, 'index'])->name('index');
        Route::get('/{document}', [BacChairConfirmationController::class, 'show'])->name('show');
        Route::patch('/{document}/confirm', [BacChairConfirmationController::class, 'confirm'])
            ->middleware(['permission:documents.comment', 'permission:workflow.update_status'])
            ->name('confirm');
        Route::patch('/{document}/return', [BacChairConfirmationController::class, 'return'])
            ->middleware(['permission:documents.comment', 'permission:documents.return', 'permission:workflow.update_status'])
            ->name('return');
    });
    Route::middleware(['role:bac-chair', 'permission:bac.resolution.view'])->prefix('bac-chair/bac-resolutions')->name('bac-chair.resolutions.')->group(function () {
        Route::get('/', [BacChairResolutionController::class, 'index'])->name('index');
        Route::get('/{resolution}', [BacChairResolutionController::class, 'show'])->name('show');
        Route::get('/{resolution}/print', [BacChairResolutionController::class, 'print'])->name('print');
        Route::patch('/{resolution}/confirm', [BacChairResolutionController::class, 'confirm'])
            ->middleware(['permission:bac.resolution.review', 'permission:bac.resolution.confirm'])
            ->name('confirm');
        Route::patch('/{resolution}/return', [BacChairResolutionController::class, 'return'])
            ->middleware(['permission:bac.resolution.review', 'permission:bac.resolution.return'])
            ->name('return');
        Route::patch('/{resolution}/forward-to-hope', [BacChairResolutionController::class, 'forwardToHope'])
            ->middleware('permission:bac.resolution.forward_to_hope')
            ->name('forward-to-hope');
    });
    Route::middleware(['role:bac-chair', 'permission:review.bac', 'permission:documents.view.assigned', 'permission:documents.track', 'permission:workflow.view_history'])->prefix('bac-chair/reviewed-documents')->name('bac-chair.reviewed.')->group(function () {
        Route::get('/', [BacChairReviewedController::class, 'index'])->name('index');
        Route::get('/{document}', [BacChairReviewedController::class, 'show'])->name('show');
    });
    Route::middleware(['role:bac-chair', 'permission:reports.procurement', 'permission:review.bac', 'permission:documents.view.assigned', 'permission:documents.track', 'permission:workflow.view_history'])->prefix('bac-chair/reports')->name('bac-chair.reports.')->group(function () {
        Route::get('/', [BacChairReportController::class, 'index'])->name('index');
        Route::get('/export', [BacChairReportController::class, 'export'])->name('export');
        Route::get('/print', [BacChairReportController::class, 'print'])->name('print');
    });
    Route::get('/approving-authority/dashboard', [DashboardController::class, 'approvingAuthority'])
        ->middleware('role:approving-authority')
        ->name('approving-authority.dashboard');
    Route::middleware(['role:approving-authority', 'permission:documents.view.assigned', 'permission:documents.track'])->prefix('approving-authority/pending-approval')->name('approving-authority.pending.')->group(function () {
        Route::get('/', [ApprovingAuthorityPendingController::class, 'index'])->name('index');
        Route::get('/{document}', [ApprovingAuthorityPendingController::class, 'show'])->name('show');
        Route::patch('/{document}/start', [ApprovingAuthorityPendingController::class, 'start'])
            ->middleware(['permission:documents.comment', 'permission:workflow.update_status'])
            ->name('start');
        Route::patch('/{document}/approve', [ApprovingAuthorityPendingController::class, 'approve'])
            ->middleware(['permission:documents.comment', 'permission:documents.approve', 'permission:workflow.update_status'])
            ->name('approve');
        Route::patch('/{document}/return', [ApprovingAuthorityPendingController::class, 'return'])
            ->middleware(['permission:documents.comment', 'permission:documents.return', 'permission:workflow.update_status'])
            ->name('return');
    });
    Route::middleware(['role:approving-authority', 'permission:documents.view.assigned', 'permission:documents.track', 'permission:documents.approve', 'permission:workflow.view_history'])->prefix('approving-authority/approved-documents')->name('approving-authority.approved.')->group(function () {
        Route::get('/', [ApprovingAuthorityApprovedController::class, 'index'])->name('index');
        Route::get('/{document}', [ApprovingAuthorityApprovedController::class, 'show'])->name('show');
    });
    Route::middleware(['role:approving-authority', 'permission:documents.view.assigned', 'permission:documents.track', 'permission:documents.return', 'permission:workflow.view_history'])->prefix('approving-authority/returned-documents')->name('approving-authority.returned.')->group(function () {
        Route::get('/', [ApprovingAuthorityReturnedController::class, 'index'])->name('index');
        Route::get('/{document}', [ApprovingAuthorityReturnedController::class, 'show'])->name('show');
    });
    Route::middleware(['role:approving-authority', 'permission:reports.approval', 'permission:documents.view.assigned', 'permission:documents.track', 'permission:documents.approve', 'permission:workflow.view_history'])->prefix('approving-authority/reports')->name('approving-authority.reports.')->group(function () {
        Route::get('/', [ApprovingAuthorityReportController::class, 'index'])->name('index');
        Route::get('/export', [ApprovingAuthorityReportController::class, 'export'])->name('export');
        Route::get('/print', [ApprovingAuthorityReportController::class, 'print'])->name('print');
    });
});
