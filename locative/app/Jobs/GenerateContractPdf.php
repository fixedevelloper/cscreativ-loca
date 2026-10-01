<?php

namespace App\Jobs;

use App\Models\Contract;
use App\Models\Document;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Traits\Localizable;

/**
 * Génère le PDF d'un contrat en tâche de fond : la requête HTTP de
 * DocumentController::store ne doit pas attendre le rendu PDF (opération
 * lente, jusqu'ici faite en synchrone dans la requête).
 */
class GenerateContractPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Localizable, Queueable, SerializesModels;

    public function __construct(
        private readonly int $documentId,
        private readonly int $contractId,
    ) {
    }

    public function handle(): void
    {
        $contract = Contract::with(['tenant.user', 'property'])->find($this->contractId);
        $document = Document::find($this->documentId);

        if (!$contract || !$document) {
            return;
        }

        // Bail rédigé dans la langue du locataire.
        $locale = $contract->tenant?->user?->preferredLocale() ?? config('app.locale');
        $pdf = $this->withLocale($locale, fn () => Pdf::loadView('pdf.contract', ['contract' => $contract])->output());
        // Chemin propre au document : deux documents d'un même contrat ne
        // s'écrasent plus mutuellement leur PDF.
        $pdfPath = "contracts/contract_{$contract->id}_document_{$document->id}.pdf";

        Storage::disk('local')->put($pdfPath, $pdf);

        // Le PDF généré remplace le fichier uploadé : on supprime ce dernier
        // pour ne pas laisser de fichier orphelin sur le disque privé.
        $previousPath = $document->file_path;
        $document->update(['file_path' => $pdfPath]);
        if ($previousPath && $previousPath !== $pdfPath) {
            Storage::disk('local')->delete($previousPath);
        }
    }
}
