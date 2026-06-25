<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // audio_output_path now holds inline base64 data URLs
        // (data:audio/wav;base64,...) for the live workflow, which routinely
        // exceed 100 KB. VARCHAR(255) was the old shape from when this was
        // always /storage/... file paths.
        Schema::table('s2s_outputs', function (Blueprint $table) {
            $table->text('audio_output_path')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('s2s_outputs', function (Blueprint $table) {
            $table->string('audio_output_path', 255)->nullable()->change();
        });
    }
};
