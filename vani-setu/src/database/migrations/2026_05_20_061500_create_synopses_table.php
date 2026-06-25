<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('synopses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sitting_id')->constrained()->cascadeOnDelete();
            $table->string('source_file');
            $table->string('language', 8)->default('en');
            $table->string('kind')->default('main');
            $table->integer('sequence')->default(1);
            $table->string('speaker_name')->nullable();
            $table->string('party')->nullable();
            $table->string('constituency')->nullable();
            $table->text('summary_text');
            $table->timestamps();
            $table->unique(['sitting_id', 'source_file', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('synopses');
    }
};
