<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentImageController extends Controller
{
    public function upload(Request $request)
    {
        $data = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'document_id' => ['nullable', 'integer', 'exists:documents,id'],
            'draft_token' => ['nullable', 'string', 'max:80'],
        ]);

        $file = $request->file('image');

        $document = null;
        $baseDir = null;

        if (!empty($data['document_id'])) {
            $document = Document::query()->findOrFail($data['document_id']);
            $this->authorize('update', $document);
            abort_unless($document->isEditable(), 403, 'Dokumen terkunci.');
            $baseDir = 'documents/'.$document->id.'/images';
        } else {
            $draftToken = $data['draft_token'] ?? null;
            $sessionToken = $request->session()->get('doc_draft_token');
            abort_unless($draftToken && $sessionToken && hash_equals($sessionToken, $draftToken), 403);
            $baseDir = 'documents/tmp/'.$draftToken.'/images';
        }

        $path = $this->storeResizedImage($file, $baseDir);
        $url = Storage::url($path);

        return response()->json([
            'uploaded' => 1,
            'fileName' => basename($path),
            'url' => $url,
            'path' => $path,
        ]);
    }

    private function storeResizedImage($file, string $baseDir): string
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $filename = Str::uuid()->toString().'.'.$ext;
        $dir = storage_path('app/public/'.$baseDir);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        [$width, $height] = getimagesize($file->getRealPath());
        $maxWidth = 1200;
        $targetWidth = $width;
        $targetHeight = $height;

        if ($width > $maxWidth) {
            $ratio = $maxWidth / $width;
            $targetWidth = (int) round($width * $ratio);
            $targetHeight = (int) round($height * $ratio);
        }

        $source = $ext === 'png'
            ? imagecreatefrompng($file->getRealPath())
            : imagecreatefromjpeg($file->getRealPath());

        $dest = imagecreatetruecolor($targetWidth, $targetHeight);
        if ($ext === 'png') {
            imagealphablending($dest, false);
            imagesavealpha($dest, true);
            $transparent = imagecolorallocatealpha($dest, 0, 0, 0, 127);
            imagefilledrectangle($dest, 0, 0, $targetWidth, $targetHeight, $transparent);
        }

        imagecopyresampled($dest, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        $fullPath = $dir.'/'.$filename;
        if ($ext === 'png') {
            imagepng($dest, $fullPath);
        } else {
            imagejpeg($dest, $fullPath, 85);
        }

        imagedestroy($source);
        imagedestroy($dest);

        return $baseDir.'/'.$filename;
    }
}
