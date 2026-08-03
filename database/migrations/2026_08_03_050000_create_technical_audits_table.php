<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technical_audit_sequences', function (Blueprint $t) {
            $t->unsignedInteger('year')->primary();
            $t->unsignedInteger('last_number')->default(0);
            $t->timestamps();
        });
        Schema::create('technical_audits', function (Blueprint $t) {
            $t->id();
            $t->string('number')->unique();
            $t->foreignId('audit_order_id')->unique()->constrained()->restrictOnDelete();
            $t->foreignId('client_id')->constrained()->restrictOnDelete();
            $t->foreignId('client_location_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('audit_type_id')->constrained()->restrictOnDelete();
            $t->foreignId('audit_type_version_id')->constrained()->restrictOnDelete();
            $t->string('title');
            $t->string('status')->default('draft')->index();
            $t->foreignId('technical_lead_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('delivery_owner_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('started_at')->nullable();
            $t->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('submitted_at')->nullable();
            $t->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('completed_at')->nullable();
            $t->json('configuration_snapshot');
            $t->json('source_snapshot');
            $t->unsignedInteger('progress_percent')->default(0);
            $t->unsignedInteger('total_controls')->default(0);
            $t->unsignedInteger('completed_controls')->default(0);
            $t->unsignedInteger('blocked_controls')->default(0);
            $t->unsignedInteger('escalated_controls')->default(0);
            $t->timestamps();
            $t->index(['technical_lead_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technical_audits');
        Schema::dropIfExists('technical_audit_sequences');
    }
};
