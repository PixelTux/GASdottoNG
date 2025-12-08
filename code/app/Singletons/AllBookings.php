<?php

namespace App\Singletons;

use App\Booking;

class AllBookings
{
    public function allPendingBookings()
    {
        /*
            È consigliabile non cachare questo risultato in un attributo locale
            della classe
        */
        return Booking::where('status', 'pending')->whereHas('order', function ($query) {
            $query->whereIn('status', ['open', 'closed']);
        })->angryload()->with(['order', 'products.booking', 'products.booking.order', 'products.booking.order.products'])->get();
    }
}
