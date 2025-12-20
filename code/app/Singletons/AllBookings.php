<?php

namespace App\Singletons;

use App\Booking;

class AllBookings
{
    public function allPendingBookings()
    {
        $cache = app()->make('TempCache');
        $ret = $cache->get('all_pending_bookings');

        if ($ret == null) {
            $ret = Booking::where('status', 'pending')->whereHas('order', function ($query) {
                $query->whereIn('status', ['open', 'closed']);
            })->angryload()->with([
                'order', 'order.aggregate', 'order.modifiers', 'order.products',
                'circles',
                'products.booking', 'products.variants.product', 'products.booking.order.products',
            ])->get();

            $cache->put('all_pending_bookings', $ret);
        }

        return $ret;
    }
}
