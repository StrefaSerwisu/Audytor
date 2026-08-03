<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technical_audit_controls', function (Blueprint $t) {
            $t->id();
            $t->foreignId('technical_audit_id')->constrained()->cascadeOnDelete();
            $t->foreignId('technical_audit_module_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('source_control_id')->nullable();
            $t->string('code');
            $t->string('name');
            $t->text('objective')->nullable();
            $t->text('description')->nullable();
            $t->text('execution_instructions')->nullable();
            $t->text('where_to_check')->nullable();
            $t->text('required_access')->nullable();
            $t->text('required_tools')->nullable();
            $t->string('minimum_competency_level')->nullable();
            $t->unsignedInteger('estimated_minutes')->default(0);
            $t->string('field_type');
            $t->json('options_json')->nullable();
            $t->boolean('required')->default(false);
            $t->boolean('allow_not_applicable')->default(true);
            $t->boolean('require_comment_when_na')->default(false);
            $t->boolean('require_evidence')->default(false);
            $t->json('evidence_types')->nullable();
            $t->text('positive_criteria')->nullable();
            $t->text('negative_criteria')->nullable();
            $t->text('escalation_criteria')->nullable();
            $t->string('default_risk_level')->nullable();
            $t->text('default_recommendation')->nullable();
            $t->string('standard_reference')->nullable();
            $t->unsignedInteger('sort_order')->default(0);
            $t->boolean('active')->default(true);
            $t->string('status')->default('not_started')->index();
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['technical_audit_module_id', 'code']);
            $t->index(['technical_audit_id', 'assigned_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technical_audit_controls');
    }
};
