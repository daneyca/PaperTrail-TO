<?php

namespace App\Http\Controllers;

use App\Models\AbstractQuotation;
use App\Models\AnnualProcurementPlan;
use App\Models\AnnualProcurementPlanItem;
use App\Models\BacResolution;
use App\Models\BacDeliberation;
use App\Models\DocumentTemplate;
use App\Models\InspectionAcceptanceRecord;
use App\Models\ProcurementDocument;
use App\Models\Ppmp;
use App\Models\PurchaseOrder;
use App\Models\Rfq;
use App\Models\SignatureRequest;
use App\Models\SupplementalApp;
use App\Services\Templates\OfficialEditableTemplateService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class DocumentSubmenuController extends Controller
{
    public function headOfficePpmp(Request $request)
    {
        return $this->render($request, 'head-office.ppmp');
    }

    public function headOfficePurchaseRequest(Request $request)
    {
        return $this->render($request, 'head-office.pr');
    }

    public function headOfficeMyDocuments(Request $request)
    {
        return $this->render($request, 'head-office.documents');
    }

    public function headOfficeReturnedDocuments(Request $request)
    {
        return $this->render($request, 'head-office.returned');
    }

    public function headOfficeReceivedBacResolutions(Request $request)
    {
        return $this->render($request, 'head-office.resolutions');
    }

    public function headOfficeRfq(Request $request)
    {
        return $this->render($request, 'head-office.rfq');
    }

    public function headOfficeAbstract(Request $request)
    {
        return $this->render($request, 'head-office.abstract');
    }

    public function headOfficePurchaseOrder(Request $request)
    {
        return $this->render($request, 'head-office.po');
    }

    public function headOfficeInspectionAcceptance(Request $request)
    {
        return $this->render($request, 'head-office.inspection');
    }

    public function prNumbering(Request $request)
    {
        return $this->render($request, 'pr-numbering');
    }

    public function bacSecretariatIncomingDocuments(Request $request)
    {
        return $this->render($request, 'bac-secretariat.incoming');
    }

    public function bacSecretariatDocumentRouting(Request $request)
    {
        return $this->render($request, 'bac-secretariat.routing');
    }

    public function bacSecretariatPpmpReview(Request $request)
    {
        return $this->render($request, 'bac-secretariat.ppmp');
    }

    public function bacSecretariatApp(Request $request)
    {
        return $this->render($request, 'bac-secretariat.app');
    }

    public function bacSecretariatSupplementalApp(Request $request)
    {
        return $this->render($request, 'bac-secretariat.supplemental-app');
    }

    public function bacSecretariatPurchaseRequest(Request $request)
    {
        return $this->render($request, 'bac-secretariat.pr');
    }

    public function bacSecretariatRfq(Request $request)
    {
        return $this->render($request, 'bac-secretariat.rfq');
    }

    public function bacSecretariatAbstract(Request $request)
    {
        return $this->render($request, 'bac-secretariat.abstract');
    }

    public function bacSecretariatBacResolution(Request $request)
    {
        return $this->render($request, 'bac-secretariat.resolution');
    }

    public function bacSecretariatPurchaseOrder(Request $request)
    {
        return $this->render($request, 'bac-secretariat.po');
    }

    public function bacSecretariatCompetitiveBidding(Request $request)
    {
        return $this->render($request, 'bac-secretariat.competitive-bidding');
    }

    public function bacSecretariatTechnicalChecklist(Request $request)
    {
        return view('bac-secretariat.competitive-bidding.checklist-technical');
    }

    public function bacSecretariatFinancialChecklist(Request $request)
    {
        return view('bac-secretariat.competitive-bidding.checklist-financial');
    }

    public function bacMemberReviews(Request $request)
    {
        return $this->render($request, 'bac-member.reviews');
    }

    public function bacChairReviews(Request $request)
    {
        return $this->render($request, 'bac-chair.reviews');
    }

    public function budgetReview(Request $request)
    {
        return $this->render($request, 'budget.review');
    }

    public function accountingReview(Request $request)
    {
        return $this->render($request, 'accounting.review');
    }

    public function approvingAuthorityApprovals(Request $request)
    {
        return $this->render($request, 'approving-authority.approvals');
    }

    public function adminManagement(Request $request)
    {
        return $this->render($request, 'admin.management');
    }

    private function render(Request $request, string $key)
    {
        $menu = $this->menu($key, $request);

        abort_unless($menu, 404);

        $menu = $this->withRecordListDefaults($key, $menu);

        return view('document-submenu.show', $menu);
    }

    private function menu(string $key, Request $request): ?array
    {
        $user = $request->user();

        return match ($key) {
            'head-office.ppmp' => $this->headOfficePpmpMenu($user),
            'head-office.pr' => $this->headOfficePurchaseRequestMenu($user),
            'head-office.documents' => $this->headOfficeDocumentsMenu($user),
            'head-office.returned' => $this->headOfficeReturnedMenu($user),
            'head-office.resolutions' => $this->headOfficeReceivedResolutionsMenu($user),
            'head-office.rfq' => $this->headOfficeDocumentMenu($user, 'RFQ Submenu', 'Open Request for Quotation records linked to your office.', Rfq::class, 'head-office.rfqs', 'rfq', 'teal', true, 'rfq'),
            'head-office.abstract' => $this->headOfficeDocumentMenu($user, 'Abstract Submenu', 'Open Abstract of Quotations records linked to your office.', AbstractQuotation::class, 'head-office.abstracts', 'abstract', 'indigo', true, 'abstract'),
            'head-office.po' => $this->headOfficeDocumentMenu($user, 'Purchase Order Submenu', 'Open Purchase Order records linked to your office.', PurchaseOrder::class, 'head-office.purchase-orders', 'po', 'orange', true, 'purchase_order'),
            'head-office.inspection' => $this->headOfficeInspectionMenu($user),
            'pr-numbering' => $this->prNumberingMenu(),
            'bac-secretariat.incoming' => $this->bacSecretariatIncomingMenu(),
            'bac-secretariat.routing' => $this->bacSecretariatRoutingMenu(),
            'bac-secretariat.ppmp' => $this->bacSecretariatPpmpMenu($user),
            'bac-secretariat.app' => $this->bacSecretariatAppMenu($user),
            'bac-secretariat.supplemental-app' => $this->bacSecretariatSupplementalAppMenu(),
            'bac-secretariat.pr' => $this->bacSecretariatPurchaseRequestMenu(),
            'bac-secretariat.rfq' => $this->bacSecretariatRfqMenu(),
            'bac-secretariat.abstract' => $this->bacSecretariatAbstractMenu(),
            'bac-secretariat.resolution' => $this->bacSecretariatResolutionMenu($user),
            'bac-secretariat.po' => $this->bacSecretariatPurchaseOrderMenu($user),
            'bac-secretariat.competitive-bidding' => $this->bacSecretariatCompetitiveBiddingMenu(),
            'bac-member.reviews' => $this->bacMemberReviewMenu(),
            'bac-chair.reviews' => $this->bacChairReviewMenu(),
            'budget.review' => $this->budgetReviewMenu(),
            'accounting.review' => $this->accountingReviewMenu(),
            'approving-authority.approvals' => $this->approvingAuthorityMenu(),
            'admin.management' => $this->adminManagementMenu(),
            default => null,
        };
    }

    private function headOfficePpmpMenu($user): array
    {
        $scope = fn (Builder $query) => $this->scopeToUserOffice($query, ProcurementDocument::class, $user)
            ->where('document_type', 'PPMP');

        return [
            'eyebrow' => 'Documents',
            'title' => 'PPMP Submenu',
            'subtitle' => 'Choose an action for Project Procurement Management Plan records.',
            'backRoute' => 'head-office.dashboard',
            'cards' => [
                $this->card('Create Online', 'Prepare a new Project Procurement Management Plan in PaperTrail.', 'ppmp', 'violet', 'head-office.ppmp.create'),
                $this->templateDownloadCard('ppmp', 'violet'),
                $this->templateUploadCard('ppmp', 'violet'),
                $this->card('Upload Supporting Document', 'Upload scanned files for PPMP review readiness.', 'upload', 'blue', 'ai-document-verification.index'),
                $this->card('View PPMP Records', 'View all PPMP records created by your office and filter statuses there.', 'view', 'violet', 'head-office.ppmp.index', [], [], $this->count(ProcurementDocument::class, [], $scope)),
                $this->card('Comments / History', 'View movement history and remarks from document tracking.', 'clock', 'slate', 'head-office.svp-tracking.index'),
            ],
            'recentRecords' => $this->recent(ProcurementDocument::class, 'head-office.ppmp.show', $scope, 'tracking_number', 'title', 'head-office.ppmp.edit', [ProcurementDocument::STATUS_PPMP_DRAFT], 'Draft #'),
            'recentRecordsLayout' => 'document-table',
            'recentRecordsTitle' => 'Recent PPMP Records',
            'recentRecordsSubtitle' => 'View and continue your latest Project Procurement Management Plan records.',
            'helpSteps' => [
                'Create or continue a PPMP draft.',
                'Upload supporting files when a scanned source is needed.',
                'Complete the required e-signature before submitting the PPMP to BACSEC-004 for APP consolidation processing.',
                'Track returned remarks or accepted records from tracking pages.',
            ],
        ];
    }

    private function headOfficePurchaseRequestMenu($user): array
    {
        $hasBacsec002PurchaseRequestCapability = $user
            && method_exists($user, 'hasBacsec002PurchaseRequestCapability')
            && $user->hasBacsec002PurchaseRequestCapability();
        $scope = fn (Builder $query) => $this->scopeToUserOffice($query, ProcurementDocument::class, $user)
            ->whereIn('document_type', ['PR', 'Purchase Request']);

        return [
            'eyebrow' => $hasBacsec002PurchaseRequestCapability ? 'Procurement Requests' : 'Documents',
            'title' => 'Purchase Request Submenu',
            'subtitle' => 'Choose an action for Purchase Request records.',
            'backRoute' => $hasBacsec002PurchaseRequestCapability ? 'dashboard' : 'head-office.dashboard',
            'cards' => [
                $this->card('Create Online', 'Prepare a new Purchase Request in PaperTrail.', 'pr', 'blue', 'head-office.pr.create'),
                $this->templateDownloadCard('pr', 'blue'),
                $this->templateUploadCard('pr', 'blue'),
                $this->card('Upload Supporting Document', 'Choose an existing PR record, then upload files from its Supporting Files section.', 'upload', 'blue', 'head-office.pr.index'),
                $this->card('View PR Records', 'View all PR records from your office and filter statuses there.', 'view', 'blue', 'head-office.pr.index', [], [], $this->count(ProcurementDocument::class, [], $scope)),
                $this->card('Comments / History', 'View remarks and movement history.', 'clock', 'slate', 'head-office.svp-tracking.index'),
            ],
            'recentRecords' => $this->recent(ProcurementDocument::class, 'head-office.pr.show', $scope, 'tracking_number', 'title'),
            'helpSteps' => [
                'Create or continue a draft.',
                'Submit for required signatures, then PR number assignment.',
                'Upload supporting files from the PR Supporting Files section when required.',
                'Track returned remarks and routing history from the record.',
            ],
        ];
    }

    private function headOfficeDocumentsMenu($user): array
    {
        $scope = fn (Builder $query) => $this->scopeToUserOffice($query, ProcurementDocument::class, $user);

        return [
            'eyebrow' => 'Tracking & Records',
            'title' => 'My Documents Submenu',
            'subtitle' => 'Choose how to view procurement records linked to your office.',
            'backRoute' => 'head-office.dashboard',
            'cards' => [
                $this->card('View All Documents', 'Open all procurement records linked to your office.', 'view', 'blue', 'head-office.documents.index', [], [], $this->count(ProcurementDocument::class, [], $scope)),
                $this->card('Returned Documents', 'Open records returned for correction.', 'return', 'rose', 'head-office.documents.index', [], ['tab' => 'returned'], $this->count(ProcurementDocument::class, ['returned', 'returned_by_bac_secretariat', 'returned_by_budget', 'returned_by_accounting', 'returned_by_pr_numbering_staff'], $scope)),
                $this->card('Documents for Signature', 'Open signature requests assigned to you.', 'signature', 'indigo', 'signature-requests.index'),
                $this->card('Routing History', 'Open SVP workflow tracking.', 'clock', 'slate', 'head-office.svp-tracking.index'),
                $this->card('Upload Supporting Document', 'Upload supporting procurement files.', 'upload', 'blue', 'ai-document-verification.index'),
            ],
            'recentRecords' => $this->recent(ProcurementDocument::class, 'head-office.documents.show', $scope, 'tracking_number', 'title'),
            'helpSteps' => [
                'Use View All Documents for the full office record list.',
                'Open returned records to find corrections and remarks.',
                'Use Routing History for movement and office-to-office tracking.',
            ],
        ];
    }

    private function headOfficeReturnedMenu($user): array
    {
        $returnedStatuses = ['returned', 'returned_by_bac_secretariat', 'returned_by_budget', 'returned_by_accounting', 'returned_by_pr_numbering_staff'];
        $scope = fn (Builder $query) => $this->scopeToUserOffice($query, ProcurementDocument::class, $user);

        return [
            'eyebrow' => 'Tracking & Records',
            'title' => 'Returned Documents Submenu',
            'subtitle' => 'Open returned records, remarks, and correction tasks for your office.',
            'backRoute' => 'head-office.dashboard',
            'cards' => [
                $this->card('View Returned', 'Open all returned documents.', 'return', 'rose', 'head-office.documents.index', [], ['tab' => 'returned'], $this->count(ProcurementDocument::class, $returnedStatuses, $scope)),
                $this->card('Returned Remarks', 'Review return comments and correction notes.', 'view', 'slate', 'head-office.documents.index', [], ['tab' => 'returned']),
                $this->card('Upload Corrected Attachment', 'Upload corrected supporting files.', 'upload', 'blue', 'ai-document-verification.index'),
                $this->card('History', 'Open workflow movement history.', 'clock', 'slate', 'head-office.svp-tracking.index'),
            ],
            'recentRecords' => $this->recent(ProcurementDocument::class, 'head-office.returned.show', fn (Builder $query) => $scope($query)->whereIn('status', $returnedStatuses), 'tracking_number', 'title'),
            'helpSteps' => [
                'Open returned records first to read the correction remarks.',
                'Continue editing from the returned document detail page.',
                'Upload corrected attachments only when supporting files are needed.',
            ],
        ];
    }

    private function headOfficeReceivedResolutionsMenu($user): array
    {
        $scope = fn (Builder $query) => $this->scopeToUserOffice($query, BacResolution::class, $user);

        return [
            'eyebrow' => 'SVP Documents',
            'title' => 'Received BAC Resolutions Submenu',
            'subtitle' => 'Open BAC Resolutions returned to your office for viewing or acknowledgement.',
            'backRoute' => 'head-office.dashboard',
            'cards' => [
                $this->card('View Received Records', 'Open all received BAC Resolution records.', 'view', 'amber', 'head-office.resolutions.index', [], [], $this->count(BacResolution::class, [], $scope)),
                $this->card('Upload Supporting Document', 'Upload related supporting files.', 'upload', 'blue', 'ai-document-verification.index'),
                $this->disabledCard('Download Final Copy', 'Open a saved BAC Resolution record to print or download the official document.', 'document', 'slate', 'Open Record'),
                $this->card('Comments / History', 'Open SVP movement history.', 'clock', 'slate', 'head-office.svp-tracking.index'),
            ],
            'recentRecords' => $this->recent(BacResolution::class, 'head-office.resolutions.show', $scope, 'resolution_number', 'title'),
            'helpSteps' => [
                'Open the received BAC Resolution record.',
                'Acknowledge receipt when the detail page allows it.',
                'Use history to trace the complete document chain.',
            ],
        ];
    }

    private function headOfficeDocumentMenu($user, string $title, string $subtitle, string $modelClass, string $routePrefix, string $icon, string $accent, bool $canCreate, ?string $templateType = null): array
    {
        $scope = fn (Builder $query) => $this->scopeToUserOffice($query, $modelClass, $user);

        $cards = [
            $this->card('View Records', 'Open records linked to your office.', 'view', $accent, "{$routePrefix}.index", [], [], $this->count($modelClass, [], $scope)),
            $this->card('Upload Supporting Document', 'Upload related supporting files.', 'upload', 'blue', 'ai-document-verification.index'),
            $this->disabledCard('Print / Download Final Copy', 'Open a saved record to print or download the official form.', 'document', 'slate', 'Open Record'),
            $this->card('Comments / History', 'Open workflow movement history.', 'clock', 'slate', 'head-office.svp-tracking.index'),
        ];

        if ($canCreate) {
            $setupCards = [
                $templateType ? $this->templateDownloadCard($templateType, $accent) : $this->disabledCard('Download Editable Template', 'Editable template is not configured for this document.', 'document', 'slate'),
                $templateType ? $this->templateUploadCard($templateType, $accent) : $this->disabledCard('Upload Filled Template', 'Template upload is not configured for this document.', 'upload', 'slate'),
            ];

            if ($routePrefix !== 'head-office.abstracts') {
                array_unshift($setupCards, $this->card('Create ' . str_replace(' Submenu', '', $title), 'Start a new record.', $icon, $accent, "{$routePrefix}.create"));
            }

            array_unshift($cards, ...$setupCards);
        }

        return [
            'eyebrow' => 'SVP Documents',
            'title' => $title,
            'subtitle' => $subtitle,
            'backRoute' => 'head-office.dashboard',
            'cards' => $cards,
            'recentRecords' => $this->recent($modelClass, "{$routePrefix}.show", $scope, $this->numberColumn($modelClass), $this->titleColumn($modelClass)),
            'helpSteps' => [
                'Open the record list to view documents linked to your office.',
                'Use upload only for supporting files.',
                'Open a saved record for print, details, and movement history.',
            ],
        ];
    }

    private function headOfficeInspectionMenu($user): array
    {
        $scope = fn (Builder $query) => $this->scopeToUserOffice($query, PurchaseOrder::class, $user);

        return [
            'eyebrow' => 'SVP Documents',
            'title' => 'Inspection / Acceptance Submenu',
            'subtitle' => 'Open inspection and acceptance records linked to Purchase Orders.',
            'backRoute' => 'head-office.dashboard',
            'cards' => [
                $this->card('Create Inspection / Acceptance', 'Open Purchase Orders and record inspection details from the detail page.', 'document', 'orange', 'head-office.inspection.index'),
                $this->templateDownloadCard('inspection_acceptance', 'orange'),
                $this->templateUploadCard('inspection_acceptance', 'orange'),
                $this->card('Upload Supporting Document', 'Upload delivery or inspection files.', 'upload', 'blue', 'ai-document-verification.index'),
                $this->card('View Inspection / Acceptance Records', 'Open all inspection and acceptance records.', 'view', 'orange', 'head-office.inspection.index'),
                $this->disabledCard('Download Final Copy', 'Open a saved record to print the official form.', 'document', 'slate', 'Open Record'),
                $this->card('Comments / History', 'Open workflow movement history.', 'clock', 'slate', 'head-office.svp-tracking.index'),
            ],
            'recentRecords' => $this->recent(PurchaseOrder::class, 'head-office.inspection.show', $scope, 'po_number', 'supplier_name'),
            'helpSteps' => [
                'Open pending records when goods or services are ready for inspection.',
                'Use the detail page to record acceptance when allowed.',
                'Upload supporting files only when scanned evidence is needed.',
            ],
        ];
    }

    private function prNumberingMenu(): array
    {
        $scope = fn (Builder $query) => $query->whereIn('document_type', ['PR', 'Purchase Request']);
        $pendingPrNumberRequests = $this->pendingPrNumberRequestsCount();

        return [
            'eyebrow' => 'SVP / Alternative Procurement',
            'title' => 'PR Number Assignment',
            'subtitle' => 'Assign official PR numbers and monitor numbered Purchase Requests.',
            'backRoute' => 'pr-numbering.dashboard',
            'cards' => [
                $this->card('Assign PR Number', 'Open the pending queue to assign PR numbers.', 'hash', $pendingPrNumberRequests > 0 ? 'amber' : 'blue', 'pr-numbering.pending.index', [], [], $pendingPrNumberRequests > 0 ? $pendingPrNumberRequests : null),
                $this->card('View Numbered PR Records', 'Open PR numbering records and filter statuses there.', 'view', 'blue', 'pr-numbering.assigned.index', [], [], $this->count(ProcurementDocument::class, [], $scope)),
                $this->card('Upload Supporting Document', 'Upload supporting reference files.', 'upload', 'blue', 'ai-document-verification.index'),
                $this->card('Comments / History', 'Review PR movement history.', 'clock', 'slate', 'pr-numbering.assigned.index'),
            ],
            'recentRecords' => $this->recent(ProcurementDocument::class, 'pr-numbering.pending.show', $scope, 'tracking_number', 'title'),
            'helpSteps' => [
                'Open pending PRs.',
                'Assign or return from the detail page.',
                'Use Numbered PRs to confirm completed assignments.',
            ],
        ];
    }

    private function pendingPrNumberRequestsCount(): int
    {
        return ProcurementDocument::query()
            ->whereIn('document_type', ['PR', 'Purchase Request'])
            ->where('status', ProcurementDocument::STATUS_PENDING_PR_NUMBER_ASSIGNMENT)
            ->where('pr_number_status', ProcurementDocument::PR_NUMBER_STATUS_PENDING_ASSIGNMENT)
            ->count();
    }

    private function bacSecretariatIncomingMenu(): array
    {
        return [
            'eyebrow' => 'Document Workflow',
            'title' => 'Incoming Documents Submenu',
            'subtitle' => 'Receive, validate, and monitor documents submitted to BAC Secretariat.',
            'backRoute' => 'bac-secretariat.dashboard',
            'cards' => [
                $this->card('View Incoming', 'Open all incoming routed documents.', 'view', 'blue', 'bac-secretariat.incoming.index', [], [], $this->count(ProcurementDocument::class, ['submitted_to_bac_secretariat', 'pr_submitted', 'pending_ppmp_review'])),
                $this->card('Routing History', 'Open document routing records.', 'clock', 'slate', 'bac-secretariat.routing.index'),
                $this->card('Upload Supporting Document', 'Upload incoming supporting files.', 'upload', 'blue', 'ai-document-verification.index'),
            ],
            'recentRecords' => $this->recent(ProcurementDocument::class, 'bac-secretariat.incoming.show', null, 'tracking_number', 'title'),
            'helpSteps' => [
                'Open View Incoming to receive newly submitted records.',
                'Validate records from their detail pages.',
                'Use Routing History after a document moves offices.',
            ],
        ];
    }

    private function bacSecretariatRoutingMenu(): array
    {
        return [
            'eyebrow' => 'Document Workflow',
            'title' => 'Document Routing Submenu',
            'subtitle' => 'Route procurement documents and monitor office-to-office movement.',
            'backRoute' => 'bac-secretariat.dashboard',
            'cards' => [
                $this->card('View Routing Queue', 'Open documents available for routing.', 'view', 'blue', 'bac-secretariat.routing.index'),
                $this->card('Route Documents', 'Open the routing work queue.', 'workflow', 'indigo', 'bac-secretariat.routing.index'),
                $this->card('History', 'Open SVP monitoring records.', 'clock', 'slate', 'bac-secretariat.svp-monitoring.index'),
                $this->card('Upload Supporting Document', 'Upload routing reference files.', 'upload', 'blue', 'ai-document-verification.index'),
            ],
            'recentRecords' => $this->recent(ProcurementDocument::class, 'bac-secretariat.routing.show', null, 'tracking_number', 'title'),
            'helpSteps' => [
                'Use the routing queue to move documents to the next office.',
                'Open returned records to see where corrections are needed.',
                'Use History for complete movement tracing.',
            ],
        ];
    }

    private function bacSecretariatPpmpMenu($user): array
    {
        $scope = function (Builder $query) use ($user) {
            $query->whereIn('document_type', ['PPMP', 'Project Procurement Management Plan'])
                ->whereIn('status', [
                    ProcurementDocument::STATUS_PENDING_PPMP_REVIEW,
                    ProcurementDocument::STATUS_UNDER_PPMP_REVIEW,
                    ProcurementDocument::STATUS_RETURNED_BY_BAC_SECRETARIAT,
                    ProcurementDocument::STATUS_ACCEPTED_FOR_APP_CONSOLIDATION,
                ]);

            $this->whereCompletedPpmpSignature($query);

            if ($user) {
                $query->where(function (Builder $assignment) use ($user) {
                    $assignment->where('assigned_to_user_id', $user->id)
                        ->orWhereNull('assigned_to_user_id');
                });
            }
        };
        $receivedScope = function (Builder $query) use ($scope) {
            $scope($query);
            $query->whereIn('status', [
                ProcurementDocument::STATUS_PENDING_PPMP_REVIEW,
                ProcurementDocument::STATUS_UNDER_PPMP_REVIEW,
            ]);
        };

        return [
            'eyebrow' => 'Planning & APP',
            'title' => 'PPMP Review Submenu',
            'subtitle' => 'Review PPMP submissions and prepare accepted records for APP consolidation.',
            'backRoute' => 'bac-secretariat.dashboard',
            'cards' => [
                $this->card('View Review Queue', 'Open the full PPMP review list and filter statuses there.', 'view', 'violet', 'bac-secretariat.ppmp.index', [], [], $this->count(ProcurementDocument::class, [], $receivedScope)),
                $this->templateDownloadCard('ppmp', 'violet'),
                $this->templateUploadCard('ppmp', 'violet'),
                $this->card('Upload Document', 'Upload scanned files for review reference.', 'upload', 'blue', 'ai-document-verification.index'),
                $this->card('Comments / History', 'Review movement history for routed documents.', 'clock', 'slate', 'bac-secretariat.svp-monitoring.index'),
            ],
            'recentRecords' => $this->recent(ProcurementDocument::class, 'bac-secretariat.ppmp.show', $scope, 'tracking_number', 'title'),
            'helpSteps' => [
                'Open incoming PPMP submissions.',
                'Start review from the PPMP detail page.',
                'Return unclear records or accept eligible PPMPs for APP consolidation.',
            ],
        ];
    }

    private function bacSecretariatAppMenu($user): array
    {
        $readyPpmpScope = function (Builder $query) use ($user): Builder {
            $query
                ->where('document_type', 'PPMP')
                ->where('status', ProcurementDocument::STATUS_ACCEPTED_FOR_APP_CONSOLIDATION)
                ->where(fn (Builder $signatureQuery) => $this->whereCompletedPpmpSignature($signatureQuery))
                ->whereNotIn('id', AnnualProcurementPlanItem::query()
                    ->whereNotNull('source_ppmp_document_id')
                    ->select('source_ppmp_document_id'));

            if ($user) {
                $query->where(function (Builder $assignment) use ($user) {
                    $assignment->where('assigned_to_user_id', $user->id)
                        ->orWhereNull('assigned_to_user_id');
                });
            }

            return $query;
        };

        return [
            'eyebrow' => 'Planning & APP',
            'title' => 'APP Consolidation Submenu',
            'subtitle' => 'Choose an action for Annual Procurement Plan consolidation records.',
            'backRoute' => 'bac-secretariat.dashboard',
            'cards' => [
                $this->card('Create Online', 'Create a consolidated Annual Procurement Plan in PaperTrail.', 'app', 'emerald', 'bac-secretariat.app.create'),
                $this->card('Ready PPMPs', 'Accepted PPMPs waiting to be added to an APP draft.', 'view', 'violet', 'bac-secretariat.ppmp.index', [], ['status' => ProcurementDocument::STATUS_ACCEPTED_FOR_APP_CONSOLIDATION], $this->count(ProcurementDocument::class, [], $readyPpmpScope)),
                $this->templateDownloadCard('app', 'emerald'),
                $this->templateUploadCard('app', 'emerald'),
                $this->card('Upload APP', 'Upload scanned or supporting APP files.', 'upload', 'blue', 'ai-document-verification.index'),
                $this->card('Supplemental APP', 'Create or continue a Supplemental APP.', 'app', 'emerald', 'bac-secretariat.supplemental-apps.create'),
                $this->card('View APP Records', 'Open all APP consolidation records and filter statuses there.', 'view', 'emerald', 'bac-secretariat.app.index', [], [], $this->count(AnnualProcurementPlan::class)),
                $this->card('Comments / History', 'Review APP movement and monitoring history.', 'clock', 'slate', 'bac-secretariat.svp-monitoring.index'),
            ],
            'recentRecords' => $this->appConsolidationQueueRecords($user),
            'recentRecordsTitle' => 'Recent APP Records',
            'recentRecordsSubtitle' => 'View and continue Annual Procurement Plan consolidation records.',
            'recentRecordsDocumentType' => 'APP',
            'helpSteps' => [
                'Open accepted PPMP records ready for APP consolidation.',
                'Create or continue an APP draft.',
                'Add accepted PPMP records into the APP draft.',
                'Submit the APP for approval and monitor returned remarks.',
            ],
        ];
    }

    private function scopeAppConsolidationPpmps(Builder $query, $user, array $statuses): Builder
    {
        return $query
            ->where('document_type', 'PPMP')
            ->whereIn('status', $statuses)
            ->where(fn (Builder $signatureQuery) => $this->whereCompletedPpmpSignature($signatureQuery))
            ->when($user, function (Builder $visibilityQuery) use ($user) {
                $visibilityQuery->where(function (Builder $visibility) use ($user) {
                    $visibility->where('assigned_to_user_id', $user->id)
                        ->orWhere(function (Builder $unassigned) {
                            $unassigned->whereNull('assigned_to_user_id')
                                ->whereHas('currentOffice', function (Builder $officeQuery) {
                                    $officeQuery->where('code', 'BACSEC')
                                        ->orWhere('name', 'BAC Secretariat');
                                });
                        });
                });
            });
    }

    private function whereCompletedPpmpSignature(Builder $query): void
    {
        $query
            ->whereExists(function ($signature) {
                $signature->selectRaw('1')
                    ->from('signature_requests')
                    ->whereColumn('signature_requests.document_id', 'procurement_documents.id')
                    ->where('signature_requests.document_type', 'ppmp')
                    ->where('signature_requests.is_required', true);
            })
            ->whereNotExists(function ($signature) {
                $signature->selectRaw('1')
                    ->from('signature_requests')
                    ->whereColumn('signature_requests.document_id', 'procurement_documents.id')
                    ->where('signature_requests.document_type', 'ppmp')
                    ->where('signature_requests.is_required', true)
                    ->where('signature_requests.status', '!=', SignatureRequest::STATUS_SIGNED);
            });
    }

    private function appConsolidationQueueRecords($user): array
    {
        return collect($this->recent(AnnualProcurementPlan::class, 'bac-secretariat.app.show', null, 'app_number', 'title'))
            ->sortByDesc(fn (array $record) => $record['date'] ?? null)
            ->take(5)
            ->values()
            ->all();
    }

    private function bacSecretariatSupplementalAppMenu(): array
    {
        return [
            'eyebrow' => 'Planning & APP',
            'title' => 'Supplemental APP Submenu',
            'subtitle' => 'Prepare and manage Supplemental APP records for unmatched PR needs.',
            'backRoute' => 'bac-secretariat.dashboard',
            'cards' => [
                $this->card('Create Online', 'Prepare a Supplemental APP record in PaperTrail.', 'app', 'emerald', 'bac-secretariat.supplemental-apps.create'),
                $this->templateDownloadCard('supplemental_app', 'emerald'),
                $this->templateUploadCard('supplemental_app', 'emerald'),
                $this->card('Upload Supporting Document', 'Upload Supplemental APP supporting files.', 'upload', 'blue', 'ai-document-verification.index'),
                $this->card('View Supplemental APP Records', 'Open all Supplemental APP records and filter statuses there.', 'view', 'emerald', 'bac-secretariat.supplemental-apps.index', [], [], $this->count(SupplementalApp::class)),
                $this->card('Comments / History', 'Review monitoring and remarks history.', 'clock', 'slate', 'bac-secretariat.svp-monitoring.index'),
            ],
            'recentRecords' => $this->recent(SupplementalApp::class, 'bac-secretariat.supplemental-apps.show', null, 'supplemental_app_number', 'title'),
            'helpSteps' => [
                'Create a Supplemental APP only when a PR has no matching APP reference.',
                'Save drafts while collecting details.',
                'Submit and link accepted records back to the PR workflow.',
            ],
        ];
    }

    private function bacSecretariatPurchaseRequestMenu(): array
    {
        $scope = fn (Builder $query) => $query->whereIn('document_type', ['PR', 'Purchase Request']);

        return [
            'eyebrow' => 'Document Workflow',
            'title' => 'Purchase Request Submenu',
            'subtitle' => 'Review and route Purchase Requests submitted to BAC Secretariat.',
            'backRoute' => 'bac-secretariat.dashboard',
            'cards' => [
                $this->card('Upload Supporting Document', 'Upload PR supporting files.', 'upload', 'blue', 'ai-document-verification.index'),
                $this->disabledCard('Download / Print', 'Open a saved PR record to print the official form.', 'document', 'slate'),
                $this->card('View PR Records', 'Open the full BAC Secretariat PR list and filter statuses there.', 'view', 'blue', 'bac-secretariat.pr.index', [], [], $this->count(ProcurementDocument::class, [], $scope)),
                $this->card('Comments / History', 'Review PR routing and remarks history.', 'clock', 'slate', 'bac-secretariat.svp-monitoring.index'),
            ],
            'recentRecords' => $this->recent(ProcurementDocument::class, 'bac-secretariat.pr.show', $scope, 'tracking_number', 'title'),
            'helpSteps' => [
                'Acknowledge newly submitted PRs.',
                'Start validation from the PR detail page.',
                'Return for correction or route the PR to the next office.',
            ],
        ];
    }

    private function bacSecretariatRfqMenu(): array
    {
        return $this->bacDocumentMenu(
            'RFQ Submenu',
            'Create and monitor Request for Quotation records.',
            Rfq::class,
            'bac-secretariat.rfqs',
            'rfq',
            'teal',
            ['issued' => ['issued', 'submitted'], 'approved' => ['quoted'], 'returned' => ['returned']],
            true,
            'rfq',
        );
    }

    private function bacSecretariatAbstractMenu(): array
    {
        return $this->bacDocumentMenu(
            'Abstract Submenu',
            'Create and monitor Abstract of Quotations records.',
            AbstractQuotation::class,
            'bac-secretariat.abstracts',
            'abstract',
            'indigo',
            ['issued' => ['submitted'], 'approved' => ['ready_for_po'], 'returned' => ['returned']],
            true,
            'abstract',
        );
    }

    private function bacSecretariatResolutionMenu($user): array
    {
        return $this->bacDocumentMenu(
            'BAC Resolution Submenu',
            'Prepare BAC Resolutions and monitor signature/approval progress.',
            BacResolution::class,
            'bac-secretariat.resolutions',
            'document',
            'amber',
            ['issued' => ['submitted_to_bac_chair', 'forwarded_to_hope'], 'approved' => ['confirmed_by_bac_chair', 'approved_by_hope'], 'returned' => ['returned_by_bac_chair', 'returned_by_hope', 'returned']],
            $user?->hasPermission('bac.resolution.create') ?? false,
            'bac_resolution',
        );
    }

    private function bacSecretariatPurchaseOrderMenu($user): array
    {
        return $this->bacDocumentMenu(
            'Purchase Order Submenu',
            'Create and monitor Purchase Order records.',
            PurchaseOrder::class,
            'bac-secretariat.purchase-orders',
            'po',
            'orange',
            ['issued' => ['submitted', 'forwarded_to_supplier', 'issued'], 'approved' => ['approved', 'completed'], 'returned' => ['returned']],
            $user?->hasPermission('po.prepare') ?? true,
            'purchase_order',
        );
    }

    private function bacSecretariatCompetitiveBiddingMenu(): array
    {
        $setupBadge = 'For setup';

        return [
            'eyebrow' => 'BACSEC-002',
            'title' => 'Competitive Bidding',
            'subtitle' => 'BACSEC-002 document preparation and evaluation work areas for competitive bidding procurement.',
            'backRoute' => 'bac-secretariat.dashboard',
            'cards' => [
                $this->disabledCard('Letter of Invitation to Observer', 'Prepare the observer invitation for bidding activities.', 'document', 'violet', $setupBadge),
                $this->disabledCard('Checklist Requirements', 'Track technical and financial requirement completeness.', 'check', 'blue', $setupBadge),
                $this->disabledCard('Abstract of Bids', 'Prepare the bid abstract with As Read and As Calculated parts.', 'document', 'indigo', $setupBadge),
                $this->disabledCard('Bid Evaluation', 'Record bid evaluation results and recommendation details.', 'check', 'violet', $setupBadge),
                $this->disabledCard('Post Qualification Evaluation', 'Record post qualification evaluation findings.', 'check', 'emerald', $setupBadge),
                $this->disabledCard('Post Qualification Report of Technical Working Group', 'Prepare the Technical Working Group post qualification report.', 'document', 'emerald', $setupBadge),
                $this->disabledCard('Notice of Post Qualification', 'Prepare the notice issued after post qualification review.', 'document', 'amber', $setupBadge),
                $this->disabledCard('BAC Resolution on Post Qualification', 'Prepare the BAC Resolution for post qualification results.', 'document', 'amber', $setupBadge),
                $this->disabledCard('Notice Issued by the BAC', 'Prepare BAC-issued notices for the bidding process.', 'document', 'blue', $setupBadge),
                $this->disabledCard('Notice of Award of Contract', 'Prepare and monitor the Notice of Award of Contract.', 'check', 'emerald', $setupBadge),
                $this->disabledCard('Performance Bond', 'Track performance bond submission and reference details.', 'document', 'orange', $setupBadge),
                $this->disabledCard('Contract Agreement Form', 'Prepare or track the contract agreement form.', 'document', 'orange', $setupBadge),
                $this->card('Purchase Order', 'Create or continue a Purchase Order.', 'po', 'orange', 'bac-secretariat.purchase-orders.create'),
                $this->disabledCard('Notice to Proceed', 'Prepare and monitor the Notice to Proceed.', 'document', 'emerald', $setupBadge),
            ],
            'recentRecords' => [],
            'helpSteps' => [
                'Use this menu as the BACSEC-002 competitive bidding workspace.',
                'Items marked For setup are visible for workflow planning but do not yet create records.',
                'Purchase Order opens the existing PaperTrail Purchase Order module.',
            ],
        ];
    }

    private function bacDocumentMenu(string $title, string $subtitle, string $modelClass, string $routePrefix, string $icon, string $accent, array $statusGroups, bool $canCreate = true, ?string $templateType = null): array
    {
        return [
            'eyebrow' => 'SVP Documents',
            'title' => $title,
            'subtitle' => $subtitle,
            'backRoute' => 'bac-secretariat.dashboard',
            'cards' => [
                $canCreate
                    ? $this->card('Create Online', 'Start a new record for this document type in PaperTrail.', $icon, $accent, "{$routePrefix}.create")
                    : $this->disabledCard('Create ' . str_replace(' Submenu', '', $title), 'Your role does not currently have create permission for this document.', $icon, 'slate'),
                $canCreate && $templateType ? $this->templateDownloadCard($templateType, $accent) : $this->disabledCard('Download Editable Template', 'Your role does not currently have template download permission for this document.', 'document', 'slate'),
                $canCreate && $templateType ? $this->templateUploadCard($templateType, $accent) : $this->disabledCard('Upload Filled Template', 'Your role does not currently have template upload permission for this document.', 'upload', 'slate'),
                $this->card('Upload Document', 'Upload supporting files for this module.', 'upload', 'blue', 'ai-document-verification.index'),
                $this->card('View Records', 'Open the complete record list and filter statuses there.', 'view', $accent, "{$routePrefix}.index", [], [], $this->count($modelClass)),
                $this->disabledCard('Print / Download Final Copy', 'Open a saved record to print or download the official form.', 'document', 'slate', 'Open Record'),
                $this->card('Comments / History', 'Review monitoring history and remarks.', 'clock', 'slate', 'bac-secretariat.svp-monitoring.index'),
            ],
            'recentRecords' => $this->recent($modelClass, "{$routePrefix}.show", null, $this->numberColumn($modelClass), $this->titleColumn($modelClass)),
            'helpSteps' => $routePrefix === 'bac-secretariat.resolutions'
                ? []
                : [
                    'Create or continue a draft record.',
                    'Submit the document when details are complete.',
                    'Open the saved record for print, attachments, and workflow actions.',
                ],
        ];
    }

    private function bacMemberReviewMenu(): array
    {
        return [
            'eyebrow' => 'BAC Member',
            'title' => 'Review Submenu',
            'subtitle' => 'Open assigned BAC review, deliberation, and reviewed document records.',
            'backRoute' => 'bac-member.dashboard',
            'cards' => [
                $this->card('View Review Queue', 'Open documents assigned for BAC member review.', 'document', 'blue', 'bac-member.review.index', [], [], $this->count(ProcurementDocument::class, ['pending_bac_review', 'under_bac_review', 'pending_bac_member_review'])),
                $this->card('BAC Deliberations', 'Open deliberation records and committee discussion.', 'clock', 'amber', 'bac-member.deliberations.index', [], [], $this->count(BacDeliberation::class)),
                $this->card('View Reviewed Records', 'Open completed BAC member review records.', 'view', 'indigo', 'bac-member.reviewed.index'),
                $this->card('Comments / History', 'Open BAC deliberations and remarks.', 'clock', 'slate', 'bac-member.deliberations.index'),
                $this->card('Upload Supporting Document', 'Upload reference documents if needed.', 'upload', 'blue', 'ai-document-verification.index'),
            ],
            'recentRecords' => $this->recent(ProcurementDocument::class, 'bac-member.review.show', null, 'tracking_number', 'title'),
            'helpSteps' => [
                'Open Documents for Review to inspect assigned records.',
                'Use deliberations for comments and committee discussion.',
                'Open Reviewed Documents to monitor completed BAC member actions.',
            ],
        ];
    }

    private function bacChairReviewMenu(): array
    {
        return [
            'eyebrow' => 'BAC Chair',
            'title' => 'Review & Approval Submenu',
            'subtitle' => 'Open BAC Chair approvals, confirmations, signed records, and reports.',
            'backRoute' => 'bac-chair.dashboard',
            'cards' => [
                $this->card('View Approval Queue', 'Open documents awaiting BAC Chair approval.', 'clock', 'amber', 'bac-chair.approvals.index', [], [], $this->count(ProcurementDocument::class, ['pending_bac_chair_review', 'under_bac_chair_review'])),
                $this->card('Documents for Confirmation', 'Open documents ready for confirmation.', 'signature', 'blue', 'bac-chair.confirmation.index', [], [], $this->count(ProcurementDocument::class, ['pending_bac_chair_confirmation', 'for_bac_chair_confirmation'])),
                $this->card('View Reviewed Records', 'Open reviewed BAC Chair document records.', 'view', 'indigo', 'bac-chair.reviewed.index'),
                $this->card('BAC Resolutions', 'Open BAC Resolution records for review or signing.', 'document', 'amber', 'bac-chair.resolutions.index', [], [], $this->count(BacResolution::class)),
                $this->card('Comments / History', 'Open BAC Chair reports and movement history.', 'clock', 'slate', 'bac-chair.reports.index'),
            ],
            'recentRecords' => $this->recent(ProcurementDocument::class, 'bac-chair.approvals.show', null, 'tracking_number', 'title'),
            'helpSteps' => [
                'Open pending approvals or confirmations first.',
                'Review BAC Resolution records when they are routed for signing.',
                'Use Reviewed Documents and Reports for completed action history.',
            ],
        ];
    }

    private function budgetReviewMenu(): array
    {
        return [
            'eyebrow' => 'Budget Office',
            'title' => 'Budget Review Submenu',
            'subtitle' => 'Choose a Budget Review action.',
            'backRoute' => 'budget.dashboard',
            'cards' => [
                $this->card('View Review Queue', 'Open documents awaiting budget review.', 'clock', 'amber', 'budget.pending-review.index', [], [], $this->count(ProcurementDocument::class, ['pending_budget_review', 'under_budget_review'])),
                $this->card('Upload Supporting Document', 'Upload supporting review files if needed.', 'upload', 'blue', 'ai-document-verification.index'),
                $this->card('View Routed Documents', 'Open Budget reports and routed summaries.', 'view', 'blue', 'budget.reports.index'),
                $this->card('Comments / History', 'Review routing history from related document pages.', 'clock', 'slate', 'budget.reports.index'),
            ],
            'recentRecords' => $this->recent(ProcurementDocument::class, 'budget.pending-review.show', fn (Builder $query) => $query->whereIn('status', ['pending_budget_review', 'under_budget_review']), 'tracking_number', 'title'),
            'helpSteps' => [
                'Open pending records.',
                'Start review from the detail page.',
                'Mark budget availability or return with remarks.',
            ],
        ];
    }

    private function accountingReviewMenu(): array
    {
        return [
            'eyebrow' => 'Accounting Office',
            'title' => 'Accounting Review Submenu',
            'subtitle' => 'Choose an Accounting Review action.',
            'backRoute' => 'accounting.dashboard',
            'cards' => [
                $this->card('View Review Queue', 'Open documents awaiting accounting review.', 'clock', 'amber', 'accounting.pending-review.index', [], [], $this->count(ProcurementDocument::class, ['pending_accounting_review', 'under_accounting_review'])),
                $this->card('Upload Supporting Document', 'Upload supporting review files if needed.', 'upload', 'blue', 'ai-document-verification.index'),
                $this->card('View Routed Documents', 'Open Accounting reports and routed summaries.', 'view', 'blue', 'accounting.reports.index'),
                $this->card('Comments / History', 'Review routing history from related document pages.', 'clock', 'slate', 'accounting.reports.index'),
            ],
            'recentRecords' => $this->recent(ProcurementDocument::class, 'accounting.pending-review.show', fn (Builder $query) => $query->whereIn('status', ['pending_accounting_review', 'under_accounting_review']), 'tracking_number', 'title'),
            'helpSteps' => [
                'Open pending records.',
                'Start review from the detail page.',
                'Verify accounting details or return with remarks.',
            ],
        ];
    }

    private function approvingAuthorityMenu(): array
    {
        $pendingApprovalCount = $this->count(ProcurementDocument::class, ['pending_approval', 'under_approval'])
            + $this->count(AnnualProcurementPlan::class, [AnnualProcurementPlan::STATUS_SUBMITTED, AnnualProcurementPlan::STATUS_CONSOLIDATED]);

        return [
            'eyebrow' => 'Approvals',
            'title' => 'Approval Submenu',
            'subtitle' => 'Choose an action for final approval records.',
            'backRoute' => 'approving-authority.dashboard',
            'cards' => [
                $this->card('View Approval Queue', 'Open documents awaiting final action.', 'clock', 'amber', 'approving-authority.pending.index', [], [], $pendingApprovalCount),
                $this->card('Documents for Signature', 'Open signature requests assigned to you.', 'signature', 'blue', 'signature-requests.index'),
                $this->card('Upload Supporting Document', 'Upload approval reference files if needed.', 'upload', 'blue', 'ai-document-verification.index'),
                $this->card('Approval History', 'Open approval reports and history.', 'clock', 'slate', 'approving-authority.reports.index'),
                $this->card('Comments / History', 'Review routed remarks from document details.', 'view', 'slate', 'approving-authority.reports.index'),
            ],
            'recentRecords' => $this->approvingAuthorityPendingRecords(),
            'recentRecordsTitle' => 'Recent Pending Approvals',
            'recentRecordsSubtitle' => 'Open APPs and procurement documents awaiting final action.',
            'helpSteps' => [
                'Open pending approvals.',
                'Review document details and signatures.',
                'Approve or return documents with clear remarks.',
            ],
        ];
    }

    private function approvingAuthorityPendingRecords(): array
    {
        $procurementRecords = collect($this->recent(
            ProcurementDocument::class,
            'approving-authority.pending.show',
            fn (Builder $query) => $query->whereIn('status', ['pending_approval', 'under_approval']),
            'tracking_number',
            'title'
        ));

        $appRecords = AnnualProcurementPlan::query()
            ->whereIn('status', [AnnualProcurementPlan::STATUS_SUBMITTED, AnnualProcurementPlan::STATUS_CONSOLIDATED])
            ->latest('updated_at')
            ->limit(5)
            ->get()
            ->map(function (AnnualProcurementPlan $app) {
                $date = $app->updated_at ?? $app->submitted_at ?? $app->created_at;

                return [
                    'id' => $app->getKey(),
                    'number' => $app->displayNumber(),
                    'title' => $app->title ?: 'Annual Procurement Plan',
                    'type' => 'APP',
                    'holder' => 'Head of the Procuring Entity',
                    'status' => $app->status,
                    'date' => $date,
                    'date_label' => $date ? $date->format('M d, Y h:i A') : 'N/A',
                    'url' => Route::has('bac-secretariat.app.show') ? route('bac-secretariat.app.show', $app) : null,
                    'edit_url' => null,
                    'print_url' => Route::has('bac-secretariat.app.print') ? route('bac-secretariat.app.print', $app) : null,
                ];
            });

        return $procurementRecords
            ->merge($appRecords)
            ->sortByDesc(fn (array $record) => $record['date'] ?? null)
            ->take(5)
            ->values()
            ->all();
    }

    private function adminManagementMenu(): array
    {
        return [
            'eyebrow' => 'Administration',
            'title' => 'Management Submenu',
            'subtitle' => 'Open monitoring and configuration areas for PaperTrail administration.',
            'backRoute' => 'admin.dashboard',
            'cards' => [
                $this->card('User Management', 'Manage PaperTrail accounts.', 'user', 'blue', 'admin.users.index'),
                $this->card('Office Management', 'Manage LGU office records.', 'document', 'emerald', 'admin.offices.index'),
                $this->card('Role Management', 'Manage role access and permissions.', 'signature', 'violet', 'admin.roles.index'),
                $this->card('Manage Document Templates', 'Upload master editable templates and manage versions.', 'document', 'indigo', 'admin.document-templates.index'),
                $this->card('Upload Master Template', 'Add the current LGU editable template file.', 'upload', 'blue', 'admin.document-templates.index'),
                $this->card('Template Version History', 'Review active and inactive editable template versions.', 'clock', 'slate', 'admin.document-templates.index'),
                $this->card('Workflow Rules', 'Configure routing rules.', 'filter', 'indigo', 'admin.settings.routing-rules.index'),
                $this->card('Document Requirements', 'Configure required document rules.', 'check', 'emerald', 'admin.settings.document-requirements.index'),
                $this->card('Delay Thresholds', 'Configure delay monitoring thresholds.', 'clock', 'amber', 'admin.settings.delay-thresholds.index'),
                $this->card('Notification Templates', 'Manage reusable notification text.', 'bell', 'blue', 'admin.settings.notification-templates.index'),
                $this->card('AI Verification Monitoring', 'Open OCR-ready upload and review foundation.', 'spark', 'violet', 'ai-document-verification.index'),
                $this->card('Audit Trail', 'Review system activity history.', 'clock', 'slate', 'admin.audit.index'),
                $this->card('Reports', 'Open administrative reports.', 'view', 'blue', 'admin.reports.index'),
                $this->card('System Activity', 'Open core system settings.', 'document', 'slate', 'admin.settings.index'),
            ],
            'recentRecords' => [],
            'helpSteps' => [
                'Use Accounts, Offices, and Roles for master data.',
                'Use rule pages to configure workflow and AI assistance settings.',
                'Use Audit Trail and Reports for monitoring.',
            ],
        ];
    }

    private function templateDownloadCard(string $documentType, string $accent = 'blue'): array
    {
        $templates = app(OfficialEditableTemplateService::class);
        $label = $this->templateLabel($documentType);

        if (! $this->templateDownloadAvailable($documentType)) {
            return $this->disabledCard(
                'Download Editable Template',
                "{$label} editable template is not yet available.",
                'document',
                'slate',
                'Missing Template',
            );
        }

        return $this->card(
            'Download Editable Template',
            "Download the current editable {$label} template.",
            'document',
            $accent,
            'document-templates.download',
            ['documentType' => $templates->routeKey($documentType)],
        );
    }

    private function templateDownloadAvailable(string $documentType): bool
    {
        return app(OfficialEditableTemplateService::class)->has($documentType)
            && app(OfficialEditableTemplateService::class)->exists($documentType);
    }

    private function templateUploadCard(string $documentType, string $accent = 'blue'): array
    {
        $templates = app(OfficialEditableTemplateService::class);
        $label = $this->templateLabel($documentType);

        return $this->card(
            'Upload Filled Template',
            "Upload a completed {$label} template for safe draft-import review.",
            'upload',
            $accent,
            'document-template-imports.upload-form',
            ['documentType' => $templates->routeKey($documentType)],
        );
    }

    private function templateLabel(string $documentType): string
    {
        return app(OfficialEditableTemplateService::class)->has($documentType)
            ? app(OfficialEditableTemplateService::class)->label($documentType)
            : (DocumentTemplateController::TYPES[$documentType]['label'] ?? str($documentType)->replace('_', ' ')->title()->toString());
    }

    private function card(string $title, string $description, string $icon, string $accent, ?string $routeName = null, array $parameters = [], array $query = [], ?int $count = null): array
    {
        $url = null;
        $disabled = false;

        if ($routeName && Route::has($routeName)) {
            $url = route($routeName, $parameters);

            if ($query) {
                $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
            }
        } elseif ($routeName) {
            $disabled = true;
        }

        return compact('title', 'description', 'icon', 'accent', 'url', 'count', 'disabled');
    }

    private function disabledCard(string $title, string $description, string $icon, string $accent, string $badge = 'Unavailable'): array
    {
        return [
            'title' => $title,
            'description' => $description,
            'icon' => $icon,
            'accent' => $accent,
            'url' => null,
            'count' => null,
            'disabled' => true,
            'badge' => $badge,
        ];
    }

    private function count(string $modelClass, array $statuses = [], ?callable $scope = null): int
    {
        if (! class_exists($modelClass)) {
            return 0;
        }

        /** @var Model $model */
        $model = new $modelClass;

        if (! Schema::hasTable($model->getTable())) {
            return 0;
        }

        $query = $modelClass::query();

        if ($scope) {
            $scope($query);
        }

        if ($statuses && Schema::hasColumn($model->getTable(), 'status')) {
            $query->whereIn('status', $statuses);
        }

        return (int) $query->count();
    }

    private function recent(string $modelClass, string $showRoute, ?callable $scope, string $numberColumn, string $titleColumn, ?string $editRoute = null, array $editableStatuses = [], string $fallbackPrefix = 'Record #', ?string $printRoute = null): array
    {
        if (! class_exists($modelClass)) {
            return [];
        }

        /** @var Model $model */
        $model = new $modelClass;

        if (! Schema::hasTable($model->getTable())) {
            return [];
        }

        $query = $modelClass::query();

        if ($scope) {
            $scope($query);
        }

        $dateColumn = $this->dateColumn($modelClass);
        $query->latest(Schema::hasColumn($model->getTable(), $dateColumn) ? $dateColumn : $model->getKeyName());
        $editRoute = $editRoute ?: $this->relatedActionRoute($showRoute, 'edit');
        $printRoute = $printRoute ?: $this->relatedActionRoute($showRoute, 'print');
        $editableStatuses = $editableStatuses ?: [
            'draft',
            'ppmp_draft',
            'pr_draft',
            'po_draft',
            'returned',
            'returned_by_bac_secretariat',
            'returned_by_budget',
            'returned_by_accounting',
            'returned_by_pr_numbering_staff',
            'po_returned',
        ];

        return $query->limit(5)->get()->map(function (Model $record) use ($showRoute, $numberColumn, $titleColumn, $dateColumn, $editRoute, $editableStatuses, $fallbackPrefix, $printRoute) {
            $date = $record->{$dateColumn} ?? $record->created_at ?? null;
            $status = $record->status ?? 'record';
            $dateLabel = $date instanceof \Carbon\CarbonInterface
                ? $date->format('M d, Y h:i A')
                : ($date ? (string) $date : 'N/A');

            return [
                'id' => $record->getKey(),
                'number' => $record->{$numberColumn} ?: ($fallbackPrefix . $record->getKey()),
                'title' => $record->{$titleColumn} ?: class_basename($record),
                'type' => $this->recordTypeLabel($record),
                'holder' => $this->recordHolderLabel($record),
                'status' => $status,
                'date' => $date,
                'date_label' => $dateLabel,
                'url' => Route::has($showRoute) ? route($showRoute, $record) : null,
                'edit_url' => $editRoute && Route::has($editRoute) && in_array($status, $editableStatuses, true)
                    ? route($editRoute, $record)
                    : null,
                'print_url' => $printRoute && Route::has($printRoute)
                    ? route($printRoute, $record)
                    : null,
            ];
        })->all();
    }

    private function withRecordListDefaults(string $key, array $menu): array
    {
        $titles = [
            'head-office.ppmp' => ['Recent PPMP Records', 'View and continue your latest Project Procurement Management Plan records.', 'PPMP'],
            'head-office.pr' => ['Recent Purchase Requests', 'View and continue your latest Purchase Request records.', 'PR'],
            'head-office.documents' => ['Recent Document Records', 'Review recent procurement records linked to your office.', null],
            'head-office.returned' => ['Recent Returned Document Records', 'Open returned records that need correction or clarification.', null],
            'head-office.resolutions' => ['Recent BAC Resolution Records', 'Open BAC Resolution records returned to your office.', 'BAC Resolution'],
            'head-office.rfq' => ['Recent RFQ Records', 'View and continue your latest Request for Quotation records.', 'RFQ'],
            'head-office.abstract' => ['Recent Abstract Records', 'View and continue your latest Abstract of Quotations records.', 'Abstract'],
            'head-office.po' => ['Recent Purchase Order Records', 'View and continue your latest Purchase Order records.', 'PO'],
            'head-office.inspection' => ['Recent Inspection / Acceptance Records', 'Open recent inspection and acceptance records linked to Purchase Orders.', 'Inspection / Acceptance'],
            'pr-numbering' => ['Recent Purchase Requests for Numbering', 'Open recent Purchase Requests awaiting or completing PR number assignment.', 'PR'],
            'bac-secretariat.incoming' => ['Recent Incoming Document Records', 'Open recent documents submitted or routed to BAC Secretariat.', null],
            'bac-secretariat.routing' => ['Recent Routed Documents', 'Open recent documents moving through BAC Secretariat routing.', null],
            'bac-secretariat.ppmp' => ['Recent PPMP Review Records', 'Review recent PPMP submissions and APP consolidation-ready records.', 'PPMP'],
            'bac-secretariat.app' => ['Recent APP Records', 'View and continue Annual Procurement Plan records.', 'APP'],
            'bac-secretariat.supplemental-app' => ['Recent Supplemental APP Records', 'View and continue Supplemental APP records.', 'Supplemental APP'],
            'bac-secretariat.pr' => ['Recent Purchase Request Records', 'Open recent Purchase Requests handled by BAC Secretariat.', 'PR'],
            'bac-secretariat.rfq' => ['Recent RFQ Records', 'View and continue Request for Quotation records.', 'RFQ'],
            'bac-secretariat.abstract' => ['Recent Abstract Records', 'View and continue Abstract of Quotations records.', 'Abstract'],
            'bac-secretariat.resolution' => ['Recent BAC Resolution Records', 'View and continue BAC Resolution records.', 'BAC Resolution'],
            'bac-secretariat.po' => ['Recent Purchase Order Records', 'View and continue Purchase Order records.', 'PO'],
            'bac-member.reviews' => ['Recent Review Documents', 'Open recent documents assigned for BAC review.', null],
            'bac-chair.reviews' => ['Recent Review Documents', 'Open recent BAC Chair review, confirmation, and approval records.', null],
            'budget.review' => ['Recent Budget Review Records', 'Open recent documents awaiting or undergoing budget review.', null],
            'accounting.review' => ['Recent Accounting Review Records', 'Open recent documents awaiting or undergoing accounting review.', null],
            'approving-authority.approvals' => ['Recent Approval Records', 'Open recent documents awaiting final action.', null],
            'admin.management' => ['Recent Administrative Records', 'Administrative activity records will appear here when available.', null],
        ];

        [$title, $subtitle, $documentType] = $titles[$key] ?? [
            'Recent Document Records',
            'Documents created, submitted, or routed through this module will appear here.',
            null,
        ];

        $menu['recentRecordsLayout'] = 'document-table';
        $menu['recentRecordsTitle'] ??= $title;
        $menu['recentRecordsSubtitle'] ??= $subtitle;
        $menu['recentRecordsDocumentType'] ??= $documentType;
        $menu['recentRecordsEmptyMessage'] ??= 'No document records found.';

        return $menu;
    }

    private function relatedActionRoute(string $showRoute, string $action): ?string
    {
        if (! str_ends_with($showRoute, '.show')) {
            return null;
        }

        $route = substr($showRoute, 0, -5) . '.' . $action;

        return Route::has($route) ? $route : null;
    }

    private function scopeToUserOffice(Builder $query, string $modelClass, $user): Builder
    {
        /** @var Model $model */
        $model = new $modelClass;
        $table = $model->getTable();

        return $query->where(function (Builder $owner) use ($table, $user) {
            $isBacsec002PrCapability = $user
                && method_exists($user, 'hasBacsec002PurchaseRequestCapability')
                && $user->hasBacsec002PurchaseRequestCapability();

            if ($isBacsec002PrCapability && $table === (new ProcurementDocument())->getTable()) {
                if (Schema::hasColumn($table, 'created_by')) {
                    $owner->orWhere('created_by', $user->id);
                }

                if (Schema::hasColumn($table, 'prepared_by_user_id')) {
                    $owner->orWhere('prepared_by_user_id', $user->id);
                }

                if (Schema::hasColumn($table, 'submitted_by_user_id')) {
                    $owner->orWhere('submitted_by_user_id', $user->id);
                }

                if (Schema::hasColumn($table, 'assigned_to_user_id')) {
                    $owner->orWhere('assigned_to_user_id', $user->id);
                }

                return;
            }

            if ($user?->office_id && Schema::hasColumn($table, 'office_id')) {
                $owner->orWhere('office_id', $user->office_id);
            }

            if ($user?->office_id && Schema::hasColumn($table, 'submitting_office_id')) {
                $owner->orWhere('submitting_office_id', $user->office_id);
            }

            if ($user?->office_id && Schema::hasColumn($table, 'requesting_office_id')) {
                $owner->orWhere('requesting_office_id', $user->office_id);
            }

            if (Schema::hasColumn($table, 'created_by')) {
                $owner->orWhere('created_by', $user?->id);
            }

            if (Schema::hasColumn($table, 'prepared_by_user_id')) {
                $owner->orWhere('prepared_by_user_id', $user?->id);
            }

            if (Schema::hasColumn($table, 'submitted_by_user_id')) {
                $owner->orWhere('submitted_by_user_id', $user?->id);
            }
        });
    }

    private function recordTypeLabel(Model $record): string
    {
        $type = $record->document_type ?? null;

        if (filled($type)) {
            $type = (string) $type;

            return match (strtolower($type)) {
                'purchase request' => 'PR',
                'project procurement management plan' => 'PPMP',
                default => strtoupper($type),
            };
        }

        if ($record instanceof AnnualProcurementPlan) {
            return 'APP';
        }

        if ($record instanceof SupplementalApp) {
            return 'SAPP';
        }

        return match (true) {
            $record instanceof Ppmp => 'PPMP',
            $record instanceof Rfq => 'RFQ',
            $record instanceof AbstractQuotation => 'Abstract',
            $record instanceof BacResolution => 'BAC Resolution',
            $record instanceof PurchaseOrder => 'PO',
            $record instanceof InspectionAcceptanceRecord => 'Inspection',
            default => class_basename($record),
        };
    }

    private function recordHolderLabel(Model $record): string
    {
        if (method_exists($record, 'currentOffice') && $record->currentOffice?->name) {
            return $record->currentOffice->name;
        }

        if (method_exists($record, 'submittingOffice') && $record->submittingOffice?->name) {
            return $record->submittingOffice->name;
        }

        if (method_exists($record, 'office') && $record->office?->name) {
            return $record->office->name;
        }

        return $record->current_holder
            ?? $record->office_name
            ?? $record->prepared_by_office
            ?? 'Not routed';
    }

    private function numberColumn(string $modelClass): string
    {
        return match ($modelClass) {
            Ppmp::class => 'ppmp_no',
            AnnualProcurementPlan::class => 'app_no',
            SupplementalApp::class => 'supplemental_app_number',
            Rfq::class => 'rfq_number',
            AbstractQuotation::class => 'abstract_number',
            BacResolution::class => 'resolution_number',
            PurchaseOrder::class => 'po_number',
            default => 'tracking_number',
        };
    }

    private function titleColumn(string $modelClass): string
    {
        return match ($modelClass) {
            Ppmp::class => 'end_user_unit',
            AbstractQuotation::class => 'project_name',
            Rfq::class, SupplementalApp::class, AnnualProcurementPlan::class, BacResolution::class => 'title',
            PurchaseOrder::class => 'supplier_name',
            default => 'title',
        };
    }

    private function dateColumn(string $modelClass): string
    {
        return 'updated_at';
    }
}
