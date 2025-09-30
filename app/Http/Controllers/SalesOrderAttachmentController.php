<?php

namespace App\Http\Controllers;

use App\Models\SalesOrder;
use App\Models\SalesOrderAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SalesOrderAttachmentController extends Controller
{
    /**
     * POST /sales-orders/upload
     * Simpan file sebagai draft attachment berdasarkan draft_token.
     */
    public function upload(Request $request)
    {
        // mode EDIT: langsung ke SO
        if ($request->filled('sales_order_id')) {
            $data = $request->validate([
                'sales_order_id' => ['required','exists:sales_orders,id'],
                'file'           => ['required','file','mimes:pdf,jpg,jpeg,png','max:20480'],
            ]);

            $so   = SalesOrder::findOrFail($data['sales_order_id']);
            $disk = 'public';
            $dir  = "so_attachments/{$so->id}";
            $path = $request->file('file')->store($dir, $disk);

            $att = SalesOrderAttachment::create([
                'sales_order_id' => $so->id,
                'draft_token'    => null,
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
                'name' => (string) ($att->original_name ?: basename($att->path)),
                'url'  => Storage::disk($disk)->url($path),
            ], 201);
        }

        // mode CREATE: simpan sebagai draft
        $data = $request->validate([
            'draft_token' => ['required','string','max:64'],
            'file'        => ['required','file','mimes:pdf,jpg,jpeg,png','max:20480'],
        ]);

        $disk = 'public';
        $dir  = 'so_attachments/_drafts/'.$data['draft_token'];
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

        \Log::info('ATT.uploaded', ['token' => $data['draft_token'], 'path' => $path]);

        return response()->json([
            'ok'          => true,
            'id'          => (int) $att->id,
            'name'        => (string) ($att->original_name ?: basename($att->path)),
            'url'         => \Storage::disk($disk)->url($path),
            'draft_token' => $data['draft_token'] ?? null, // <—
        ], 201);
    }


    /**
     * GET /sales-orders/attachments?draft_token=...
     * Daftar draft attachments berdasar token.
     * Selalu JSON murni, tanpa echo/HTML.
     */
    public function index(Request $request)
    {
        $token = $request->query('draft_token');
        $soId  = $request->query('sales_order_id');

        if ($soId) {
            $rows = SalesOrderAttachment::where('sales_order_id', $soId)->get();
        } elseif ($token) {
            // LOG DI SINI
            \Log::info('ATT.index', [
                'token' => $token,
                'cnt'   => SalesOrderAttachment::where('draft_token', $token)->count(),
                'null_so_cnt' => SalesOrderAttachment::where('draft_token', $token)
                            ->whereNull('sales_order_id')->count(),
            ]);

            $rows = SalesOrderAttachment::where('draft_token', $token)
                ->whereNull('sales_order_id')
                ->orderByDesc('id')
                ->get();
        } else {
            return response()->json([], 200);
        }

        $out = $rows->map(function ($att) {
            $disk = $att->disk ?: 'public';
            return [
                'id'   => (int) $att->id,
                'name' => (string) ($att->original_name ?: basename($att->path)),
                'size' => (int) ($att->size ?: 0),
                'url'  => Storage::disk($disk)->url($att->path),
            ];
        });

        return response()->json($out, 200)->header('Cache-Control', 'no-store');
    }


    /**
     * DELETE /sales-orders/attachments/{attachment}
     * Hapus satu draft attachment.
     */
    public function destroy(SalesOrderAttachment $attachment)
    {
        $disk = $attachment->disk ?: 'public';
        if ($attachment->path) {
            Storage::disk($disk)->delete($attachment->path);
        }
        $attachment->delete();

        return response()->noContent(); // 204
    }

    /**
     * Util: pindah semua draft -> lampiran SO final (dipanggil saat create SO sukses).
     */
    public static function attachFromDraft(string $draftToken, SalesOrder $so): void
    {
        $rows = SalesOrderAttachment::where('draft_token', $draftToken)->get();
        foreach ($rows as $att) {
            $disk = $att->disk ?: 'public';
            $old  = $att->path;
            $filename = basename($old);
            $new = "so_attachments/{$so->id}/{$filename}";
            if ($old && Storage::disk($disk)->exists($old)) {
                Storage::disk($disk)->makeDirectory("so_attachments/{$so->id}");
                Storage::disk($disk)->move($old, $new);
            } else {
                $new = $old;
            }
            $att->update([
                'sales_order_id' => $so->id,
                'draft_token'    => null,
                'path'           => $new,
                'uploaded_by'    => optional(request()->user())->id,
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
