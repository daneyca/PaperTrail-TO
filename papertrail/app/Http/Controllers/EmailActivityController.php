<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailActivityController extends Controller
{
    public function index(Request $request): View
    {
        AuditLogger::log('Email Activity', 'Email Activity Page Viewed', 'User viewed email notification activity.');

        $query = $this->baseEmailQuery($request)->latest();

        $this->applyFilters($query, $request);

        return view('email-activity.index', [
            'emails' => $query->paginate(12)->withQueryString(),
            'filters' => $request->only(['search', 'status', 'date_from', 'date_to']),
            'counts' => [
                'all' => $this->baseEmailQuery($request)->count(),
                'sent' => $this->baseEmailQuery($request)->where('action', 'Email Notification Sent')->count(),
                'failed' => $this->baseEmailQuery($request)->where('action', 'Email Notification Failed')->count(),
                'skipped' => $this->baseEmailQuery($request)->where('action', 'Email Notification Skipped')->count(),
            ],
        ]);
    }

    private function baseEmailQuery(Request $request): Builder
    {
        $user = $request->user();

        return AuditLog::query()
            ->where('module', 'Email Notifications')
            ->whereIn('action', [
                'Email Notification Sent',
                'Email Notification Failed',
                'Email Notification Skipped',
            ])
            ->when(! $user->isAdmin(), function (Builder $query) use ($user): void {
                $query->where(function (Builder $scope) use ($user): void {
                    $scope->where('user_id', $user->id);

                    if ($user->office_id) {
                        $scope->orWhere('office_id', $user->office_id);
                    }

                    if ($user->user_id) {
                        $scope->orWhere('metadata->recipient_user_id', $user->user_id);
                    }
                });
            });
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $query->when($request->filled('search'), function (Builder $builder) use ($request): void {
            $search = $request->string('search')->toString();

            $builder->where(function (Builder $nested) use ($search): void {
                $nested->where('target_label', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('tracking_number', 'like', "%{$search}%")
                    ->orWhere('user_identifier', 'like', "%{$search}%")
                    ->orWhere('metadata->subject', 'like', "%{$search}%")
                    ->orWhere('metadata->recipient_user_id', 'like', "%{$search}%");
            });
        });

        $query->when($request->filled('status'), function (Builder $builder) use ($request): void {
            $action = match ($request->query('status')) {
                'sent' => 'Email Notification Sent',
                'failed' => 'Email Notification Failed',
                'skipped' => 'Email Notification Skipped',
                default => null,
            };

            if ($action) {
                $builder->where('action', $action);
            }
        });

        $query->when($request->filled('date_from'), fn (Builder $builder) => $builder->whereDate('created_at', '>=', $request->date('date_from')));
        $query->when($request->filled('date_to'), fn (Builder $builder) => $builder->whereDate('created_at', '<=', $request->date('date_to')));
    }

    public static function statusLabel(AuditLog $email): string
    {
        return match ($email->action) {
            'Email Notification Sent' => 'Sent',
            'Email Notification Failed' => 'Failed',
            'Email Notification Skipped' => 'Skipped',
            default => $email->status ?: 'Recorded',
        };
    }

    public static function statusType(AuditLog $email): string
    {
        return match ($email->action) {
            'Email Notification Sent' => 'success',
            'Email Notification Failed' => 'error',
            'Email Notification Skipped' => 'warning',
            default => 'info',
        };
    }
}
