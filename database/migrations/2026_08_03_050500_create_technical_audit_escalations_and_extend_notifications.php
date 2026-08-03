<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technical_audit_escalations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('technical_audit_id')->constrained()->cascadeOnDelete();
            $t->foreignId('technical_audit_control_id')->constrained()->cascadeOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->text('reason');
            $t->text('question')->nullable();
            $t->string('status')->default('open')->index();
            $t->string('priority')->default('normal')->index();
            $t->text('response')->nullable();
            $t->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('resolved_at')->nullable();
            $t->timestamps();
            $t->index(['technical_audit_id', 'created_by', 'assigned_to']);
        });
        Schema::table('audit_notifications', fn (Blueprint $t) => $t->foreignId('technical_audit_id')->nullable()->after('audit_order_id')->constrained()->cascadeOnDelete());
    }

    public function down(): void
    {
        Schema::table('audit_notifications', fn (Blueprint $t) => $t->dropConstrainedForeignId('technical_audit_id'));
        Schema::dropIfExists('technical_audit_escalations');
    }
};
