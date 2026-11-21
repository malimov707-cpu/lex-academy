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
        Schema::table('legal_sources', function (Blueprint $table): void {
            $table->string('topic', 100)->nullable()->index();
        });
        Schema::table('legal_sources', function (Blueprint $table) {
            //
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('legal_sources', function (Blueprint $table): void {
            $table->dropColumn('topic');
        });
        Schema::table('legal_sources', function (Blueprint $table) {
            //
        });
    }
};
