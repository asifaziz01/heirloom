<?php

namespace App\Http\Controllers;

use App\Jobs\AnalyzeMedia;
use App\Models\Family;
use App\Models\Media;
use App\Services\PlaceResolver;
use App\Support\PartialDate;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MediaController extends Controller
{
    public function index(Request $request, Family $family)
    {
        $q = $family->media()->with('people')->latest();
        if ($kind = $request->string('kind')->value()) {
            $q->where('kind', $kind);
        }
        if ($personId = $request->integer('person')) {
            $q->whereHas('people', fn ($w) => $w->where('persons.id', $personId));
        }
        if ($term = $request->string('q')->trim()->value()) {
            $q->where(fn ($w) => $w->where('title', 'like', "%{$term}%")->orWhere('description', 'like', "%{$term}%")->orWhere('transcription', 'like', "%{$term}%"));
        }

        return view('media.index', [
            'media' => $q->paginate(48)->withQueryString(),
            'albums' => $family->albums()->withCount('media')->get(),
            'person' => $personId ? $family->persons()->find($personId) : null,
        ]);
    }

    public function create(Request $request, Family $family)
    {
        return view('media.create', [
            'people' => $family->persons()->orderBy('given_name')->get(['id', 'given_name', 'surname', 'birth_date']),
            'albums' => $family->albums()->get(),
            'preselect' => $request->integer('person') ?: null,
            'kind' => $request->string('kind', '')->value(),
        ]);
    }

    public function store(Request $request, Family $family)
    {
        $maxKb = 200 * 1024;
        $data = $request->validate([
            'files' => 'required|array|max:50',
            'files.*' => "file|max:{$maxKb}|mimetypes:image/jpeg,image/png,image/gif,image/webp,image/heic,image/tiff,video/mp4,video/quicktime,video/webm,audio/mpeg,audio/mp4,audio/wav,audio/x-wav,audio/webm,audio/ogg,application/pdf,text/plain",
            'title' => 'nullable|string|max:200',
            'description' => 'nullable|string|max:5000',
            'taken_date' => 'nullable|string|max:20',
            'place' => 'nullable|string|max:200',
            'album_id' => ['nullable', Rule::exists('albums', 'id')->where('family_id', $family->id)],
            'document_type' => ['nullable', Rule::in(array_keys(Media::DOCUMENT_TYPES))],
            'people' => 'nullable|array',
            'people.*' => [Rule::exists('persons', 'id')->where('family_id', $family->id)],
        ]);
        $files = $request->file('files');
        $bytes = array_sum(array_map(fn ($f) => $f->getSize(), $files));
        if ($msg = $family->limitReached('storage_mb', (int) ceil($bytes / 1048576))) {
            return back()->withErrors(['files' => $msg]);
        }
        $photos = count(array_filter($files, fn ($f) => str_starts_with((string) $f->getMimeType(), 'image/')));
        if ($photos && ($msg = $family->limitReached('photos', $photos))) {
            return back()->withErrors(['files' => $msg]);
        }

        $placeId = PlaceResolver::resolve($family, $data['place'] ?? null);
        $created = [];
        foreach ($files as $file) {
            $mime = $file->getMimeType() ?: 'application/octet-stream';
            $kind = Media::kindFromMime($mime);
            if ($kind === 'video' && ! $family->hasFeature('video')) {
                return back()->withErrors(['files' => 'Video uploads are part of the Heritage plan.']);
            }
            $media = $family->media()->create([
                'kind' => $kind === 'photo' && $request->filled('document_type') ? 'document' : $kind,
                'document_type' => $data['document_type'] ?? null,
                'title' => count($files) === 1 ? ($data['title'] ?? null) : null,
                'description' => $data['description'] ?? null,
                'path' => $file->store("families/{$family->id}", 'local'),
                'original_name' => $file->getClientOriginalName(),
                'mime' => $mime,
                'size' => $file->getSize(),
                'taken_date' => PartialDate::normalize($data['taken_date'] ?? null),
                'place_id' => $placeId,
                'album_id' => $data['album_id'] ?? null,
                'uploaded_by' => $request->user()->id,
            ]);
            $media->people()->sync($data['people'] ?? []);
            $created[] = $media;
        }
        $family->increment('storage_used', $bytes);

        return count($created) === 1
            ? redirect()->route('media.show', [$family, $created[0]])->with('status', 'Uploaded. Add a description or tag people so future generations know who is who.')
            : redirect()->route('media.index', $family)->with('status', count($created).' files uploaded.');
    }

    public function show(Family $family, Media $media)
    {
        $media->load(['people', 'place', 'album', 'uploader']);

        return view('media.show', [
            'media' => $media,
            'people' => $family->persons()->orderBy('given_name')->get(['id', 'given_name', 'surname', 'birth_date']),
            'albums' => $family->albums()->get(),
            'prev' => $family->media()->where('id', '<', $media->id)->where('kind', $media->kind)->max('id'),
            'next' => $family->media()->where('id', '>', $media->id)->where('kind', $media->kind)->min('id'),
        ]);
    }

    public function update(Request $request, Family $family, Media $media)
    {
        $data = $request->validate([
            'title' => 'nullable|string|max:200',
            'description' => 'nullable|string|max:5000',
            'taken_date' => 'nullable|string|max:20',
            'place' => 'nullable|string|max:200',
            'album_id' => ['nullable', Rule::exists('albums', 'id')->where('family_id', $family->id)],
            'document_type' => ['nullable', Rule::in(array_keys(Media::DOCUMENT_TYPES))],
            'transcription' => 'nullable|string|max:100000',
            'people' => 'nullable|array',
            'people.*' => [Rule::exists('persons', 'id')->where('family_id', $family->id)],
        ]);
        $media->update([
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'taken_date' => PartialDate::normalize($data['taken_date'] ?? null),
            'place_id' => PlaceResolver::resolve($family, $data['place'] ?? null),
            'album_id' => $data['album_id'] ?? null,
            'document_type' => $data['document_type'] ?? $media->document_type,
            'transcription' => $data['transcription'] ?? $media->transcription,
        ]);
        $media->people()->sync($data['people'] ?? []);

        return back()->with('status', 'Saved.');
    }

    public function destroy(Family $family, Media $media)
    {
        abort_unless($this->member()->canManage($media->uploaded_by), 403);
        Storage::disk('local')->delete($media->path);
        $family->decrement('storage_used', min($media->size, $family->storage_used));
        $family->persons()->where('photo_media_id', $media->id)->update(['photo_media_id' => null]);
        $media->delete();

        return redirect()->route('media.index', $family)->with('status', 'File deleted.');
    }

    /**
     * Streams a file to family members, and to anyone when the archive is open to visitors.
     * ?thumb=1 serves a cached 480px JPEG for photos.
     */
    public function file(Request $request, Media $media)
    {
        if (! $media->family->is_public) {
            throw_unless($request->user(), AuthenticationException::class);
            abort_unless($request->user()->membershipIn($media->family_id), 403);
        }
        $disk = Storage::disk('local');
        abort_unless($disk->exists($media->path), 404);

        if ($request->boolean('thumb') && $media->isImage() && function_exists('imagecreatefromstring')) {
            $thumb = "thumbs/{$media->family_id}/{$media->id}.jpg";
            if (! $disk->exists($thumb) && self::fitsInMemory($disk->path($media->path)) && ($img = @imagecreatefromstring($disk->get($media->path)))) {
                $w = imagesx($img);
                $h = imagesy($img);
                $scale = min(1, 480 / max($w, $h));
                $t = imagescale($img, max(1, (int) ($w * $scale)), max(1, (int) ($h * $scale)));
                ob_start();
                imagejpeg($t, null, 82);
                $disk->put($thumb, ob_get_clean());
            }
            if ($disk->exists($thumb)) {
                return response()->file($disk->path($thumb), ['Cache-Control' => 'private, max-age=86400', 'Content-Type' => 'image/jpeg']);
            }
        }

        return $request->boolean('download')
            ? $disk->download($media->path, $media->original_name)
            : response()->file($disk->path($media->path), ['Content-Type' => $media->mime, 'Cache-Control' => 'private, max-age=86400']);
    }

    /**
     * GD decodes the whole bitmap (about 5 bytes per pixel), so a large scan can exceed memory_limit
     * and turn a thumbnail request into a 500. When it would not fit, the full image is served instead.
     */
    private static function fitsInMemory(string $file): bool
    {
        $size = @getimagesize($file);
        if (! $size || ! $size[0] || ! $size[1]) {
            return false;
        }
        $limit = ini_get('memory_limit');
        if ($limit === false || (int) $limit < 0) {
            return true;
        }
        $bytes = (int) $limit * match (strtolower(substr($limit, -1))) {
            'g' => 1073741824, 'm' => 1048576, 'k' => 1024, default => 1
        };

        return memory_get_usage() + $size[0] * $size[1] * 5 < $bytes * 0.8;
    }

    /** AI photo/document analysis; it runs on the queue and the result is shown as suggestions for confirmation. */
    public function analyze(Family $family, Media $media)
    {
        abort_unless($family->hasFeature('ai'), 403, 'AI features are part of the Heritage plan.');
        if (in_array($media->aiStatus(), ['pending', 'running'], true)) {
            return back()->with('status', 'Analysis is already running.');
        }
        AnalyzeMedia::setSuggestions($media, ['status' => 'pending']);
        AnalyzeMedia::dispatch($media);

        return back()->with('status', match ($media->fresh()->aiStatus()) {
            'done' => 'AI suggestions are ready below. Review them before applying.',
            'failed' => 'Analysis did not finish; see the message below.',
            default => 'Analysing… this usually takes a minute or two. The suggestions appear here when they are ready.',
        });
    }
}
