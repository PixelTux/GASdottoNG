<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use GeneaLabs\LaravelModelCaching\Traits\Cachable;

use App\Models\Concerns\LeafReducibleTrait;

class BookedProductVariant extends Model
{
    use Cachable, GASModel, LeafReducibleTrait;

    public function product(): BelongsTo
    {
        return $this->belongsTo(BookedProduct::class, 'product_id');
    }

    public function components(): HasMany
    {
        return $this->hasMany(BookedProductComponent::class, 'productvariant_id')->with(['value']);
    }

    public function getStatusAttribute()
    {
        return $this->product->status;
    }

    public function hasCombination($variant_id, $value_id)
    {
        $components = $this->components;

        foreach ($components as $c) {
            if ($c->variant_id == $variant_id && $c->value_id == $value_id) {
                return true;
            }
        }

        return false;
    }

    public function variantsCombo()
    {
        $values = [];

        foreach ($this->components as $c) {
            $values[] = $c->value_id;
        }

        sort($values);
        $myid = implode(' ', $values);

        return $this->product->product->variantCombos->first(function ($v) use ($myid) {
            return $v->innerIdentifier() == $myid;
        });
    }

    public function unitPrice($rectify = true)
    {
        $combo = $this->variantsCombo();

        if ($combo) {
            return $combo->getPrice($rectify);
        }
        else {
            return $this->product->product->getPrice($rectify);
        }
    }

    public function fixWeight($attribute)
    {
        $base = $this->product->basicWeight($attribute);

        $combo = $this->variantsCombo();
        if ($combo) {
            $base += $combo->weight_offset * $this->$attribute;
        }

        return $base;
    }

    private function fixQuantity($attribute, $rectify)
    {
        return $this->unitPrice($rectify) * $this->$attribute;
    }

    public function quantityValue()
    {
        return $this->fixQuantity('quantity', true);
    }

    public function deliveredValue()
    {
        return $this->fixQuantity('delivered', false);
    }

    public function printableName()
    {
        $combo = $this->variantsCombo();
        if ($combo) {
            return $combo->printableShortName();
        }
        else {
            return '';
        }
    }

    private function normalizeQuantity($attribute)
    {
        $product = $this->product->product;
        if ($product->portion_quantity != 0) {
            return $this->$attribute * $product->portion_quantity;
        }
        else {
            return $this->$attribute;
        }
    }

    public function getTrueQuantityAttribute()
    {
        return $this->normalizeQuantity('quantity');
    }

    public function getTrueDeliveredAttribute()
    {
        return $this->normalizeQuantity('delivered');
    }

    public function getSupplierCodeAttribute()
    {
        $combo = $this->variantsCombo();
        if ($combo) {
            return $combo->code;
        }
        else {
            return '';
        }
    }

    /********************************************************* ReducibleTrait */

    protected function reduxBehaviour()
    {
        /*
            Essendo la variante prenotata la foglia più estrema dell'albero di
            riduzione, questa funzione non dovrebbe mai essere chiamata
        */
        throw new \BadMethodCallException('Invocata funzione reduxBehaviour() su BookedProductVariant', 1);
    }

    public function reduxData($filters = null)
    {
        $ret = (object) [
            'id' => $this->printableName(),
            'variant' => $this,

            'relative_price' => 0,
            'relative_weight' => 0,
            'relative_quantity' => 0,
            'relative_pieces' => 0,
        ];

        $ret = $this->describingAttributesMerge($ret, (object) [
            'price' => $this->quantityValue(),
            'weight' => $this->fixWeight('quantity'),
            'quantity' => $this->product->product->portion_quantity > 0 ? $this->quantity * $this->product->product->portion_quantity : $this->quantity,
            'quantity_pieces' => $this->quantity,
            'price_delivered' => $this->deliveredValue(),
            'weight_delivered' => $this->fixWeight('delivered'),
            'delivered' => $this->delivered,

            /*
                Cfr. nota in BookedProduct
            */
            'delivered_pieces' => $this->delivered,
        ]);

        return $this->relativeRedux($ret);
    }
}
