<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technical_audit_answers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('technical_audit_id')->constrained()->cascadeOnDelete();
            $t->foreignId('technical_audit_control_id')->constrained()->cascadeOnDelete();
            $t->foreignId('answered_by')->constrained('users')->restrictOnDelete();
            $t->json('value_json')->nullable();
            $t->text('comment')->nullable();
            $t->boolean('not_applicable')->default(false);
            $t->text('not_applicable_reason')->nullable();
            $t->string('result_status')->nullable();
            $t->string('proposed_risk_level')->nullable();
            $t->text('proposed_recommendation')->nullable();
            $t->boolean('customer_statement')->default(false);
            $t->text('customer_statement_source')->nullable();
            $t->string('confidence_level')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('answered_at')->nullable();
            $t->timestamps();
            $t->unique(['technical_audit_id', 'technical_audit_control_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technical_audit_answers');
    }
};
