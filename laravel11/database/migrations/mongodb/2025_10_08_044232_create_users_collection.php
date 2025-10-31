<?php
// php artisan make:migration create_users_collection --path=database/migrations/mongodb
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    protected $connection = 'mongodb';

    public function up(): void
    {
        Schema::connection($this->connection)->create('users_collection', function ($collection) {
            $collection->index('name');
            $collection->index('email');
            $collection->unique('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('users_collection');
    }
};
