<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use GeneaLabs\LaravelModelCaching\Traits\Cachable;

use Illuminate\Support\Str;

use App;
use Log;

use App\Events\SluggableCreating;

class Product extends Model
{
    use HasFactory, SoftDeletes, ModifiableTrait, GASModel, SluggableID, Cachable;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $dispatchesEvents = [
        'creating' => SluggableCreating::class,
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo('App\Category');
    }

    public function measure()
    {
        return $this->belongsTo('App\Measure');
    }

    public function supplier()
    {
        return $this->belongsTo('App\Supplier');
    }

    public function orders()
    {
        return $this->belongsToMany('App\Order');
    }

    public function variants()
    {
        return $this->hasMany('App\Variant')->with('values')->orderBy('name', 'asc');
    }

    public function vat_rate()
    {
        return $this->belongsTo('App\VatRate');
    }

    public function getSlugID()
    {
        return sprintf('%s::%s', $this->supplier_id, Str::slug($this->name));
    }

    public function getPictureUrlAttribute()
    {
        if (empty($this->picture))
            return '';
        else
            return url('products/picture/' . $this->id);
    }

    public function getFixedPackageSizeAttribute()
    {
        if ($this->portion_quantity <= 0) {
            return $this->package_size;
        }
        else {
            return round($this->portion_quantity * $this->package_size, 2);
        }
    }

    public function getVariantCombosAttribute()
    {
        $product = $this;

        return VariantCombo::whereHas('values', function($query) use ($product) {
            $query->whereHas('variant', function($query) use ($product) {
                $query->where('product_id', $product->id);
            });
        })->get();
    }

    public function getCategoryNameAttribute()
    {
        $cat = $this->category;
        if ($cat)
            return $cat->name;
        else
            return '';
    }

    public function stillAvailable($order)
    {
        if ($this->max_available == 0) {
            return 0;
        }

        $product = $this;

        $quantity = App::make('GlobalScopeHub')->executedForAll($order->keep_open_packages != 'each', function() use ($product, $order) {
            return BookedProduct::where('product_id', '=', $product->id)->whereHas('booking', function ($query) use ($order) {
                $query->where('order_id', '=', $order->id);
            })->sum('quantity');
        });

        if ($this->portion_quantity != 0) {
            $quantity *= $this->portion_quantity;
        }

        return $this->max_available - $quantity;
    }

    public function bookingsInOrder($order)
    {
        $id = $this->id;

        return Booking::where('order_id', '=', $order->id)->whereHas('products', function ($query) use ($id) {
            $query->where('product_id', '=', $id);
        })->get();
    }

    public function printablePrice($order)
    {
        $price = $this->contextualPrice($order, false);
        $currency = currentAbsoluteGas()->currency;
        $str = sprintf('%.02f %s / %s', $price, $currency, $this->measure->name);

        if ($this->variable) {
            $str .= '<small> <span class="d-none d-sm-block">' . _i('(prodotto a prezzo variabile)') . '</span><span class="d-block d-sm-none">' . _i('(variabile)') . '</span></small>';
        }

        return $str;
    }

    /*
        Attenzione: questo non tiene conto dell'eventuale sconto
        applicato sull'ordine in cui il prodotto si trova
    */
    public function getDiscountPriceAttribute()
    {
        if (empty($this->discount)) {
            return $this->price;
        } else {
            return applyPercentage($this->price, $this->discount);
        }
    }

    /*
        Per i prodotti con pezzatura, ritorna già il prezzo per singola unità
        e non è dunque necessario normalizzare ulteriormente
    */
    public function contextualPrice($order, $rectify = true)
    {
        $price = $this->price;

        if ($rectify && $this->portion_quantity != 0) {
            $price = $price * $this->portion_quantity;
        }

        return (float) $price;
    }

    public function printableMeasure($verbose = false)
    {
        if ($this->portion_quantity != 0) {
            if ($verbose) {
                return sprintf('Pezzi da %.02f %s', $this->portion_quantity, $this->measure->name);
            }
            else {
                return sprintf('%.02f %s', $this->portion_quantity, $this->measure->name);
            }
        }
        else {
            $m = $this->measure;
            if (is_null($m)) {
                return '';
            }
            else {
                return $m->name;
            }
        }
    }

    public function printableDetails($order)
    {
        $details = [];

        if ($this->min_quantity != 0) {
            $details[] = _i('Minimo: %.02f', $this->min_quantity);
        }
        if ($this->max_quantity != 0) {
            $details[] = _i('Massimo Consigliato: %.02f', $this->max_quantity);
        }
        if ($this->max_available != 0) {
            $still_available = $this->stillAvailable($order);

            // L'attributo is_pending_package non fa parte del model Product,
            // viene valorizzato staticamente da Order::pendingPackages() ai
            // prodotti per i quali si devono completare le confezioni
            // @phpstan-ignore-next-line
            if ($this->is_pending_package ?? false) {
                $details[] = _i('%s Disponibile: %.02f', [
                    sprintf('<span class="badge rounded-pill bg-primary" data-bs-toggle="popover" data-bs-trigger="hover" data-bs-content="%s" data-bs-original-title="" title="">?</span>', _i('Mancano %s %s per completare la confezione per questo ordine', [$still_available, $this->printableMeasure(true)])),
                    $still_available
                ]);
            }
            else {
                $details[] = _i('Disponibile: %.02f (%.02f totale)', [$still_available, $this->max_available]);
            }
        }
        if ($this->multiple != 0) {
            $details[] = _i('Multiplo: %.02f', $this->multiple);
        }

        return implode(', ', $details);
    }
}
