<?php

namespace Tests\Feature;

use Tests\TestCase;

class InventoryRequestRoutingTest extends TestCase
{
    public function test_inventory_request_decision_route_is_registered(): void
    {
        $url = route('inventory-requests.decide', [
            'inventoryRequest' => 42,
            'decision' => 'approve',
        ]);

        $this->assertSame(url('/inventory-requests/42/approve'), $url);
    }

    public function test_inventory_request_decision_route_rejects_invalid_decisions(): void
    {
        $this->post('/inventory-requests/42/invalid')->assertNotFound();
    }
}
