<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ItemGroup extends Model
{
    use HasFactory;

    protected $fillable = ['code','name'];

    public function items()
    {
        return $this->hasMany(Item::class);
    }
}
