<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Booking;

trait BookerTrait
{
    /*
        Reminder: non cadere nella tentazione di non utilizzare esplicitamente
        questi trait in User, altrimenti non funzionano alcuni controlli (e.g.
        la visualizzazione del credito utente nel modale di pagamento consegna)
    */
    use CreditableTrait, FriendTrait {
        scopeCreditable as overriddenScopeCreditable;
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class)->orderBy('created_at', 'desc');
    }

    public function getLastBookingAttribute()
    {
        return $this->innerCache('last_booking_date', function ($obj) {
            $last = $obj->bookings()->first();

            if ($last == null) {
                return null;
            }
            else {
                return $last->created_at;
            }
        });
    }

    public function canBook()
    {
        if ($this->gas->hasFeature('restrict_booking_to_credit')) {
            if ($this->isFriend()) {
                return $this->parent->canBook();
            }
            else {
                return $this->activeBalance() > $this->gas->restrict_booking_to_credit['limit'];
            }
        }
        else {
            return true;
        }
    }

    /*
        Questa funzione ritorna la cifra dovuta dall'utente per le prenotazioni
        fatte dall'utente e non ancora pagate, ma senza considerare anche gli
        eventuali amici
    */
    public function getPendingBalanceAttribute()
    {
        $all_bookings = app()->make('AllBookings')->allPendingBookings();
        $bookings = $all_bookings->filter(fn($b) => $b->user_id == $this->id);

        $value = 0;

        foreach ($bookings as $b) {
            $value += $b->getValue('effective', false);
        }

        return $value;
    }

    /*
        Ritorna la cifra dovuta dall'utente per le prenotazioni fatte e non
        ancora pagate, considerando anche gli amici
    */
    public function getPendingBalanceWithFriendsAttribute()
    {
        $total = $this->pending_balance;

        foreach($this->friends as $friend) {
            $total += $friend->pending_balance;
        }

        return $total;
    }

    /*
        Attenzione: questa funzione ritorna solo il saldo in euro
    */
    public function activeBalance()
    {
        if ($this->isFriend()) {
            return $this->parent->activeBalance();
        }
        else {
            $current_balance = $this->currentBalanceAmount();
            $to_pay = $this->pending_balance;

            foreach ($this->friends as $friend) {
                $tpf = $friend->pending_balance;
                $to_pay += $tpf;
            }

            return $current_balance - $to_pay;
        }
    }

    /*
        Genera la notifica relativa ad altre prenotazioni (oltre a quella
        dell'aggregato specificato) che devono essere ritirate dall'utente nel
        corso della giornata. Viene aggiunta nel pannello delle consegne e nel
        Dettaglio Consegne PDF
    */
    public function morePendingBookings($aggregate)
    {
        $other_bookings = $this->bookings()->where('status', 'pending')->whereHas('order', function ($query) use ($aggregate) {
            $query->where('aggregate_id', '!=', $aggregate->id)->where('shipping', $aggregate->shipping);
        })->get();

        if ($other_bookings->isEmpty() === false) {
            $notice = __('texts.user.pending_deliveries');
            $notice .= '<ul>';

            foreach ($other_bookings as $ob) {
                $notice .= '<li>' . $ob->order->printableName() . '</li>';
            }

            $notice .= '</ul>';

            return $notice;
        }

        return null;
    }

    /******************************************************** CreditableTrait */

    public function scopeCreditable($query)
    {
        $query->whereNull('parent_id');
    }

    public function balanceFields()
    {
        return [
            'bank' => __('texts.movements.credit'),
        ];
    }
}
