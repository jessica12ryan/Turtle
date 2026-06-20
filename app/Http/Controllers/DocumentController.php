<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function download(Request $request, Document $document)
    {
        $user = $request->user();
        $parent = $document->documentable;

        if ($parent instanceof \App\Models\Lease) {
            if ($user->isStaff() && !$user->companies->contains($parent->property->company_id)) {
                abort(403);
            }
            if ($user->isTenant() && !$parent->property->tenants->contains($user)) {
                abort(403);
            }
        }

        if (!Storage::exists($document->file_path)) {
            abort(404);
        }

        return Storage::download($document->file_path, $document->original_name);
    }

    public function destroy(Request $request, Document $document)
    {
        $parent = $document->documentable;
        if ($parent instanceof \App\Models\Lease) {
            if (!$request->user()->companies->contains($parent->property->company_id)) {
                abort(403);
            }
        }
        Storage::delete($document->file_path);
        $document->delete();
        return redirect()->back()->with('success', 'Document deleted successfully.');
    }
}
