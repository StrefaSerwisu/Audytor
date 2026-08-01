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
        Schema::create('audit_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('audit_question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('audit_module_id')->constrained()->restrictOnDelete();
            $table->foreignId('answered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('value_json')->nullable();
            $table->text('comment')->nullable();
            $table->boolean('not_applicable')->default(false)->index();
            $table->text('not_applicable_reason')->nullable();
            $table->string('risk_level')->nullable()->index();
            $table->text('recommendation_text')->nullable();
            $table->string('status')->default('draft')->index();
            $table->string('sync_status')->default('synced')->index();
            $table->uuid('local_uuid')->unique();
            $table->timestamps();

            $table->unique(['audit_id', 'audit_question_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_answers');
    }
};
