<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The blog: posts written by admins, and comments that show once an admin approves them. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->string('slug', 80)->unique();
            $table->string('excerpt', 300)->nullable();
            $table->text('body');
            $table->string('topic', 30)->default('learning');
            $table->string('cover_path')->nullable();
            $table->string('cover_alt', 160)->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable()->index(); // null = draft; in the future = scheduled
            $table->timestamps();
        });

        Schema::create('post_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('post_comments')->cascadeOnDelete(); // an admin's reply
            $table->text('body');
            $table->timestamp('approved_at')->nullable()->index(); // shown only once approved
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_comments');
        Schema::dropIfExists('posts');
    }
};
