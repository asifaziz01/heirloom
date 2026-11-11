<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\ChangeRequest;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\Place;
use App\Models\User;
use App\Services\FamilyGraph;
use App\Services\PlaceResolver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * A three-country family that exercises the relationship engine:
 * a widower who remarries, a divorce and remarriage, an adopted child,
 * a stepson, half-siblings, conflicting birth dates and multilingual names.
 *
 * Sign in: owner@heirloom.test or +1 408 555 0101 / password (owner), zara@heirloom.test or +1 416 555 0102 / password (contributor)
 */
class DemoFamilySeeder extends Seeder
{
    public function run(): void
    {
        ActivityLog::quietly(fn () => $this->seed());
    }

    private function seed(): void
    {
        $owner = User::firstOrCreate(['email' => 'owner@heirloom.test'], ['name' => 'Arman Rahman', 'phone' => '+14085550101', 'password' => 'password']);
        $zaraUser = User::firstOrCreate(['email' => 'zara@heirloom.test'], ['name' => 'Zara Siddiqui', 'phone' => '+14165550102', 'password' => 'password']);

        $family = Family::create([
            'name' => 'Rahman Family',
            'slug' => 'rahman-family-'.substr(md5((string) microtime(true)), 0, 5),
            'description' => 'From Gorakhpur and Lucknow to London, Dubai, Toronto and California. Collected so our children know where they come from.',
            'owner_id' => $owner->id,
            'plan' => 'heritage',
        ]);
        $g = FamilyGraph::for($family);

        foreach ([
            ['Gorakhpur, India', 'Gorakhpur', 'India', 26.7606, 83.3732],
            ['Lucknow, India', 'Lucknow', 'India', 26.8467, 80.9462],
            ['London, United Kingdom', 'London', 'United Kingdom', 51.5072, -0.1276],
            ['Dubai, UAE', 'Dubai', 'UAE', 25.2048, 55.2708],
            ['San Jose, USA', 'San Jose', 'USA', 37.3382, -121.8863],
            ['Toronto, Canada', 'Toronto', 'Canada', 43.6532, -79.3832],
        ] as [$name, $city, $country, $lat, $lng]) {
            Place::create(['family_id' => $family->id, 'name' => $name, 'city' => $city, 'country' => $country, 'latitude' => $lat, 'longitude' => $lng]);
        }

        $p = fn (array $a) => $g->createPerson($a + ['is_living' => true, 'gender' => 'unknown'], null, $owner->id);

        // Generation 1
        $abdul = $p(['given_name' => 'Abdul', 'surname' => 'Rahman', 'gender' => 'male', 'birth_date' => '1932', 'birth_qualifier' => 'about', 'birth_place' => 'Gorakhpur, India',
            'death_date' => '2008-01-19', 'death_place' => 'Lucknow, India', 'occupation' => 'Schoolteacher', 'education' => 'B.A., Gorakhpur University',
            'biography' => "Abdul Rahman taught mathematics and Urdu at a government school for thirty-four years. Former students still visit the family in Lucknow.\n\nHe was widowed young, remarried, and raised five children, including Rizwan, whom he and Ameena adopted in 1971.",
            'names' => [['full_name' => 'मोहम्मद अब्दुल रहमान', 'language' => 'hi', 'type' => 'transliteration'], ['full_name' => 'محمد عبد الرحمن', 'language' => 'ur', 'type' => 'transliteration']]]);
        $zainab = $p(['given_name' => 'Zainab', 'surname' => 'Begum', 'gender' => 'female', 'birth_date' => '1935', 'birth_qualifier' => 'about', 'birth_place' => 'Gorakhpur, India', 'death_date' => '1961-08-02', 'death_place' => 'Gorakhpur, India']);
        $ameena = $p(['given_name' => 'Ameena', 'surname' => 'Khatoon', 'gender' => 'female', 'birth_date' => '1940-11-12', 'birth_place' => 'Lucknow, India', 'death_date' => '2019-03-04', 'death_place' => 'Lucknow, India', 'occupation' => 'Homemaker, later literacy volunteer',
            'names' => [['full_name' => 'آمنہ خاتون', 'language' => 'ur', 'type' => 'transliteration']]]);
        $u1 = $g->createPartnership($abdul->id, $zainab->id, ['type' => 'marriage', 'status' => 'widowed', 'start_date' => '1953', 'end_date' => '1961-08-02', 'place' => 'Gorakhpur, India']);
        $u2 = $g->createPartnership($abdul->id, $ameena->id, ['type' => 'marriage', 'status' => 'current', 'start_date' => '1963-'.now()->addDays(12)->format('m-d'), 'place' => 'Lucknow, India']);

        // Generation 2
        $yusuf = $p(['given_name' => 'Yusuf', 'surname' => 'Rahman', 'gender' => 'male', 'birth_date' => '1955-06-21', 'birth_place' => 'Gorakhpur, India', 'occupation' => 'Civil engineer']);
        $fatima = $p(['given_name' => 'Fatima', 'surname' => 'Siddiqui', 'gender' => 'female', 'birth_date' => '1958-02-09', 'birth_place' => 'Gorakhpur, India', 'occupation' => 'Pharmacist']);
        $imran = $p(['given_name' => 'Imran', 'surname' => 'Rahman', 'gender' => 'male', 'birth_date' => '1965-'.now()->addDays(5)->format('m-d'), 'birth_place' => 'Lucknow, India', 'occupation' => 'Software architect']);
        $sana = $p(['given_name' => 'Sana', 'surname' => 'Rahman', 'gender' => 'female', 'birth_date' => '1968-09-30', 'birth_place' => 'Lucknow, India', 'occupation' => 'Professor of history']);
        $rizwan = $p(['given_name' => 'Rizwan', 'surname' => 'Rahman', 'gender' => 'male', 'birth_date' => '1970', 'birth_place' => 'Lucknow, India', 'occupation' => 'Shop owner']);
        foreach ([$yusuf, $fatima] as $c) {
            $g->linkChild($abdul->id, $c->id, 'biological', $u1->id);
        }
        foreach ([$imran, $sana] as $c) {
            $g->linkChild($abdul->id, $c->id, 'biological', $u2->id);
        }
        $g->linkChild($abdul->id, $rizwan->id, 'adoptive', $u2->id);
        // Ameena raised Yusuf and Fatima as her own: a step link (not kinship)
        $g->linkParent($ameena->id, $yusuf->id, 'step');
        $g->linkParent($ameena->id, $fatima->id, 'step');

        // Yusuf: divorce, then remarriage in London to Claire, who had a son
        $nusrat = $p(['given_name' => 'Nusrat', 'surname' => 'Jahan', 'gender' => 'female', 'birth_date' => '1958', 'birth_place' => 'Lucknow, India']);
        $claire = $p(['given_name' => 'Claire', 'surname' => 'Thompson', 'gender' => 'female', 'birth_date' => '1962-04-03', 'birth_place' => 'London, United Kingdom', 'occupation' => 'Nurse']);
        $u3 = $g->createPartnership($yusuf->id, $nusrat->id, ['type' => 'marriage', 'status' => 'divorced', 'start_date' => '1978', 'end_date' => '1985']);
        $u4 = $g->createPartnership($yusuf->id, $claire->id, ['type' => 'marriage', 'status' => 'current', 'start_date' => '1988-07-16', 'place' => 'London, United Kingdom']);
        $ayesha = $p(['given_name' => 'Ayesha', 'surname' => 'Rahman', 'gender' => 'female', 'birth_date' => '1980-12-01', 'birth_place' => 'Lucknow, India', 'occupation' => 'Doctor']);
        $daniel = $p(['given_name' => 'Daniel', 'surname' => 'Rahman', 'gender' => 'male', 'birth_date' => '1990-'.now()->addDays(20)->format('m-d'), 'birth_place' => 'London, United Kingdom']);
        $oliver = $p(['given_name' => 'Oliver', 'surname' => 'Thompson', 'gender' => 'male', 'birth_date' => '1984-05-14', 'birth_place' => 'London, United Kingdom']);
        $g->linkChild($yusuf->id, $ayesha->id, 'biological', $u3->id);
        $g->linkChild($yusuf->id, $daniel->id, 'biological', $u4->id);
        $g->linkParent($claire->id, $oliver->id, 'biological');
        $g->linkParent($yusuf->id, $oliver->id, 'step');

        // Fatima & Tariq move to Dubai; their daughter Zara to Toronto
        $tariq = $p(['given_name' => 'Tariq', 'surname' => 'Siddiqui', 'gender' => 'male', 'birth_date' => '1954', 'birth_place' => 'Lucknow, India', 'occupation' => 'Accountant']);
        $u5 = $g->createPartnership($fatima->id, $tariq->id, ['type' => 'marriage', 'start_date' => '1982-02-14', 'place' => 'Lucknow, India']);
        $zara = $p(['given_name' => 'Zara', 'surname' => 'Siddiqui', 'gender' => 'female', 'birth_date' => '1987-10-22', 'birth_place' => 'Dubai, UAE', 'occupation' => 'Architect']);
        $g->linkChild($fatima->id, $zara->id, 'biological', $u5->id);

        // Imran & Neha to California
        $neha = $p(['given_name' => 'Neha', 'surname' => 'Rahman', 'gender' => 'female', 'birth_date' => '1968-01-25', 'birth_place' => 'Lucknow, India', 'occupation' => 'Teacher']);
        $u6 = $g->createPartnership($imran->id, $neha->id, ['type' => 'marriage', 'start_date' => '1993-11-28', 'place' => 'Lucknow, India']);
        $arman = $p(['given_name' => 'Arman', 'surname' => 'Rahman', 'gender' => 'male', 'birth_date' => '1995-03-17', 'birth_place' => 'Lucknow, India', 'occupation' => 'Product designer']);
        $inaya = $p(['given_name' => 'Inaya', 'surname' => 'Rahman', 'gender' => 'female', 'birth_date' => '2001-'.now()->addDays(2)->format('m-d'), 'birth_place' => 'San Jose, USA']);
        foreach ([$arman, $inaya] as $c) {
            $g->linkChild($imran->id, $c->id, 'biological', $u6->id);
        }

        // Rizwan stays in Lucknow
        $huma = $p(['given_name' => 'Huma', 'surname' => 'Rahman', 'gender' => 'female', 'birth_date' => '1973', 'birth_place' => 'Lucknow, India']);
        $u7 = $g->createPartnership($rizwan->id, $huma->id, ['type' => 'marriage', 'start_date' => '1997']);
        $samir = $p(['given_name' => 'Samir', 'surname' => 'Rahman', 'gender' => 'male', 'birth_date' => '2000-08-08', 'birth_place' => 'Lucknow, India']);
        $g->linkChild($rizwan->id, $samir->id, 'biological', $u7->id);

        // Members
        FamilyMember::create(['family_id' => $family->id, 'user_id' => $owner->id, 'role' => 'owner', 'person_id' => $arman->id]);
        FamilyMember::create(['family_id' => $family->id, 'user_id' => $zaraUser->id, 'role' => 'contributor', 'person_id' => $zara->id]);
        $owner->update(['current_family_id' => $family->id]);
        $zaraUser->update(['current_family_id' => $family->id]);

        // Conflicting, sourced facts
        $g->addFact($abdul->id, ['field' => 'birth_date', 'value' => '1932', 'source_title' => 'School service record', 'source_type' => 'record', 'confidence' => 'high', 'status' => 'verified', 'is_preferred' => true], $owner->id);
        $g->addFact($abdul->id, ['field' => 'birth_date', 'value' => '1931', 'source_title' => 'Sana’s recollection', 'source_type' => 'recollection', 'confidence' => 'low', 'status' => 'disputed', 'notes' => 'Sana remembers Dada saying he was born the year of the big flood.'], $owner->id);
        $g->addFact($ameena->id, ['field' => 'birth_date', 'value' => '1940-11-12', 'source_title' => 'Passport (1985)', 'source_type' => 'document', 'confidence' => 'high', 'status' => 'verified', 'is_preferred' => true], $owner->id);

        // Life events & migrations
        $strip = fn ($a) => collect($a)->except(['place', 'from_place'])->all() + ['place_id' => PlaceResolver::resolve($family, $a['place'] ?? null), 'from_place_id' => PlaceResolver::resolve($family, $a['from_place'] ?? null)];
        foreach ([
            [['type' => 'migration', 'title' => 'Family moves to Lucknow', 'date' => '1972', 'place' => 'Lucknow, India', 'from_place' => 'Gorakhpur, India', 'description' => 'Abdul took a teaching post at a Lucknow school; the family travelled by overnight train.'], [$abdul, $ameena, $yusuf, $fatima, $imran, $sana, $rizwan]],
            [['type' => 'migration', 'title' => 'Fatima and Tariq move to Dubai', 'date' => '1985', 'place' => 'Dubai, UAE', 'from_place' => 'Lucknow, India'], [$fatima, $tariq]],
            [['type' => 'migration', 'title' => 'Yusuf moves to London', 'date' => '1986', 'place' => 'London, United Kingdom', 'from_place' => 'Lucknow, India'], [$yusuf]],
            [['type' => 'migration', 'title' => 'Imran’s family moves to California', 'date' => '1998-08', 'place' => 'San Jose, USA', 'from_place' => 'Lucknow, India'], [$imran, $neha, $arman]],
            [['type' => 'migration', 'title' => 'Zara moves to Toronto', 'date' => '2012', 'place' => 'Toronto, Canada', 'from_place' => 'Dubai, UAE'], [$zara]],
            [['type' => 'occupation', 'title' => 'Abdul retires after 34 years of teaching', 'date' => '1992', 'place' => 'Lucknow, India'], [$abdul]],
            [['type' => 'award', 'title' => 'Ameena honoured for adult-literacy volunteering', 'date' => '2004', 'place' => 'Lucknow, India'], [$ameena]],
        ] as [$a, $people]) {
            $e = $family->events()->create($strip($a) + ['created_by' => $owner->id]);
            $e->people()->sync(collect($people)->pluck('id'));
        }

        // Occasions: what the family is looking forward to (and remembers every year)
        foreach ([
            [['type' => 'wedding', 'title' => 'Arman & Hiba’s wedding', 'date' => now()->addDays(45)->toDateString(), 'end_date' => now()->addDays(47)->toDateString(), 'place' => 'Lucknow, India', 'venue' => 'Rahman house, Gomti Nagar', 'description' => "Mehndi on the first evening, nikah and walima on the following days. Relatives from abroad: let us know your flights and we will arrange pick-ups.\n\nDress code for the walima: traditional."], [$arman]],
            [['type' => 'memorial', 'title' => 'Dada Abdul’s barsi', 'date' => '2009-01-19', 'repeats_yearly' => true, 'place' => 'Lucknow, India', 'venue' => 'Family home, after Asr', 'rsvp_enabled' => false, 'description' => 'Quran khwani at home followed by a meal for the neighbourhood, as every year.'], [$abdul]],
            [['type' => 'reunion', 'title' => 'Rahman family summer get-together', 'date' => now()->addDays(120)->toDateString(), 'time' => '12:00', 'place' => 'Toronto, Canada', 'venue' => 'High Park, picnic area 7', 'description' => 'Bring a dish from wherever you live now.'], []],
        ] as [$a, $people]) {
            $o = $family->occasions()->create(collect($a)->except('place')->all() + ['place_id' => PlaceResolver::resolve($family, $a['place']), 'created_by' => $owner->id]);
            $o->people()->sync(collect($people)->pluck('id'));
        }
        $family->occasions()->where('type', 'wedding')->first()->rsvps()->create(['user_id' => $zaraUser->id, 'status' => 'going', 'guests' => 1, 'note' => 'Landing Friday morning']);

        // Stories & questions
        $s1 = $family->stories()->create([
            'title' => 'The overnight train to Lucknow',
            'body' => "Dadi always said the move to Lucknow began with a tin trunk and a borrowed bedroll.\n\nIt was the winter of 1972. Dada had been offered a post at a bigger school, and he decided in one evening that the family would go. Yusuf chacha was seventeen and sulked the whole way because he had to leave his cricket team. Sana phuphi was four and slept on top of the trunk.\n\nAt Charbagh station, Dada bought everyone hot jalebis before they had even found a rickshaw. \"A new city should begin with something sweet,\" he said. We still say it whenever someone in the family moves house.",
            'told_by_person_id' => $sana->id,
            'story_date' => '1972',
            'place_id' => PlaceResolver::resolve($family, 'Lucknow, India'),
            'created_by' => $owner->id,
        ]);
        $s1->people()->sync([$abdul->id, $ameena->id, $yusuf->id, $sana->id]);
        $s2 = $family->stories()->create([
            'title' => 'How Ameena learned to read',
            'body' => "Ameena Khatoon never went to school. After her own children started school, she sat with them every evening and learned the alphabet alongside them, first Urdu, then Hindi.\n\nBy the 1990s she was teaching women in the neighbourhood to sign their names. When she was honoured in 2004 she told the audience, \"My children were my teachers.\"",
            'told_by_person_id' => $imran->id,
            'created_by' => $owner->id,
        ]);
        $s2->people()->sync([$ameena->id, $imran->id]);
        foreach ([
            ['What was Zainab Begum like? Does anyone have a photograph of her?', $zainab->id],
            ['Which village did Dada’s family come from before Gorakhpur?', $abdul->id],
            ['What was the name of the school where Dada taught in Lucknow?', $abdul->id],
        ] as [$q, $about]) {
            $family->questions()->create(['question' => $q, 'about_person_id' => $about, 'asked_by' => $owner->id]);
        }

        // Photos (generated sepia placeholders so the gallery has content)
        foreach ([['Wedding of Abdul and Ameena', '1963', [$abdul, $ameena]], ['Family at Charbagh station', '1972', [$abdul, $ameena, $sana, $imran]], ['Yusuf and Claire, London', '1988', [$yusuf, $claire]], ['Eid in San Jose', '2005', [$imran, $neha, $arman, $inaya]]] as $i => [$title, $year, $people]) {
            if (! function_exists('imagecreatetruecolor')) {
                break;
            }
            $img = imagecreatetruecolor(800, 560);
            imagefill($img, 0, 0, imagecolorallocate($img, 196 - $i * 8, 170 - $i * 6, 130 - $i * 4));
            imagefilledrectangle($img, 30, 30, 770, 530, imagecolorallocate($img, 222, 204, 170));
            imagestring($img, 5, 60, 260, $title.' ('.$year.')', imagecolorallocate($img, 70, 50, 30));
            ob_start();
            imagejpeg($img, null, 85);
            $bytes = ob_get_clean();
            $path = "families/{$family->id}/demo-{$i}.jpg";
            Storage::disk('local')->put($path, $bytes);
            $m = $family->media()->create(['kind' => 'photo', 'title' => $title, 'path' => $path, 'original_name' => "demo-{$i}.jpg", 'mime' => 'image/jpeg', 'size' => strlen($bytes), 'taken_date' => $year, 'uploaded_by' => $owner->id]);
            $m->people()->sync(collect($people)->pluck('id'));
            $family->increment('storage_used', strlen($bytes));
            if ($i === 0) {
                $abdul->update(['photo_media_id' => $m->id]);
            }
        }

        // A contribution from Zara waiting for review
        ChangeRequest::create([
            'family_id' => $family->id,
            'user_id' => $zaraUser->id,
            'action' => 'person.create',
            'summary' => 'Add Maryam Siddiqui as child of Zara Siddiqui',
            'payload' => ['person' => ['given_name' => 'Maryam', 'surname' => 'Siddiqui', 'gender' => 'female', 'birth_date' => '2021-06-02', 'birth_place' => 'Toronto, Canada', 'is_living' => true], 'relation' => ['type' => 'child', 'person_id' => $zara->id]],
        ]);
    }
}
