<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PluginsVersion extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];
    protected $with = ['creator'];
    protected $casts = [
        'settings' => 'json'
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }
}
