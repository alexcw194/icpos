<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\DocumentCounter;
use Illuminate\Http\Request;

class DocumentCounterController extends Controller
{
    private const ALLOWED_TYPES = ['quotation', 'invoice', 'delivery', 'document'];

    public function index(Request $request)
    {
        $companyId = $request->input('company_id');
        $year      = $request->input('year');

        $query = DocumentCounter::query()
            ->with('company:id,name,alias')
            ->whereIn('doc_type', self::ALLOWED_TYPES)
            ->orderBy('year', 'desc')
            ->orderBy('doc_type')
            ->orderBy('company_id');

        if ($companyId) {
            $query->where('company_id', $companyId);
        }
        if ($year) {
            $query->where('year', (int) $year);
        }

        $counters  = $query->get();
        $companies = Company::orderBy('name')->get(['id', 'alias', 'name']);

        return view('admin.document_counters.index', [
            'counters'  => $counters,
            'companies' => $companies,
            'companyId' => $companyId,
            'year'      => $year,
            'types'     => self::ALLOWED_TYPES,
        ]);
    }

    public function update(Request $request, DocumentCounter $documentCounter)
    {
        if (!in_array($documentCounter->doc_type, self::ALLOWED_TYPES, true)) {
            abort(403, 'Doc type is not allowed.');
        }

        $data = $request->validate([
            'last_seq' => ['required', 'integer', 'min:0'],
        ]);

        $documentCounter->update([
            'last_seq' => (int) $data['last_seq'],
        ]);

        return back()->with('success', 'Counter updated.');
    }
}
