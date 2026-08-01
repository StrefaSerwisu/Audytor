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
        Schema::create('audit_answer_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_answer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('audit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('audit_question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('audit_module_id')->constrained()->restrictOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('evidence_type')->default('file')->index();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->text('caption')->nullable();
            $table->uuid('local_uuid')->unique();
            $table->timestamps();

            $table->index(['audit_id', 'audit_question_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_answer_attachments');
    }
};
