<?php

// app/Http/Controllers/SalesOrderAttachmentController.php
namespace App\Http\Controllers;

use App\Models\SalesOrder;
use App\Models\SalesOrderAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class SalesOrderAttachmentController extends Controller
{
    // POST /sales-orders/upload
    public function upload(Request $request)
    {
        $request->validate([
            'file'        => ['required','file','mimes:pdf,jpg,jpeg,png','max:5120'],
            'draft_token' => ['required','string'],
        ]);

        $file = $request->file('file');
        $token = $request->input('draft_token');

        // simpan ke storage publik di folder draft
        $path = $file->store("so_attachments/_drafts/{$token}", 'public');

        // catat ke tabel draft
        $id = \DB::table('so_draft_attachments')->insertGetId([
            'draft_token'   => $token,
            'path'          => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime'          => $file->getClientMimeType(),
            'size'          => $file->getSize(),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        return response()->json([
            'id'   => $id,
            'name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'url'  => asset('storage/' . $path),
        ], 201);
    }


    // GET /sales-orders/attachments?draft_token=... or ?sales_order_id=...
    public function index(Request $request)
    {
        // LIST draft attachments (tanpa SO)
        if ($request->filled('draft_token')) {
            $token = $request->query('draft_token');

            $rows = \DB::table('so_draft_attachments')
                ->where('draft_token', $token)
                ->orderBy('id')
                ->get()
                ->map(function ($r) {
                    return [
                        'id'   => $r->id,
                        'name' => $r->original_name,
                        'size' => (int) $r->size,
                        'url'  => asset('storage/' . $r->path),
                    ];
                });

            return response()->json($rows); // <— PENTING: JSON murni
        }

        // (opsional) kalau tanpa token & tanpa SO, balas array kosong
        return response()->json([]);
    }

    // DELETE /sales-orders/attachments/{id}
    public function destroy(Request $request, SalesOrderAttachment $attachment = null)
    {
        // Hapus draft (tanpa SO)
        if ($request->filled('draft_token') && $request->filled('id')) {
            $row = \DB::table('so_draft_attachments')
                ->where('id', $request->input('id'))
                ->where('draft_token', $request->input('draft_token'))
                ->first();

            if ($row) {
                \Storage::disk('public')->delete($row->path);
                \DB::table('so_draft_attachments')->where('id', $row->id)->delete();
            }
            return response()->json(['ok' => true]);
        }

        // (mode lama) hapus attachment yang sudah melekat ke SO
        // ... kode kamu sebelumnya ...
        // tetapi pastikan untuk request AJAX sebaiknya juga return JSON
    }


    // util: assign dari draft ke SO (dipakai saat create SO sukses)
    public static function attachFromDraft(string $draftToken, SalesOrder $so): void
    {
        $atts = SalesOrderAttachment::whereNull('sales_order_id')
            ->where('draft_token', $draftToken)->get();

        foreach ($atts as $att) {
            $newPath = str_replace("sales_orders/draft/{$draftToken}",
                                   "sales_orders/{$so->id}", $att->path);
            if (Storage::disk('public')->exists($att->path)) {
                Storage::disk('public')->move($att->path, $newPath);
            }
            $att->update([
                'sales_order_id' => $so->id,
                'draft_token'    => null,
                'path'           => $newPath,
            ]);
        }
    }

    // util: bersihkan draft (dipakai saat Cancel)
    public static function purgeDraft(string $draftToken): void
    {
        $atts = SalesOrderAttachment::where('draft_token',$draftToken)->get();
        foreach ($atts as $a) {
            Storage::disk($a->disk)->delete($a->path);
            $a->delete();
        }
        Storage::disk('public')->deleteDirectory('so_attachments/_drafts/'.$draftToken);
    }
}
