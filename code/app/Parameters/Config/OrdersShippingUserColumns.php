<?php

namespace App\Parameters\Config;

class OrdersShippingUserColumns extends Config
{
    public function identifier()
    {
        return 'orders_shipping_user_columns';
    }

    public function type()
    {
        return 'array';
    }

    public function default()
    {
        return ['lastname', 'firstname'];
    }
}
