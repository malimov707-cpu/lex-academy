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
        Schema::table('scenario_legal_references', function (Blueprint $table) {
            $table->timestamp('library_verified_at')->nullable();
            $table->text('library_excerpt')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scenario_legal_references', function (Blueprint $table) {
            $table->dropColumn(['library_verified_at', 'library_excerpt']);
        });
    }
};
