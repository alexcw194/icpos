<?php

namespace App\Http\Controllers;

use App\Models\BqLineTemplate;
use App\Models\BqLineTemplateLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BqLineTemplateLineController extends Controller
{
    public function index(BqLineTemplate $bqLineTemplate)
    {
        $lines = $bqLineTemplate->lines()->get();

        return view('admin.bq_line_templates.lines.index', [
            'template' => $bqLineTemplate,
            'lines' => $lines,
        ]);
    }

    public function create(BqLineTemplate $bqLineTemplate)
    {
        $line = new BqLineTemplateLine([
            'type' => 'charge',
            'sort_order' => 0,
            'default_qty' => 1,
            'default_unit' => 'LS',
            'basis_type' => 'bq_product_total',
            'applies_to' => 'both',
            'editable_price' => true,
            'editable_percent' => true,
            'can_remove' => true,
        ]);

        return view('admin.bq_line_templates.lines.form', [
            'template' => $bqLineTemplate,
            'line' => $line,
        ]);
    }

    public function store(Request $request, BqLineTemplate $bqLineTemplate)
    {
        $data = $this->validateLine($request);
        $data['bq_line_template_id'] = $bqLineTemplate->id;

        BqLineTemplateLine::create($data);
        $this->normalizeSortOrder($bqLineTemplate);

        return redirect()->route('bq-line-templates.lines.index', $bqLineTemplate)
            ->with('success', 'Line created.');
    }

    public function edit(BqLineTemplate $bqLineTemplate, BqLineTemplateLine $line)
    {
        $this->ensureTemplateMatch($bqLineTemplate, $line);

        return view('admin.bq_line_templates.lines.form', [
            'template' => $bqLineTemplate,
            'line' => $line,
        ]);
    }

    public function update(Request $request, BqLineTemplate $bqLineTemplate, BqLineTemplateLine $line)
    {
        $this->ensureTemplateMatch($bqLineTemplate, $line);

        $data = $this->validateLine($request);

        $line->update($data);
        $this->normalizeSortOrder($bqLineTemplate);

        return redirect()->route('bq-line-templates.lines.index', $bqLineTemplate)
            ->with('ok', 'Line updated.');
    }

    public function destroy(BqLineTemplate $bqLineTemplate, BqLineTemplateLine $line)
    {
        $this->ensureTemplateMatch($bqLineTemplate, $line);

        $line->delete();
        $this->normalizeSortOrder($bqLineTemplate);

        return redirect()->route('bq-line-templates.lines.index', $bqLineTemplate)
            ->with('ok', 'Line deleted.');
    }

    public function moveUp(BqLineTemplate $bqLineTemplate, BqLineTemplateLine $line)
    {
        $this->ensureTemplateMatch($bqLineTemplate, $line);

        $this->moveLine($bqLineTemplate, $line, -1);

        return redirect()->route('bq-line-templates.lines.index', $bqLineTemplate);
    }

    public function moveDown(BqLineTemplate $bqLineTemplate, BqLineTemplateLine $line)
    {
        $this->ensureTemplateMatch($bqLineTemplate, $line);

        $this->moveLine($bqLineTemplate, $line, 1);

        return redirect()->route('bq-line-templates.lines.index', $bqLineTemplate);
    }

    private function validateLine(Request $request): array
    {
        $data = $request->validate([
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'type' => ['required', Rule::in(['charge', 'percent'])],
            'label' => ['required', 'string', 'max:190'],
            'default_qty' => ['nullable', 'numeric', 'min:0'],
            'default_unit' => ['nullable', 'string', 'max:20'],
            'default_unit_price' => ['nullable', 'numeric', 'min:0'],
            'percent_value' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'basis_type' => ['nullable', Rule::in(['bq_product_total', 'section_product_total'])],
            'applies_to' => ['nullable', Rule::in(['material', 'labor', 'both'])],
            'editable_price' => ['nullable', 'boolean'],
            'editable_percent' => ['nullable', 'boolean'],
            'can_remove' => ['nullable', 'boolean'],
        ]);

        $type = $data['type'] ?? 'charge';
        if ($type === 'charge') {
            $data['percent_value'] = null;
        } else {
            if (!isset($data['percent_value'])) {
                $data['percent_value'] = 0;
            }
            $data['default_qty'] = null;
            $data['default_unit'] = null;
            $data['default_unit_price'] = null;
        }

        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['default_qty'] = $data['default_qty'] !== null ? (float) $data['default_qty'] : null;
        $data['default_unit'] = $data['default_unit'] ?? null;
        $data['default_unit_price'] = $data['default_unit_price'] !== null ? (float) $data['default_unit_price'] : null;
        $data['percent_value'] = $data['percent_value'] !== null ? (float) $data['percent_value'] : null;
        $data['basis_type'] = $data['basis_type'] ?? 'bq_product_total';
        $data['applies_to'] = $data['applies_to'] ?? 'both';
        $data['editable_price'] = $request->boolean('editable_price');
        $data['editable_percent'] = $request->boolean('editable_percent');
        $data['can_remove'] = $request->boolean('can_remove');

        return $data;
    }

    private function normalizeSortOrder(BqLineTemplate $template): void
    {
        $lines = $template->lines()->orderBy('sort_order')->orderBy('id')->get();
        foreach ($lines as $idx => $line) {
            if ((int) $line->sort_order !== $idx) {
                $line->update(['sort_order' => $idx]);
            }
        }
    }

    private function moveLine(BqLineTemplate $template, BqLineTemplateLine $line, int $direction): void
    {
        DB::transaction(function () use ($template, $line, $direction) {
            $lines = $template->lines()->orderBy('sort_order')->orderBy('id')->lockForUpdate()->get();
            $this->normalizeSortOrder($template);

            $lines = $template->lines()->orderBy('sort_order')->orderBy('id')->lockForUpdate()->get();
            $currentIndex = $lines->search(fn ($row) => (int) $row->id === (int) $line->id);
            if ($currentIndex === false) {
                return;
            }

            $targetIndex = $currentIndex + $direction;
            if ($targetIndex < 0 || $targetIndex >= $lines->count()) {
                return;
            }

            $currentLine = $lines[$currentIndex];
            $targetLine = $lines[$targetIndex];

            $currentOrder = (int) $currentLine->sort_order;
            $targetOrder = (int) $targetLine->sort_order;

            $currentLine->update(['sort_order' => $targetOrder]);
            $targetLine->update(['sort_order' => $currentOrder]);
        });
    }

    private function ensureTemplateMatch(BqLineTemplate $template, BqLineTemplateLine $line): void
    {
        if ((int) $line->bq_line_template_id !== (int) $template->id) {
            abort(404);
        }
    }
}
