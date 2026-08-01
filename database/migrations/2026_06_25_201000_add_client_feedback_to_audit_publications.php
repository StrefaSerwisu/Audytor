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
        Schema::table('audit_publications', function (Blueprint $table) {
            $table->text('client_comment')->nullable()->after('client_status_updated_at');
            $table->json('accepted_recommendations_json')->nullable()->after('client_comment');
            $table->timestamp('client_feedback_at')->nullable()->after('accepted_recommendations_json');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_publications', function (Blueprint $table) {
            $table->dropColumn([
                'client_comment',
                'accepted_recommendations_json',
                'client_feedback_at',
            ]);
        });
    }
};
