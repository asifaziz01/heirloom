<?php

namespace App\Services;

use App\Models\Family;
use App\Services\Gedcom\GedcomExporter;
use App\Support\PartialDate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * "Your family owns its data": a complete, self-describing backup that stays useful
 * without this product — JSON for machines, GEDCOM for genealogy software,
 * Markdown stories and original media files for people.
 */
class ArchiveExporter
{
    public function __construct(private Family $family) {}

    /**
     * Writes the ZIP to $path (normally families/{id}/exports/ on the private disk, via BuildArchive)
     * or, when omitted, to a short-lived file in the system temp directory. Returns the path.
     */
    public function build(?string $path = null): string
    {
        $path ??= sys_get_temp_dir().DIRECTORY_SEPARATOR.$this->family->slug.'-archive-'.now()->format('Y-m-d-His').'.zip';
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the archive file.');
        }
        $f = $this->family;
        $f->load(['persons.names', 'partnerships', 'parentLinks', 'places', 'events.people', 'stories.people', 'media.people', 'albums', 'sources', 'facts']);

        $zip->addFromString('README.txt', $this->readme());
        $zip->addFromString('family.ged', (new GedcomExporter($f))->export());
        $zip->addFromString('archive.json', json_encode([
            'format' => 'heirloom-archive/1',
            'exported_at' => now()->toIso8601String(),
            'family' => $f->only(['name', 'slug', 'description', 'primary_language']),
            'people' => $f->persons->map(fn ($p) => $p->toArray())->values(),
            'partnerships' => $f->partnerships,
            'parent_child' => $f->parentLinks,
            'places' => $f->places,
            'events' => $f->events,
            'stories' => $f->stories,
            'albums' => $f->albums,
            'media' => $f->media->map(fn ($m) => $m->toArray() + ['archive_path' => $this->mediaPath($m)])->values(),
            'sources' => $f->sources,
            'facts' => $f->facts,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        foreach ($f->stories as $s) {
            $md = "# {$s->title}\n\n";
            $md .= $s->story_date ? '*'.PartialDate::format($s->story_date)."*\n\n" : '';
            $md .= $s->people->isNotEmpty() ? 'People: '.$s->people->pluck('full_name')->join(', ')."\n\n" : '';
            $zip->addFromString('stories/'.str_pad((string) $s->id, 4, '0', STR_PAD_LEFT).'-'.Str::slug($s->title).'.md', $md.$s->body."\n");
        }

        $disk = Storage::disk('local');
        foreach ($f->media as $m) {
            if ($disk->exists($m->path)) {
                $zip->addFile($disk->path($m->path), $this->mediaPath($m));
            }
        }
        $zip->close();

        return $path;
    }

    private function mediaPath($m): string
    {
        $folder = ['photo' => 'photos', 'video' => 'videos', 'audio' => 'audio', 'document' => 'documents'][$m->kind] ?? 'files';

        return "{$folder}/{$m->id}-".Str::limit(Str::slug(pathinfo($m->original_name, PATHINFO_FILENAME)), 60, '').'.'.pathinfo($m->original_name, PATHINFO_EXTENSION);
    }

    private function readme(): string
    {
        return <<<TXT
        {$this->family->name} — family archive
        Exported {$this->now()}

        family.ged     Family tree in GEDCOM 5.5.1. Opens in almost any genealogy program.
        archive.json   Everything in structured form: people, names in every language,
                       relationships, events, places, sources, facts and media metadata.
        stories/       Every family story as a plain Markdown text file.
        photos/, documents/, audio/, videos/
                       Original files exactly as uploaded. The number prefix matches
                       the "id" of the item in archive.json.

        This archive belongs to your family. Keep a copy somewhere safe.
        TXT;
    }

    private function now(): string
    {
        return now()->toDayDateTimeString();
    }
}
