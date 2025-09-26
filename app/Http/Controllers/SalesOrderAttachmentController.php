<?php

namespace App\Http\Controllers;

use App\Models\SalesOrder;
use App\Models\SalesOrderAttachment;
use Illuminate\Http\Request;

class SalesOrderAttachmentController extends Controller
{
    /**
     * POST /sales-orders/upload
     * Simpan file sebagai draft attachment berdasarkan draft_token.
     */
    public function upload(Request $request)
    {
        $data = $request->validate([
            'draft_token' => ['required', 'string', 'max:64'],
            'file'        => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:20480'], // 20MB
        ]);

        $disk = 'public';
        $dir  = 'so_attachments/_drafts/' . $data['draft_token'];
        $path = $request->file('file')->store($dir, $disk);

        $att = SalesOrderAttachment::create([
            'sales_order_id' => null,
            'draft_token'    => $data['draft_token'],
            'disk'           => $disk,
            'path'           => $path,
            'original_name'  => $request->file('file')->getClientOriginalName(),
            'mime'           => $request->file('file')->getClientMimeType(),
            'size'           => $request->file('file')->getSize(),
            'uploaded_by'    => optional($request->user())->id,
        ]);

        return response()->json([
            'ok'   => true,
            'id'   => (int) $att->id,
            'name' => (string) $att->original_name,
        ], 201);
    }

    /**
     * GET /sales-orders/attachments?draft_token=...
     * Daftar draft attachments berdasar token.
     * Selalu JSON murni, tanpa echo/HTML.
     */
    public function index(Request $request)
    {
        $draft = (string) $request->query('draft_token', '');

        if ($draft === '') {
            return response()->json([]);
        }

        $rows = SalesOrderAttachment::query()
            ->whereNull('sales_order_id')               // hanya draft
            ->where('draft_token', $draft)
            ->orderBy('id')
            ->get()
            ->map(function (SalesOrderAttachment $r) {
                $disk = $r->disk ?: 'public';
                $url  = \Storage::disk($disk)->url($r->path);
                return [
                    'id'   => (int) $r->id,
                    'name' => (string) $r->original_name,
                    'size' => (int) ($r->size ?? 0),
                    'url'  => $url,
                ];
            })
            ->values();

        return response()->json($rows);
    }

    /**
     * DELETE /sales-orders/attachments/{attachment}
     * Hapus satu draft attachment.
     */
    public function destroy(SalesOrderAttachment $attachment)
    {
        $disk = $attachment->disk ?: 'public';
        if ($attachment->path) {
            \Storage::disk($disk)->delete($attachment->path);
        }
        $attachment->delete();

        return response()->noContent();
    }

    /**
     * Util: pindah semua draft -> lampiran SO final (dipanggil saat create SO sukses).
     */
    public static function attachFromDraft(string $draftToken, SalesOrder $so): void
    {
        $atts = SalesOrderAttachment::whereNull('sales_order_id')
            ->where('draft_token', $draftToken)
            ->get();

        foreach ($atts as $att) {
            $disk    = $att->disk ?: 'public';
            $oldPath = $att->path;                                 // so_attachments/_drafts/{token}/file.ext
            $file    = basename((string) $oldPath);
            $newDir  = "so_attachments/{$so->id}";
            $newPath = "{$newDir}/{$file}";

            if ($oldPath && \Storage::disk($disk)->exists($oldPath)) {
                \Storage::disk($disk)->makeDirectory($newDir);
                \Storage::disk($disk)->move($oldPath, $newPath);
            } else {
                // kalau file fisik tidak ada, jangan kosongkan path
                $newPath = $oldPath;
            }

            $att->update([
                'sales_order_id' => $so->id,
                'draft_token'    => null,
                'path'           => $newPath,
                'uploaded_by'    => auth()->id(),
            ]);
        }
    }

    /**
     * Util: bersihkan semua draft untuk token (dipakai saat Cancel).
     */
    public static function purgeDraft(string $draftToken): void
    {
        $atts = SalesOrderAttachment::where('draft_token', $draftToken)->get();

        foreach ($atts as $a) {
            $disk = $a->disk ?: 'public';
            if ($a->path) {
                \Storage::disk($disk)->delete($a->path);
            }
            $a->delete();
        }

        \Storage::disk('public')->deleteDirectory('so_attachments/_drafts/' . $draftToken);
    }
}
