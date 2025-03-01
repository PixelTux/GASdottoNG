<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateUsersTable extends Migration
{
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->timestamps();
            $table->string('updated_by')->default('');
            $table->softDeletes();

            $table->date('suspended_at')->nullable()->default(null);
            $table->boolean('pending')->default(false);

            $table->string('gas_id');
            $table->string('parent_id')->nullable();
            $table->string('username')->unique();
            $table->string('firstname');
            $table->string('lastname');
            $table->string('password');
            $table->boolean('enforce_password_change')->default(false);
            $table->string('access_token')->default('');
            $table->date('birthday')->nullable();
            $table->integer('family_members')->unsigned()->nullable();
            $table->string('picture')->default('');
            $table->string('taxcode')->default('');
            $table->date('member_since')->useCurrent();
            $table->string('card_number')->default('');
            $table->datetime('last_login')->nullable();
            $table->string('preferred_delivery_id')->default('0');
            $table->string('payment_method_id')->default('none');
            $table->text('rid')->nullable();
            $table->boolean('tour')->default(false);

            $table->integer('fee_id')->nullable()->default(null);
            $table->integer('deposit_id')->nullable()->default(null);

            $table->rememberToken();

            $table->foreign('gas_id')->references('id')->on('gas');
        });
    }

    public function down()
    {
        Schema::drop('users');
    }
}
