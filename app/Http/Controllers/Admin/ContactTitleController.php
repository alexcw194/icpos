<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactTitle;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactTitleController extends Controller
{
    public function index()
    {
        $rows = ContactTitle::ordered()->paginate(20);

        return view('admin.contact_titles.index', compact('rows'));
    }

    public function create()
    {
        $title = new ContactTitle(['is_active' => true, 'sort_order' => 0]);

        return view('admin.contact_titles.create', compact('title'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', 'unique:contact_titles,name'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        ContactTitle::create($data);

        return redirect()->route('contact-titles.index')->with('success', 'Contact title saved.');
    }

    public function edit(ContactTitle $contactTitle)
    {
        return view('admin.contact_titles.edit', ['title' => $contactTitle]);
    }

    public function update(Request $request, ContactTitle $contactTitle)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', Rule::unique('contact_titles', 'name')->ignore($contactTitle->id)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        $contactTitle->update($data);

        return redirect()->route('contact-titles.index')->with('success', 'Contact title updated.');
    }
}
