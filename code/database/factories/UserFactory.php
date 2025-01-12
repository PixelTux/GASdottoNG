<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

use App\User;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition()
    {
        do {
            $username = $this->faker->userName();
        }
        while (User::where('username', $username)->count() != 0);

        do {
            $firstname = $this->faker->firstName();
            $lastname = $this->faker->lastName();
        }
        while (User::where('firstname', $firstname)->where('lastname', $lastname)->count() != 0);

        return [
            'username' => $username,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'password' => Hash::make(Str::random(10)),
            'member_since' => date('Y-m-d H:i:s'),
            'card_number' => Str::random(20),
        ];
    }
}
