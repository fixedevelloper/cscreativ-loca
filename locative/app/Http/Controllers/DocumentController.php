<?php


namespace App\Http\Controllers;

use App\Http\Requests\StoreDocumentRequest;
use App\Jobs\GenerateContractPdf;
use App\Models\Contract;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    /**
     * Disque privé : jamais de fichier utilisateur (CNI, bail, quittance)
     * servi en clair sur une URL publique/devinable.
     */
    private const DISK = 'local';

    public function index(Request $request)
    {
        $query = Document::with(['contract.tenant', 'uploader:id,name'])
            ->visibleTo(auth()->user())
            ->latest();

        if ($search = $request->get('search')) {
            $query->where(fn ($q) => $q->where('title', 'like', "%$search%")
                ->orWhere('type', 'like', "%$search%"));
        }

        return $this->paginated($query, $request);
    }

    public function store(StoreDocumentRequest $request)
    {
        $user = auth()->user();

        // 🔹 Stockage du fichier uploadé sur le disque PRIVÉ
        $path = $request->file('file')->store('documents', self::DISK);

        // 🔹 Création du document
        $document = Document::create([
            'contract_id' => $request->contract_id,
            'uploaded_by' => $user->id,
            'uploader_role' => $user->role,
            'title' => $request->title,
            'type' => $request->type,
            'description' => $request->description,
            'file_path' => $path,
            'is_shared' => !empty($request->shared_with),
            'shared_with' => $request->shared_with ?: null,
            'shared_at' => !empty($request->shared_with) ? now() : null,
        ]);

        // 🔹 Génération PDF si type = contract, en tâche de fond (ne bloque
        //    pas la réponse HTTP — voir App\Jobs\GenerateContractPdf).
        //    Réservé à qui peut modifier le contrat : sinon n'importe qui
        //    pourrait faire générer (et récupérer) le bail d'un autre client.
        if ($request->type === 'contract' && $request->contract_id
            && $user->can('update', Contract::findOrFail($request->contract_id))) {
            GenerateContractPdf::dispatch($document->id, (int) $request->contract_id);
        }

        return response()->json(['message' => __('messages.documents.created'), 'data' => $document]);
    }

    /**
     * Téléchargement contrôlé : le fichier n'est jamais accessible par une
     * URL publique. Chaque requête revalide le droit d'accès (uploader,
     * bailleur du bien concerné, locataire du contrat, agent ou admin).
     */
    public function download($id): StreamedResponse|\Illuminate\Http\JsonResponse
    {
        $document = Document::with('contract.property')->findOrFail($id);
        $this->authorize('view', $document);

        if (!Storage::disk(self::DISK)->exists($document->file_path)) {
            return $this->error(__('messages.documents.file_missing'), 404);
        }

        return Storage::disk(self::DISK)->download($document->file_path, $document->title);
    }

    public function share($id)
    {
        $document = Document::with('contract.property')->findOrFail($id);
        $this->authorize('share', $document);

        $document->update([
            'is_shared' => true,
            'shared_at' => now(),
        ]);

        return response()->json(['message' => __('messages.documents.shared')]);
    }

    public function destroy($id)
    {
        $document = Document::findOrFail($id);

        $this->authorize('delete', $document);

        Storage::disk(self::DISK)->delete($document->file_path);
        $document->delete();

        return response()->json(['message' => __('messages.documents.deleted')]);
    }
}

