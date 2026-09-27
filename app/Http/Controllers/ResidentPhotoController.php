<?php

namespace App\Http\Controllers;

use App\Models\Resident;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Streams a resident's photo from private storage.
 *
 * Photos never live on a public URL: the file is only reachable through this
 * authenticated route, which allows office users plus the resident who owns
 * the picture.
 */
class ResidentPhotoController extends Controller
{
    public function show(Request $request, Resident $resident): Response
    {
        $user = $request->user();

        $allowed = $user !== null
            && ($user->isAdmin()
                || $user->isStaff()
                || (int) $user->resident_id === (int) $resident->id);

        abort_unless($allowed, 403);

        $path = $resident->photo_path;

        if ($path === null || $path === '' || str_contains($path, '..')) {
            abort(404);
        }

        $diskName = 'local';
        $disk = Storage::disk($diskName);

        if (! $disk->exists($path)) {
            // Legacy files may still sit on the public disk.
            $diskName = 'public';
            $disk = Storage::disk($diskName);

            if (! $disk->exists($path)) {
                abort(404);
            }
        }

        $response = $disk->response($path, $resident->full_name, [
            'Content-Type' => $this->mimeType($diskName, $path),
        ]);

        // The photo is personal data: never cache it in a shared cache.
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        if (! str_starts_with((string) $response->headers->get('Content-Disposition'), 'inline')) {
            $response->headers->set('Content-Disposition', 'inline');
        }

        return $response;
    }

    /** Content type for the stored file, guessed from the extension as a fallback. */
    private function mimeType(string $diskName, string $path): string
    {
        try {
            $mime = Storage::disk($diskName)->mimeType($path);
        } catch (\Throwable) {
            $mime = null;
        }

        if (is_string($mime) && str_starts_with($mime, 'image/')) {
            return $mime;
        }

        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            default => 'image/jpeg',
        };
    }
}
