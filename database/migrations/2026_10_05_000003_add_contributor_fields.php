<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Native speakers who record audio and check the Luganda.
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_reviewer')->default(false);
        });

        foreach (['phrases', 'words'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('review_note')->nullable(); // set when a reviewer flags a problem
                $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['phrases', 'words'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('reviewed_by');
                $table->dropConstrainedForeignId('recorded_by');
                $table->dropColumn(['reviewed_at', 'review_note']);
            });
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_reviewer');
        });
    }
};
