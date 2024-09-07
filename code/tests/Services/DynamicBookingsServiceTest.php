<?php

namespace Tests\Services;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;

use App\Exceptions\AuthException;
use App\Booking;

class DynamicBookingsServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function setUp(): void
    {
        parent::setUp();

        $this->order = $this->initOrder(null);
        $this->userWithBasePerms = $this->createRoleAndUser($this->gas, 'supplier.book,users.subusers');
    }

    /*
        Lettura dinamica della prenotazione
    */
    public function testSimple()
    {
        $this->actingAs($this->userWithBasePerms);

        list($data, $booked_count, $total) = $this->randomQuantities($this->order->products);
        $data['action'] = 'booked';
        app()->make('BookingsService')->bookingUpdate($data, $this->order->aggregate, $this->userWithBasePerms, false);

        $this->nextRound();

        list($data2, $booked_count2, $total2) = $this->randomQuantities($this->order->products);
        $data2['action'] = 'booked';
        $ret = app()->make('DynamicBookingsService')->dynamicModifiers($data2, $this->order->aggregate, $this->userWithBasePerms);

        $this->assertEquals(count($ret->bookings), 1);

        foreach($ret->bookings as $b) {
            $this->assertEquals($b->total, $total2);
            $this->assertEquals(count($b->modifiers), 0);

            $this->assertEquals(count(array_filter($b->products, function($p) {
                return $p->quantity != 0;
            })), $booked_count2);

            foreach($b->products as $pid => $p) {
                $target_product = null;
                foreach($this->order->products as $prod) {
                    if ($prod->id == $pid) {
                        $target_product = $prod;
                        break;
                    }
                }

                $this->assertEquals($p->total, $target_product->price * $data2[$pid] ?? 0);
                $this->assertEquals($p->quantity, $data2[$pid] ?? 0);
                $this->assertEquals(count($p->variants), 0);
                $this->assertEquals($p->message, '');
            }
        }

        $this->nextRound();

        $booking = Booking::where('order_id', $this->order->id)->where('user_id', $this->userWithBasePerms->id)->first();
        $this->assertEquals($booking->getValue('effective', true), $total);
        $this->assertEquals($booking->products()->count(), $booked_count);
    }

    /*
        Prenotazioni e consegne in presenza di amici
    */
    public function testFriend()
    {
        $friend = $this->createFriend($this->userWithBasePerms);
        $this->actingAs($friend);

        list($data_friend, $booked_count_friend, $total_friend) = $this->randomQuantities($this->order->products);
        $data_friend['action'] = 'booked';
        app()->make('BookingsService')->bookingUpdate($data_friend, $this->order->aggregate, $friend, false);

        $this->nextRound();

        $booking = $this->order->userBooking($this->userWithBasePerms->id);
        $this->assertEquals($booking->getValue('effective', true), $total_friend);

        $this->nextRound();

        $this->actingAs($this->userWithBasePerms);
        list($data_master, $booked_count_master, $total_master) = $this->randomQuantities($this->order->products);
        $data_master['action'] = 'booked';
        app()->make('BookingsService')->bookingUpdate($data_master, $this->order->aggregate, $this->userWithBasePerms, false);

        $this->nextRound();

        $booking = Booking::where('order_id', $this->order->id)->where('user_id', $this->userWithBasePerms->id)->first();
        $this->assertEquals($booking->getValue('effective', true), $total_master + $total_friend);

        $this->nextRound();

        $this->actingAs($this->userWithShippingPerms);
        $data = $this->mergeBookingQuantity($data_master, $data_friend);
        $data['action'] = 'shipped';
        $ret = app()->make('DynamicBookingsService')->dynamicModifiers($data, $this->order->aggregate, $this->userWithBasePerms);

        $this->assertEquals(count($ret->bookings), 1);

        foreach($ret->bookings as $b) {
            $this->assertEquals($b->total, $total_master + $total_friend);
            $this->assertEquals(count($b->modifiers), 0);

            foreach($b->products as $pid => $p) {
                $target_product = null;
                foreach($this->order->products as $prod) {
                    if ($prod->id == $pid) {
                        $target_product = $prod;
                        break;
                    }
                }

                $this->assertEquals($p->quantity, $data[$pid] ?? 0);
                $this->assertEquals($p->message, '');
            }
        }
    }

    /*
        Lettura dinamica della prenotazione con permessi sbagliati
    */
    public function testFailsToRead()
    {
        $this->actingAs($this->userWithBasePerms);
        $ret = app()->make('DynamicBookingsService')->dynamicModifiers(['action' => 'booked'], $this->order->aggregate, $this->userWithShippingPerms);
        $this->assertEquals('error', $ret->status);
    }

    /*
        Lettura dinamica della prenotazione con permessi corretti
    */
    public function testReferrerReads()
    {
        $this->actingAs($this->userWithShippingPerms);
        $ret = app()->make('DynamicBookingsService')->dynamicModifiers(['action' => 'booked'], $this->order->aggregate, $this->userWithBasePerms);
        $this->assertEquals(count($ret->bookings), 0);
    }

    /*
        Lettura dinamica delle prenotazioni, prodotti con pezzatura
    */
    public function testPortions()
    {
        $this->actingAs($this->userWithBasePerms);

        $product = $this->order->products->random();
        $product->portion_quantity = 0.3;
        $product->save();

        $data = [
            'action' => 'booked',
            $product->id => 2,
        ];

        $ret = app()->make('DynamicBookingsService')->dynamicModifiers($data, $this->order->aggregate, $this->userWithBasePerms);

        $this->assertEquals(count($ret->bookings), 1);

        foreach($ret->bookings as $b) {
            $this->assertEquals(count($b->products), 1);
            $this->assertEquals($b->total, round($product->price * 0.3 * 2, 2));

            foreach($b->products as $pid => $p) {
                $this->assertEquals($p->quantity, 2);
                $this->assertEquals($p->total, round($product->price * 0.3 * 2, 2));
            }
        }
    }

    private function contraintOnProduct($ret, $expected_message)
    {
        $this->assertEquals(count($ret->bookings), 1);

        foreach($ret->bookings as $b) {
            $this->assertEquals(count($b->products), 1);

            foreach($b->products as $pid => $p) {
                $this->assertEquals($p->quantity, 0);
                $this->assertEquals($p->message, $expected_message);
            }
        }
    }

    /*
        Lettura dinamica della prenotazione, vincolo sul minimo
    */
    public function testConstraintMinimum()
    {
        $this->actingAs($this->userWithBasePerms);

        $product = $this->order->products->random();
        $product->min_quantity = 3;
        $product->save();

        $data = [
            'action' => 'booked',
            $product->id => 2,
        ];

        $ret = app()->make('DynamicBookingsService')->dynamicModifiers($data, $this->order->aggregate, $this->userWithBasePerms);
        $this->contraintOnProduct($ret, 'Quantità inferiore al minimo consentito');
    }


    /*
        Lettura dinamica della prenotazione, vincolo sul multiplo
    */
    public function testConstraintMultiple()
    {
        $this->actingAs($this->userWithBasePerms);

        $product = $this->order->products->random();
        $product->multiple = 2;
        $product->save();

        $data = [
            'action' => 'booked',
            $product->id => 5,
        ];

        $ret = app()->make('DynamicBookingsService')->dynamicModifiers($data, $this->order->aggregate, $this->userWithBasePerms);
        $this->contraintOnProduct($ret, 'Quantità non multipla del valore consentito');
    }

    /*
        Lettura dinamica della prenotazione, vincolo sul massimo disponibile
    */
    public function testConstraintMaximum()
    {
        $this->actingAs($this->userWithBasePerms);

        $product = $this->order->products->random();
        $product->max_available = 10;
        $product->save();

        $data = [
            'action' => 'booked',
            $product->id => 11,
        ];

        $ret = app()->make('DynamicBookingsService')->dynamicModifiers($data, $this->order->aggregate, $this->userWithBasePerms);
        $this->contraintOnProduct($ret, 'Quantità superiore alla disponibilità');
    }

    /*
        Lettura dinamica di una consegna manuale
    */
    public function testManualShipping()
    {
        $this->actingAs($this->userWithBasePerms);

        list($data, $booked_count, $total) = $this->randomQuantities($this->order->products);
        $data['action'] = 'booked';
        app()->make('BookingsService')->bookingUpdate($data, $this->order->aggregate, $this->userWithBasePerms, false);

        $this->nextRound();

        $booking = $this->order->userBooking($this->userWithBasePerms);
        $actual_total = $booking->getValue('effective', true);

        $this->nextRound();

        $this->actingAs($this->userWithShippingPerms);

        $data['action'] = 'shipped';
        $data['manual_total_' . $this->order->id] = 100;
        $ret = app()->make('DynamicBookingsService')->dynamicModifiers($data, $this->order->aggregate, $this->userWithBasePerms);

        $this->assertEquals(1, count($ret->bookings));

        foreach($ret->bookings as $b) {
            $this->assertEquals($b->total, 100);

            $this->assertEquals(count($b->modifiers), 1);
            foreach($b->modifiers as $m) {
                $this->assertEquals(100, $actual_total + $m->amount);
            }
        }
    }

    private function attachDiscount($product)
    {
        $this->actingAs($this->userReferrer);

        $mod = null;
        $modifiers = $product->applicableModificationTypes();
        foreach ($modifiers as $mod) {
            if ($mod->id == 'sconto') {
                $mod = $product->modifiers()->where('modifier_type_id', $mod->id)->first();
                app()->make('ModifiersService')->update($mod->id, [
                    'value' => 'percentage',
                    'arithmetic' => 'sub',
                    'scale' => 'minor',
                    'applies_type' => 'none',
                    'applies_target' => 'product',
                    'simplified_amount' => 10,
                ]);

                break;
            }
        }

        $this->assertNotNull($mod);
        $this->nextRound();
    }

    /*
        Lettura dinamica delle prenotazioni, prodotto con modificatori
    */
    public function testModifiersOnProductBooking()
    {
        $product = $this->order->products->random();
        $this->attachDiscount($product);

        $this->actingAs($this->userWithBasePerms);

        $data = [
            'action' => 'booked',
            $product->id => 2,
        ];

        $ret = app()->make('DynamicBookingsService')->dynamicModifiers($data, $this->order->aggregate, $this->userWithBasePerms);

        $this->assertEquals(count($ret->bookings), 1);

        foreach($ret->bookings as $b) {
            $this->assertEquals($b->total, $product->price * 2 - ($product->price * 0.10 * 2));

            $this->assertEquals(count($b->modifiers), 1);
            foreach($b->modifiers as $mid => $mod) {
                $this->assertEquals($mod->amount, round($product->price * 0.10 * 2, 2) * -1);
            }

            $this->assertEquals(count($b->products), 1);
            foreach($b->products as $pid => $p) {
                $this->assertEquals($p->quantity, 2);
                $this->assertEquals($p->total, $product->price * 2);
            }
        }
    }

    /*
        Lettura dinamica delle consegne, prodotto con modificatori
    */
    public function testModifiersOnProductShipping()
    {
        $product = $this->order->products->random();
        $this->attachDiscount($product);

        $this->actingAs($this->userWithBasePerms);

        $data = [
            'action' => 'booked',
            $product->id => 2,
        ];

        app()->make('BookingsService')->bookingUpdate($data, $this->order->aggregate, $this->userWithBasePerms, false);

        $this->nextRound();

        $this->actingAs($this->userWithShippingPerms);

        $data = [
            'action' => 'shipped',
            $product->id => 2,
        ];

        $ret = app()->make('DynamicBookingsService')->dynamicModifiers($data, $this->order->aggregate, $this->userWithBasePerms);

        $this->assertEquals(count($ret->bookings), 1);

        foreach($ret->bookings as $b) {
            $this->assertEquals($b->total, $product->price * 2 - ($product->price * 0.10 * 2));

            $this->assertEquals(count($b->modifiers), 1);
            foreach($b->modifiers as $mid => $mod) {
                $this->assertEquals($mod->amount, round($product->price * 0.10 * 2, 2) * -1);
            }

            $this->assertEquals(count($b->products), 1);
            foreach($b->products as $pid => $p) {
                $this->assertEquals($p->quantity, 2);
                $this->assertEquals($p->total, $product->price * 2);
            }
        }
    }

    /*
        Lettura dinamica delle prenotazioni, prodotto con varianti e modificatori
    */
    public function testModifiersOnProductAndVariants()
    {
        $this->actingAs($this->userReferrer);

        $product = $this->order->products->random();

        $variant = app()->make('VariantsService')->store([
            'product_id' => $product->id,
            'name' => 'Colore',
            'id' => ['', '', ''],
            'value' => ['Rosso', 'Verde', 'Blu'],
        ]);

        $this->nextRound();

        $this->attachDiscount($product);

        $this->actingAs($this->userWithBasePerms);

        $data = [
            'action' => 'booked',
            $product->id => 0,
            'variant_quantity_' . $product->id => [2],
            'variant_selection_' . $variant->id => [$variant->values()->first()->id],
        ];

        $ret = app()->make('DynamicBookingsService')->dynamicModifiers($data, $this->order->aggregate, $this->userWithBasePerms);

        $this->assertEquals(count($ret->bookings), 1);

        foreach($ret->bookings as $b) {
            $this->assertEquals(count($b->products), 1);
			$this->assertTrue($b->total > 0);
            $this->assertEquals($b->total, $product->price * 2 - ($product->price * 0.10 * 2));
            $this->assertEquals(count($b->modifiers), 1);

            foreach($b->products as $pid => $p) {
                $this->assertEquals($p->quantity, 2);
                $this->assertEquals($p->total, $product->price * 2);
            }
        }
    }

    /*
        Lettura dinamica delle prenotazioni, modificatore su ordine
    */
    public function testModifiersOnOrder()
    {
        $this->actingAs($this->userReferrer);

        /*
            Creo modificatore su ordine
        */
        $mod = null;
        $modifiers = $this->order->applicableModificationTypes();
        foreach ($modifiers as $mod) {
            if ($mod->id == 'sconto') {
                $mod = $this->order->modifiers()->where('modifier_type_id', $mod->id)->first();
                app()->make('ModifiersService')->update($mod->id, [
                    'value' => 'percentage',
                    'arithmetic' => 'sub',
                    'scale' => 'major',
                    'applies_type' => 'price',
                    'applies_target' => 'order',
                    'distribution_type' => 'price',
                    'threshold' => [1000],
                    'amount' => [10],
                ]);

                break;
            }
        }

        $this->assertNotNull($mod);
        $product = $this->order->products->random();

        /*
            Effettuo prenotazione senza attivare modificatori
        */

        $this->nextRound();
        $this->actingAs($this->userWithBasePerms);

        $data = [
            'action' => 'booked',
            $product->id => 2,
        ];

        $ret = app()->make('DynamicBookingsService')->dynamicModifiers($data, $this->order->aggregate, $this->userWithBasePerms);

        $this->assertEquals(count($ret->bookings), 1);

        foreach($ret->bookings as $b) {
            $this->assertTrue($b->total < 1000);
            $this->assertEquals(count($b->products), 1);
            $this->assertEquals($b->total, $product->price * 2);
            $this->assertEquals(count($b->modifiers), 0);
        }

        /*
            Creo una prenotazione che faccia attivare i modificatori
        */

        $this->nextRound();
        $this->actingAs($this->userReferrer);

        $data = ['action' => 'booked'];
        $total = 0;

        foreach($this->order->products as $p) {
            $data[$p->id] = 100;
            $total += $p->price * 100;
        }

        $this->assertTrue($total > 100);
        $this->updateAndFetch($data, $this->order, $this->userReferrer, false);
        $booking = Booking::where('order_id', $this->order->id)->where('user_id', $this->userReferrer->id)->first();
        $this->assertNotNull($booking);

        /*
            Aggiorno prenotazione con modificatori attivi
        */

        $this->nextRound();
        $this->actingAs($this->userWithBasePerms);

        $data = [
            'action' => 'booked',
            $product->id => 2,
        ];

        $ret = app()->make('DynamicBookingsService')->dynamicModifiers($data, $this->order->aggregate, $this->userWithBasePerms);

        $this->assertEquals(count($ret->bookings), 1);

        foreach($ret->bookings as $b) {
            $this->assertEquals(count($b->products), 1);
            $this->assertEquals($b->total, $product->price * 2 - ($product->price * 0.10 * 2));
            $this->assertEquals(count($b->modifiers), 1);
        }
    }
}
