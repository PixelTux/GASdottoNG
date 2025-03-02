<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateBookedProductsTable extends Migration
{
    public function up()
    {
        Schema::create('booked_products', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->timestamps();
            $table->string('updated_by')->default('');

            $table->string('booking_id');
            $table->string('product_id');
            $table->decimal('quantity', 6, 2)->default(0);
            $table->decimal('delivered', 7, 3)->default(0);
            $table->decimal('final_price', 6, 2)->default(0);
            $table->decimal('final_transport', 6, 2)->default(0);

            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::drop('booked_products');
    }
}
