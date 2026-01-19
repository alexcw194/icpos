<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentEditorUploadController extends Controller
{
    public function store(Request $request, Document $document)
    {
        $this->authorize('update', $document);
        abort_unless($document->status === Document::STATUS_DRAFT, 403, 'Dokumen terkunci.');

        $request->validate([
            'upload' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        $file = $request->file('upload');
        $baseDir = 'documents/'.$document->id.'/images';
        $path = $this->storeResizedImage($file, $baseDir);
        $url = Storage::url($path);

        if ($request->has('CKEditorFuncNum')) {
            $funcNum = $request->input('CKEditorFuncNum');
            $escapedUrl = addslashes($url);
            $script = "<script>window.parent.CKEDITOR.tools.callFunction({$funcNum}, '{$escapedUrl}', '');</script>";

            return response($script)->header('Content-Type', 'text/html; charset=utf-8');
        }

        return response()->json([
            'uploaded' => 1,
            'fileName' => basename($path),
            'url' => $url,
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
