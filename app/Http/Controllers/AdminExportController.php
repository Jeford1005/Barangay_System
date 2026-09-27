<?php

namespace App\Http\Controllers;

use App\Services\ExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Administrative CSV export entry points.
 */
class AdminExportController extends Controller
{
    public function __invoke(Request $request, string $dataset, ExportService $exports): StreamedResponse
    {
        return $this->serve($request, $dataset, $exports);
    }

    public function residents(Request $request, ExportService $exports): StreamedResponse
    {
        return $this->serve($request, 'residents', $exports);
    }

    public function households(Request $request, ExportService $exports): StreamedResponse
    {
        return $this->serve($request, 'households', $exports);
    }

    public function blotter(Request $request, ExportService $exports): StreamedResponse
    {
        return $this->serve($request, 'blotter', $exports);
    }

    public function welfare(Request $request, ExportService $exports): StreamedResponse
    {
        return $this->serve($request, 'welfare', $exports);
    }

    public function certificates(Request $request, ExportService $exports): StreamedResponse
    {
        return $this->serve($request, 'certificates', $exports);
    }

    public function certificateIssuances(Request $request, ExportService $exports): StreamedResponse
    {
        return $this->serve($request, 'certificates', $exports);
    }

    private function serve(Request $request, string $dataset, ExportService $exports): StreamedResponse
    {
        $user = $request->user();

        // Route middleware provides the normal login/redirect behavior. Keep a
        // defense-in-depth check for callers that invoke the controller
        // directly or mount it under a different route group.
        abort_unless($user?->isAdmin(), 403);

        abort_unless($exports->supports($dataset), 404);

        return $exports->stream($dataset, $request);
    }
}
