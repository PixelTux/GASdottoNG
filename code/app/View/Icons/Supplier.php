<?php

namespace App\View\Icons;

use App\View\Icons\Concerns\Status;

class Supplier extends IconsMap
{
    use Status;

    private static function altIcons($ret, $user)
    {
        if ($user->can('supplier.add', $user->gas)) {
            $ret = self::statusIcons($ret);
        }

        return $ret;
    }

    public static function commons($user)
    {
        $ret = [
            'pencil' => (object) [
                'test' => function ($obj) use ($user) {
                    return $user->can('supplier.modify', $obj);
                },
                'text' => _i('Puoi modificare il fornitore'),
            ],
            'card-list' => (object) [
                'test' => function ($obj) use ($user) {
                    return $user->can('supplier.orders', $obj);
                },
                'text' => _i('Puoi aprire nuovi ordini per il fornitore'),
            ],
            'arrow-down' => (object) [
                'test' => function ($obj) use ($user) {
                    return $user->can('supplier.shippings', $obj);
                },
                'text' => _i('Gestisci le consegne per il fornitore'),
            ],
        ];

        return self::altIcons($ret, $user);
    }
}
