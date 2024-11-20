<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferralLog extends Model
{
    use HasFactory;
    protected $fillable = ['referral_code_id', 'referrer_user_id', 'referred_user_id', 'status','point_credit_user_id','points','amount','currency'];

}
