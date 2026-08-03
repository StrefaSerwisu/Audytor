<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_order_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_order_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk');
            $table->string('path', 1000);
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->text('description')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['source_type', 'source_id']);
            $table->index(['audit_order_id', 'category']);
        });

        Schema::table('audit_notifications', function (Blueprint $table) {
            $table->foreignId('audit_order_id')->nullable()->after('audit_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('audit_notifications', fn (Blueprint $table) => $table->dropConstrainedForeignId('audit_order_id'));
        Schema::dropIfExists('audit_order_documents');
    }
};
