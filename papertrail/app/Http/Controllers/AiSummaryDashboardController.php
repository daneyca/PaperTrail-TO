<?php

namespace App\Http\Controllers;

use App\Models\Office;
use App\Services\AiSummaryDashboardService;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class AiSummaryDashboardController extends Controller
{
    public function __construct(private readonly AiSummaryDashboardService $summaryService)
    {
    }

    public function index(Request $request): View
    {
        $filters = $this->validatedFilters($request);
        $data = $this->summaryService->dashboardData($request->user(), $filters);

        AuditLogger::log(
            'AI Procurement Summary',
            'ai_summary_viewed',
            'Admin viewed the AI procurement summary dashboard.',
            null,
            null,
            null,
            'info',
            [
                'period' => $data['summary']['period'] ?? null,
                'document_type' => $data['filters']['document_type'] ?? null,
                'office_id' => $data['filters']['office_id'] ?? null,
                'status' => $data['filters']['status'] ?? null,
            ]
        );

        return view('admin.ai-summary.index', [
            ...$data,
            'officeOptions' => Office::query()->orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        $filters = $this->validatedFilters($request);

        try {
            $report = $this->summaryService->generateSummary($request->user(), $filters);

            return redirect()
                ->route('admin.ai-summary.index', $this->queryParameters($filters))
                ->with('status', 'AI procurement insight generated for ' . $report->report_period . '.');
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('admin.ai-summary.index', $this->queryParameters($filters))
                ->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            Log::error('AI summary dashboard request failed.', [
                'user_id' => $request->user()?->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('admin.ai-summary.index', $this->queryParameters($filters))
                ->with('error', 'Unable to generate AI procurement insight right now.');
        }
    }

    public function export(): RedirectResponse
    {
        return redirect()
            ->route('admin.ai-summary.index')
            ->with('status', 'AI summary export will be available in a later phase.');
    }

    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'document_type' => ['nullable', 'string', 'max:80'],
            'office_id' => ['nullable'],
            'status' => ['nullable', 'string', 'max:120'],
        ]);
    }

    private function queryParameters(array $filters): array
    {
        return collect($filters)
            ->filter(fn ($value): bool => filled($value))
            ->all();
    }
}
