<?php

namespace Tests\Feature;

use App\Support\NapBoxes;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BranchTestData;
use Tests\TestCase;

/**
 * The photo of a NAP box: the branch admin sets, replaces and removes it on the NAP Boxes page, the admin pages and the employee
 * app show it, and a branch only ever sees its own boxes' photos.
 *
 * Seed: NAP-01 (box 1, Boctol, Balilihan, ports 1-4) and NAP-02 (box 2, Agsoso, Loon). Users: 1 super admin, 2 Balilihan admin,
 * 3 Loon admin. Employees: 1 (Balilihan), 2 (Loon). Balilihan has 5 NAP Box items in stock.
 */
class NapBoxPhotoTest extends TestCase
{
    use BranchTestData;

    /** A real 1 x 1 PNG. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createBranchSchema();
        $this->seedBranchData();
        DB::connection()->getPdo()->sqliteCreateFunction('GREATEST', fn (...$values) => max($values), -1);
        DB::table('inventory')->insert(['id' => 50, 'item_name' => 'NAP Box 8-port', 'sku' => 'SKU-50', 'category' => 'NAP Box', 'quantity' => 5, 'unit_price' => 900, 'reorder_level' => 1, 'branch_name' => 'Balilihan']);
        NapBoxes::create('Balilihan', 'NAP-01', 'Boctol', 'Purok 2', 4);
        NapBoxes::create('Loon', 'NAP-02', 'Agsoso', null, 2);
    }

    private function png(): string
    {
        return 'data:image/png;base64,' . self::PNG;
    }

    private function newBox(array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->post('/nap-boxes', ['action' => 'save_box', 'name' => 'NAP-03', 'barangay' => 'Boctol', 'total_ports' => 8] + $extra);
    }

    private function editBox(array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->post('/nap-boxes', ['action' => 'save_box', 'box_id' => 1, 'name' => 'NAP-01', 'barangay' => 'Boctol', 'total_ports' => 4, 'status' => 'active'] + $extra);
    }

    public function test_a_box_is_added_with_its_photo_and_the_photo_is_shown(): void
    {
        $this->signIn(2);
        $this->newBox(['photo_data' => $this->png()])->assertOk();

        $box = DB::table('nap_boxes')->where('name', 'NAP-03')->first();
        $this->assertSame('image/png', DB::table('nap_box_photos')->where('nap_box_id', $box->id)->value('mime_type'));

        $page = $this->get('/nap-boxes?box=' . $box->id)->assertOk();
        $boxes = collect($page->viewData('boxes'))->keyBy('name');
        $this->assertTrue($boxes['NAP-03']['has_photo']);
        $this->assertNotNull($boxes['NAP-03']['photo_version']);
        $this->assertFalse($boxes['NAP-01']['has_photo']);
        $this->assertStringContainsString('src="' . route('nap-boxes.photo', $box->id), $page->getContent());

        $photo = $this->get('/nap-boxes/' . $box->id . '/photo')->assertOk();
        $photo->assertHeader('Content-Type', 'image/png');
        $this->assertSame(base64_decode(self::PNG), $photo->getContent());
    }

    public function test_a_box_without_a_photo_is_fine_and_has_no_picture(): void
    {
        $this->signIn(2);
        $this->newBox()->assertOk();

        $this->assertSame(0, DB::table('nap_box_photos')->count());
        $this->get('/nap-boxes/1/photo')->assertNotFound();
        $this->assertStringNotContainsString('class="nap-photo" src', $this->get('/nap-boxes?box=1')->getContent());
    }

    public function test_a_wrong_photo_saves_nothing_and_keeps_the_stock(): void
    {
        $this->signIn(2);
        $cases = [
            'not a picture type' => 'data:text/html;base64,' . base64_encode('<script>alert(1)</script>'),
            'text called png' => 'data:image/png;base64,' . base64_encode('this is not a picture'),
            'jpeg that is a png' => 'data:image/jpeg;base64,' . self::PNG,
            'not base64' => 'data:image/png;base64,@@@@',
            'no data address' => self::PNG,
            'too large' => 'data:image/png;base64,' . str_repeat('A', (int) (NapBoxes::PHOTO_MAX_BYTES * 4 / 3) + 100),
        ];
        foreach ($cases as $name => $data) {
            $error = $this->newBox(['photo_data' => $data])->viewData('error');
            $this->assertNotSame('', (string) $error, $name);
            $this->assertStringContainsString('photo', strtolower((string) $error), $name);
        }

        $this->assertSame(0, DB::table('nap_boxes')->where('name', 'NAP-03')->count());
        $this->assertSame(0, DB::table('nap_box_photos')->count());
        $this->assertSame(5, (int) DB::table('inventory')->where('id', 50)->value('quantity'), 'no NAP box was taken from the stock');
    }

    public function test_the_photo_is_replaced_kept_and_removed(): void
    {
        $this->signIn(2);
        $this->editBox(['photo_data' => $this->png()])->assertOk();
        $this->assertSame(1, DB::table('nap_box_photos')->where('nap_box_id', 1)->count());

        // Saving the box again without a new photo keeps the one it has.
        $this->editBox(['location' => 'Near the chapel'])->assertOk();
        $this->assertSame(1, DB::table('nap_box_photos')->where('nap_box_id', 1)->count());

        // A new picture replaces it (still one row).
        DB::table('nap_box_photos')->where('nap_box_id', 1)->update(['mime_type' => 'image/jpeg', 'data' => 'old']);
        $this->editBox(['photo_data' => $this->png()])->assertOk();
        $row = DB::table('nap_box_photos')->where('nap_box_id', 1)->first();
        $this->assertSame('image/png', $row->mime_type);
        $this->assertSame(self::PNG, $row->data);
        $this->assertSame(1, DB::table('nap_box_photos')->count());

        $this->editBox(['remove_photo' => '1'])->assertOk();
        $this->assertSame(0, DB::table('nap_box_photos')->count());
        $this->get('/nap-boxes/1/photo')->assertNotFound();
    }

    public function test_a_branch_only_sees_the_photos_of_its_own_boxes(): void
    {
        NapBoxes::savePhoto(1, $this->png());
        NapBoxes::savePhoto(2, $this->png());

        $this->signIn(2);
        $this->get('/nap-boxes/1/photo')->assertOk();
        $this->get('/nap-boxes/2/photo')->assertNotFound();

        $this->signIn(1);
        $this->get('/nap-boxes/1/photo')->assertOk();
        $this->get('/nap-boxes/2/photo')->assertOk();

        auth()->logout();
        $this->get('/nap-boxes/1/photo')->assertRedirect();
    }

    public function test_the_super_admin_cannot_change_a_photo(): void
    {
        $this->signIn(1);

        $this->editBox(['photo_data' => $this->png()])->assertOk();

        $this->assertSame(0, DB::table('nap_box_photos')->count());
    }

    public function test_the_app_gets_the_photo_of_a_box_of_its_branch(): void
    {
        $box = $this->getJson('/backend/api/nap_box.php?nap_box_id=1', $this->appHeaders(1))->assertOk();
        $box->assertJsonPath('data.has_photo', false);
        $this->getJson('/backend/api/nap_box_photo.php?nap_box_id=1', $this->appHeaders(1))->assertNotFound();

        NapBoxes::savePhoto(1, $this->png());
        NapBoxes::savePhoto(2, $this->png());

        $box = $this->getJson('/backend/api/nap_box.php?nap_box_id=1', $this->appHeaders(1))->assertOk();
        $box->assertJsonPath('data.has_photo', true);
        $this->assertNotNull($box->json('data.photo_version'));
        $list = $this->getJson('/backend/api/nap_boxes.php', $this->appHeaders(1))->assertOk()->json('data');
        $this->assertTrue($list[0]['has_photo']);

        $photo = $this->get('/backend/api/nap_box_photo.php?nap_box_id=1', $this->appHeaders(1))->assertOk();
        $photo->assertHeader('Content-Type', 'image/png');
        $this->assertSame(base64_decode(self::PNG), $photo->getContent());

        $this->get('/backend/api/nap_box_photo.php?nap_box_id=2', $this->appHeaders(1))->assertNotFound();
        $this->getJson('/backend/api/nap_box_photo.php?nap_box_id=1')->assertUnauthorized();
    }
}
