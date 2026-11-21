<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('simulation_sessions', function (Blueprint $table): void {
            $table->unsignedBigInteger('student_id')->nullable()->change();
            $table->foreignId('education_group_id')->nullable()->index()->constrained('education_groups')->restrictOnDelete();
            $table->foreignId('course_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->foreignId('facilitator_id')->nullable()->index()->constrained('users')->restrictOnDelete();
            $table->uuid('group_creation_key')->nullable()->unique();
        });
    }

    public function down(): void
    {
        if (DB::table('simulation_sessions')->whereNull('student_id')->exists()) {
            throw new RuntimeException('Group sessions must be archived before restoring the single-student schema.');
        }

        Schema::table('simulation_sessions', function (Blueprint $table): void {
            $table->dropUnique(['group_creation_key']);
            $table->dropIndex(['course_id']);
            $table->dropIndex(['facilitator_id']);
            $table->dropIndex(['education_group_id']);
            $table->dropConstrainedForeignId('education_group_id');
            $table->dropConstrainedForeignId('course_id');
            $table->dropConstrainedForeignId('facilitator_id');
            $table->dropColumn('group_creation_key');
            $table->unsignedBigInteger('student_id')->nullable(false)->change();
        });
    }
};
