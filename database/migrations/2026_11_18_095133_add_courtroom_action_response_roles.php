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
        Schema::table('scenario_action_rules', function (Blueprint $table): void {
            $table->json('response_role_keys')->nullable();
        });

        Schema::table('session_actions', function (Blueprint $table): void {
            $table->string('target_role_key', 60)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('session_actions', function (Blueprint $table): void {
            $table->dropColumn('target_role_key');
        });

        Schema::table('scenario_action_rules', function (Blueprint $table): void {
            $table->dropColumn('response_role_keys');
        });
    }
};
