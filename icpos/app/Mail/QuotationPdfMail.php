<?php

namespace App\Mail;

use App\Models\Quotation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class QuotationPdfMail extends Mailable
{
    use Queueable, SerializesModels;

    public Quotation $quotation;
    public string $pdfBinary;
    public string $subjectLine;

    public function __construct(Quotation $quotation, string $pdfBinary, string $subjectLine)
    {
        $this->quotation   = $quotation;
        $this->pdfBinary   = $pdfBinary;
        $this->subjectLine = $subjectLine;
    }

    public function build()
    {
        $brand = $this->quotation->brand_snapshot;
        if (is_string($brand)) {
            $brand = json_decode($brand, true) ?: [];
        }

        $sender = $this->quotation->salesUser; // user yang kirim
        // Ambil signature dari profil; fallback ke Setting global atau default
        $signature =
            trim((string) ($sender->email_signature ?? '')) ?:
            (string) (\App\Models\Setting::get('mail.signature', '')) ?:
            ('Best regards,' . PHP_EOL . ($sender->name ?? ''));  // fallback terakhir

        $filename = 'Quotation-'.$this->quotation->number.'.pdf';

        return $this->subject($this->subjectLine)
            ->view('emails.quotation_pdf')
            ->with([
                'quotation' => $this->quotation,
                'sender'    => $sender,
                'brand'     => $brand,
                'signature' => $signature,   // <— kirim ke blade
            ])
            ->attachData($this->pdfBinary, $filename, ['mime' => 'application/pdf']);
    }
}
