<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        Schema::table('audit_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('audit_logs', 'event_uuid')) {
                $table->string('event_uuid')->nullable()->unique();
            }

            if (! Schema::hasColumn('audit_logs', 'role_id')) {
                $table->unsignedBigInteger('role_id')->nullable();
            }

            if (! Schema::hasColumn('audit_logs', 'role_name')) {
                $table->string('role_name')->nullable();
            }

            if (! Schema::hasColumn('audit_logs', 'office_id')) {
                $table->unsignedBigInteger('office_id')->nullable();
            }

            if (! Schema::hasColumn('audit_logs', 'office_name')) {
                $table->string('office_name')->nullable();
            }

            if (! Schema::hasColumn('audit_logs', 'target_type')) {
                $table->string('target_type')->nullable();
            }

            if (! Schema::hasColumn('audit_logs', 'target_id')) {
                $table->unsignedBigInteger('target_id')->nullable();
            }

            if (! Schema::hasColumn('audit_logs', 'target_label')) {
                $table->string('target_label')->nullable();
            }

            if (! Schema::hasColumn('audit_logs', 'document_type')) {
                $table->string('document_type')->nullable();
            }

            if (! Schema::hasColumn('audit_logs', 'document_id')) {
                $table->unsignedBigInteger('document_id')->nullable();
            }

            if (! Schema::hasColumn('audit_logs', 'tracking_number')) {
                $table->string('tracking_number')->nullable();
            }

            if (! Schema::hasColumn('audit_logs', 'route_name')) {
                $table->string('route_name')->nullable();
            }

            if (! Schema::hasColumn('audit_logs', 'status')) {
                $table->string('status')->default('success');
            }

            if (! Schema::hasColumn('audit_logs', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
        });

        $this->index('audit_logs', 'user_id', 'audit_logs_user_id_idx');
        $this->index('audit_logs', 'office_id', 'audit_logs_office_id_idx');
        $this->index('audit_logs', 'action', 'audit_logs_action_idx');
        $this->index('audit_logs', 'module', 'audit_logs_module_idx');
        $this->index('audit_logs', 'document_type', 'audit_logs_document_type_idx');
        $this->index('audit_logs', 'document_id', 'audit_logs_document_id_idx');
        $this->index('audit_logs', 'tracking_number', 'audit_logs_tracking_number_idx');
        $this->index('audit_logs', 'status', 'audit_logs_status_idx');
        $this->index('audit_logs', 'severity', 'audit_logs_severity_idx');
        $this->index('audit_logs', 'created_at', 'audit_logs_created_at_idx');
    }

    public function down(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        foreach ([
            'audit_logs_user_id_idx',
            'audit_logs_office_id_idx',
            'audit_logs_action_idx',
            'audit_logs_module_idx',
            'audit_logs_document_type_idx',
            'audit_logs_document_id_idx',
            'audit_logs_tracking_number_idx',
            'audit_logs_status_idx',
            'audit_logs_severity_idx',
            'audit_logs_created_at_idx',
        ] as $index) {
            $this->dropIndex('audit_logs', $index);
        }

        Schema::table('audit_logs', function (Blueprint $table) {
            foreach ([
                'event_uuid',
                'role_id',
                'role_name',
                'office_id',
                'office_name',
                'target_type',
                'target_id',
                'target_label',
                'document_type',
                'document_id',
                'tracking_number',
                'route_name',
                'status',
                'updated_at',
            ] as $column) {
                if (Schema::hasColumn('audit_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function index(string $table, string $column, string $name): void
    {
        if (! Schema::hasColumn($table, $column) || $this->indexExists($table, $name)) {
            return;
        }

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($column, $name));
    }

    private function dropIndex(string $table, string $name): void
    {
        if (! $this->indexExists($table, $name)) {
            return;
        }

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($name));
    }

    private function indexExists(string $table, string $name): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            return collect(DB::select('SHOW INDEX FROM ' . $table . ' WHERE Key_name = ?', [$name]))->isNotEmpty();
        }

        if ($driver === 'sqlite') {
            return collect(DB::select("PRAGMA index_list('{$table}')"))
                ->contains(fn ($index) => ($index->name ?? null) === $name);
        }

        return false;
    }
};
