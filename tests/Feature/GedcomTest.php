<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Family;
use App\Models\ParentChild;
use App\Models\Partnership;
use App\Models\Person;
use App\Models\User;
use App\Services\Gedcom\GedcomExporter;
use App\Services\Gedcom\GedcomImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GedcomTest extends TestCase
{
    use RefreshDatabase;

    private const SAMPLE = "\xEF\xBB\xBF0 HEAD\r\n1 SOUR TEST\r\n1 GEDC\r\n2 VERS 5.5.1\r\n2 FORM LINEAGE-LINKED\r\n1 CHAR UTF-8\r\n"
        ."0 @I1@ INDI\r\n1 NAME Ram Prasad /Sharma/\r\n2 GIVN Ram Prasad\r\n2 SURN Sharma\r\n1 NAME राम प्रसाद शर्मा\r\n2 TYPE aka\r\n2 LANG Hindi\r\n"
        ."1 SEX M\r\n1 BIRT\r\n2 DATE ABT 1890\r\n2 PLAC Lahore, Punjab, India\r\n1 DEAT\r\n2 DATE 15 MAR 1962\r\n1 OCCU Teacher\r\n"
        ."1 NOTE First line of his story\r\n2 CONC  continued here.\r\n2 CONT Second paragraph.\r\n1 FAMS @F1@\r\n1 FAMS @F2@\r\n"
        ."0 @I2@ INDI\r\n1 NAME Sita /Devi/\r\n1 SEX F\r\n1 BIRT\r\n2 DATE MAR 1895\r\n1 FAMS @F1@\r\n"
        ."0 @I3@ INDI\r\n1 NAME Kamla /Devi/\r\n1 SEX F\r\n1 BIRT\r\n2 DATE BET 1900 AND 1905\r\n1 FAMS @F2@\r\n"
        ."0 @I4@ INDI\r\n1 NAME Mohan /Sharma/\r\n1 SEX M\r\n1 BIRT\r\n2 DATE 2 JAN 1915\r\n1 FAMC @F1@\r\n2 PEDI birth\r\n"
        ."0 @I5@ INDI\r\n1 NAME Gita /Sharma/\r\n1 SEX F\r\n1 FAMC @F1@\r\n"
        ."0 @I6@ INDI\r\n1 NAME Ravi /Sharma/\r\n1 SEX M\r\n1 BIRT\r\n2 DATE AFT 1930\r\n1 FAMC @F2@\r\n2 PEDI adopted\r\n"
        ."0 @F1@ FAM\r\n1 HUSB @I1@\r\n1 WIFE @I2@\r\n1 MARR\r\n2 DATE 1912\r\n2 PLAC Lahore, Punjab, India\r\n1 DIV\r\n2 DATE 1925\r\n1 CHIL @I4@\r\n1 CHIL @I5@\r\n"
        ."0 @F2@ FAM\r\n1 HUSB @I1@\r\n1 WIFE @I3@\r\n1 MARR\r\n2 DATE ABT 1928\r\n1 CHIL @I6@\r\n"
        ."0 TRLR\r\n";

    private function makeFamily(): Family
    {
        $user = User::factory()->create();

        return Family::create(['name' => 'Test', 'slug' => 'test-'.uniqid(), 'owner_id' => $user->id, 'plan' => 'heritage']);
    }

    public function test_it_imports_a_gedcom_file(): void
    {
        $family = $this->makeFamily();
        $stats = (new GedcomImporter($family, $family->owner_id))->import(self::SAMPLE);

        $this->assertSame(6, $stats['people']);
        $this->assertSame(2, $stats['partnerships']);
        $this->assertSame(6, $stats['links']); // 2 kids × 2 parents + adopted kid × 2 parents
        $this->assertSame([], $stats['warnings']);

        $ram = Person::where('family_id', $family->id)->where('gedcom_id', '@I1@')->firstOrFail();
        $this->assertSame('Ram Prasad', $ram->given_name);
        $this->assertSame('Sharma', $ram->surname);
        $this->assertSame('male', $ram->gender);
        $this->assertSame(['1890', 'about'], [$ram->birth_date, $ram->birth_qualifier]);
        $this->assertSame('1962-03-15', $ram->death_date);
        $this->assertFalse($ram->is_living);
        $this->assertSame('Teacher', $ram->occupation);
        $this->assertSame("First line of his story continued here.\nSecond paragraph.", $ram->biography);
        $this->assertSame('Lahore, Punjab, India', $ram->birthPlace->name);
        $alt = $ram->names()->sole();
        $this->assertSame(['राम प्रसाद शर्मा', 'alternate', 'hi'], [$alt->full_name, $alt->type, $alt->language]);

        $byXref = fn ($x) => Person::where('family_id', $family->id)->where('gedcom_id', $x)->value('id');
        $this->assertSame('1895-03', Person::find($byXref('@I2@'))->birth_date);
        $kamla = Person::find($byXref('@I3@'));
        $this->assertSame(['1900', 'about'], [$kamla->birth_date, $kamla->birth_qualifier]);
        $ravi = Person::find($byXref('@I6@'));
        $this->assertSame(['1930', 'after'], [$ravi->birth_date, $ravi->birth_qualifier]);
        $this->assertSame('1915-01-02', Person::find($byXref('@I4@'))->birth_date);

        $first = Partnership::where('family_id', $family->id)->where('partner2_id', $byXref('@I2@'))->sole();
        $this->assertSame([$ram->id, 'marriage', 'divorced', '1912', '1925'],
            [$first->partner1_id, $first->type, $first->status, $first->start_date, $first->end_date]);
        $this->assertNotNull($first->place_id);
        $second = Partnership::where('family_id', $family->id)->where('partner2_id', $byXref('@I3@'))->sole();
        $this->assertSame(['current', '1928'], [$second->status, $second->start_date]);

        $adopted = ParentChild::where('child_id', $ravi->id)->get();
        $this->assertCount(2, $adopted);
        $this->assertSame(['adoptive'], $adopted->pluck('type')->unique()->values()->all());
        $this->assertSame([$second->id], $adopted->pluck('partnership_id')->unique()->values()->all());
        $this->assertSame('biological', ParentChild::where('child_id', $byXref('@I4@'))->value('type'));
        $this->assertSame(2, ParentChild::where('partnership_id', $first->id)->where('parent_id', $ram->id)->count());

        $logs = ActivityLog::where('family_id', $family->id)->get();
        $this->assertCount(1, $logs);
        $this->assertSame('imported', $logs->first()->action);
        $this->assertSame('Imported GEDCOM: 6 people', $logs->first()->description);
    }

    public function test_it_round_trips_through_export(): void
    {
        $source = $this->makeFamily();
        (new GedcomImporter($source))->import(self::SAMPLE);

        // A loose parent link (no partnership) and a step link must survive too.
        $mohan = Person::where('family_id', $source->id)->where('gedcom_id', '@I4@')->first();
        $grandchild = Person::create(['family_id' => $source->id, 'given_name' => 'Asha', 'surname' => 'Sharma', 'gender' => 'female', 'birth_date' => '1950']);
        ParentChild::create(['family_id' => $source->id, 'parent_id' => $mohan->id, 'child_id' => $grandchild->id, 'type' => 'biological']);
        $kamla = Person::where('family_id', $source->id)->where('gedcom_id', '@I3@')->first();
        $gita = Person::where('family_id', $source->id)->where('gedcom_id', '@I5@')->first();
        ParentChild::create(['family_id' => $source->id, 'parent_id' => $kamla->id, 'child_id' => $gita->id, 'type' => 'step']);
        $ram = Person::where('family_id', $source->id)->where('gedcom_id', '@I1@')->first();
        $ram->update(['biography' => trim(str_repeat('A long biography sentence. ', 30))."\nSecond line."]);

        $gedcom = (new GedcomExporter($source))->export();

        $this->assertStringStartsWith("0 HEAD\r\n1 SOUR HEIRLOOM", $gedcom);
        $this->assertStringContainsString('1 CHAR UTF-8', $gedcom);
        $this->assertStringContainsString('2 VERS 5.5.1', $gedcom);
        $this->assertStringContainsString('2 DATE ABT 1890', $gedcom);
        $this->assertStringContainsString('2 DATE 15 MAR 1962', $gedcom);
        $this->assertStringContainsString('2 LANG Hindi', $gedcom);
        $this->assertStringContainsString('2 PEDI adopted', $gedcom);
        $this->assertStringContainsString("1 DIV\r\n2 DATE 1925", $gedcom);
        $this->assertStringEndsWith("0 TRLR\r\n", $gedcom);
        foreach (explode("\r\n", $gedcom) as $line) {
            $this->assertLessThanOrEqual(255, mb_strlen($line));
        }

        $target = $this->makeFamily();
        $stats = (new GedcomImporter($target))->import($gedcom);

        $count = fn (Family $f, string $model) => $model::where('family_id', $f->id)->count();
        $this->assertSame($count($source, Person::class), $stats['people']);
        $this->assertSame($count($source, Partnership::class), $stats['partnerships']);
        $this->assertSame($count($source, ParentChild::class), $stats['links']);
        $this->assertSame($count($source, ParentChild::class), $count($target, ParentChild::class));
        $this->assertSame([], $stats['warnings']);

        $types = fn (Family $f) => ParentChild::where('family_id', $f->id)->pluck('type')->countBy()->sort()->all();
        $this->assertSame($types($source), $types($target));
        $this->assertSame(
            Partnership::where('family_id', $source->id)->orderBy('id')->get(['type', 'status', 'start_date', 'end_date'])->toArray(),
            Partnership::where('family_id', $target->id)->orderBy('id')->get(['type', 'status', 'start_date', 'end_date'])->toArray(),
        );
        $ram2 = Person::where('family_id', $target->id)->where('given_name', 'Ram Prasad')->first();
        $this->assertSame(trim($ram->fresh()->biography), $ram2->biography);
        $this->assertSame('hi', $ram2->names()->value('language'));
    }

    public function test_it_respects_the_plan_people_limit(): void
    {
        $family = $this->makeFamily();
        $family->update(['plan' => 'free']);
        config(['plans.free.people' => 3, 'services.heritage.billing' => true]);

        $this->expectException(\RuntimeException::class);
        (new GedcomImporter($family))->import(self::SAMPLE);
    }
}
