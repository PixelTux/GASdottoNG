<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

use Illuminate\Support\Collection;

use DB;
use Log;
use URL;

use App\User;
use App\Aggregate;
use App\BookedProductVariant;
use App\BookedProductComponent;
use App\Events\BookingDelivered;

/*
    Questa classe è destinata ad essere estesa dai Controller che maneggiano
    le prenotazioni, ed in particolare il loro aggiornamento.
*/

class BookingHandler extends Controller
{
    private function initVariant($booked, $quantity, $delivering, $values)
    {
        if ($quantity == 0) {
            return null;
        }

        $bpv = new BookedProductVariant();
        $bpv->product_id = $booked->id;

        if ($delivering == false) {
            $bpv->quantity = $quantity;
            $bpv->delivered = 0;
        }
        else {
            $bpv->quantity = 0;
            $bpv->delivered = $quantity;
        }

        $bpv->save();

        foreach ($values as $variant_id => $value_id) {
            $bpc = new BookedProductComponent();
            $bpc->productvariant_id = $bpv->id;
            $bpc->variant_id = $variant_id;
            $bpc->value_id = $value_id;
            $bpc->save();
        }

        return $bpv;
    }

    private function findVariant($booked, $values, $saved_variants)
    {
        $query = BookedProductVariant::where('product_id', $booked->id);

        foreach ($values as $variant_id => $value_id) {
            $query->whereHas('components', function ($q) use ($variant_id, $value_id) {
                $q->where('variant_id', $variant_id)->where('value_id', $value_id);
            });
        }

        return $query->whereNotIn('id', $saved_variants)->first();
    }

    private function adjustVariantValues($values, $i)
    {
        $real_values = [];

        foreach($values as $variant_id => $vals) {
            if (isset($vals[$i]) && !empty($vals[$i])) {
                $real_values[$variant_id] = $vals[$i];
            }
        }

        return $real_values;
    }

    private function touchingParam($delivering)
    {
        if ($delivering == false) {
            return 'quantity';
        }
        else {
            return 'delivered';
        }
    }

    private function readVariants($product, $booked, $values, $quantities, $delivering)
    {
        $param = $this->touchingParam($delivering);
        $quantity = 0;
        $saved_variants = [];

        for ($i = 0; $i < count($quantities); ++$i) {
            $q = (float) $quantities[$i];
            if ($q == 0) {
                continue;
            }

            $real_values = $this->adjustVariantValues($values, $i);
            if (empty($real_values)) {
                continue;
            }

            $bpv = $this->findVariant($booked, $real_values, $saved_variants);

            if (is_null($bpv)) {
                $bpv = $this->initVariant($booked, $q, $delivering, $real_values);
                if (is_null($bpv)) {
                    continue;
                }
            }
            else {
                if ($q == 0 && $delivering == false) {
                    $bpv->delete();
                    continue;
                }

                if ($bpv->$param != $q) {
                    $bpv->$param = $q;
                    $bpv->save();
                }
            }

            $saved_variants[] = $bpv->id;
            $quantity += $q;
        }

        /*
            Attenzione: in fase di consegna/salvataggio è lecito che una
            quantità sia a zero, ma ciò non implica eliminare la variante
        */
        if ($delivering == false) {
            BookedProductVariant::where('product_id', '=', $booked->id)->whereNotIn('id', $saved_variants)->delete();
        }

        /*
            Per ogni evenienza qui ricarico le varianti appena salvate, affinché
            il computo del prezzo totale finale per il prodotto risulti corretto
        */
        $booked->load('variants');

        return [$booked, $quantity];
    }

    public function readBooking($request, $order, $user, $delivering)
    {
        $param = $this->touchingParam($delivering);
        $booking = $order->userBooking($user->id);

        if ($request->has('notes_' . $order->id)) {
            $booking->notes = $request->input('notes_' . $order->id) ?: '';
        }

        $booking->save();

        $count_products = 0;
        $booked_products = new Collection();

        /*
            In caso di ordini chiusi ma con confezioni da completare, ci
            sono un paio di casi speciali...
            O sto prenotando tra i prodotti da completare, e dunque devo
            intervenire solo su di essi (nel form booking.edit viene
            aggiunto un campo nascosto "limited") senza intaccare le
            quantità già prenotate degli altri, oppure sono un
            amministratore e sto intervenendo sull'intera prenotazione
            (dunque posso potenzialmente modificare tutto).
        */
        if ($request->has('limited')) {
            $products = $order->status == 'open' ? $order->products : $order->pendingPackages();
        }
        else {
            $products = $order->products;
        }

        foreach ($products as $product) {
            /*
                $booking->getBooked() all'occorrenza crea un nuovo
                BookedProduct, che deve essere salvato per potergli agganciare
                le varianti.
                Ma se la quantità è 0 (e bisogna badare che lo sia in caso di
                varianti che senza varianti) devo evitare di salvare tale
                oggetto temporaneo, che andrebbe solo a complicare le cose nel
                database
            */

            $quantity = (float) $request->input($product->id, 0);
            if (empty($quantity)) {
                $quantity = 0;
            }

            if ($product->variants->isEmpty() == false) {
                $quantities = $request->input('variant_quantity_' . $product->id);
                if (empty($quantities)) {
                    continue;
                }
            }

            $booked = $booking->getBooked($product, true);

            if ($quantity != 0 || !empty($quantities)) {
                $booked->save();

                if ($product->variants->isEmpty() == false) {
                    $values = [];
                    foreach ($product->variants as $variant) {
                        $values[$variant->id] = $request->input('variant_selection_' . $variant->id);
                    }

                    list($booked, $quantity) = $this->readVariants($product, $booked, $values, $quantities, $delivering);
                }
            }

            if ($delivering == false && $quantity == 0) {
                $booked->delete();
            }
            else {
                if ($booked->$param != 0 || $quantity != 0) {
                    $booked->$param = $quantity;
                    $booked->save();

                    $count_products++;
                    $booked_products->push($booked);
                }
            }
        }

        /*
            Attenzione: se sto consegnando, e tutte le quantità sono a 0,
            comunque devo preservare i dati della prenotazione
        */
        if ($delivering == false && $count_products == 0) {
            $booking->delete();
            return null;
        }
        else {
            $booking->setRelation('products', $booked_products);
            return $booking;
        }
    }

    public function bookingUpdate(Request $request, $aggregate_id, $user_id, $delivering)
    {
        DB::beginTransaction();

        $user = $request->user();
        $target_user = User::find($user_id);
        $aggregate = Aggregate::findOrFail($aggregate_id);

        if ($target_user->testUserAccess() == false && $user->can('supplier.shippings', $aggregate) == false) {
            abort(503);
        }

        foreach ($aggregate->orders as $order) {
            $booking = $this->readBooking($request, $order, $target_user, $delivering);
            if ($booking && $delivering) {
                BookingDelivered::dispatch($booking, $request->input('action'), $user);
            }
        }

        /*
            In contesti diversi ritorno risposte diverse, da cui dipende
            l'header che verrà visualizzato chiudendo il pannello su cui si è
            operato
        */
        if ($delivering == false) {
            if ($user_id != $user->id && $target_user->isFriend() && $target_user->parent_id == $user->id) {
                /*
                    Ho effettuato una prenotazione per un amico
                */
                return $this->successResponse([
                    'id' => $aggregate->id,
                    'header' => $target_user->printableFriendHeader($aggregate),
                    'url' => URL::action('BookingUserController@show', ['booking' => $aggregate_id, 'user' => $user_id])
                ]);
            }
            else {
                /*
                    Ho effettuato una prenotazione per me o per un utente di
                    primo livello (non un amico)
                */
                return $this->successResponse([
                    'id' => $aggregate->id,
                    'header' => $aggregate->printableUserHeader(),
                    'url' => URL::action('BookingController@show', $aggregate->id)
                ]);
            }
        }
        else {
            $subject = $aggregate->bookingBy($user_id);
            $subject->generateReceipt();

            $total = $subject->total_delivered;

            if ($total == 0) {
                return $this->successResponse();
            }
            else {
                return $this->successResponse([
                    'id' => $subject->id,
                    'header' => $subject->printableHeader(),
                    'url' => URL::action('DeliveryUserController@show', ['delivery' => $aggregate_id, 'user' => $user_id])
                ]);
            }
        }
    }
}
