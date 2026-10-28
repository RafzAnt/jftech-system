<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BranchTestData;
use Tests\TestCase;

/**
 * The coordinates of a new client fill in by themselves from the place: the barangay on OpenStreetMap, or the town of the branch when
 * the map has no such barangay. Users: 1 super admin, 2 Balilihan admin, 3 Loon admin; employee 1 works in Balilihan.
 */
class GeocodeTest extends TestCase
{
    use BranchTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createBranchSchema();
        $this->seedBranchData();
        Cache::flush();
        // Nominatim wants a second between requests; the test does not wait for it.
        config(['services.geocoder.gap' => 0]);
    }

    private static function place(string $name, string $category, string $type, string $town, string $lat, string $lon): array
    {
        return ['category' => $category, 'type' => $type, 'name' => $name, 'display_name' => "{$name}, {$town}, Bohol, Central Visayas, Philippines", 'lat' => $lat, 'lon' => $lon];
    }

    private function boctol(): array
    {
        return self::place('Boctol', 'place', 'village', 'Balilihan', '9.7682059', '123.9930662');
    }

    private function where(string $branch, string $barangay = ''): array
    {
        return $this->getJson('/locations/geocode?branch=' . urlencode($branch) . '&barangay=' . urlencode($barangay))->assertOk()->json();
    }

    public function test_the_coordinates_of_a_barangay_come_from_the_map(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([$this->boctol()])]);
        $this->signIn(1);

        $answer = $this->where('Balilihan', 'boctol');

        $this->assertSame(true, $answer['found']);
        $this->assertSame('9.768206, 123.993066', $answer['coordinates']);
        $this->assertSame('barangay', $answer['precision']);
        $this->assertSame('Boctol, Balilihan', $answer['label'], 'the barangay is spelled as the branch has it');
        $this->assertEqualsWithDelta(9.7682059, $answer['latitude'], 0.000001);
        Http::assertSent(function (Request $request) {
            return $request['q'] === 'Boctol, Balilihan, Bohol, Philippines' && $request['countrycodes'] === 'ph' && str_contains($request->header('User-Agent')[0], 'JFTECH');
        });
    }

    public function test_an_answer_is_kept_so_the_map_is_asked_once(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([$this->boctol()])]);
        $this->signIn(1);

        $this->where('Balilihan', 'Boctol');
        $this->where('balilihan', 'BOCTOL');

        Http::assertSentCount(1);
    }

    public function test_a_barangay_that_is_not_on_the_map_gets_the_town_instead(): void
    {
        // The only "Agsoso" is a school in another barangay: not a place, so the town of the branch is used.
        Http::fake(['nominatim.openstreetmap.org/*' => Http::sequence()
            ->push([self::place('Agsoso Elementary School', 'amenity', 'school', 'Loon', '9.7960430', '123.8185171')])
            ->push([self::place('Loon', 'boundary', 'administrative', 'Loon', '9.8033', '123.7961')])]);
        $this->signIn(1);

        $answer = $this->where('Loon', 'Agsoso');

        $this->assertSame(true, $answer['found']);
        $this->assertSame('town', $answer['precision']);
        $this->assertSame('Loon', $answer['label']);
        $this->assertSame('9.803300, 123.796100', $answer['coordinates']);
        Http::assertSentCount(2);
    }

    public function test_a_result_outside_bohol_or_in_another_town_is_not_used(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([
            self::place('Boctol', 'place', 'village', 'Balilihan', '14.5995', '120.9842'),
            self::place('Boctol', 'place', 'village', 'Sagbayan', '9.9', '124.1'),
        ])]);
        $this->signIn(1);

        $answer = $this->where('Balilihan', 'Boctol');

        $this->assertSame(false, $answer['found']);
        $this->assertArrayNotHasKey('error', $answer);
    }

    public function test_nothing_found_is_kept_for_a_while(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([])]);
        $this->signIn(1);

        $this->assertSame(['found' => false], $this->where('Balilihan', 'Boctol'));
        $this->where('Balilihan', 'Boctol');

        Http::assertSentCount(2); // one search for the barangay and one for the town, only the first time
    }

    public function test_a_failed_request_is_never_kept(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::sequence()->push('busy', 503)->push([$this->boctol()])]);
        $this->signIn(1);

        $this->assertSame(['found' => false, 'error' => true], $this->where('Balilihan', 'Boctol'), 'the map could not be asked');
        $this->assertSame(true, $this->where('Balilihan', 'Boctol')['found'], 'the next try asks again');
    }

    public function test_a_branch_admin_always_gets_their_own_branch(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([$this->boctol()])]);
        $this->signIn(2);

        $this->where('Loon', 'Boctol');

        Http::assertSent(fn (Request $request) => $request['q'] === 'Boctol, Balilihan, Bohol, Philippines');
    }

    public function test_a_barangay_the_branch_does_not_have_is_looked_up_as_the_town_and_an_unknown_branch_is_not_looked_up(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([self::place('Balilihan', 'boundary', 'administrative', 'Balilihan', '9.75', '123.97')])]);
        $this->signIn(1);

        $answer = $this->where('Balilihan', 'Agsoso');
        $this->assertSame('town', $answer['precision']);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request['q'] === 'Balilihan, Bohol, Philippines');

        Http::fake();
        $this->assertSame(['found' => false], $this->where('Atlantis', 'Boctol'));
        Http::assertNothingSent();
    }

    public function test_the_employee_app_asks_for_a_barangay_of_its_own_branch(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([$this->boctol()])]);

        $answer = $this->getJson('/backend/api/barangay_location.php?barangay=Boctol', $this->appHeaders(1))->assertOk()->json();

        $this->assertTrue($answer['success']);
        $this->assertTrue($answer['found']);
        $this->assertSame('9.768206, 123.993066', $answer['coordinates']);

        $this->getJson('/backend/api/barangay_location.php?barangay=Boctol')->assertUnauthorized();
    }

    public function test_the_register_form_knows_where_to_ask_and_has_a_note_for_the_coordinates(): void
    {
        $this->signIn(2);

        $html = $this->get('/clients')->assertOk()->getContent();

        $this->assertStringContainsString('id="registerCoordinatesNote"', $html);
        $this->assertStringContainsString(json_encode(route('locations.geocode')), $html);
        $this->assertStringContainsString('fillCoordinatesFromBarangay', $html);
    }
}
