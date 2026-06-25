<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('s2s_glossary', function (Blueprint $table) {
            $table->id();
            $table->string('src_lang', 16);
            $table->string('tgt_lang', 16);
            $table->string('source_term');
            $table->string('target_term');
            $table->string('pronunciation')->default('');
            $table->string('notes')->default('');
            $table->timestamps();

            $table->index(['src_lang', 'tgt_lang'], 'idx_s2s_glossary_pair');
            $table->unique(['src_lang', 'tgt_lang', 'source_term'], 'uq_s2s_glossary_pair_src_term');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('s2s_glossary');
    }
};
