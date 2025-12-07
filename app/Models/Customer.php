<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'address',
        'adharno',
        'pan_no',
        'phone_no',
        'gender',
        'is_prime_user',
        'is_red_flag',
    ];

    protected $casts = [
        'is_prime_user' => 'boolean',
        'is_red_flag' => 'boolean',
    ];
}

