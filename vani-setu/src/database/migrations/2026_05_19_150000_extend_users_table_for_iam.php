<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('employee_id')->unique()->after('id');
            $table->string('section')->nullable()->after('email_verified_at');
            $table->string('designation')->nullable()->after('section');
            $table->json('language_competencies')->default('[]')->after('designation');
            $table->boolean('is_active')->default(true)->after('language_competencies');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'employee_id',
                'section',
                'designation',
                'language_competencies',
                'is_active',
                'last_login_at',
            ]);
        });
    }
};
