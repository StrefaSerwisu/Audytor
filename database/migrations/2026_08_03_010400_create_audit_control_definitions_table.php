<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_control_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_type_module_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->text('objective')->nullable();
            $table->text('description')->nullable();
            $table->text('execution_instructions')->nullable();
            $table->text('where_to_check')->nullable();
            $table->text('required_access')->nullable();
            $table->text('required_tools')->nullable();
            $table->string('minimum_competency_level')->nullable();
            $table->unsignedInteger('estimated_minutes')->default(0);
            $table->string('field_type')->index();
            $table->json('options_json')->nullable();
            $table->boolean('required')->default(false);
            $table->boolean('allow_not_applicable')->default(true);
            $table->boolean('require_comment_when_na')->default(false);
            $table->boolean('require_evidence')->default(false);
            $table->json('evidence_types')->nullable();
            $table->text('positive_criteria')->nullable();
            $table->text('negative_criteria')->nullable();
            $table->text('escalation_criteria')->nullable();
            $table->string('default_risk_level')->nullable();
            $table->text('default_recommendation')->nullable();
            $table->string('standard_reference')->nullable();
            $table->json('conditional_logic')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();

            $table->unique(['audit_type_module_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_control_definitions');
    }
};
