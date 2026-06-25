<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blocks', function (Blueprint $table) {
            if (! Schema::hasColumn('blocks', 'committee_id')) {
                $table->foreignId('committee_id')->nullable()->after('slot_id')->constrained('committees')->nullOnDelete();
            }
            if (! Schema::hasColumn('blocks', 'source_type')) {
                $table->string('source_type')->default('house')->after('committee_id');
            }
            if (! Schema::hasColumn('blocks', 'in_camera_flag')) {
                $table->boolean('in_camera_flag')->default(false)->after('source_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('blocks', function (Blueprint $table) {
            if (Schema::hasColumn('blocks', 'in_camera_flag')) {
                $table->dropColumn('in_camera_flag');
            }
            if (Schema::hasColumn('blocks', 'source_type')) {
                $table->dropColumn('source_type');
            }
            if (Schema::hasColumn('blocks', 'committee_id')) {
                $table->dropConstrainedForeignId('committee_id');
            }
        });
    }
};
