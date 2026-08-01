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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->after('role')->constrained()->nullOnDelete();
        });

        Schema::table('audit_publications', function (Blueprint $table) {
            $table->string('client_status', 40)->nullable()->after('expires_at')->index();
            $table->timestamp('client_status_updated_at')->nullable()->after('client_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_publications', function (Blueprint $table) {
            $table->dropColumn(['client_status', 'client_status_updated_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
        });
    }
};
