<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payments extends Model
{
     protected $table = 'payments';
     protected $primaryKey = 'id';
     public $timestamps = false;
     protected $fillable = [
        'company_id',
        'date',
        'srlno',
        'mno',
        'membername',
        'remarks',
        'towardscode',
        'towards',
        'modeofpmtcode',
        'modeofpmtname',
        'amount',
        'chqno',
        'chqamount',
        'bankname',
        'brname',
        'acntno',
        'entrydate',
        'entryby',
        'modifydate',
        'modifyby',
        'isallowflag',
        'loanacntno',
        'loantypecode',
        'loantypename',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'entryby');
    }
}
