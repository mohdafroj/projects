<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('slot_assignments', function (Blueprint $table) {
            $table->enum('workflow_stage', ['reporter', 'supervisor', 'chief', 'returned'])->default('reporter')->after('status');
            $table->foreignId('assignee_user_id')->nullable()->after('user_id')->constrained('users');
            $table->timestampTz('last_workflow_action_at')->nullable()->after('committed_audit_log_id');
        });
    }

    public function down(): void
    {
        Schema::table('slot_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assignee_user_id');
            $table->dropColumn(['workflow_stage', 'last_workflow_action_at']);
        });
    }
};
