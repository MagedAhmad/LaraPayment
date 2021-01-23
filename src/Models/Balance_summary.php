<?php

namespace MagedAhmad\LaraPayment\Models;

use Illuminate\Database\Eloquent\Model;

class Balance_Summary extends Model
{
    protected $guarded = [];
    
    protected $table = "balance_summaries";
    
    protected $casts = [
        'payment_response' => 'array'
    ];
}
