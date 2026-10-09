<?php

namespace App\Domain\Marketing;

use Illuminate\Database\Eloquent\Model;

class Enquiry extends Model
{
    protected $fillable = ['name', 'email', 'phone', 'business_name', 'message'];
}
