<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Hold;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\InventoryService;

class CombinedFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected $inventoryService;

    public function setUp(): void
    {
        parent::setUp();
        
        $this->inventoryService = app(InventoryService::class);
    }

    /**
     * Test the combined cases: Hold expiry, Parallel holds, Webhook before order creation, Webhook idempotency.
     *
     * @return void
     */
    public function test_combined_cases()
    {
        $this->testHoldExpiryReturnsAvailability();

        $this->testParallelHoldBoundary();

        $this->testWebhookBeforeOrderCreation();

        $this->testWebhookIdempotency();

        $this->testOrderWithTwoWebhooks();
    }

    public function testHoldExpiryReturnsAvailability()
    {
        $product = Product::create([
            'name' => 'Product 1',
            'price' => 10.00,
            'stock_quantity' => 10,
            'reserved' => 0,
            'sold' => 0,
        ]);

        $productBody = [
            'product_id' => $product->id,
            'qty' => 5,
        ];

        $response = $this->postJson('http://localhost:8000/api/holds', $productBody);

        $response->assertStatus(201);
        $holdData = $response->json(); 

        $hold = Hold::find($holdData['hold_id']);
        $product->refresh();

        $this->assertSame(10, $product->stock_quantity); 
        $this->assertSame(5, $product->reserved);
        $this->assertSame(5, $product->available()); 

        $hold->update(['hold_expires_at' => now()->subMinutes(2)]);

        $this->artisan('holds:release-expired', [
            '--batch' => 200,
            '--max-seconds' => 2
        ])
        ->assertExitCode(0);

        $product->refresh();
        $hold->refresh();

        $this->assertSame('expired', $hold->status);
        $this->assertSame(10, $product->stock_quantity); 
        $this->assertSame(0, $product->reserved);         
        $this->assertSame(0, $product->sold);           
    }

    public function testParallelHoldBoundary()
    {
        $product = Product::create([
            'name' => 'Product 1',
            'price' => 10.00,
            'stock_quantity' => 10,
            'reserved' => 0,
            'sold' => 0,
        ]);

        $ok = 0;
        $quantity = 1;
        $batchSize = 10;

        $productBody = [
            'product_id' => $product->id,
            'qty' => $quantity,
        ];

        for ($i = 0; $i < $batchSize; $i++) {
            $hold = $this->postJson('http://localhost:8000/api/holds', $productBody);
            if ($hold) {
                $ok++;
            }
        }

        $this->assertSame(10, $ok, 'Exactly stock_quantity holds should succeed at qty=1');
        $product->refresh();
        $this->assertSame(10, $product->reserved);
        $this->assertSame(0, $product->sold);
        $this->assertTrue(($product->reserved + $product->sold) <= $product->stock_quantity);
    }

    public function testWebhookBeforeOrderCreation()
    {
        $webhookPayload = [
            'order_id' => 123,
            'success' => true,
        ];

        $response = $this->withHeader('Idempotency-Key', 'evt_before_create')
                         ->postJson('http://localhost:8000/api/webhook/payment', $webhookPayload);

        $response->assertStatus(404); 
    }

    public function testWebhookIdempotency()
    {
        $product = Product::create([
            'name' => 'Test Product',
            'price' => 10.00,
            'stock_quantity' => 10,
            'reserved' => 0,
            'sold' => 0,
        ]);

        $order = Order::create([
            'product_id' => $product->id,
            'quantity' => 1,
            'total_price' => $product->price,
            'status' => 'pending',
        ]);

        $webhookPayload = [
            'order_id' => $order->id,
            'success' => true,
        ];

        $response1 = $this->withHeader('Idempotency-Key', 'evt_001')
                        ->postJson('http://localhost:8000/api/webhook/payment', $webhookPayload);
        $response1->assertOk();

        $response = $this->withHeader('Idempotency-Key', 'evt_001')
                        ->postJson('http://localhost:8000/api/webhook/payment', $webhookPayload);
        $response->assertOk();

        $this->assertEquals($response1->getContent(), $response->getContent());
    }

    public function testOrderWithTwoWebhooks()
    {
        $product = Product::create([
            'name' => 'Test Product 1',
            'price' => 10.00,
            'stock_quantity' => 10,
            'reserved' => 0,
            'sold' => 0,
        ]);


        $order = Order::create([
            'product_id' => $product->id,
            'quantity' => 1,
            'total_price' => $product->price,
            'status' => 'pending',
        ]);


        $webhookPayload = [
            'order_id' => $order->id,
            'success' => false,
        ];

        $response = $this->withHeader('Idempotency-Key', 'evt_002')
                        ->postJson('http://localhost:8000/api/webhook/payment', $webhookPayload);
        $response->assertOk();

        $this->artisan('order:cancel-stale-orders')
            ->assertExitCode(0);

        $order->refresh();
        $this->assertSame('pending', $order->status);

        $webhookPayload2 = [
            'order_id' => $order->id,
            'success' => true,
        ];

        $response2 = $this->withHeader('Idempotency-Key', 'evt_003')
                        ->postJson('http://localhost:8000/api/webhook/payment', $webhookPayload2);
        $response2->assertOk();

        $order->refresh();
        $this->assertSame('paid', $order->status);
    }

}
