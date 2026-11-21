<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simulation_scenarios', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description');
            $table->string('legal_area', 80)->index();
            $table->string('case_type', 80);
            $table->string('difficulty', 30)->default('beginner')->index();
            $table->unsignedSmallInteger('estimated_duration_minutes')->default(45);
            $table->string('status', 30)->default('draft')->index();
            $table->timestamps();
        });

        Schema::create('scenario_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('scenario_id')->constrained('simulation_scenarios')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('procedure_type', 80);
            $table->json('learning_objectives');
            $table->longText('case_brief');
            $table->longText('case_facts');
            $table->text('teacher_notes')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('snapshot_hash', 64)->nullable();
            $table->timestamps();
            $table->unique(['scenario_id', 'version']);
        });

        Schema::create('scenario_roles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('scenario_version_id')->constrained()->cascadeOnDelete();
            $table->string('key', 60);
            $table->string('name');
            $table->text('description');
            $table->text('instructions');
            $table->text('private_context')->nullable();
            $table->boolean('is_student_selectable')->default(false)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['scenario_version_id', 'key']);
        });

        Schema::create('scenario_stages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('scenario_version_id')->constrained()->cascadeOnDelete();
            $table->string('key', 80);
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order');
            $table->unsignedInteger('time_limit_seconds')->nullable();
            $table->json('allowed_next_stages')->nullable();
            $table->boolean('is_terminal')->default(false);
            $table->timestamps();
            $table->unique(['scenario_version_id', 'key']);
            $table->unique(['scenario_version_id', 'sort_order']);
        });

        Schema::create('scenario_action_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('scenario_stage_id')->constrained()->cascadeOnDelete();
            $table->string('role_key', 60)->default('*');
            $table->string('action_type', 80);
            $table->string('label');
            $table->boolean('requires_content')->default(false);
            $table->boolean('requires_evidence')->default(false);
            $table->string('advances_to_stage_key', 80)->nullable();
            $table->json('scoring_metadata')->nullable();
            $table->timestamps();
            $table->unique(['scenario_stage_id', 'role_key', 'action_type']);
        });

        Schema::create('scenario_evidence', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('scenario_version_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('type', 40);
            $table->text('summary');
            $table->longText('body')->nullable();
            $table->string('storage_path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->string('source_label');
            $table->unsignedInteger('version')->default(1);
            $table->string('visibility', 30)->default('all');
            $table->json('allowed_role_keys')->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->string('checksum', 64)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['scenario_version_id', 'visibility']);
        });

        Schema::create('scenario_legal_references', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('scenario_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_source_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('source_url', 2048)->nullable();
            $table->text('citation_text');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['scenario_version_id', 'verified_at']);
        });

        Schema::create('evaluation_rubrics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('scenario_version_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->unsignedSmallInteger('total_score')->default(100);
            $table->string('status', 30)->default('draft');
            $table->timestamps();
        });

        Schema::create('evaluation_criteria', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('evaluation_rubric_id')->constrained()->cascadeOnDelete();
            $table->string('key', 80);
            $table->string('name');
            $table->text('description');
            $table->unsignedSmallInteger('weight');
            $table->unsignedSmallInteger('max_score');
            $table->text('guidance')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['evaluation_rubric_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_criteria');
        Schema::dropIfExists('evaluation_rubrics');
        Schema::dropIfExists('scenario_legal_references');
        Schema::dropIfExists('scenario_evidence');
        Schema::dropIfExists('scenario_action_rules');
        Schema::dropIfExists('scenario_stages');
        Schema::dropIfExists('scenario_roles');
        Schema::dropIfExists('scenario_versions');
        Schema::dropIfExists('simulation_scenarios');
    }
};
