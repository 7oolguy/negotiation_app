<?php

namespace App\Http\Controllers;

use App\Models\CollectionAction;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CollectionEvidenceController extends Controller
{
    public function __invoke(CollectionAction $collectionAction): StreamedResponse
    {
        Gate::authorize('view', $collectionAction);

        $path = $collectionAction->evidence_path;

        abort_if(blank($path) || ! Storage::disk('private')->exists($path), 404);

        AuditLogger::log(
            event: 'collection_evidence_downloaded',
            auditable: $collectionAction,
            metadata: [
                'debt_id' => $collectionAction->debt_id,
                'evidence_path' => $path,
            ],
        );

        return Storage::disk('private')->download(
            $path,
            $collectionAction->evidence_original_name ?: basename($path),
        );
    }
}
