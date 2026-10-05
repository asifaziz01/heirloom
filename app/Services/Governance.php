<?php

namespace App\Services;

use App\Models\ChangeRequest;
use App\Models\FamilyMember;
use App\Models\ParentChild;
use InvalidArgumentException;

/**
 * Family governance: editors and above change the archive directly; contributors' structural
 * changes are stored as change requests and applied only when an editor or admin approves them.
 */
class Governance
{
    public const ACTIONS = [
        'person.create' => 'Add a person',
        'person.update' => 'Edit a person',
        'person.delete' => 'Remove a person',
        'parent.link' => 'Link parent and child',
        'child.link' => 'Link a child',
        'sibling.link' => 'Link siblings',
        'parent.unlink' => 'Remove a parent link',
        'partnership.create' => 'Add a partnership',
        'partnership.update' => 'Edit a partnership',
        'partnership.delete' => 'Remove a partnership',
        'fact.create' => 'Add a sourced fact',
    ];

    /**
     * Apply now or queue for review.
     *
     * @return array{applied: bool, result: mixed, request: ?ChangeRequest}
     */
    public static function perform(FamilyMember $member, string $action, string $summary, array $payload): array
    {
        if (! isset(self::ACTIONS[$action])) {
            throw new InvalidArgumentException("Unknown action {$action}");
        }
        if ($member->needsReview()) {
            $request = ChangeRequest::create([
                'family_id' => $member->family_id,
                'user_id' => $member->user_id,
                'action' => $action,
                'summary' => $summary,
                'payload' => $payload,
            ]);

            return ['applied' => false, 'result' => null, 'request' => $request];
        }

        return ['applied' => true, 'result' => self::apply($member->family, $action, $payload, $member->user_id), 'request' => null];
    }

    public static function apply($family, string $action, array $payload, ?int $userId = null): mixed
    {
        $graph = FamilyGraph::for($family);

        return match ($action) {
            'person.create' => $graph->createPerson($payload['person'], $payload['relation'] ?? null, $userId),
            'person.update' => $graph->updatePerson($family->persons()->findOrFail($payload['id']), $payload['person']),
            'person.delete' => $graph->deletePerson($family->persons()->findOrFail($payload['id'])),
            'parent.link' => $graph->linkParent($payload['parent_id'], $payload['child_id'], $payload['type'] ?? 'biological', $payload['coparent_id'] ?? null, $payload['partnership_id'] ?? null),
            'child.link' => $graph->linkChild($payload['parent_id'], $payload['child_id'], $payload['type'] ?? 'biological', $payload['partnership_id'] ?? null),
            'sibling.link' => $graph->linkSibling($payload['person_id'], $payload['sibling_id']),
            'parent.unlink' => $graph->unlinkParent(ParentChild::where('family_id', $family->id)->findOrFail($payload['id'])),
            'partnership.create' => $graph->createPartnership($payload['partner1_id'], $payload['partner2_id'] ?? null, $payload),
            'partnership.update' => $graph->updatePartnership($family->partnerships()->findOrFail($payload['id']), $payload),
            'partnership.delete' => $graph->deletePartnership($family->partnerships()->findOrFail($payload['id'])),
            'fact.create' => $graph->addFact($payload['person_id'], $payload, $userId),
        };
    }

    public static function approve(ChangeRequest $request, FamilyMember $reviewer, ?string $note = null): mixed
    {
        $result = self::apply($reviewer->family, $request->action, $request->payload, $request->user_id);
        $request->update(['status' => 'approved', 'reviewed_by' => $reviewer->user_id, 'reviewed_at' => now(), 'review_note' => $note]);

        return $result;
    }

    public static function reject(ChangeRequest $request, FamilyMember $reviewer, ?string $note = null): void
    {
        $request->update(['status' => 'rejected', 'reviewed_by' => $reviewer->user_id, 'reviewed_at' => now(), 'review_note' => $note]);
    }
}
