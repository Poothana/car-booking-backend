<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteBasicSetting extends Model
{
    protected $table = 'site_basic_settings';

    protected $fillable = [
        'key',
        'type',
        'value',
    ];
}

