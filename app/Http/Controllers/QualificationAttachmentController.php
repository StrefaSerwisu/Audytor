<?php

namespace App\Http\Controllers;

use App\Models\QualificationAttachment;
use App\Support\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QualificationAttachmentController extends Controller
{
    public function download(Request $request, QualificationAttachment $attachment): StreamedResponse
    {
        abort_unless($request->user()?->can('download', $attachment), 403);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    public function destroy(Request $request, QualificationAttachment $attachment): RedirectResponse
    {
        abort_unless($request->user()?->can('delete', $attachment), 403);
        Storage::disk($attachment->disk)->delete($attachment->path);
        AuditLogService::record('sales_qualification.file_deleted', $attachment, metadata: [
            'qualification_id' => $attachment->sales_qualification_id,
            'original_name' => $attachment->original_name,
        ]);
        $attachment->delete();

        return back()->with('status', 'Plik zostal usuniety.');
    }
}
