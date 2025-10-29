<?php

// app/Models/Size.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Size extends Model
{
    protected $fillable = ['name','slug','description','is_active','sort_order'];
    protected $casts    = ['is_active'=>'boolean','sort_order'=>'integer'];

    public function scopeActive($q){ return $q->where('is_active', true); }
    public function scopeOrdered($q){ return $q->orderBy('sort_order')->orderBy('name'); }
    public function items(){ return $this->hasMany(Item::class); }
}
