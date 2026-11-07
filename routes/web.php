<?php

use App\Http\Controllers;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FamilyController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\PasswordResetController;
use Illuminate\Support\Facades\Route;

Route::get('/', [Controllers\HomeController::class, 'index'])->name('home');
Route::view('/offline', 'offline')->name('offline');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');

    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:password-reset')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:password-reset')->name('password.update');
});

Route::get('/invite/{token}', [InvitationController::class, 'show'])->name('invitations.show');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/invite/{token}', [InvitationController::class, 'accept'])->name('invitations.accept');

    Route::get('/families', [FamilyController::class, 'index'])->name('families.index');
    Route::get('/families/create', [FamilyController::class, 'create'])->name('families.create');
    Route::post('/families', [FamilyController::class, 'store'])->name('families.store');
    Route::get('/media-file/{media}', [Controllers\MediaController::class, 'file'])->name('media.file');

    Route::prefix('f/{family}')->middleware('family')->scopeBindings()->group(function () {
        Route::get('/', [FamilyController::class, 'show'])->name('families.show');
        Route::get('/search', Controllers\SearchController::class)->name('search');

        // People & relationships
        Route::get('/people', [Controllers\PersonController::class, 'index'])->name('people.index');
        Route::get('/people/{person}', [Controllers\PersonController::class, 'show'])->name('people.show');
        Route::get('/people/{person}/timeline', [Controllers\PersonController::class, 'timeline'])->name('people.timeline');
        Route::get('/tree', [Controllers\TreeController::class, 'show'])->name('tree.show');
        Route::get('/tree/pedigree/{person}', [Controllers\TreeController::class, 'pedigree'])->name('tree.pedigree');
        Route::get('/tree/pedigree/{person}/download', [Controllers\TreeController::class, 'pedigreeDownload'])->name('tree.pedigree.download');
        Route::get('/relationship', [Controllers\RelationshipController::class, 'calculator'])->name('relationship.calculator');

        // History
        Route::get('/timeline', [Controllers\TimelineController::class, 'index'])->name('timeline');
        Route::get('/places', [Controllers\PlaceController::class, 'index'])->name('places.index');
        Route::get('/stories', [Controllers\StoryController::class, 'index'])->name('stories.index');
        Route::get('/stories/{story}', [Controllers\StoryController::class, 'show'])->name('stories.show')->whereNumber('story');
        Route::get('/events', [Controllers\EventController::class, 'index'])->name('events.index');
        Route::get('/media', [Controllers\MediaController::class, 'index'])->name('media.index');
        Route::get('/media/{media}', [Controllers\MediaController::class, 'show'])->name('media.show')->whereNumber('media');
        Route::get('/albums/{album}', [Controllers\AlbumController::class, 'show'])->name('albums.show');
        Route::get('/sources', [Controllers\SourceController::class, 'index'])->name('sources.index');
        Route::get('/questions', [Controllers\QuestionController::class, 'index'])->name('questions.index');
        Route::get('/book', [Controllers\ExportController::class, 'book'])->name('book');

        // Contributors and above: add memories, ask questions, propose structural changes (reviewed)
        Route::middleware('family:contributor')->group(function () {
            Route::get('/people/create', [Controllers\PersonController::class, 'create'])->name('people.create');
            Route::post('/people', [Controllers\PersonController::class, 'store'])->name('people.store');
            Route::get('/people/{person}/edit', [Controllers\PersonController::class, 'edit'])->name('people.edit');
            Route::put('/people/{person}', [Controllers\PersonController::class, 'update'])->name('people.update');
            Route::delete('/people/{person}', [Controllers\PersonController::class, 'destroy'])->name('people.destroy');
            Route::post('/people/{person}/names', [Controllers\PersonController::class, 'storeName'])->name('people.names.store');
            Route::delete('/people/{person}/names/{name}', [Controllers\PersonController::class, 'destroyName'])->name('people.names.destroy')->withoutScopedBindings();
            Route::post('/people/{person}/photo', [Controllers\PersonController::class, 'setPhoto'])->name('people.photo');
            Route::post('/people/{person}/link', [Controllers\RelationshipController::class, 'link'])->name('relationships.link');
            Route::delete('/links/{link}', [Controllers\RelationshipController::class, 'unlink'])->name('relationships.unlink')->withoutScopedBindings();
            Route::get('/partnerships/{partnership}/edit', [Controllers\RelationshipController::class, 'editPartnership'])->name('partnerships.edit');
            Route::put('/partnerships/{partnership}', [Controllers\RelationshipController::class, 'updatePartnership'])->name('partnerships.update');
            Route::delete('/partnerships/{partnership}', [Controllers\RelationshipController::class, 'destroyPartnership'])->name('partnerships.destroy');
            Route::post('/people/{person}/facts', [Controllers\FactController::class, 'store'])->name('facts.store');

            Route::get('/stories/create', [Controllers\StoryController::class, 'create'])->name('stories.create');
            Route::post('/stories', [Controllers\StoryController::class, 'store'])->name('stories.store');
            Route::get('/stories/{story}/edit', [Controllers\StoryController::class, 'edit'])->name('stories.edit');
            Route::put('/stories/{story}', [Controllers\StoryController::class, 'update'])->name('stories.update');
            Route::delete('/stories/{story}', [Controllers\StoryController::class, 'destroy'])->name('stories.destroy');

            Route::get('/events/create', [Controllers\EventController::class, 'create'])->name('events.create');
            Route::post('/events', [Controllers\EventController::class, 'store'])->name('events.store');
            Route::get('/events/{event}/edit', [Controllers\EventController::class, 'edit'])->name('events.edit');
            Route::put('/events/{event}', [Controllers\EventController::class, 'update'])->name('events.update');
            Route::delete('/events/{event}', [Controllers\EventController::class, 'destroy'])->name('events.destroy');

            Route::get('/upload', [Controllers\MediaController::class, 'create'])->name('media.create');
            Route::post('/media', [Controllers\MediaController::class, 'store'])->name('media.store');
            Route::put('/media/{media}', [Controllers\MediaController::class, 'update'])->name('media.update');
            Route::delete('/media/{media}', [Controllers\MediaController::class, 'destroy'])->name('media.destroy');
            Route::post('/media/{media}/analyze', [Controllers\MediaController::class, 'analyze'])->name('media.analyze');
            Route::post('/albums', [Controllers\AlbumController::class, 'store'])->name('albums.store');

            Route::put('/places/{place}', [Controllers\PlaceController::class, 'update'])->name('places.update');

            Route::post('/questions', [Controllers\QuestionController::class, 'store'])->name('questions.store');
            Route::get('/questions/{question}/answer', [Controllers\QuestionController::class, 'answer'])->name('questions.answer');
            Route::delete('/questions/{question}', [Controllers\QuestionController::class, 'destroy'])->name('questions.destroy');

            Route::get('/interviews', [Controllers\InterviewController::class, 'index'])->name('interviews.index');
            Route::get('/interviews/create', [Controllers\InterviewController::class, 'create'])->name('interviews.create');
            Route::post('/interviews', [Controllers\InterviewController::class, 'store'])->name('interviews.store');
            Route::get('/interviews/{interview}', [Controllers\InterviewController::class, 'show'])->name('interviews.show');
            Route::put('/interviews/{interview}', [Controllers\InterviewController::class, 'update'])->name('interviews.update');
            Route::post('/interviews/{interview}/extract', [Controllers\InterviewController::class, 'extract'])->name('interviews.extract');
            Route::post('/interviews/{interview}/apply', [Controllers\InterviewController::class, 'apply'])->name('interviews.apply');

            Route::post('/people/{person}/ai/biography', [Controllers\AiController::class, 'biography'])->name('ai.biography');
            Route::post('/people/{person}/ai/questions', [Controllers\AiController::class, 'questions'])->name('ai.questions');

            // Progress of queued AI jobs, polled by the page that started them
            Route::get('/interviews/{interview}/status', [Controllers\JobStatusController::class, 'interview'])->name('interviews.status');
            Route::get('/media/{media}/status', [Controllers\JobStatusController::class, 'media'])->name('media.status');
            Route::get('/ai/tasks/{aiTask}', [Controllers\JobStatusController::class, 'aiTask'])->name('ai.tasks.status');
        });

        // Editors and above
        Route::middleware('family:editor')->group(function () {
            Route::post('/facts/{fact}/prefer', [Controllers\FactController::class, 'prefer'])->name('facts.prefer');
            Route::put('/facts/{fact}', [Controllers\FactController::class, 'update'])->name('facts.update');
            Route::delete('/facts/{fact}', [Controllers\FactController::class, 'destroy'])->name('facts.destroy');
            Route::get('/review', [Controllers\ReviewController::class, 'index'])->name('review.index');
            Route::post('/review/{changeRequest}/approve', [Controllers\ReviewController::class, 'approve'])->name('review.approve');
            Route::post('/review/{changeRequest}/reject', [Controllers\ReviewController::class, 'reject'])->name('review.reject');
        });

        // Administrators and owner
        Route::middleware('family:admin')->group(function () {
            Route::get('/settings', [FamilyController::class, 'edit'])->name('families.edit');
            Route::put('/settings', [FamilyController::class, 'update'])->name('families.update');
            Route::delete('/settings', [FamilyController::class, 'destroy'])->name('families.destroy');
            Route::get('/members', [InvitationController::class, 'index'])->name('members.index');
            Route::post('/invitations', [InvitationController::class, 'store'])->name('invitations.store');
            Route::delete('/invitations/{invitation}', [InvitationController::class, 'destroy'])->name('invitations.destroy');
            Route::put('/members/{member}', [InvitationController::class, 'updateMember'])->name('members.update');
            Route::delete('/members/{member}', [InvitationController::class, 'removeMember'])->name('members.destroy');
            Route::get('/activity', [Controllers\ActivityController::class, 'index'])->name('activity.index');
            Route::get('/export', [Controllers\ExportController::class, 'index'])->name('export.index');
            Route::get('/export/gedcom', [Controllers\ExportController::class, 'gedcom'])->name('export.gedcom');
            Route::post('/import/gedcom', [Controllers\ExportController::class, 'importGedcom'])->name('import.gedcom');
            Route::post('/export/archive', [Controllers\ExportController::class, 'archive'])->name('export.archive');
            Route::get('/export/archive/{export}/status', [Controllers\JobStatusController::class, 'export'])->name('export.status');
            Route::get('/export/archive/{export}/download', [Controllers\ExportController::class, 'download'])->name('export.download');
            Route::get('/plans', [Controllers\BillingController::class, 'index'])->name('billing.index');
            Route::post('/plans', [Controllers\BillingController::class, 'change'])->name('billing.change');
        });
    });
});
