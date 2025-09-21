<?php
// app/Http/Controllers/ColorController.php
namespace App\Http\Controllers;

use App\Models\Color;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class ColorController extends Controller
{
    public function index(){
        $colors = Color::orderBy('sort_order')->orderBy('name')->paginate(20);
        return view('colors.index', compact('colors'));
    }
    public function create(Request $r){
        if ($r->boolean('modal')) {
            return view('colors._modal_create');
        }
        return view('colors.create');
    }
    public function store(Request $r){
        $data = $r->validate([
            'name'=>['required','max:50', Rule::unique('colors','name')->ignore($color->id ?? null)],
            'hex'=>['nullable','regex:/^#[0-9A-Fa-f]{6}$/'],
            'description'=>['nullable','max:255'],
            'is_active'=>['sometimes','boolean'],
            'sort_order'=>['nullable','integer'],
        ]);
        $data['slug'] = Str::slug($data['name']);
        $data['is_active'] = $r->boolean('is_active', true);
        Color::create($data);
        return redirect()->route('colors.index')->with('ok','Color ditambahkan.');
    }
    public function edit(Request $r, Color $color){
        if ($r->boolean('modal')) {
            return view('colors._modal_edit', compact('color'));
        }
        return view('colors.edit', compact('color'));
    }
    public function update(Request $r, Color $color){
        $data = $r->validate([
            'name'=>['required','max:50', Rule::unique('colors','name')->ignore($color->id ?? null)],
            'hex'=>['nullable','regex:/^#[0-9A-Fa-f]{6}$/'],
            'description'=>['nullable','max:255'],
            'is_active'=>['sometimes','boolean'],
            'sort_order'=>['nullable','integer'],
        ]);
        $data['slug'] = Str::slug($data['name']);
        $data['is_active'] = $r->boolean('is_active', true);
        $color->update($data);
        return back()->with('ok','Color diperbarui.');
    }
    public function destroy(Color $color){
        if ($color->items()->exists()) {
            return back()->with('error','Tidak bisa dihapus karena sudah digunakan item. Nonaktifkan saja.');
        }
        $color->delete();
        return back()->with('ok','Color dihapus.');
    }
}
