<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_qualifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_location_id')->nullable()->constrained('client_locations')->nullOnDelete();
            $table->foreignId('audit_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('audit_type_version_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->text('purpose')->nullable();
            $table->date('expected_date')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->foreignId('sales_owner_id')->constrained('users')->restrictOnDelete();
            $table->string('status')->default('draft')->index();
            $table->json('qualification_snapshot');
            $table->text('scope_summary')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_qualifications');
    }
};
