<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_dispatches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('channel', 32)->index();
            $table->string('status', 32)->index();
            $table->string('producer')->nullable()->index();
            $table->json('recipients');
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->string('template_id')->nullable()->index();
            $table->string('idempotency_key')->nullable()->unique();
            $table->json('metadata')->nullable();
            $table->json('provider_response')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_dispatches');
    }
};
