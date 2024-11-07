<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferralCode extends Model
{
    use HasFactory;
    protected $fillable = ['referral_campaign_id','user_id', 'code'];

     public function ReferralCampaign(){
        return $this->belongsTo(ReferralCampaign::class);
    }


}
