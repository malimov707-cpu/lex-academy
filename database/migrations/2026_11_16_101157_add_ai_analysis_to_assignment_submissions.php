<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignment_submissions', function (Blueprint $table): void {
            $table->json('ai_analysis')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('assignment_submissions', fn (Blueprint $table) => $table->dropColumn('ai_analysis'));
    }
};
