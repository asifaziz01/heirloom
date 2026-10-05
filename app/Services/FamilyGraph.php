<?php

namespace App\Services;

use App\Models\Fact;
use App\Models\Family;
use App\Models\ParentChild;
use App\Models\Partnership;
use App\Models\Person;
use App\Models\PersonName;
use App\Support\PartialDate;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * All structural writes to the family graph go through here, so they can be
 * applied directly (editors) or replayed later from an approved change request (contributors).
 */
class FamilyGraph
{
    public const PERSON_FIELDS = ['given_name', 'surname', 'nickname', 'gender', 'birth_date', 'birth_qualifier', 'death_date',
        'death_qualifier', 'is_living', 'occupation', 'education', 'religion', 'biography', 'privacy', 'branch', 'photo_media_id'];

    public function __construct(private Family $family) {}

    public static function for(Family $family): self
    {
        return new self($family);
    }

    /**
     * @param  array  $data  person attributes; birth_place / death_place as free-text names
     * @param  array|null  $relation  ['type' => parent|child|partner|sibling, 'person_id' => id, ...options]
     */
    public function createPerson(array $data, ?array $relation = null, ?int $userId = null): Person
    {
        return DB::transaction(function () use ($data, $relation, $userId) {
            $person = Person::create($this->personAttributes($data) + [
                'family_id' => $this->family->id,
                'created_by' => $userId,
            ]);
            foreach ($data['names'] ?? [] as $name) {
                if (! empty($name['full_name'])) {
                    $person->names()->create(Arr::only($name, ['type', 'language', 'full_name', 'notes']));
                }
            }
            if ($relation) {
                $this->relate($person, $relation);
            }

            return $person;
        });
    }

    public function updatePerson(Person $person, array $data): Person
    {
        $this->assertOwned($person);
        $person->update($this->personAttributes($data, $person));

        return $person;
    }

    public function deletePerson(Person $person): void
    {
        $this->assertOwned($person);
        DB::transaction(function () use ($person) {
            ParentChild::where('parent_id', $person->id)->orWhere('child_id', $person->id)->delete();
            Partnership::where('partner1_id', $person->id)->orWhere('partner2_id', $person->id)->get()->each->delete();
            $person->delete();
        });
    }

    private function personAttributes(array $data, ?Person $existing = null): array
    {
        $attrs = Arr::only($data, self::PERSON_FIELDS);
        foreach (['birth_date', 'death_date'] as $f) {
            if (array_key_exists($f, $attrs)) {
                $attrs[$f] = PartialDate::normalize($attrs[$f]);
            }
        }
        foreach (['birth_place' => 'birth_place_id', 'death_place' => 'death_place_id'] as $in => $col) {
            if (array_key_exists($in, $data)) {
                $attrs[$col] = PlaceResolver::resolve($this->family, $data[$in]);
            }
        }
        if (array_key_exists('is_living', $attrs)) {
            $attrs['is_living'] = (bool) $attrs['is_living'];
        }
        if (! empty($attrs['death_date'])) {
            $attrs['is_living'] = false;
        }

        return $attrs;
    }

    /** Attach a newly created person to an existing one. */
    public function relate(Person $person, array $relation): void
    {
        $other = $this->family->persons()->findOrFail($relation['person_id']);
        $type = $relation['link_type'] ?? 'biological';

        switch ($relation['type']) {
            case 'parent':
                $this->linkParent($person->id, $other->id, $type, $relation['coparent_id'] ?? null);
                break;
            case 'child':
                $this->linkChild($other->id, $person->id, $type, $relation['partnership_id'] ?? null);
                break;
            case 'partner':
                $this->createPartnership($other->id, $person->id, Arr::only($relation, ['partnership_type', 'status', 'start_date', 'end_date']));
                break;
            case 'sibling':
                $this->linkSibling($other->id, $person->id);
                break;
            default:
                throw new InvalidArgumentException('Unknown relation type');
        }
    }

    /**
     * Link parent -> child. When the child already has another parent and a coparent is given (or found),
     * both links are attached to the partnership between the two parents (created if needed).
     */
    public function linkParent(int $parentId, int $childId, string $type = 'biological', ?int $coparentId = null, ?int $partnershipId = null): ParentChild
    {
        if ($parentId === $childId) {
            throw new InvalidArgumentException('A person cannot be their own parent.');
        }
        $this->family->persons()->findOrFail($parentId);
        $this->family->persons()->findOrFail($childId);
        if ($this->isAncestor($childId, $parentId)) {
            throw new InvalidArgumentException('That link would make someone their own ancestor.');
        }

        if (! $partnershipId && $coparentId) {
            $partnershipId = $this->findOrCreatePartnership($parentId, $coparentId)->id;
            ParentChild::where('parent_id', $coparentId)->where('child_id', $childId)->update(['partnership_id' => $partnershipId]);
        }

        return ParentChild::updateOrCreate(
            ['parent_id' => $parentId, 'child_id' => $childId],
            ['family_id' => $this->family->id, 'type' => $type, 'partnership_id' => $partnershipId]
        );
    }

    /** Link a child to a parent and, when a partnership is given, to that partnership's other partner too. */
    public function linkChild(int $parentId, int $childId, string $type = 'biological', ?int $partnershipId = null): ParentChild
    {
        $link = $this->linkParent($parentId, $childId, $type, partnershipId: $partnershipId);
        if ($partnershipId) {
            $p = $this->family->partnerships()->findOrFail($partnershipId);
            if ($co = $p->partnerOf($parentId)) {
                $this->linkParent($co->id, $childId, $type, partnershipId: $p->id);
            }
        }

        return $link;
    }

    /** Give $siblingId the same parents (and link types) as $personId. */
    public function linkSibling(int $personId, int $siblingId): void
    {
        $links = ParentChild::where('child_id', $personId)->get();
        if ($links->isEmpty()) {
            throw new InvalidArgumentException('Add at least one parent first, then siblings can share them.');
        }
        foreach ($links as $link) {
            $this->linkParent($link->parent_id, $siblingId, $link->type, partnershipId: $link->partnership_id);
        }
    }

    public function unlinkParent(ParentChild $link): void
    {
        abort_unless($link->family_id === $this->family->id, 404);
        $link->delete();
    }

    public function createPartnership(int $a, ?int $b, array $data = []): Partnership
    {
        if ($a === $b) {
            throw new InvalidArgumentException('A person cannot partner with themselves.');
        }

        return Partnership::create([
            'family_id' => $this->family->id,
            'partner1_id' => $a,
            'partner2_id' => $b,
            'type' => $data['partnership_type'] ?? $data['type'] ?? 'marriage',
            'status' => $data['status'] ?? 'current',
            'start_date' => PartialDate::normalize($data['start_date'] ?? null),
            'end_date' => PartialDate::normalize($data['end_date'] ?? null),
            'place_id' => PlaceResolver::resolve($this->family, $data['place'] ?? null),
            'notes' => $data['notes'] ?? null,
        ]);
    }

    public function updatePartnership(Partnership $p, array $data): Partnership
    {
        abort_unless($p->family_id === $this->family->id, 404);
        $p->update([
            'type' => $data['type'] ?? $p->type,
            'status' => $data['status'] ?? $p->status,
            'start_date' => PartialDate::normalize($data['start_date'] ?? null),
            'end_date' => PartialDate::normalize($data['end_date'] ?? null),
            'place_id' => PlaceResolver::resolve($this->family, $data['place'] ?? null),
            'notes' => $data['notes'] ?? null,
        ]);

        return $p;
    }

    public function deletePartnership(Partnership $p): void
    {
        abort_unless($p->family_id === $this->family->id, 404);
        $p->delete();
    }

    public function findOrCreatePartnership(int $a, int $b): Partnership
    {
        return Partnership::where('family_id', $this->family->id)
            ->where(fn ($q) => $q->where(fn ($w) => $w->where('partner1_id', $a)->where('partner2_id', $b))
                ->orWhere(fn ($w) => $w->where('partner1_id', $b)->where('partner2_id', $a)))
            ->first() ?? $this->createPartnership($a, $b, ['type' => 'unknown']);
    }

    /** Record a sourced claim; if marked preferred, it becomes the value shown on the profile. */
    public function addFact(int $personId, array $data, ?int $userId = null): Fact
    {
        $person = $this->family->persons()->findOrFail($personId);
        $sourceId = $data['source_id'] ?? null;
        if (! $sourceId && ! empty($data['source_title'])) {
            $sourceId = $this->family->sources()->create([
                'title' => $data['source_title'],
                'type' => $data['source_type'] ?? 'recollection',
                'citation' => $data['source_citation'] ?? null,
            ])->id;
        }
        $fact = Fact::create([
            'family_id' => $this->family->id,
            'person_id' => $person->id,
            'field' => $data['field'],
            'value' => $data['value'],
            'source_id' => $sourceId,
            'confidence' => $data['confidence'] ?? 'medium',
            'status' => $data['status'] ?? 'unverified',
            'notes' => $data['notes'] ?? null,
            'added_by' => $userId,
        ]);
        if (! empty($data['is_preferred'])) {
            $this->preferFact($fact);
        }

        return $fact;
    }

    public function preferFact(Fact $fact): void
    {
        Fact::where('person_id', $fact->person_id)->where('field', $fact->field)->update(['is_preferred' => false]);
        $fact->update(['is_preferred' => true]);
        $person = $fact->person;
        match ($fact->field) {
            'birth_date', 'death_date' => $person->update([$fact->field => PartialDate::normalize($fact->value) ?? $person->{$fact->field}]),
            'birth_place', 'death_place' => $person->update([$fact->field.'_id' => PlaceResolver::resolve($this->family, $fact->value)]),
            'occupation' => $person->update(['occupation' => $fact->value]),
            default => null,
        };
    }

    public function addName(Person $person, array $data): PersonName
    {
        $this->assertOwned($person);

        return $person->names()->create(Arr::only($data, ['type', 'language', 'full_name', 'notes']));
    }

    /** True when $ancestorId is an ancestor of $personId (prevents cycles). */
    public function isAncestor(int $ancestorId, int $personId): bool
    {
        $seen = [];
        $frontier = [$personId];
        while ($frontier) {
            $parents = ParentChild::whereIn('child_id', $frontier)->pluck('parent_id')->all();
            if (in_array($ancestorId, $parents, true)) {
                return true;
            }
            $frontier = array_diff($parents, $seen);
            $seen = array_merge($seen, $frontier);
        }

        return false;
    }

    private function assertOwned(Person $person): void
    {
        abort_unless($person->family_id === $this->family->id, 404);
    }
}
