<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'typed_signature_name')) {
                $table->string('typed_signature_name')->nullable()->after('profile_photo_path');
            }

            if (! Schema::hasColumn('users', 'signer_position')) {
                $table->string('signer_position')->nullable()->after('typed_signature_name');
            }

            if (! Schema::hasColumn('users', 'signature_image_path')) {
                $table->string('signature_image_path')->nullable()->after('signer_position');
            }

            if (! Schema::hasColumn('users', 'signature_style')) {
                $table->string('signature_style')->nullable()->after('signature_image_path');
            }

            if (! Schema::hasColumn('users', 'signature_setup_completed_at')) {
                $table->timestamp('signature_setup_completed_at')->nullable()->after('signature_style');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach ([
                'signature_setup_completed_at',
                'signature_style',
                'signature_image_path',
                'signer_position',
                'typed_signature_name',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
