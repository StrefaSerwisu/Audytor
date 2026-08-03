<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technical_audit_modules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('technical_audit_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('source_module_id')->nullable();
            $t->string('code');
            $t->string('name');
            $t->text('description')->nullable();
            $t->text('instructions')->nullable();
            $t->unsignedInteger('sort_order')->default(0);
            $t->unsignedInteger('estimated_minutes')->default(0);
            $t->string('status')->default('not_started')->index();
            $t->unsignedInteger('progress_percent')->default(0);
            $t->timestamp('started_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
            $t->unique(['technical_audit_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technical_audit_modules');
    }
};
