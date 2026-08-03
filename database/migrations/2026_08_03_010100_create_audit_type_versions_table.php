<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_type_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('status')->default('draft')->index();
            $table->string('name_snapshot');
            $table->text('description_snapshot')->nullable();
            $table->text('sales_instructions')->nullable();
            $table->text('delivery_instructions')->nullable();
            $table->string('minimum_competency_level')->nullable();
            $table->unsignedInteger('estimated_preparation_minutes')->default(0);
            $table->unsignedInteger('estimated_execution_minutes')->default(0);
            $table->unsignedInteger('estimated_reporting_minutes')->default(0);
            $table->unsignedInteger('estimated_review_minutes')->default(0);
            $table->boolean('ai_enabled')->default(false);
            $table->json('ai_configuration')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['audit_type_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_type_versions');
    }
};
