<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Occasions are things the family is planning or looking forward to: a wedding, an
 * anniversary dinner, a reunion, a graduation, a remembrance gathering. Life events
 * (the `events` table) record what already happened; occasions look ahead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('occasions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->default('other'); // see Occasion::TYPES
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('date');
            $table->date('end_date')->nullable();
            $table->string('time', 5)->nullable(); // "18:30", local to the venue
            $table->boolean('repeats_yearly')->default(false); // annual reunion, death anniversary, ...
            $table->foreignId('place_id')->nullable()->constrained('places')->nullOnDelete();
            $table->string('venue')->nullable(); // hall name, address, video-call link
            $table->boolean('rsvp_enabled')->default(true);
            $table->boolean('send_reminders')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['family_id', 'date']);
        });

        Schema::create('occasion_person', function (Blueprint $table) {
            $table->foreignId('occasion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            $table->primary(['occasion_id', 'person_id']);
        });

        Schema::create('occasion_rsvps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('occasion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 8); // going, maybe, no
            $table->unsignedSmallInteger('guests')->default(0); // extra people coming along
            $table->string('note', 500)->nullable();
            $table->timestamps();
            $table->unique(['occasion_id', 'user_id']);
        });

        // One row per reminder email batch that went out ("occasion:12:2026-12-20:7",
        // "dates:3:45:2026-11-12:1"), so a cron that runs twice never emails twice.
        Schema::create('reminder_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->string('key', 80)->unique();
            $table->timestamp('created_at')->nullable();
        });

        Schema::table('family_members', function (Blueprint $table) {
            $table->boolean('email_reminders')->default(true)->after('person_id');
            $table->string('calendar_token', 40)->nullable()->unique()->after('email_reminders');
        });
    }

    public function down(): void
    {
        Schema::table('family_members', function (Blueprint $table) {
            $table->dropColumn(['email_reminders', 'calendar_token']);
        });
        Schema::dropIfExists('reminder_log');
        Schema::dropIfExists('occasion_rsvps');
        Schema::dropIfExists('occasion_person');
        Schema::dropIfExists('occasions');
    }
};
