<?php

namespace Database\Seeders;

use App\Models\DocumentTemplate;
use App\Services\Templates\OfficialEditableTemplateService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DocumentTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = app(OfficialEditableTemplateService::class);

        $this->ensureStoredOfficialTemplates($templates);

        foreach ($templates->definitions() as $type => $definition) {
            $directory = $templates->directory($type);

            if (! Storage::disk('local')->exists($directory)) {
                Storage::disk('local')->makeDirectory($directory);
            }

            if (! Schema::hasTable('document_templates')) {
                continue;
            }

            DocumentTemplate::updateOrCreate(
                [
                    'document_type' => $type,
                    'file_path' => $definition['path'],
                ],
                [
                    'template_name' => "{$definition['label']} Editable Template",
                    'file_format' => $definition['format'],
                    'version' => 'Official editable master',
                    'is_active' => true,
                    'notes' => 'Canonical editable template served by the Download Editable Template action.',
                ],
            );
        }

        if (Schema::hasTable('document_templates')) {
            DocumentTemplate::query()
                ->where('document_type', 'po')
                ->update([
                    'is_active' => false,
                    'notes' => 'Legacy alias retained for compatibility. Use the purchase_order canonical template.',
                ]);
        }
    }

    private function ensureStoredOfficialTemplates(OfficialEditableTemplateService $templates): void
    {
        try {
            $templates->ensureAll();
        } catch (Throwable $exception) {
            Log::warning('PaperTrail official editable templates could not all be prepared.', [
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
