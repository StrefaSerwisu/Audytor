<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_sequences', function (Blueprint $table) {
            $table->unsignedInteger('year')->primary();
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });

        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('sales_qualification_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('audit_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('audit_type_version_id')->constrained()->restrictOnDelete();
            $table->foreignId('sales_owner_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_current')->default(true)->index();
            $table->string('status')->default('draft')->index();
            $table->string('currency', 3)->default('PLN');
            $table->decimal('base_hours', 12, 2)->default(0);
            $table->decimal('additional_hours', 12, 2)->default(0);
            $table->decimal('total_hours', 12, 2)->default(0);
            $table->unsignedInteger('engineers_count')->default(1);
            $table->decimal('hourly_rate', 14, 2)->default(0);
            $table->decimal('base_price', 14, 2)->default(0);
            $table->decimal('additional_costs', 14, 2)->default(0);
            $table->string('discount_type')->nullable();
            $table->decimal('discount_value', 14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('net_price', 14, 2)->default(0);
            $table->decimal('tax_rate', 7, 2)->default(23);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('gross_price', 14, 2)->default(0);
            $table->date('valid_until')->nullable();
            $table->text('assumptions')->nullable();
            $table->text('exclusions')->nullable();
            $table->text('internal_notes')->nullable();
            $table->text('client_notes')->nullable();
            $table->json('calculation_snapshot');
            $table->json('final_calculation_snapshot')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->foreignId('calculated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('internally_approved_at')->nullable();
            $table->foreignId('internally_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->string('accepted_by')->nullable();
            $table->string('purchase_order_number')->nullable();
            $table->text('acceptance_comment')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->unique(['sales_qualification_id', 'version']);
            $table->index(['client_id', 'audit_type_id', 'sales_owner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotations');
        Schema::dropIfExists('quotation_sequences');
    }
};
