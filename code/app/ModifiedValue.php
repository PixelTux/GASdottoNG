<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

use Log;

class ModifiedValue extends Model
{
    use CreditableTrait;

    public function modifier()
    {
        return $this->belongsTo('App\Modifier');
    }

    public function target()
    {
        return $this->morphTo();
    }

    public function getIsVariableAttribute()
    {
        return ($this->modifier->value == 'absolute' && $this->modifier->applies_target == 'order');
    }

    public function getEffectiveAmountAttribute()
    {
        switch($this->modifier->arithmetic) {
            case 'sum':
            case 'passive':
                return $this->amount;

            case 'apply':
            case 'sub':
                return $this->amount * -1;
        }
    }

    public function getDescriptiveNameAttribute()
    {
        return $this->modifier->modifierType->name . ' ' . $this->modifier->target->printableName() . ': ' . $this->modifier->name;
    }

    public function getSummary()
    {
        return $this->target->getModifiedRelations();
    }

    public static function aggregateByType($collection)
    {
        return $collection->reduce(function($carry, $value) {
            $id = $value->modifier->modifierType->id;

            if (!isset($carry[$id])) {
                $carry[$id] = (object) [
                    'name' => $value->modifier->modifierType->name,
                    'amount' => 0,
                ];
            }

            $carry[$id]->amount += $value->effective_amount;
            return $carry;
        }, []);
    }

    public function sumAmount($value)
    {
        if ($this->modifier->arithmetic != 'passive') {
            return $value + $this->effective_amount;
        }
        else {
            return $value;
        }
    }

    public static function sumAmounts($values, $starting_value = 0)
    {
        return $values->reduce(function($carry, $item) {
            return $item->sumAmount($carry);
        }, $starting_value);
    }

    private function wireMovement($class_type)
    {
        $rel = $this->target->getModifiedRelations();

        switch($class_type) {
            case 'App\Gas':
                return $rel->user->gas;

            case 'App\User':
                return $rel->user;

            case null:
            case '':
                return null;

            default:
                return $this;
        }
    }

    public function generateMovement($master_movement)
    {
        if ($this->modifier->arithmetic == 'passive' || $this->effective_amount == 0) {
            return;
        }

        $type = $this->modifier->movementType;
        if (is_null($type)) {
            return;
        }

        $movement = new Movement();
        $movement->type = $type->id;

        $sender = $this->wireMovement($type->sender_type);
        if ($sender) {
            $movement->sender_type = get_class($sender);
            $movement->sender_id = $sender->id;
        }

        $target = $this->wireMovement($type->target_type);
        if ($target) {
            $movement->target_type = get_class($target);
            $movement->target_id = $target->id;
        }

        $movement->amount = $this->effective_amount;
        $movement->currency_id = $master_movement->currency_id;
        $movement->date = date('Y-m-d');
        $movement->method = $master_movement->method;
        $movement->related_id = $master_movement->id;

        $movement->save();
    }

    /******************************************************** CreditableTrait */

    public function getBalanceProxy()
    {
        $rel = $this->target->getModifiedRelations();
        return $rel->supplier;
    }

    public static function balanceFields()
    {
        return [
            'bank' => _i('Saldo'),
        ];
    }

    public static function commonClassName()
    {
        return 'Modificatore';
    }
}
