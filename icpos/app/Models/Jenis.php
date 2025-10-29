<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Jenis extends Model
{
    use SoftDeletes;

    protected $table = 'jenis'; // penting
    protected $fillable = ['name','slug','is_active','description'];
    protected $casts = ['is_active' => 'boolean'];

    public function customers() { return $this->hasMany(Customer::class); }
}
