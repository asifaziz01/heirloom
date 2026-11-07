<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI calls and the archive export now run as queued jobs, so each one needs somewhere
 * to record progress and errors: interviews get an error column (their status column already
 * exists), person-level AI requests get an ai_tasks table, and archive builds get an exports table.
 * Media analysis keeps its state inside media.ai_suggestions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interviews', function (Blueprint $table) {
            $table->text('extraction_error')->nullable()->after('extraction');
        });

        Schema::create('ai_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->nullable()->constrained('persons')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20); // biography, questions
            $table->string('status', 12)->default('pending'); // pending, running, done, failed
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });

        Schema::create('exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 12)->default('pending'); // pending, running, done, failed
            $table->string('path')->nullable(); // on the private local disk: families/{id}/exports/...
            $table->string('filename')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('downloaded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exports');
        Schema::dropIfExists('ai_tasks');
        Schema::table('interviews', fn (Blueprint $table) => $table->dropColumn('extraction_error'));
    }
};
