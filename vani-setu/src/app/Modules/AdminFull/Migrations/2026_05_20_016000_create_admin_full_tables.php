<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_full_sitting_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->integer('session_no')->nullable();
            $table->string('default_status')->default('planned');
            $table->json('metadata')->default('{}');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('admin_full_slot_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sitting_template_id')->nullable()->constrained('admin_full_sitting_templates')->nullOnDelete();
            $table->string('name');
            $table->string('code_prefix')->default('S');
            $table->integer('start_offset_ms')->default(0);
            $table->integer('duration_ms')->default(300000);
            $table->string('topic')->default('Proceedings');
            $table->json('lang_roles')->default('["en","hi"]');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['sitting_template_id', 'name']);
        });

        Schema::create('admin_full_custom_member_masters', function (Blueprint $table) {
            $table->id();
            $table->string('reference_code')->unique();
            $table->string('name_en');
            $table->string('name_hi');
            $table->string('role_title')->nullable();
            $table->string('state_jur')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('admin_full_config_toggles', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->boolean('enabled')->default(false);
            $table->json('value')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_full_config_toggles');
        Schema::dropIfExists('admin_full_custom_member_masters');
        Schema::dropIfExists('admin_full_slot_templates');
        Schema::dropIfExists('admin_full_sitting_templates');
    }
};
