<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technical_audit_evidence', function (Blueprint $t) {
            $t->id();
            $t->foreignId('technical_audit_id')->constrained()->cascadeOnDelete();
            $t->foreignId('technical_audit_control_id')->constrained()->cascadeOnDelete();
            $t->foreignId('technical_audit_answer_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $t->string('disk');
            $t->string('path', 1000);
            $t->string('original_name');
            $t->string('mime_type')->nullable();
            $t->unsignedBigInteger('size_bytes')->default(0);
            $t->string('evidence_type');
            $t->text('caption')->nullable();
            $t->string('status')->default('draft');
            $t->string('scan_status')->default('not_scanned');
            $t->timestamp('created_at')->useCurrent();
            $t->index(['technical_audit_id', 'technical_audit_control_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technical_audit_evidence');
    }
};
