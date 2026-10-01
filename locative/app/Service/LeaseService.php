<?php

namespace App\Service;

use App\Models\Contract;
use App\Models\ContractSignature;
use App\Models\Document;
use App\Models\User;
use App\Notifications\LeaseUpdate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Traits\Localizable;
use RuntimeException;

/**
 * Bail PDF et signature électronique SIMPLE (au sens eIDAS) : chaque partie
 * signe depuis son compte authentifié ; on conserve l'horodatage, l'IP, le
 * navigateur et l'empreinte SHA-256 du PDF signé, puis on produit un bail
 * signé avec page de certificat. Ce n'est pas une signature qualifiée
 * (prestataire de confiance type Yousign/DocuSign) : valeur probante moindre
 * en cas de contestation.
 */
class LeaseService
{
    use Localizable;

    private const DISK = 'local';

    /**
     * Génère (ou régénère) le PDF du bail, partagé avec le locataire.
     * Interdit dès qu'une partie a signé : le document signé ne doit plus changer.
     *
     * @throws RuntimeException clé de traduction en message
     */
    public function generate(Contract $contract, User $actor): Document
    {
        if ($contract->signatures()->exists()) {
            throw new RuntimeException('messages.lease.locked');
        }

        $contract->loadMissing('tenant.user', 'property', 'leaseDocument');
        $locale = $this->tenantLocale($contract);
        $path = "leases/lease_{$contract->id}_" . now()->format('YmdHis') . '.pdf';
        Storage::disk(self::DISK)->put($path, $this->render($contract, $locale));

        $document = DB::transaction(function () use ($contract, $actor, $locale, $path) {
            $previous = $contract->leaseDocument;
            $document = Document::create([
                'contract_id' => $contract->id,
                'uploaded_by' => $actor->id,
                'uploader_role' => $actor->role,
                'title' => __('pdf.contract.document_title', [], $locale),
                'type' => 'contract',
                'file_path' => $path,
                'is_shared' => true,
                'shared_at' => now(),
            ]);
            $contract->update(['lease_document_id' => $document->id]);

            // Version précédente non signée : remplacée.
            if ($previous) {
                Storage::disk(self::DISK)->delete($previous->file_path);
                $previous->delete();
            }

            return $document;
        });

        $contract->tenant?->user?->notify(new LeaseUpdate($contract, 'ready', $actor));

        return $document;
    }

    /**
     * Signature d'une partie ("landlord" ou "tenant").
     *
     * @throws RuntimeException clé de traduction en message
     */
    public function sign(Contract $contract, User $signer, string $party, ?string $ip, ?string $userAgent): ContractSignature
    {
        $contract->loadMissing('leaseDocument', 'tenant.user', 'property.owner.agents');
        $document = $contract->leaseDocument;

        if (!$document || !Storage::disk(self::DISK)->exists($document->file_path)) {
            throw new RuntimeException('messages.lease.no_document');
        }
        if ($contract->signatures()->where('party', $party)->exists()) {
            throw new RuntimeException('messages.lease.already_signed');
        }

        $signature = $contract->signatures()->create([
            'user_id' => $signer->id,
            'party' => $party,
            // Empreinte du PDF effectivement présenté et signé.
            'document_hash' => hash('sha256', Storage::disk(self::DISK)->get($document->file_path)),
            'ip_address' => $ip,
            'user_agent' => $userAgent ? mb_substr($userAgent, 0, 500) : null,
            'signed_at' => now(),
        ]);

        if ($contract->signatures()->count() === 2) {
            $this->finalize($contract);
            Notification::send($this->parties($contract), new LeaseUpdate($contract, 'fully_signed', $signer));
        } else {
            Notification::send($this->parties($contract, $signer), new LeaseUpdate($contract, 'signed_by', $signer));
        }

        return $signature;
    }

    /** Bail signé : PDF avec page de certificat des signatures. */
    private function finalize(Contract $contract): void
    {
        $locale = $this->tenantLocale($contract);
        $signatures = $contract->signatures()->with('signer')->orderBy('signed_at')->get();
        $path = "leases/lease_{$contract->id}_signed.pdf";
        Storage::disk(self::DISK)->put($path, $this->render($contract, $locale, $signatures));

        $document = Document::create([
            'contract_id' => $contract->id,
            'uploaded_by' => $contract->property?->user_id,
            'uploader_role' => 'owner',
            'title' => __('pdf.contract.signed_document_title', [], $locale),
            'type' => 'contract',
            'file_path' => $path,
            'is_shared' => true,
            'shared_at' => now(),
        ]);

        $contract->update(['signed_document_id' => $document->id, 'signed_at' => now()]);
    }

    private function render(Contract $contract, string $locale, $signatures = null): string
    {
        return $this->withLocale($locale, fn () => Pdf::loadView('pdf.contract', [
            'contract' => $contract,
            'signatures' => $signatures,
        ])->output());
    }

    private function tenantLocale(Contract $contract): string
    {
        return $contract->tenant?->user?->preferredLocale() ?? config('app.locale');
    }

    /** Locataire, bailleur et agents du bailleur (sauf $except). */
    private function parties(Contract $contract, ?User $except = null)
    {
        return collect([$contract->tenant?->user, $contract->property?->owner])
            ->concat($contract->property?->owner?->agents ?? [])
            ->filter()
            ->unique('id')
            ->reject(fn (User $u) => $except && $u->id === $except->id)
            ->values();
    }
}
