<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveQuota extends Model
{
    protected $fillable = ['type', 'days'];

    public const TYPES = ['sick', 'casual', 'annual'];
}
