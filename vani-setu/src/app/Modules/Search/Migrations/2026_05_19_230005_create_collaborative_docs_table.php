<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collaborative_docs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->binary('state');
            $table->json('context')->nullable();
            $table->timestampTz('last_saved_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collaborative_docs');
    }
};
