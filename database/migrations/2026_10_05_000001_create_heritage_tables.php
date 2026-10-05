<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Core heritage schema.
 *
 * The data model is relationship-first: people never carry father_id / mother_id / spouse_id.
 * Family structure lives in two edge tables:
 *   - partnerships  (partner <-> partner, any number per person, with status & dates)
 *   - parent_child  (parent -> child, typed: biological / adoptive / step / foster / guardian)
 * The visual tree is always derived from this graph.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('families', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->foreignId('owner_id')->constrained('users');
            $table->string('plan')->default('free');
            $table->unsignedBigInteger('storage_used')->default(0);
            $table->string('primary_language', 8)->default('en');
            $table->boolean('hide_living_from_viewers')->default(false);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('current_family_id')->nullable()->constrained('families')->nullOnDelete();
        });

        Schema::create('places', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('city')->nullable();
            $table->string('region')->nullable();
            $table->string('country')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('persons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->string('given_name');
            $table->string('surname')->nullable();
            $table->string('nickname')->nullable();
            $table->string('gender', 10)->default('unknown'); // male, female, other, unknown
            // Partial dates: "1942", "1942-03", "1942-03-15"; qualifier: exact, about, before, after
            $table->string('birth_date', 10)->nullable();
            $table->string('birth_qualifier', 8)->default('exact');
            $table->foreignId('birth_place_id')->nullable()->constrained('places')->nullOnDelete();
            $table->string('death_date', 10)->nullable();
            $table->string('death_qualifier', 8)->default('exact');
            $table->foreignId('death_place_id')->nullable()->constrained('places')->nullOnDelete();
            $table->boolean('is_living')->default(true);
            $table->string('occupation')->nullable();
            $table->string('education')->nullable();
            $table->string('religion')->nullable();
            $table->text('biography')->nullable();
            $table->unsignedBigInteger('photo_media_id')->nullable();
            $table->string('privacy', 10)->default('family'); // family, admins
            $table->string('branch')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('gedcom_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['family_id', 'surname']);
        });

        Schema::create('person_names', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            $table->string('type', 16)->default('alternate'); // birth, married, alternate, religious, nickname, transliteration
            $table->string('language', 8)->default('en');
            $table->string('full_name');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('partnerships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partner1_id')->constrained('persons')->cascadeOnDelete();
            $table->foreignId('partner2_id')->nullable()->constrained('persons')->cascadeOnDelete(); // null = unknown partner
            $table->string('type', 16)->default('marriage'); // marriage, civil, partnership, engagement, unknown
            $table->string('status', 16)->default('current'); // current, divorced, separated, widowed, annulled, ended
            $table->string('start_date', 10)->nullable();
            $table->string('end_date', 10)->nullable();
            $table->foreignId('place_id')->nullable()->constrained('places')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('parent_child', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->constrained('persons')->cascadeOnDelete();
            $table->foreignId('child_id')->constrained('persons')->cascadeOnDelete();
            $table->foreignId('partnership_id')->nullable()->constrained('partnerships')->nullOnDelete();
            $table->string('type', 16)->default('biological'); // biological, adoptive, step, foster, guardian, unknown
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['parent_id', 'child_id']);
        });

        Schema::create('albums', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('album_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 10); // photo, video, audio, document
            $table->string('document_type', 20)->nullable(); // certificate, letter, record, passport, deed, other
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime');
            $table->unsignedBigInteger('size');
            $table->string('taken_date', 10)->nullable();
            $table->foreignId('place_id')->nullable()->constrained('places')->nullOnDelete();
            $table->longText('transcription')->nullable();
            $table->json('ai_suggestions')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('media_person', function (Blueprint $table) {
            $table->foreignId('media_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            $table->primary(['media_id', 'person_id']);
        });

        Schema::create('stories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->longText('body');
            $table->string('language', 8)->default('en');
            $table->foreignId('told_by_person_id')->nullable()->constrained('persons')->nullOnDelete();
            $table->string('story_date', 10)->nullable();
            $table->foreignId('place_id')->nullable()->constrained('places')->nullOnDelete();
            $table->foreignId('audio_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('person_story', function (Blueprint $table) {
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            $table->primary(['story_id', 'person_id']);
        });

        Schema::create('media_story', function (Blueprint $table) {
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_id')->constrained()->cascadeOnDelete();
            $table->primary(['story_id', 'media_id']);
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // migration, education, occupation, military, religious, residence, award, illness, custom
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('date', 10)->nullable();
            $table->string('date_qualifier', 8)->default('exact');
            $table->string('end_date', 10)->nullable();
            $table->foreignId('place_id')->nullable()->constrained('places')->nullOnDelete();
            $table->foreignId('from_place_id')->nullable()->constrained('places')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('event_person', function (Blueprint $table) {
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            $table->string('role', 16)->default('principal');
            $table->primary(['event_id', 'person_id']);
        });

        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('type', 20)->default('document'); // document, recollection, interview, record, book, website
            $table->text('citation')->nullable();
            $table->string('url')->nullable();
            $table->foreignId('media_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        // Sourced, possibly conflicting claims about a person ("relatives remember different dates").
        Schema::create('facts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            $table->string('field', 20); // birth_date, death_date, birth_place, death_place, occupation, name, other
            $table->string('value');
            $table->foreignId('source_id')->nullable()->constrained()->nullOnDelete();
            $table->string('confidence', 8)->default('medium'); // high, medium, low
            $table->string('status', 12)->default('unverified'); // verified, unverified, disputed
            $table->boolean('is_preferred')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('family_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 12)->default('viewer'); // owner, admin, editor, contributor, viewer
            $table->foreignId('person_id')->nullable()->constrained('persons')->nullOnDelete(); // "this is me"
            $table->timestamps();
            $table->unique(['family_id', 'user_id']);
        });

        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->string('email')->nullable();
            $table->string('role', 12)->default('contributor');
            $table->string('token', 64)->unique();
            $table->foreignId('person_id')->nullable()->constrained('persons')->nullOnDelete();
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        // Governance: contributor submissions wait here until an editor/admin approves them.
        Schema::create('change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('action', 40);
            $table->string('summary');
            $table->json('payload');
            $table->string('status', 10)->default('pending'); // pending, approved, rejected
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 20);
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('description');
            $table->json('changes')->nullable();
            $table->timestamps();
            $table->index(['family_id', 'created_at']);
        });

        // Family questions / memory prompts: "Ask Grandma where she grew up".
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->text('question');
            $table->foreignId('about_person_id')->nullable()->constrained('persons')->nullOnDelete();
            $table->foreignId('asked_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('answer_story_id')->nullable()->constrained('stories')->nullOnDelete();
            $table->string('status', 10)->default('open');
            $table->timestamps();
        });

        Schema::create('interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->nullable()->constrained('persons')->nullOnDelete(); // interviewee
            $table->string('title');
            $table->string('language', 8)->default('en');
            $table->longText('transcript')->nullable();
            $table->foreignId('audio_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->json('extraction')->nullable();
            $table->string('status', 12)->default('draft'); // draft, extracted, applied
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['interviews', 'questions', 'activity_logs', 'change_requests', 'invitations', 'family_members',
            'facts', 'sources', 'event_person', 'events', 'media_story', 'person_story', 'stories', 'media_person',
            'media', 'albums', 'parent_child', 'partnerships', 'person_names', 'persons', 'places'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('current_family_id'));
        Schema::dropIfExists('families');
    }
};
