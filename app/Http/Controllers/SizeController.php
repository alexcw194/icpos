<?php

// app/Http/Controllers/SizeController.php
namespace App\Http\Controllers;

use App\Models\Size;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class SizeController extends Controller
{
    public function index(){
        $sizes = Size::orderBy('sort_order')->orderBy('name')->paginate(20);
        return view('sizes.index', compact('sizes'));
    }
    public function create(Request $r) {
        if ($r->boolean('modal')) {
            return view('sizes._modal_create'); // form di dalam modal
        }
        return view('sizes.create');
    }       
    public function store(Request $r){
        $data = $r->validate([
            'name'=>['required','max:50', Rule::unique('sizes','name')->ignore($size->id ?? null)],
            'description'=>['nullable','max:255'],
            'is_active'=>['sometimes','boolean'],
            'sort_order'=>['nullable','integer'],
        ]);
        $data['slug'] = Str::slug($data['name']);
        $data['is_active'] = $r->boolean('is_active', true);
        Size::create($data);
        return redirect()->route('sizes.index')->with('ok','Size ditambahkan.');
    }
    public function edit(Request $r, Size $size) {
        if ($r->boolean('modal')) {
            return view('sizes._modal_edit', compact('size'));
        }
        return view('sizes.edit', compact('size'));
    }
    public function update(Request $r, Size $size){
        $data = $r->validate([
            'name'=>['required','max:50', Rule::unique('sizes','name')->ignore($size->id ?? null)],
            'description'=>['nullable','max:255'],
            'is_active'=>['sometimes','boolean'],
            'sort_order'=>['nullable','integer'],
        ]);
        $data['slug'] = Str::slug($data['name']);
        $data['is_active'] = $r->boolean('is_active', true);
        $size->update($data);
        return back()->with('ok','Size diperbarui.');
    }
    public function destroy(Size $size){
        if ($size->items()->exists()) {
            return back()->with('error','Tidak bisa dihapus karena sudah digunakan item. Nonaktifkan saja.');
        }
        $size->delete();
        return back()->with('ok','Size dihapus.');
    }
}
