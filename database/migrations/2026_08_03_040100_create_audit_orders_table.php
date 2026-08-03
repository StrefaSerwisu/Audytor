<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_order_sequences', function (Blueprint $table) {
            $table->unsignedInteger('year')->primary();
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });

        Schema::create('audit_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('quotation_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('sales_qualification_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_location_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('audit_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('audit_type_version_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->string('status')->default('awaiting_planning')->index();
            $table->foreignId('sales_owner_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('delivery_owner_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('technical_lead_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('planned_start_at')->nullable();
            $table->timestamp('planned_end_at')->nullable();
            $table->decimal('expected_hours', 12, 2)->default(0);
            $table->decimal('planned_hours', 12, 2)->nullable();
            $table->unsignedInteger('engineers_count')->default(1);
            $table->string('minimum_competency_level')->nullable();
            $table->text('purpose')->nullable();
            $table->text('scope_summary')->nullable();
            $table->text('assumptions')->nullable();
            $table->text('exclusions')->nullable();
            $table->text('delivery_instructions')->nullable();
            $table->string('client_contact_name')->nullable();
            $table->string('client_contact_email')->nullable();
            $table->string('client_contact_phone')->nullable();
            $table->json('configuration_snapshot');
            $table->json('source_snapshot');
            $table->text('planning_notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'status']);
            $table->index(['sales_owner_id', 'status']);
            $table->index(['delivery_owner_id', 'technical_lead_id']);
            $table->index(['planned_start_at', 'planned_end_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_orders');
        Schema::dropIfExists('audit_order_sequences');
    }
};
