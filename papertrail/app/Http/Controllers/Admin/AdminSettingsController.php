<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\AuditLogger;
use App\Services\SystemSettingService;
use App\Support\MailConfigurationDiagnostics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

class AdminSettingsController extends Controller
{
    public function index(): View
    {
        AuditLogger::log('Settings', 'Settings Viewed', 'Settings dashboard viewed.');
        $groups = $this->groups();
        $groupKey = 'general';

        return view('admin.settings.index', [
            'groups' => $groups,
            'groupKey' => $groupKey,
            'group' => $groups[$groupKey],
            'settings' => SystemSetting::where('group', $groupKey)->orderBy('sort_order')->get(),
        ]);
    }

    public function group(string $group): View
    {
        $label = $this->groups()[$group]['title'] ?? abort(404);
        AuditLogger::log('Settings', 'Settings Group Viewed', "{$label} settings viewed.");

        return view('admin.settings.group', [
            'groups' => $this->groups(),
            'groupKey' => $group,
            'group' => $this->groups()[$group],
            'settings' => SystemSetting::where('group', $group)->orderBy('sort_order')->get(),
        ]);
    }

    public function email(): View
    {
        AuditLogger::log('Settings', 'Email Notification Settings Viewed', 'Admin viewed email notification settings.');

        return view('admin.settings.email', [
            'groups' => $this->groups(),
            'groupKey' => 'email',
            'mail' => array_merge(
                ['enabled' => config('papertrail.email_notifications_enabled', true)],
                MailConfigurationDiagnostics::safeConfig(),
            ),
            'mailWarnings' => MailConfigurationDiagnostics::warnings(),
        ]);
    }

    public function sendTestEmail(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        try {
            Mail::raw(
                'PaperTrail Gmail SMTP is working.',
                function ($message) use ($validated) {
                    $message->to($validated['email'])
                        ->subject('PaperTrail SMTP Test Email');
                }
            );

            AuditLogger::log('Settings', 'Email Test Sent', 'Admin sent a PaperTrail SMTP test email.', null, null, null, 'info', [
                'recipient' => $validated['email'],
            ]);

            return back()->with('status', 'SMTP accepted the test email. If it does not arrive, check the recipient inbox, spam folder, Gmail app password, and Google account security settings.');
        } catch (Throwable $exception) {
            Log::error('PaperTrail admin test email failed.', [
                'recipient' => $validated['email'],
                'exception' => $exception->getMessage(),
            ]);

            AuditLogger::log('Settings', 'Email Test Failed', 'Admin test email failed.', null, null, null, 'warning', [
                'recipient' => $validated['email'],
                'error' => $exception->getMessage(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Email service is not configured. Please contact the system administrator.');
        }
    }

    public function update(Request $request, string $group): RedirectResponse
    {
        $settings = SystemSetting::where('group', $group)->get();
        $old = $settings->pluck('value', 'key')->all();
        $new = [];

        foreach ($settings as $setting) {
            if ($setting->is_locked) {
                continue;
            }

            $value = $request->input(str_replace('.', '_', $setting->key));
            if ($setting->type === 'boolean') {
                $value = $request->boolean(str_replace('.', '_', $setting->key)) ? '1' : '0';
            }
            if ($setting->type === 'email' && $value) {
                $request->validate([str_replace('.', '_', $setting->key) => ['email']]);
            }
            if ($setting->type === 'number' && $value !== null) {
                $request->validate([str_replace('.', '_', $setting->key) => ['numeric']]);
            }
            if ($setting->type === 'select') {
                $allowed = collect($setting->options ?? [])->pluck('value')->all();
                $request->validate([str_replace('.', '_', $setting->key) => ['nullable', 'in:' . implode(',', $allowed)]]);
            }

            $setting->update(['value' => $value, 'updated_by_user_id' => $request->user()->id]);
            $new[$setting->key] = $value;
        }

        SystemSettingService::clearCache();
        AuditLogger::log('Settings', 'settings_updated', 'Settings updated.', null, $old, $new, $group === 'security' ? 'warning' : 'info');

        return back()->with('status', 'Settings updated successfully.');
    }

    public function uploadLogo(Request $request): RedirectResponse
    {
        $request->validate(['logo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048']]);
        $path = $request->file('logo')->store('settings', 'public');
        SystemSettingService::set('lgu.logo_path', $path, $request->user()->id);
        AuditLogger::log('Settings', 'Logo Uploaded', 'LGU logo uploaded.', null, null, ['lgu.logo_path' => $path]);
        return back()->with('status', 'Logo uploaded successfully.');
    }

    private function groups(): array
    {
        return [
            'general' => ['title' => 'General Settings', 'description' => 'Core PaperTrail system information.'],
            'lgu' => ['title' => 'LGU Profile', 'description' => 'Municipality profile and contact information.'],
            'security' => ['title' => 'Security and Login', 'description' => 'Login and password policy settings.'],
            'reports' => ['title' => 'Reports', 'description' => 'Report output and export defaults.'],
            'email' => ['title' => 'Email Notifications', 'description' => 'SMTP status and test email tools.'],
            'ai-rules' => ['title' => 'AI Rules', 'description' => 'AI-ready prompt and output rules.', 'route' => 'admin.settings.ai-rules.index'],
            'document-requirements' => ['title' => 'Document Requirements', 'description' => 'Field and attachment requirements.', 'route' => 'admin.settings.document-requirements.index'],
            'routing-rules' => ['title' => 'Routing Rules', 'description' => 'Workflow route suggestion rules.', 'route' => 'admin.settings.routing-rules.index'],
            'delay-thresholds' => ['title' => 'Delay Thresholds', 'description' => 'Stage delay risk day settings.', 'route' => 'admin.settings.delay-thresholds.index'],
            'notification-templates' => ['title' => 'Notification Templates', 'description' => 'Email and in-app message wording.', 'route' => 'admin.settings.notification-templates.index'],
            'uploads' => ['title' => 'File Uploads', 'description' => 'Future upload restrictions and storage defaults.'],
            'procurement' => ['title' => 'Future Procurement Defaults', 'description' => 'Reserved procurement workflow defaults.'],
            'maintenance' => ['title' => 'System Maintenance', 'description' => 'Maintenance notices and backup reminders.'],
        ];
    }
}
