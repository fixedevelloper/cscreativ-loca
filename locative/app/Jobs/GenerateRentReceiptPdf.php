<?php

namespace App\Jobs;

use App\Models\Document;
use App\Models\Rent;
use App\Notifications\ReceiptAvailable;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Traits\Localizable;

/**
 * Génère la quittance PDF d'un loyer réglé et l'enregistre comme Document
 * partagé (visible par le locataire). Déclenché après enregistrement d'un
 * paiement qui solde intégralement le loyer — voir PaymentController::store.
 */
class GenerateRentReceiptPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Localizable, Queueable, SerializesModels;

    public function __construct(private readonly int $rentId)
    {
    }

    public function handle(): void
    {
        $rent = Rent::with(['contract.tenant.user', 'contract.property.owner', 'payments'])->find($this->rentId);

        if (!$rent) {
            return;
        }

        // Quittance rédigée dans la langue du locataire (son destinataire).
        $locale = $rent->contract->tenant?->user?->preferredLocale() ?? config('app.locale');

        $this->withLocale($locale, function () use ($rent) {
            $pdf = Pdf::loadView('pdf.receipt', ['rent' => $rent]);
            Storage::disk('local')->put("receipts/receipt_rent_{$rent->id}.pdf", $pdf->output());
        });
        $path = "receipts/receipt_rent_{$rent->id}.pdf";

        // Une seule quittance par loyer, même si le job est rejoué.
        $document = Document::updateOrCreate(['rent_id' => $rent->id, 'type' => 'quittance'], [
            'contract_id' => $rent->contract_id,
            'uploaded_by' => $rent->contract->property->user_id ?? null,
            'uploader_role' => 'owner',
            'title' => __('pdf.receipt.document_title', ['month' => $rent->month], $locale),
            'description' => __('pdf.receipt.document_description', ['month' => $rent->month], $locale),
            'file_path' => $path,
            'is_shared' => true, // visible par le locataire
            'shared_at' => now(),
        ]);

        // Prévient le locataire (une seule fois, même si le job est rejoué).
        if ($document->wasRecentlyCreated) {
            $rent->contract->tenant?->user?->notify(new ReceiptAvailable($rent));
        }
    }
}
