<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactPosition;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactPositionController extends Controller
{
    public function index()
    {
        $rows = ContactPosition::ordered()->paginate(20);

        return view('admin.contact_positions.index', compact('rows'));
    }

    public function create()
    {
        $position = new ContactPosition(['is_active' => true, 'sort_order' => 0]);

        return view('admin.contact_positions.create', compact('position'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:contact_positions,name'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        ContactPosition::create($data);

        return redirect()->route('contact-positions.index')->with('success', 'Contact position saved.');
    }

    public function edit(ContactPosition $contactPosition)
    {
        return view('admin.contact_positions.edit', ['position' => $contactPosition]);
    }

    public function update(Request $request, ContactPosition $contactPosition)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('contact_positions', 'name')->ignore($contactPosition->id)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        $contactPosition->update($data);

        return redirect()->route('contact-positions.index')->with('success', 'Contact position updated.');
    }
}
