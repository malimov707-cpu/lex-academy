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
        Schema::table('legal_source_checks', function (Blueprint $table): void {
            $table->string('impact_status', 32)->nullable()->after('reviewed_at');
            $table->text('review_note')->nullable()->after('impact_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('legal_source_checks', function (Blueprint $table): void {
            $table->dropColumn(['impact_status', 'review_note']);
        });
    }
};
