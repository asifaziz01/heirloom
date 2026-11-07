<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\ChangeRequest;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\Invitation;
use App\Models\Media;
use App\Models\Person;
use App\Models\Place;
use App\Models\Source;
use App\Models\User;
use App\Services\FamilyGraph;
use App\Services\PlaceResolver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Richer sample data for manual testing. Builds on the Rahman family created by
 * DemoFamilySeeder and fills every area the demo leaves empty: a fourth generation,
 * every member role, albums, documents with transcriptions, an audio recording,
 * interviews in each state, answered questions, invitations, a review queue with
 * history, an audit trail, more sourced/conflicting facts, and a second family so
 * family switching can be tested.
 *
 * Extra logins (all with password "password"; the mobile number works in place of the email):
 *   imran@heirloom.test  / +1 408 555 0103   admin        (Imran Rahman)
 *   sana@heirloom.test   / +91 98765 50104   editor       (Sana Rahman)
 *   oliver@heirloom.test / +44 7700 900105   viewer       (Oliver Thompson)
 *   claire@heirloom.test / +44 7700 900106   owner of the Thompson family, viewer in the Rahman family
 *
 * Safe to run once on top of the demo data: php artisan db:seed --class=SampleDataSeeder
 */
class SampleDataSeeder extends Seeder
{
    private Family $family;

    private FamilyGraph $g;

    private User $owner;

    /** @var array<string, Person> */
    private array $people = [];

    public function run(): void
    {
        $this->owner = User::where('email', 'owner@heirloom.test')->firstOrFail();
        $this->family = Family::where('owner_id', $this->owner->id)->where('name', 'Rahman Family')->firstOrFail();

        if ($this->family->persons()->where('given_name', 'Ismail')->exists()) {
            $this->command?->warn('SampleDataSeeder already ran for the Rahman family; skipping.');

            return;
        }

        $this->g = FamilyGraph::for($this->family);
        $this->loadDemoPeople();

        ActivityLog::quietly(function () {
            $this->users();
            $this->fourthGeneration();
            $this->moreDescendants();
            $this->places();
            $this->events();
            $this->factsAndSources();
            $this->albumsAndMedia();
            $this->stories();
            $this->questions();
            $this->interviews();
            $this->invitations();
            $this->reviewQueue();
            $this->secondFamily();
        });
        $this->activityTrail();
    }

    private function loadDemoPeople(): void
    {
        foreach ($this->family->persons()->get() as $p) {
            $this->people[Str::lower($p->given_name)] = $p;
        }
    }

    private function p(string $key): Person
    {
        return $this->people[$key];
    }

    private function create(string $key, array $attrs): Person
    {
        $person = $this->g->createPerson($attrs + ['is_living' => true, 'gender' => 'unknown'], null, $this->owner->id);
        $this->people[$key] = $person;

        return $person;
    }

    private function users(): void
    {
        $f = $this->family;
        $make = fn (string $email, string $name, string $phone) => User::firstOrCreate(['email' => $email], ['name' => $name, 'phone' => $phone, 'password' => 'password', 'current_family_id' => $f->id]);

        $imran = $make('imran@heirloom.test', 'Imran Rahman', '+14085550103');
        $sana = $make('sana@heirloom.test', 'Sana Rahman', '+919876550104');
        $oliver = $make('oliver@heirloom.test', 'Oliver Thompson', '+447700900105');
        $claire = $make('claire@heirloom.test', 'Claire Thompson', '+447700900106');

        FamilyMember::firstOrCreate(['family_id' => $f->id, 'user_id' => $imran->id], ['role' => 'admin', 'person_id' => $this->p('imran')->id]);
        FamilyMember::firstOrCreate(['family_id' => $f->id, 'user_id' => $sana->id], ['role' => 'editor', 'person_id' => $this->p('sana')->id]);
        FamilyMember::firstOrCreate(['family_id' => $f->id, 'user_id' => $oliver->id], ['role' => 'viewer', 'person_id' => $this->p('oliver')->id]);
        FamilyMember::firstOrCreate(['family_id' => $f->id, 'user_id' => $claire->id], ['role' => 'viewer', 'person_id' => $this->p('claire')->id]);
    }

    /** Abdul's, Zainab's and Ameena's parents: gives the pedigree chart four generations. */
    private function fourthGeneration(): void
    {
        $ismail = $this->create('ismail', ['given_name' => 'Ismail', 'surname' => 'Khan', 'nickname' => 'Haji Sahab', 'gender' => 'male', 'birth_date' => '1905', 'birth_qualifier' => 'about',
            'birth_place' => 'Basti, India', 'death_date' => '1968-04', 'death_place' => 'Gorakhpur, India', 'occupation' => 'Cloth merchant', 'religion' => 'Islam',
            'biography' => 'Haji Mohammad Ismail ran a cloth shop in the Gorakhpur bazaar. He performed Hajj in 1952, by ship from Bombay, and was known afterwards as Haji Sahab. He insisted that all his children, daughters included, finish school.',
            'names' => [['full_name' => 'حاجی محمد اسماعیل', 'language' => 'ur', 'type' => 'transliteration'], ['full_name' => 'हाजी मोहम्मद इस्माइल', 'language' => 'hi', 'type' => 'transliteration']]]);
        $rukhsana = $this->create('rukhsana', ['given_name' => 'Rukhsana', 'surname' => 'Bibi', 'gender' => 'female', 'birth_date' => '1910', 'birth_qualifier' => 'about', 'birth_place' => 'Basti, India',
            'death_date' => '1979', 'death_qualifier' => 'about', 'death_place' => 'Gorakhpur, India', 'occupation' => 'Homemaker']);
        $u = $this->g->createPartnership($ismail->id, $rukhsana->id, ['type' => 'marriage', 'status' => 'widowed', 'start_date' => '1927', 'start_qualifier' => 'about', 'place' => 'Basti, India']);
        $this->g->linkChild($ismail->id, $this->p('abdul')->id, 'biological', $u->id);

        // Abdul's siblings
        $farida = $this->create('farida', ['given_name' => 'Farida', 'surname' => 'Khatoon', 'gender' => 'female', 'birth_date' => '1929', 'birth_place' => 'Gorakhpur, India', 'death_date' => '2001', 'death_place' => 'Karachi, Pakistan']);
        $hamid = $this->create('hamid', ['given_name' => 'Hamid', 'surname' => 'Khan', 'gender' => 'male', 'birth_date' => '1936-02-11', 'birth_place' => 'Gorakhpur, India', 'death_date' => '2015-12-30', 'death_place' => 'Gorakhpur, India', 'occupation' => 'Cloth merchant (took over the shop)']);
        foreach ([$farida, $hamid] as $c) {
            $this->g->linkChild($ismail->id, $c->id, 'biological', $u->id);
        }

        // Zainab's parents (only names known; dates unknown)
        $karim = $this->create('karim', ['given_name' => 'Karim', 'surname' => 'Bakhsh', 'gender' => 'male', 'is_living' => false, 'birth_place' => 'Gorakhpur, India', 'occupation' => 'Farmer']);
        $zainabMother = $this->create('amna', ['given_name' => 'Amna', 'gender' => 'female', 'is_living' => false]);
        $u2 = $this->g->createPartnership($karim->id, $zainabMother->id, ['type' => 'marriage', 'status' => 'ended']);
        $this->g->linkChild($karim->id, $this->p('zainab')->id, 'biological', $u2->id);

        // Ameena's parents
        $sadiq = $this->create('sadiq', ['given_name' => 'Sadiq', 'surname' => 'Ali', 'gender' => 'male', 'birth_date' => '1912', 'birth_qualifier' => 'about', 'birth_place' => 'Lucknow, India', 'death_date' => '1975', 'death_place' => 'Lucknow, India', 'occupation' => 'Chikan embroidery trader']);
        $noor = $this->create('noor', ['given_name' => 'Noor', 'surname' => 'Jahan', 'gender' => 'female', 'birth_date' => '1918', 'birth_qualifier' => 'about', 'birth_place' => 'Lucknow, India', 'death_date' => '1996-06-15', 'death_place' => 'Lucknow, India']);
        $u3 = $this->g->createPartnership($sadiq->id, $noor->id, ['type' => 'marriage', 'status' => 'widowed', 'start_date' => '1936']);
        $this->g->linkChild($sadiq->id, $this->p('ameena')->id, 'biological', $u3->id);

        // Farida's branch moved to Karachi at Partition, kept in a named branch
        $farida->update(['branch' => 'Karachi branch']);
        $akbar = $this->create('akbar', ['given_name' => 'Akbar', 'surname' => 'Hussain', 'gender' => 'male', 'birth_date' => '1925', 'birth_qualifier' => 'about', 'birth_place' => 'Gorakhpur, India', 'death_date' => '1990', 'death_qualifier' => 'about', 'death_place' => 'Karachi, Pakistan', 'branch' => 'Karachi branch']);
        $u4 = $this->g->createPartnership($akbar->id, $farida->id, ['type' => 'marriage', 'status' => 'widowed', 'start_date' => '1946']);
        $shahid = $this->create('shahid', ['given_name' => 'Shahid', 'surname' => 'Hussain', 'gender' => 'male', 'birth_date' => '1950-03-03', 'birth_place' => 'Karachi, Pakistan', 'occupation' => 'Bank manager (retired)', 'branch' => 'Karachi branch']);
        $this->g->linkChild($akbar->id, $shahid->id, 'biological', $u4->id);
    }

    /** Fills in the younger generation: Ayesha's family, Daniel's partner, Zara's husband, Samir, and Hamid's line. */
    private function moreDescendants(): void
    {
        // Ayesha (b. 1980, doctor) married in Lucknow, two children, one born this month for the dashboard
        $bilal = $this->create('bilal', ['given_name' => 'Bilal', 'surname' => 'Ahmed', 'gender' => 'male', 'birth_date' => '1978-11-05', 'birth_place' => 'Lucknow, India', 'occupation' => 'Surgeon']);
        $u = $this->g->createPartnership($this->p('ayesha')->id, $bilal->id, ['type' => 'marriage', 'status' => 'current', 'start_date' => '2007-12-20', 'place' => 'Lucknow, India']);
        $ahmad = $this->create('ahmad', ['given_name' => 'Ahmad', 'surname' => 'Ahmed', 'gender' => 'male', 'birth_date' => '2010-'.now()->addDays(9)->format('m-d'), 'birth_place' => 'Lucknow, India']);
        $laila = $this->create('laila', ['given_name' => 'Laila', 'surname' => 'Ahmed', 'gender' => 'female', 'birth_date' => '2014-02-28', 'birth_place' => 'Lucknow, India']);
        foreach ([$ahmad, $laila] as $c) {
            $this->g->linkChild($this->p('ayesha')->id, $c->id, 'biological', $u->id);
        }

        // Daniel: unmarried partnership in London
        $priya = $this->create('priya', ['given_name' => 'Priya', 'surname' => 'Nair', 'gender' => 'female', 'birth_date' => '1991-07-07', 'birth_place' => 'London, United Kingdom', 'occupation' => 'Barrister']);
        $this->g->createPartnership($this->p('daniel')->id, $priya->id, ['type' => 'partnership', 'status' => 'current', 'start_date' => '2019']);

        // Oliver (Claire's son, Yusuf's stepson) married with a daughter
        $emma = $this->create('emma', ['given_name' => 'Emma', 'surname' => 'Thompson', 'gender' => 'female', 'birth_date' => '1985-09-12', 'birth_place' => 'Manchester, United Kingdom']);
        $u = $this->g->createPartnership($this->p('oliver')->id, $emma->id, ['type' => 'marriage', 'status' => 'current', 'start_date' => '2011-06-04', 'place' => 'London, United Kingdom']);
        $sophie = $this->create('sophie', ['given_name' => 'Sophie', 'surname' => 'Thompson', 'gender' => 'female', 'birth_date' => '2015-01-20', 'birth_place' => 'London, United Kingdom']);
        $this->g->linkChild($this->p('oliver')->id, $sophie->id, 'biological', $u->id);

        // Zara married in Toronto (Maryam is still waiting in the review queue as a change request)
        $omar = $this->create('omar', ['given_name' => 'Omar', 'surname' => 'Haddad', 'gender' => 'male', 'birth_date' => '1985-04-18', 'birth_place' => 'Beirut, Lebanon', 'occupation' => 'Civil engineer']);
        $this->g->createPartnership($this->p('zara')->id, $omar->id, ['type' => 'marriage', 'status' => 'current', 'start_date' => '2017-08-26', 'place' => 'Toronto, Canada']);

        // Hamid's line stays in Gorakhpur: cousins for the relationship calculator
        $hamid = $this->p('hamid');
        $shabnam = $this->create('shabnam', ['given_name' => 'Shabnam', 'surname' => 'Khan', 'gender' => 'female', 'birth_date' => '1940', 'birth_qualifier' => 'about', 'birth_place' => 'Gorakhpur, India']);
        $u = $this->g->createPartnership($hamid->id, $shabnam->id, ['type' => 'marriage', 'status' => 'widowed', 'start_date' => '1960']);
        $faisal = $this->create('faisal', ['given_name' => 'Faisal', 'surname' => 'Khan', 'gender' => 'male', 'birth_date' => '1962-10-10', 'birth_place' => 'Gorakhpur, India', 'occupation' => 'Cloth merchant (third generation)']);
        $this->g->linkChild($hamid->id, $faisal->id, 'biological', $u->id);
        $nadia = $this->create('nadia', ['given_name' => 'Nadia', 'surname' => 'Khan', 'gender' => 'female', 'birth_date' => '1990-05-30', 'birth_place' => 'Gorakhpur, India', 'occupation' => 'Textile designer']);
        $this->g->linkParent($faisal->id, $nadia->id, 'biological');

        // A child whose other parent is unknown, and a foster link
        $unknownMotherChild = $this->create('bashir', ['given_name' => 'Bashir', 'gender' => 'male', 'birth_date' => '1958', 'birth_qualifier' => 'about', 'birth_place' => 'Gorakhpur, India', 'death_date' => '1959', 'death_qualifier' => 'about',
            'biography' => 'Died in infancy. Only his name is remembered; the family is not sure whether he was Zainab Begum’s or a cousin’s child raised in the house.']);
        $this->g->linkParent($this->p('abdul')->id, $unknownMotherChild->id, 'unknown');
        $this->g->linkParent($this->p('rukhsana')->id, $this->p('fatima')->id, 'foster');

        // Privacy: the ex-wife's record is admin-only
        $this->p('nusrat')->update(['privacy' => 'admins']);
    }

    private function places(): void
    {
        foreach ([
            ['Basti, India', 26.7948, 82.7330, 'Uttar Pradesh'],
            ['Karachi, Pakistan', 24.8607, 67.0011, 'Sindh'],
            ['Beirut, Lebanon', 33.8938, 35.5018, null],
            ['Manchester, United Kingdom', 53.4808, -2.2426, 'England'],
            ['Mecca, Saudi Arabia', 21.4225, 39.8262, null],
            ['Bombay, India', 18.9582, 72.8321, 'Maharashtra'],
            ['Aligarh, India', 27.8974, 78.0880, 'Uttar Pradesh'],
        ] as [$name, $lat, $lng, $region]) {
            $id = PlaceResolver::resolve($this->family, $name);
            Place::whereKey($id)->update(['latitude' => $lat, 'longitude' => $lng, 'region' => $region]);
        }
        Place::where('family_id', $this->family->id)->where('name', 'Lucknow, India')->update([
            'region' => 'Uttar Pradesh',
            'notes' => 'The family home from 1972 is in Aminabad, a ten-minute walk from Charbagh station. Rizwan and Huma still live there.',
        ]);
        Place::where('family_id', $this->family->id)->where('name', 'Gorakhpur, India')->update(['region' => 'Uttar Pradesh', 'notes' => 'Ismail’s cloth shop in the Golghar bazaar is still run by Faisal.']);
    }

    private function events(): void
    {
        $pl = fn (?string $n) => PlaceResolver::resolve($this->family, $n);
        $rows = [
            ['migration', 'Farida and Akbar leave for Karachi', '1947-10', 'about', 'Karachi, Pakistan', 'Gorakhpur, India', 'At Partition. The brothers stayed in India; the family did not meet again until 1974.', ['farida', 'akbar']],
            ['religious', 'Ismail performs Hajj', '1952', 'exact', 'Mecca, Saudi Arabia', 'Bombay, India', 'By ship from Bombay; the voyage took eleven days each way.', ['ismail']],
            ['education', 'Abdul enrols at Gorakhpur University', '1950', 'exact', 'Gorakhpur, India', null, 'B.A. in Mathematics and Urdu.', ['abdul']],
            ['occupation', 'Abdul’s first teaching post', '1954-07', 'exact', 'Gorakhpur, India', null, null, ['abdul']],
            ['residence', 'Family reunion in Karachi', '1974-12', 'exact', 'Karachi, Pakistan', null, 'Abdul and Hamid visited their sister Farida for the first time since Partition.', ['abdul', 'hamid', 'farida', 'shahid']],
            ['education', 'Sana completes her PhD at Aligarh', '1996', 'exact', 'Aligarh, India', null, 'Thesis on Awadhi court culture.', ['sana']],
            ['military', 'Tariq’s national service', '1972', 'about', 'Lucknow, India', null, null, ['tariq']],
            ['illness', 'Abdul’s heart operation', '2003-02', 'exact', 'Lucknow, India', null, 'Recovered fully; walked to the mosque within a month.', ['abdul', 'ameena']],
            ['celebration', 'Ameena’s 75th birthday', '2015-11-12', 'exact', 'Lucknow, India', null, 'Forty-one relatives from five countries. The last time everyone was in the same room.', ['ameena', 'yusuf', 'fatima', 'imran', 'sana', 'rizwan', 'arman', 'zara', 'ayesha']],
            ['residence', 'Daniel and Priya move in together', '2019', 'exact', 'London, United Kingdom', null, null, ['daniel', 'priya']],
            ['custom', 'Zara and Omar’s wedding', '2017-08-26', 'exact', 'Toronto, Canada', null, 'Nikah at home, reception at the Aga Khan Museum.', ['zara', 'omar', 'fatima', 'tariq']],
            ['occupation', 'Faisal takes over the Gorakhpur shop', '2015', 'exact', 'Gorakhpur, India', null, 'Third generation in the same shop.', ['faisal', 'hamid']],
        ];
        foreach ($rows as [$type, $title, $date, $qual, $place, $from, $desc, $keys]) {
            $e = $this->family->events()->create([
                'type' => $type, 'title' => $title, 'date' => $date, 'date_qualifier' => $qual,
                'place_id' => $pl($place), 'from_place_id' => $pl($from), 'description' => $desc, 'created_by' => $this->owner->id,
            ]);
            $e->people()->sync(collect($keys)->map(fn ($k) => $this->p($k)->id));
        }
    }

    private function factsAndSources(): void
    {
        $f = $this->family;
        $book = Source::create(['family_id' => $f->id, 'title' => 'Gorakhpur District Gazetteer (1909)', 'type' => 'book', 'citation' => 'Nevill, H. R. (1909). Gorakhpur: A Gazetteer. Allahabad: Government Press. p. 214.']);
        $site = Source::create(['family_id' => $f->id, 'title' => 'FamilySearch: India, Select Marriages', 'type' => 'website', 'url' => 'https://www.familysearch.org/', 'citation' => 'Index entry for Sadiq Ali and Noor Jahan, Lucknow, 1936.']);
        $grave = Source::create(['family_id' => $f->id, 'title' => 'Gravestone, Aishbagh qabristan, Lucknow', 'type' => 'gravestone', 'citation' => 'Photographed by Rizwan Rahman, 2020.']);
        $letter = Source::create(['family_id' => $f->id, 'title' => 'Letter from Yusuf to Ameena, Nov 1986', 'type' => 'document']);
        $interview = Source::create(['family_id' => $f->id, 'title' => 'Interview with Ameena Khatoon (2018)', 'type' => 'interview']);

        $add = fn (string $key, array $data) => $this->g->addFact($this->p($key)->id, $data, $this->owner->id);

        // Zainab: three different death years, none verified
        $add('zainab', ['field' => 'death_date', 'value' => '1961-08-02', 'source_title' => 'Family recollection (Abdul)', 'source_type' => 'recollection', 'confidence' => 'medium', 'status' => 'unverified', 'is_preferred' => true]);
        $add('zainab', ['field' => 'death_date', 'value' => '1962', 'source_title' => 'Farida’s letter to Hamid, 1980', 'source_type' => 'document', 'confidence' => 'low', 'status' => 'disputed', 'notes' => 'Farida wrote “the year after Yusuf turned six”, which would be 1962.']);
        $add('zainab', ['field' => 'death_place', 'value' => 'Gorakhpur, India', 'source_title' => 'Family recollection (Abdul)', 'source_type' => 'recollection', 'confidence' => 'high', 'status' => 'verified', 'is_preferred' => true]);

        // Ismail: birth year from the gazetteer vs the gravestone
        $add('ismail', ['field' => 'birth_date', 'value' => '1905', 'source_id' => $book->id, 'confidence' => 'low', 'status' => 'disputed', 'notes' => 'The gazetteer lists an Ismail Khan, cloth merchant, aged “about 4” in 1909.']);
        $add('ismail', ['field' => 'birth_date', 'value' => '1903', 'source_id' => $grave->id, 'confidence' => 'medium', 'status' => 'unverified', 'is_preferred' => true, 'notes' => 'Gravestone reads 1903–1968, but the stone was placed in 1990 from memory.']);
        $add('ismail', ['field' => 'death_date', 'value' => '1968-04', 'source_id' => $grave->id, 'confidence' => 'high', 'status' => 'verified', 'is_preferred' => true]);

        // Ameena's parents' marriage, from an online index
        $add('sadiq', ['field' => 'other', 'value' => 'Married Noor Jahan, Lucknow, 1936', 'source_id' => $site->id, 'confidence' => 'medium', 'status' => 'unverified']);
        $add('ameena', ['field' => 'occupation', 'value' => 'Adult literacy volunteer, 1991–2010', 'source_id' => $interview->id, 'confidence' => 'high', 'status' => 'verified', 'is_preferred' => true]);

        // Yusuf's birthplace disputed: Gorakhpur (certificate) vs Basti (his own recollection)
        $add('yusuf', ['field' => 'birth_place', 'value' => 'Gorakhpur, India', 'source_title' => 'Birth certificate (copy, 1979)', 'source_type' => 'record', 'confidence' => 'high', 'status' => 'verified', 'is_preferred' => true]);
        $add('yusuf', ['field' => 'birth_place', 'value' => 'Basti, India', 'source_title' => 'Yusuf’s recollection', 'source_type' => 'recollection', 'confidence' => 'low', 'status' => 'disputed', 'notes' => 'Yusuf believes he was born at his grandmother’s house in Basti.']);
        $add('yusuf', ['field' => 'other', 'value' => 'Arrived in London 14 Nov 1986, lodged in Whitechapel', 'source_id' => $letter->id, 'confidence' => 'high', 'status' => 'verified']);

        // Abdul: a verified occupation fact and a name variant
        $add('abdul', ['field' => 'occupation', 'value' => 'Mathematics and Urdu teacher, Govt. Inter College, Lucknow (1972–1992)', 'source_title' => 'School service record', 'source_type' => 'record', 'confidence' => 'high', 'status' => 'verified', 'is_preferred' => true]);
        $add('abdul', ['field' => 'name', 'value' => 'Mohammad Abdul Rahman Khan', 'source_title' => 'School service record', 'source_type' => 'record', 'confidence' => 'high', 'status' => 'verified', 'notes' => 'The family dropped “Khan” after moving to Lucknow.']);

        // Extra names
        $this->p('sana')->names()->create(['type' => 'nickname', 'language' => 'en', 'full_name' => 'Sana Phuphi']);
        $this->p('ameena')->names()->create(['type' => 'nickname', 'language' => 'en', 'full_name' => 'Dadi']);
        $this->p('abdul')->names()->create(['type' => 'nickname', 'language' => 'en', 'full_name' => 'Dada']);
        $this->p('fatima')->names()->create(['type' => 'married', 'language' => 'en', 'full_name' => 'Fatima Siddiqui (née Rahman)']);
        $this->p('zara')->names()->create(['type' => 'married', 'language' => 'en', 'full_name' => 'Zara Haddad']);
        $this->p('claire')->names()->create(['type' => 'birth', 'language' => 'en', 'full_name' => 'Claire Elizabeth Marsh']);
    }

    private function albumsAndMedia(): void
    {
        $f = $this->family;
        $albums = [];
        foreach ([
            ['Weddings', 'Every wedding we have a photograph of, 1963 to 2017.'],
            ['Lucknow years', 'The Aminabad house, school days and Eid gatherings, 1972 onwards.'],
            ['Across the world', 'London, Dubai, Toronto, California: the diaspora.'],
            ['Documents & certificates', 'Scanned records. Add a transcription when you can read the original.'],
        ] as [$title, $desc]) {
            $albums[$title] = $f->albums()->create(['title' => $title, 'description' => $desc, 'created_by' => $this->owner->id]);
        }

        // File the demo photos into albums
        $f->media()->where('title', 'Wedding of Abdul and Ameena')->update(['album_id' => $albums['Weddings']->id]);
        $f->media()->where('title', 'Yusuf and Claire, London')->update(['album_id' => $albums['Weddings']->id]);
        $f->media()->where('title', 'Family at Charbagh station')->update(['album_id' => $albums['Lucknow years']->id]);
        $f->media()->where('title', 'Eid in San Jose')->update(['album_id' => $albums['Across the world']->id]);

        if (! function_exists('imagecreatetruecolor')) {
            return;
        }

        // More photos
        $photos = [
            ['Ismail outside the cloth shop', '1955', 'Gorakhpur, India', 'Lucknow years', ['ismail', 'hamid'], [120, 100, 80]],
            ['Hamid and Shabnam’s wedding', '1960', 'Gorakhpur, India', 'Weddings', ['hamid', 'shabnam', 'abdul', 'ismail'], [150, 130, 100]],
            ['Fatima and Tariq, Lucknow', '1982', 'Lucknow, India', 'Weddings', ['fatima', 'tariq', 'abdul', 'ameena'], [180, 150, 110]],
            ['Sana’s graduation, Aligarh', '1996', 'Aligarh, India', 'Lucknow years', ['sana', 'abdul', 'ameena'], [170, 160, 140]],
            ['Zara in Dubai, first day of school', '1992', 'Dubai, UAE', 'Across the world', ['zara', 'fatima'], [200, 180, 140]],
            ['Ameena’s 75th birthday', '2015-11-12', 'Lucknow, India', 'Lucknow years', ['ameena', 'yusuf', 'fatima', 'imran', 'sana', 'rizwan', 'arman', 'zara'], [190, 170, 150]],
            ['Zara and Omar’s nikah', '2017-08-26', 'Toronto, Canada', 'Weddings', ['zara', 'omar'], [160, 150, 150]],
            ['Daniel and Priya, Brighton', '2021-06', 'London, United Kingdom', 'Across the world', ['daniel', 'priya'], [140, 160, 180]],
            ['Four generations: Ameena, Yusuf, Ayesha, Ahmad', '2012', 'Lucknow, India', 'Lucknow years', ['ameena', 'yusuf', 'ayesha', 'ahmad'], [185, 165, 135]],
        ];
        $first = null;
        foreach ($photos as $i => [$title, $date, $place, $album, $keys, $tint]) {
            $m = $this->image($title, $date, $tint, 'photo', null, "sample-photo-{$i}.jpg", $place, $albums[$album]->id);
            $m->people()->sync(collect($keys)->map(fn ($k) => $this->p($k)->id));
            $first ??= $m;
            if ($title === 'Ismail outside the cloth shop') {
                $this->p('ismail')->update(['photo_media_id' => $m->id]);
            }
            if ($title === 'Sana’s graduation, Aligarh') {
                $this->p('sana')->update(['photo_media_id' => $m->id]);
            }
            if ($title === 'Zara and Omar’s nikah') {
                $this->p('zara')->update(['photo_media_id' => $m->id]);
            }
        }

        // Documents with transcriptions and AI-style suggestions
        $docs = [
            ['Abdul Rahman’s school service record', '1992', 'record', 'Lucknow, India', ['abdul'],
                "GOVERNMENT INTER COLLEGE, LUCKNOW\nService Record\n\nName: Mohammad Abdul Rahman Khan\nDate of birth: 1932 (as per matriculation certificate)\nAppointed: 15 July 1954, Gorakhpur (transferred 1972)\nSubjects: Mathematics, Urdu\nRetired: 31 March 1992, after 34 years of continuous service.\nRemarks: Exemplary. Recommended for the State Teacher Award, 1988.",
                ['title' => 'School service record, Abdul Rahman', 'description' => 'Service record for a schoolteacher, Lucknow, issued at retirement in 1992.', 'estimated_year' => 1992, 'era_reasoning' => 'Typewritten government form with a 1992 retirement date.', 'document_type' => 'record', 'transcription' => null, 'people' => [['name' => 'Mohammad Abdul Rahman Khan', 'matched_person_id' => $this->p('abdul')->id, 'role' => 'subject']], 'places' => ['Lucknow', 'Gorakhpur'], 'dates' => [['date' => '1954-07-15', 'meaning' => 'Appointed'], ['date' => '1972', 'meaning' => 'Transferred to Lucknow'], ['date' => '1992-03-31', 'meaning' => 'Retired']]]],
            ['Letter from Yusuf in London, November 1986', '1986-11-20', 'letter', 'London, United Kingdom', ['yusuf', 'ameena'],
                "Whitechapel, 20 November 1986\n\nDearest Ammi,\n\nI arrived on the 14th. The cold is like nothing in Lucknow; the fog here gets into your coat. I am sharing a room with two boys from Sylhet above a shop on Brick Lane. The engineering firm starts me on Monday.\n\nTell Abba the trains here run exactly on time and nobody sells jalebi at the station. I miss everyone. Send Sana’s exam results.\n\nYour son,\nYusuf",
                ['title' => 'Letter from Yusuf in London', 'description' => 'Letter from a son newly arrived in London to his mother in Lucknow.', 'estimated_year' => 1986, 'era_reasoning' => 'Dated 20 November 1986; airmail paper.', 'document_type' => 'letter', 'transcription' => null, 'people' => [['name' => 'Yusuf', 'matched_person_id' => $this->p('yusuf')->id, 'role' => 'writer'], ['name' => 'Ammi', 'matched_person_id' => $this->p('ameena')->id, 'role' => 'recipient (mother)'], ['name' => 'Abba', 'matched_person_id' => $this->p('abdul')->id, 'role' => 'mentioned (father)'], ['name' => 'Sana', 'matched_person_id' => $this->p('sana')->id, 'role' => 'mentioned (sister)']], 'places' => ['Whitechapel', 'Brick Lane', 'Lucknow', 'Sylhet'], 'dates' => [['date' => '1986-11-14', 'meaning' => 'Arrived in London'], ['date' => '1986-11-20', 'meaning' => 'Letter written']]]],
            ['Ameena Khatoon’s passport (1985)', '1985', 'passport', 'Lucknow, India', ['ameena'],
                "REPUBLIC OF INDIA · PASSPORT\nSurname: KHATOON\nGiven names: AMEENA\nDate of birth: 12/11/1940\nPlace of birth: LUCKNOW\nDate of issue: 03/06/1985   Place of issue: LUCKNOW",
                ['title' => 'Passport of Ameena Khatoon', 'description' => 'Indian passport issued in Lucknow in 1985.', 'estimated_year' => 1985, 'era_reasoning' => 'Issue date printed on the document.', 'document_type' => 'passport', 'transcription' => null, 'people' => [['name' => 'Ameena Khatoon', 'matched_person_id' => $this->p('ameena')->id, 'role' => 'holder']], 'places' => ['Lucknow'], 'dates' => [['date' => '1940-11-12', 'meaning' => 'Date of birth'], ['date' => '1985-06-03', 'meaning' => 'Date of issue']]]],
            ['Nikah nama, Abdul and Ameena, 1963', '1963', 'certificate', 'Lucknow, India', ['abdul', 'ameena'],
                null,
                ['title' => 'Nikah nama, 1963', 'description' => 'Marriage contract in Urdu. Names of the groom, bride and two witnesses are legible; the mehr amount is faded.', 'estimated_year' => 1963, 'era_reasoning' => 'Handwritten Urdu on a printed 1960s form.', 'document_type' => 'certificate', 'transcription' => 'نکاح نامہ — دولہا: محمد عبد الرحمن — دلہن: آمنہ خاتون — گواہ: صادق علی', 'people' => [['name' => 'Mohammad Abdul Rahman', 'matched_person_id' => $this->p('abdul')->id, 'role' => 'groom'], ['name' => 'Ameena Khatoon', 'matched_person_id' => $this->p('ameena')->id, 'role' => 'bride'], ['name' => 'Sadiq Ali', 'matched_person_id' => $this->p('sadiq')->id, 'role' => 'witness']], 'places' => ['Lucknow'], 'dates' => [['date' => '1963', 'meaning' => 'Marriage']]]],
            ['Property deed, Aminabad house', '1972-03', 'deed', 'Lucknow, India', ['abdul'], null, null],
            ['Newspaper clipping: Ameena honoured', '2004-09', 'newspaper', 'Lucknow, India', ['ameena'],
                'LUCKNOW, Sept 2004 — Forty women from Aminabad received literacy certificates on Saturday. Volunteer teacher Ameena Khatoon, 63, who learned to read alongside her own children, was presented with a shawl by the District Magistrate. “My children were my teachers,” she said.', null],
        ];
        foreach ($docs as $i => [$title, $date, $type, $place, $keys, $transcript, $ai]) {
            $m = $this->image($title, $date, [235, 228, 210], 'document', $type, "sample-doc-{$i}.jpg", $place, $albums['Documents & certificates']->id, $transcript, $ai);
            $m->people()->sync(collect($keys)->map(fn ($k) => $this->p($k)->id));
            if ($type === 'record') {
                Source::where('family_id', $f->id)->where('title', 'School service record')->update(['media_id' => $m->id]);
            }
            if ($type === 'letter') {
                Source::where('family_id', $f->id)->where('title', 'Letter from Yusuf to Ameena, Nov 1986')->update(['media_id' => $m->id]);
            }
            if ($type === 'passport') {
                Source::where('family_id', $f->id)->where('title', 'Passport (1985)')->update(['media_id' => $m->id]);
            }
        }

        // An audio recording (one second of a soft tone) so the audio player and interview pages have a file
        $audio = $this->wav("families/{$f->id}/sample-interview-ameena.wav");
        $this->audioMedia = $f->media()->create([
            'kind' => 'audio', 'title' => 'Ameena Khatoon interview, March 2018', 'path' => $audio['path'], 'original_name' => 'ameena-2018.wav',
            'mime' => 'audio/wav', 'size' => $audio['size'], 'taken_date' => '2018-03', 'place_id' => PlaceResolver::resolve($f, 'Lucknow, India'),
            'uploaded_by' => $this->owner->id, 'description' => 'Recorded by Imran on his phone during his last visit, in Urdu with some English.',
        ]);
        $this->audioMedia->people()->sync([$this->p('ameena')->id, $this->p('imran')->id]);
        $f->increment('storage_used', $audio['size']);
    }

    private ?Media $audioMedia = null;

    private function image(string $title, string $date, array $tint, string $kind, ?string $docType, string $file, ?string $place, ?int $albumId, ?string $transcription = null, ?array $ai = null): Media
    {
        $img = imagecreatetruecolor(800, $kind === 'document' ? 1040 : 560);
        imagefill($img, 0, 0, imagecolorallocate($img, ...$tint));
        $h = imagesy($img);
        $inner = imagecolorallocate($img, min(255, $tint[0] + 20), min(255, $tint[1] + 20), min(255, $tint[2] + 20));
        imagefilledrectangle($img, 30, 30, 770, $h - 30, $inner);
        $ink = imagecolorallocate($img, 60, 45, 30);
        if ($kind === 'document') {
            // ruled lines to look like a scanned page
            for ($y = 120; $y < $h - 80; $y += 36) {
                imageline($img, 70, $y, 730, $y, imagecolorallocate($img, 200, 190, 170));
            }
            imagestring($img, 5, 60, 60, strtoupper($title), $ink);
            foreach (array_slice(explode("\n", (string) $transcription), 0, 22) as $i => $line) {
                imagestring($img, 3, 70, 100 + $i * 36, mb_substr($line, 0, 90), $ink);
            }
        } else {
            imagestring($img, 5, 60, (int) ($h / 2) - 10, $title.' ('.$date.')', $ink);
        }
        ob_start();
        imagejpeg($img, null, 85);
        $bytes = ob_get_clean();
        $path = "families/{$this->family->id}/{$file}";
        Storage::disk('local')->put($path, $bytes);
        $this->family->increment('storage_used', strlen($bytes));

        return $this->family->media()->create([
            'kind' => $kind, 'document_type' => $docType, 'title' => $title, 'path' => $path, 'original_name' => $file, 'mime' => 'image/jpeg', 'size' => strlen($bytes),
            'taken_date' => $date, 'place_id' => PlaceResolver::resolve($this->family, $place), 'album_id' => $albumId,
            'transcription' => $transcription, 'ai_suggestions' => $ai, 'uploaded_by' => $this->owner->id,
        ]);
    }

    /** One second of a quiet 440 Hz tone as 16-bit mono PCM WAV. */
    private function wav(string $path): array
    {
        $rate = 8000;
        $samples = '';
        for ($i = 0; $i < $rate; $i++) {
            $samples .= pack('v', (int) (3000 * sin(2 * M_PI * 440 * $i / $rate) * (1 - $i / $rate)));
        }
        $data = 'RIFF'.pack('V', 36 + strlen($samples)).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, $rate, $rate * 2, 2, 16).'data'.pack('V', strlen($samples)).$samples;
        Storage::disk('local')->put($path, $data);

        return ['path' => $path, 'size' => strlen($data)];
    }

    private function stories(): void
    {
        $f = $this->family;
        $pl = fn ($n) => PlaceResolver::resolve($f, $n);
        $rows = [
            ['Leaving for Karachi, 1947', "Farida was eighteen and newly married when the trains stopped being safe. Akbar’s brother had already gone to Karachi and wrote that there was work at the port.\n\nIsmail would not go. “My shop is here, my customers are Hindu and Muslim, and my father is buried here,” he said. So the family split, as so many did. Farida took one trunk and her mother’s copper pots.\n\nFor twenty-seven years letters took three weeks each way. When Abdul and Hamid finally visited in 1974, Farida fed them for a week and would not let them pay for anything. “You are in my house now,” she said, “it took you long enough.”", 'shahid', '1947', 'Karachi, Pakistan', ['farida', 'akbar', 'ismail', 'abdul', 'hamid'], 'en'],
            ['Yusuf’s first winter in London', "He arrived on 14 November 1986 with one suitcase, a letter of appointment and £40. The room above the shop on Brick Lane had a gas heater that ate fifty-pence coins.\n\nWhat he remembers most is the silence of the Tube in the mornings, and the first time he saw snow settle on his sleeve outside Aldgate East. He phoned home once a month from a call box, feeding it coins, while Ameena held the receiver so everyone could hear.\n\nTwo years later he married Claire, a nurse from the London Hospital who had treated his sprained wrist. She says he kept coming back for check-ups that he didn’t need.", 'yusuf', '1986', 'London, United Kingdom', ['yusuf', 'claire', 'ameena'], 'en'],
            ['The shop that stayed', "Ismail opened the shop in the Golghar bazaar in 1931 with cloth bought on credit. Hamid took it over in 1968 and Faisal in 2015. The sign has been repainted four times and the name has never changed.\n\nFaisal still keeps his grandfather’s brass measuring rod on the counter. Customers whose grandmothers bought their wedding saris there still come. Nadia designs the printed cottons now; the shop sells online, which Ismail would have found suspicious.", 'faisal', '2015', 'Gorakhpur, India', ['ismail', 'hamid', 'faisal', 'nadia'], 'en'],
            ['دادی کی روٹی', "دادی کہتی تھیں کہ روٹی گول نہ ہو تو کوئی بات نہیں، بس دل گول ہونا چاہیے۔ ہر عید پر وہ صبح چار بجے اٹھ کر پچاس روٹیاں بناتی تھیں۔\n\nآخری عید پر، 2018 میں، انہوں نے زارا کو سکھایا۔ زارا کی روٹیاں نقشے کی طرح تھیں۔ دادی نے کہا، ”کینیڈا کا نقشہ بہت اچھا بنا ہے۔“", 'zara', '2018', 'Lucknow, India', ['ameena', 'zara'], 'ur'],
            ['How Ayesha became a doctor', "When Ayesha was nine her parents divorced and she moved with her mother Nusrat back to Nusrat’s parents’ house. Her grandfather Abdul visited every Sunday with maths problems written on the back of old exam papers.\n\nShe says she became a doctor because of his heart operation in 2003: she was in her first year at King George’s Medical College and sat with him in the ward, reading his chart and asking the registrars too many questions. Dada told the surgeon, “She will have your job one day.” She is a cardiologist at the same hospital now.", 'ayesha', '2003', 'Lucknow, India', ['ayesha', 'abdul', 'nusrat'], 'en'],
            ['Eid in San Jose', "The first Eid in California, 1999, was a Tuesday and nobody had the day off. Imran went to work, Neha made sheer khurma for a family of three, and Arman cried because there were no cousins.\n\nBy 2005 there were forty people in the backyard: Gujaratis from down the street, Neha’s colleagues, a Mexican family whose son was in Arman’s class. Inaya, who was four, decided that Eid meant tamales and still thinks so.", 'arman', '2005', 'San Jose, USA', ['imran', 'neha', 'arman', 'inaya'], 'en'],
        ];
        $this->stories = [];
        foreach ($rows as [$title, $body, $teller, $date, $place, $keys, $lang]) {
            $s = $f->stories()->create(['title' => $title, 'body' => $body, 'language' => $lang, 'told_by_person_id' => $this->p($teller)->id, 'story_date' => $date, 'place_id' => $pl($place), 'created_by' => $this->owner->id]);
            $s->people()->sync(collect($keys)->map(fn ($k) => $this->p($k)->id));
            $this->stories[$title] = $s;
        }
        // Attach the letter to Yusuf's story, and the interview recording to the roti story
        if ($letter = $f->media()->where('document_type', 'letter')->first()) {
            $this->stories['Yusuf’s first winter in London']->media()->sync([$letter->id]);
        }
        if ($this->audioMedia) {
            $this->stories['دادی کی روٹی']->update(['audio_media_id' => $this->audioMedia->id]);
        }
    }

    private array $stories = [];

    private function questions(): void
    {
        $f = $this->family;
        $imran = User::where('email', 'imran@heirloom.test')->first();
        $sana = User::where('email', 'sana@heirloom.test')->first();
        $zara = User::where('email', 'zara@heirloom.test')->first();

        // Two questions already answered with stories
        $f->questions()->create(['question' => 'Why did Farida phuphi end up in Karachi when everyone else stayed?', 'about_person_id' => $this->p('farida')->id, 'asked_by' => $zara->id, 'answer_story_id' => $this->stories['Leaving for Karachi, 1947']->id, 'status' => 'answered']);
        $f->questions()->create(['question' => 'How did Yusuf chacha meet Claire?', 'about_person_id' => $this->p('yusuf')->id, 'asked_by' => $this->owner->id, 'answer_story_id' => $this->stories['Yusuf’s first winter in London']->id, 'status' => 'answered']);
        // One of the demo questions is now answered too
        $f->questions()->where('question', 'like', 'What was the name of the school%')->update(['status' => 'answered', 'answer_story_id' => $f->stories()->where('title', 'The overnight train to Lucknow')->value('id')]);

        foreach ([
            ['Does anyone know the name of the ship Ismail took to Hajj in 1952?', 'ismail', $sana],
            ['Where exactly in Basti did Rukhsana Bibi’s family live?', 'rukhsana', $imran],
            ['Who were the two witnesses on Dada and Dadi’s nikah nama?', 'ameena', $this->owner],
            ['Nusrat aunty, would you be willing to share a photo of Ayesha as a baby?', 'nusrat', $zara],
            ['What did Tariq do during his national service year?', 'tariq', $this->owner],
        ] as [$q, $about, $asker]) {
            $f->questions()->create(['question' => $q, 'about_person_id' => $this->p($about)->id, 'asked_by' => $asker->id]);
        }
    }

    private function interviews(): void
    {
        $f = $this->family;
        $imran = User::where('email', 'imran@heirloom.test')->first();

        // 1. Applied: Ameena, 2018, with audio. Its extraction was already confirmed.
        $f->interviews()->create([
            'person_id' => $this->p('ameena')->id, 'title' => 'Ameena Khatoon on learning to read', 'language' => 'ur', 'status' => 'applied',
            'audio_media_id' => $this->audioMedia?->id, 'created_by' => $imran->id,
            'transcript' => "میں نے کبھی اسکول نہیں دیکھا۔ جب یوسف اور فاطمہ اسکول جانے لگے تو میں شام کو ان کے ساتھ بیٹھتی تھی۔ پہلے اردو کے حروف، پھر ہندی۔\n\n1991 میں محلے کی ایک عورت نے کہا کہ اسے خط لکھنا نہیں آتا۔ میں نے کہا، آؤ، میں سکھاتی ہوں۔ پھر دس آ گئیں۔ پھر چالیس۔\n\n2004 میں مجھے شال ملی۔ میں نے کہا، میرے بچے میرے استاد تھے۔ یہ سچ ہے۔",
            'extraction' => [
                'summary' => 'Ameena describes learning to read alongside her children and later teaching neighbourhood women, leading to recognition in 2004.',
                'story_title' => 'How Ameena learned to read',
                'story_body' => 'Ameena never went to school…',
                'people' => [
                    ['name' => 'Yusuf', 'matched_person_id' => $this->p('yusuf')->id, 'relationship_to_interviewee' => 'stepson', 'gender' => 'male', 'birth_date' => null, 'death_date' => null, 'birth_place' => null, 'occupation' => null, 'notes' => null],
                    ['name' => 'Fatima', 'matched_person_id' => $this->p('fatima')->id, 'relationship_to_interviewee' => 'stepdaughter', 'gender' => 'female', 'birth_date' => null, 'death_date' => null, 'birth_place' => null, 'occupation' => null, 'notes' => null],
                ],
                'places' => [['name' => 'Aminabad, Lucknow', 'country' => 'India', 'context' => 'Neighbourhood where she taught']],
                'events' => [
                    ['title' => 'Ameena begins teaching neighbourhood women to read', 'type' => 'occupation', 'date' => '1991', 'date_qualifier' => 'exact', 'place' => 'Lucknow, India', 'from_place' => null, 'people' => ['Ameena'], 'description' => null],
                    ['title' => 'Ameena honoured for adult-literacy volunteering', 'type' => 'award', 'date' => '2004', 'date_qualifier' => 'exact', 'place' => 'Lucknow, India', 'from_place' => null, 'people' => ['Ameena'], 'description' => 'Presented with a shawl.'],
                ],
                'follow_up_questions' => ['Which women did she teach first?', 'Did she keep the shawl?'],
            ],
        ]);

        // 2. Extracted but not yet confirmed: Hamid's son Faisal talks about the shop. Mentions a new person (Ismail's brother) and a new event.
        $f->interviews()->create([
            'person_id' => $this->p('faisal')->id, 'title' => 'Faisal Khan on the Golghar shop', 'language' => 'en', 'status' => 'extracted', 'created_by' => $this->owner->id,
            'transcript' => "Interviewer: Tell me about how the shop started.\n\nFaisal: My great-grandfather Ismail opened it in 1931. He had a younger brother, Yunus, who was his partner at first but moved to Bombay around 1938 to work in the mills. Nobody kept in touch with that side. Yunus had a daughter, I think, called Khurshid.\n\nInterviewer: And your father?\n\nFaisal: Abba took over in 1968 when Dada Ismail died. There was a fire in the bazaar in 1979, the whole row burned. Abba rebuilt in six months. He borrowed from Abdul chacha in Lucknow, who had just bought the Aminabad house and had nothing spare, but sent money anyway.\n\nI took over in 2015. My daughter Nadia designs the prints now.",
            'extraction' => [
                'summary' => 'Faisal recounts the founding of the family cloth shop in 1931, a brother Yunus who left for Bombay, a bazaar fire in 1979, and the succession to Hamid and then himself.',
                'story_title' => 'The fire of 1979',
                'story_body' => "In 1979 a fire swept through the Golghar bazaar and the whole row of shops burned. Hamid rebuilt in six months. He borrowed from his brother Abdul in Lucknow, who had just bought the Aminabad house and had nothing spare, but sent the money anyway.\n\n“Abba never spoke of it,” Faisal says. “Abdul chacha never mentioned it either. We found the repayment book after Abba died.”",
                'people' => [
                    ['name' => 'Ismail', 'matched_person_id' => $this->p('ismail')->id, 'relationship_to_interviewee' => 'great-grandfather', 'gender' => 'male', 'birth_date' => null, 'death_date' => '1968', 'birth_place' => null, 'occupation' => 'Cloth merchant', 'notes' => null],
                    ['name' => 'Yunus Khan', 'matched_person_id' => null, 'relationship_to_interviewee' => 'great-grandfather’s brother', 'gender' => 'male', 'birth_date' => null, 'death_date' => null, 'birth_place' => 'Gorakhpur, India', 'occupation' => 'Mill worker', 'notes' => 'Moved to Bombay about 1938; family lost contact.'],
                    ['name' => 'Khurshid', 'matched_person_id' => null, 'relationship_to_interviewee' => 'cousin (Yunus’s daughter)', 'gender' => 'female', 'birth_date' => null, 'death_date' => null, 'birth_place' => null, 'occupation' => null, 'notes' => 'Uncertain; “I think”.'],
                    ['name' => 'Hamid', 'matched_person_id' => $this->p('hamid')->id, 'relationship_to_interviewee' => 'father', 'gender' => 'male', 'birth_date' => null, 'death_date' => null, 'birth_place' => null, 'occupation' => 'Cloth merchant', 'notes' => null],
                    ['name' => 'Abdul', 'matched_person_id' => $this->p('abdul')->id, 'relationship_to_interviewee' => 'uncle', 'gender' => 'male', 'birth_date' => null, 'death_date' => null, 'birth_place' => null, 'occupation' => null, 'notes' => null],
                    ['name' => 'Nadia', 'matched_person_id' => $this->p('nadia')->id, 'relationship_to_interviewee' => 'daughter', 'gender' => 'female', 'birth_date' => null, 'death_date' => null, 'birth_place' => null, 'occupation' => 'Textile designer', 'notes' => null],
                ],
                'places' => [['name' => 'Golghar bazaar, Gorakhpur', 'country' => 'India', 'context' => 'Location of the shop'], ['name' => 'Bombay', 'country' => 'India', 'context' => 'Where Yunus moved']],
                'events' => [
                    ['title' => 'Ismail opens the cloth shop', 'type' => 'occupation', 'date' => '1931', 'date_qualifier' => 'exact', 'place' => 'Gorakhpur, India', 'from_place' => null, 'people' => ['Ismail'], 'description' => null],
                    ['title' => 'Yunus moves to Bombay', 'type' => 'migration', 'date' => '1938', 'date_qualifier' => 'about', 'place' => 'Bombay, India', 'from_place' => 'Gorakhpur, India', 'people' => ['Yunus Khan'], 'description' => 'To work in the mills.'],
                    ['title' => 'Fire in the Golghar bazaar', 'type' => 'custom', 'date' => '1979', 'date_qualifier' => 'exact', 'place' => 'Gorakhpur, India', 'from_place' => null, 'people' => ['Hamid'], 'description' => 'The shop burned and was rebuilt within six months.'],
                    ['title' => 'Ismail dies', 'type' => 'death', 'date' => '1968', 'date_qualifier' => 'exact', 'place' => null, 'from_place' => null, 'people' => ['Ismail'], 'description' => null],
                ],
                'follow_up_questions' => ['Does anyone have Yunus’s address in Bombay, or know what became of Khurshid?', 'Is the repayment book still in the family?', 'What was the shop called before 1931?'],
            ],
        ]);

        // 3. Draft: a transcript typed in, nothing extracted yet
        $f->interviews()->create([
            'person_id' => $this->p('tariq')->id, 'title' => 'Tariq Siddiqui on moving to Dubai', 'language' => 'en', 'status' => 'draft', 'created_by' => User::where('email', 'zara@heirloom.test')->value('id'),
            'transcript' => 'We landed in Dubai in June 1985 and the heat came through the aircraft door like an oven. Fatima was pregnant with Zara. I had a job at an accountancy firm in Deira that a friend from Lucknow had arranged. Our first flat was in Bur Dubai above a Keralite restaurant and for two years we ate there more than we cooked.',
        ]);
    }

    private function invitations(): void
    {
        $f = $this->family;
        $imran = User::where('email', 'imran@heirloom.test')->first();
        // Pending (share this link to test joining as a new user)
        Invitation::create(['family_id' => $f->id, 'email' => 'ayesha@heirloom.test', 'role' => 'contributor', 'token' => 'sample-invite-ayesha-'.Str::random(16), 'person_id' => $this->p('ayesha')->id, 'invited_by' => $this->owner->id, 'expires_at' => now()->addDays(30)]);
        Invitation::create(['family_id' => $f->id, 'email' => null, 'role' => 'viewer', 'token' => 'sample-invite-open-'.Str::random(16), 'invited_by' => $imran->id, 'expires_at' => now()->addDays(7)]);
        // Expired
        Invitation::create(['family_id' => $f->id, 'email' => 'shahid@heirloom.test', 'role' => 'contributor', 'token' => Str::random(40), 'person_id' => $this->p('shahid')->id, 'invited_by' => $this->owner->id, 'expires_at' => now()->subDays(12), 'created_at' => now()->subDays(42)]);
        // Accepted (how Oliver joined)
        Invitation::create(['family_id' => $f->id, 'email' => 'oliver@heirloom.test', 'role' => 'viewer', 'token' => Str::random(40), 'person_id' => $this->p('oliver')->id, 'invited_by' => $this->owner->id, 'accepted_at' => now()->subDays(20), 'expires_at' => now()->addDays(10), 'created_at' => now()->subDays(25)]);
    }

    private function reviewQueue(): void
    {
        $f = $this->family;
        $zara = User::where('email', 'zara@heirloom.test')->first();
        $imran = User::where('email', 'imran@heirloom.test')->first();
        $sana = User::where('email', 'sana@heirloom.test')->first();

        // Pending
        ChangeRequest::create(['family_id' => $f->id, 'user_id' => $zara->id, 'action' => 'person.update', 'summary' => 'Edit Tariq Siddiqui: add education', 'payload' => ['id' => $this->p('tariq')->id, 'person' => ['education' => 'B.Com, Lucknow University, 1975']], 'created_at' => now()->subDays(2)]);
        ChangeRequest::create(['family_id' => $f->id, 'user_id' => $zara->id, 'action' => 'fact.create', 'summary' => 'Add sourced fact for Fatima Siddiqui: birth date 1958-02-09', 'payload' => ['person_id' => $this->p('fatima')->id, 'field' => 'birth_date', 'value' => '1958-02-09', 'source_title' => 'Fatima’s passport (2019)', 'source_type' => 'document', 'confidence' => 'high', 'status' => 'verified'], 'created_at' => now()->subDays(1)]);
        ChangeRequest::create(['family_id' => $f->id, 'user_id' => $zara->id, 'action' => 'partnership.create', 'summary' => 'Add partnership: Shahid Hussain and unknown partner', 'payload' => ['partner1_id' => $this->p('shahid')->id, 'partner2_id' => null, 'type' => 'marriage', 'status' => 'current', 'start_date' => '1978'], 'created_at' => now()->subHours(5)]);

        // Approved
        ChangeRequest::create(['family_id' => $f->id, 'user_id' => $zara->id, 'action' => 'person.create', 'summary' => 'Add Omar Haddad as partner of Zara Siddiqui', 'payload' => ['person' => ['given_name' => 'Omar', 'surname' => 'Haddad', 'gender' => 'male', 'birth_date' => '1985-04-18'], 'relation' => ['type' => 'partner', 'person_id' => $this->p('zara')->id]], 'status' => 'approved', 'reviewed_by' => $imran->id, 'review_note' => 'Welcome Omar!', 'reviewed_at' => now()->subDays(30), 'created_at' => now()->subDays(31)]);
        ChangeRequest::create(['family_id' => $f->id, 'user_id' => $zara->id, 'action' => 'person.update', 'summary' => 'Edit Zara Siddiqui: occupation', 'payload' => ['id' => $this->p('zara')->id, 'person' => ['occupation' => 'Architect']], 'status' => 'approved', 'reviewed_by' => $sana->id, 'reviewed_at' => now()->subDays(14), 'created_at' => now()->subDays(15)]);

        // Rejected
        ChangeRequest::create(['family_id' => $f->id, 'user_id' => $zara->id, 'action' => 'person.delete', 'summary' => 'Remove Bashir', 'payload' => ['id' => $this->p('bashir')->id], 'status' => 'rejected', 'reviewed_by' => $imran->id, 'review_note' => 'Even if we are unsure of his mother, he was part of the family. Keeping the record with a note.', 'reviewed_at' => now()->subDays(8), 'created_at' => now()->subDays(9)]);
    }

    /** A believable audit trail spread over the last 60 days. */
    private function activityTrail(): void
    {
        $f = $this->family;
        $users = User::whereIn('email', ['owner@heirloom.test', 'zara@heirloom.test', 'imran@heirloom.test', 'sana@heirloom.test'])->get()->keyBy('email');
        $rows = [
            [60, 'owner', 'created', 'Family', 'Family “Rahman Family” created', null],
            [59, 'owner', 'created', 'Person', 'Person “Abdul Rahman”', null],
            [59, 'owner', 'created', 'Person', 'Person “Ameena Khatoon”', null],
            [58, 'owner', 'created', 'Partnership', 'Partnership “Abdul Rahman & Ameena Khatoon”', null],
            [55, 'owner', 'created', 'Media', 'Media “Wedding of Abdul and Ameena”', null],
            [50, 'imran', 'created', 'Person', 'Person “Ismail Khan”', null],
            [49, 'imran', 'updated', 'Person', 'Person “Ismail Khan”', ['nickname' => ['from' => null, 'to' => 'Haji Sahab'], 'death_date' => ['from' => '1968', 'to' => '1968-04']]],
            [45, 'sana', 'created', 'Story', 'Story “The overnight train to Lucknow”', null],
            [44, 'sana', 'updated', 'Story', 'Story “The overnight train to Lucknow”', ['story_date' => ['from' => null, 'to' => '1972']]],
            [40, 'zara', 'created', 'Story', 'Story “دادی کی روٹی”', null],
            [38, 'owner', 'created', 'Media', 'Media “Letter from Yusuf in London, November 1986”', null],
            [37, 'owner', 'updated', 'Media', 'Media “Letter from Yusuf in London, November 1986”', ['transcription' => ['from' => null, 'to' => '(transcribed)']]],
            [31, 'zara', 'created', 'ChangeRequest', 'Change request “Add Omar Haddad as partner of Zara Siddiqui”', null],
            [30, 'imran', 'updated', 'ChangeRequest', 'Approved “Add Omar Haddad as partner of Zara Siddiqui”', ['status' => ['from' => 'pending', 'to' => 'approved']]],
            [30, 'imran', 'created', 'Person', 'Person “Omar Haddad”', null],
            [25, 'owner', 'created', 'Invitation', 'Invitation for oliver@heirloom.test (viewer)', null],
            [20, 'owner', 'created', 'FamilyMember', 'Oliver Thompson joined as viewer', null],
            [18, 'sana', 'updated', 'Person', 'Person “Zainab Begum”', ['death_date' => ['from' => '1962', 'to' => '1961-08-02']]],
            [18, 'sana', 'created', 'Fact', 'Fact “death_date = 1962” for Zainab Begum (disputed)', null],
            [15, 'owner', 'created', 'Event', 'Event “Ameena’s 75th birthday”', null],
            [12, 'imran', 'updated', 'Person', 'Person “Nusrat Jahan”', ['privacy' => ['from' => 'family', 'to' => 'admins']]],
            [10, 'imran', 'created', 'Interview', 'Interview “Ameena Khatoon on learning to read”', null],
            [9, 'imran', 'updated', 'ChangeRequest', 'Rejected “Remove Bashir”', ['status' => ['from' => 'pending', 'to' => 'rejected']]],
            [7, 'owner', 'created', 'Album', 'Album “Documents & certificates”', null],
            [5, 'owner', 'created', 'Interview', 'Interview “Faisal Khan on the Golghar shop”', null],
            [4, 'owner', 'updated', 'Interview', 'Interview “Faisal Khan on the Golghar shop”', ['status' => ['from' => 'draft', 'to' => 'extracted']]],
            [3, 'zara', 'created', 'Question', 'Question “Nusrat aunty, would you be willing to share a photo…”', null],
            [2, 'zara', 'created', 'ChangeRequest', 'Change request “Edit Tariq Siddiqui: add education”', null],
            [1, 'owner', 'updated', 'Family', 'Family “Rahman Family”', ['description' => ['from' => 'From Gorakhpur and Lucknow to London…', 'to' => 'From Gorakhpur and Lucknow to London, Dubai, Toronto and California. Collected so our children know where they come from.']]],
            [0, 'zara', 'created', 'Interview', 'Interview “Tariq Siddiqui on moving to Dubai”', null],
        ];
        foreach ($rows as [$daysAgo, $who, $action, $type, $desc, $changes]) {
            ActivityLog::create([
                'family_id' => $f->id, 'user_id' => $users[$who.'@heirloom.test']->id, 'action' => $action, 'subject_type' => $type, 'subject_id' => null,
                'description' => $desc, 'changes' => $changes, 'created_at' => now()->subDays($daysAgo)->subMinutes($daysAgo * 7 % 60), 'updated_at' => now()->subDays($daysAgo),
            ]);
        }
    }

    /** A small second family owned by Claire, with the Rahman owner as a viewer, to test switching families. */
    private function secondFamily(): void
    {
        $claire = User::where('email', 'claire@heirloom.test')->firstOrFail();
        if (Family::where('owner_id', $claire->id)->exists()) {
            return;
        }
        $fam = Family::create(['name' => 'Thompson Family', 'slug' => 'thompson-family-'.Str::lower(Str::random(5)), 'description' => 'Claire’s side: Marsh and Thompson, Manchester and London.', 'owner_id' => $claire->id, 'plan' => 'free']);
        $g = FamilyGraph::for($fam);
        $p = fn (array $a) => $g->createPerson($a + ['is_living' => true, 'gender' => 'unknown'], null, $claire->id);

        $george = $p(['given_name' => 'George', 'surname' => 'Marsh', 'gender' => 'male', 'birth_date' => '1934-05-02', 'birth_place' => 'Manchester, United Kingdom', 'death_date' => '2011-10-10', 'death_place' => 'London, United Kingdom', 'occupation' => 'Bus driver']);
        $margaret = $p(['given_name' => 'Margaret', 'surname' => 'Marsh', 'gender' => 'female', 'birth_date' => '1937-08-19', 'birth_place' => 'Manchester, United Kingdom', 'occupation' => 'Seamstress']);
        $u = $g->createPartnership($george->id, $margaret->id, ['type' => 'marriage', 'status' => 'widowed', 'start_date' => '1958-03-22', 'place' => 'Manchester, United Kingdom']);
        $claireP = $p(['given_name' => 'Claire', 'surname' => 'Thompson', 'nickname' => 'née Marsh', 'gender' => 'female', 'birth_date' => '1962-04-03', 'birth_place' => 'London, United Kingdom', 'occupation' => 'Nurse']);
        $steve = $p(['given_name' => 'Steven', 'surname' => 'Marsh', 'gender' => 'male', 'birth_date' => '1965-12-25', 'birth_place' => 'London, United Kingdom', 'occupation' => 'Electrician']);
        foreach ([$claireP, $steve] as $c) {
            $g->linkChild($george->id, $c->id, 'biological', $u->id);
        }
        $mark = $p(['given_name' => 'Mark', 'surname' => 'Thompson', 'gender' => 'male', 'birth_date' => '1960-02-14', 'birth_place' => 'London, United Kingdom']);
        $u2 = $g->createPartnership($claireP->id, $mark->id, ['type' => 'marriage', 'status' => 'divorced', 'start_date' => '1983', 'end_date' => '1986']);
        $oliver = $p(['given_name' => 'Oliver', 'surname' => 'Thompson', 'gender' => 'male', 'birth_date' => '1984-05-14', 'birth_place' => 'London, United Kingdom']);
        $g->linkChild($claireP->id, $oliver->id, 'biological', $u2->id);
        $yusuf = $p(['given_name' => 'Yusuf', 'surname' => 'Rahman', 'gender' => 'male', 'birth_date' => '1955-06-21', 'birth_place' => 'Gorakhpur, India', 'occupation' => 'Civil engineer']);
        $g->createPartnership($claireP->id, $yusuf->id, ['type' => 'marriage', 'status' => 'current', 'start_date' => '1988-07-16', 'place' => 'London, United Kingdom']);
        $g->linkParent($yusuf->id, $oliver->id, 'step');

        FamilyMember::create(['family_id' => $fam->id, 'user_id' => $claire->id, 'role' => 'owner', 'person_id' => $claireP->id]);
        FamilyMember::create(['family_id' => $fam->id, 'user_id' => $this->owner->id, 'role' => 'viewer']);
        FamilyMember::create(['family_id' => $fam->id, 'user_id' => User::where('email', 'oliver@heirloom.test')->value('id'), 'role' => 'admin', 'person_id' => $oliver->id]);
        $claire->update(['current_family_id' => $fam->id]);

        $fam->stories()->create(['title' => 'Grandad George and the number 86 bus', 'body' => 'George drove the 86 from Stratford to Romford for thirty-one years. He claimed to know every regular by name and most of their dogs. On his last day the passengers clubbed together for a carriage clock, which still doesn’t keep time.', 'told_by_person_id' => $claireP->id, 'story_date' => '1994', 'created_by' => $claire->id])->people()->sync([$george->id]);
    }
}
