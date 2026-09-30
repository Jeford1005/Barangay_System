<?php

namespace App\Http\Controllers;

use App\Models\Resident;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves resident photos.
 *
 * Photos are personal data. They used to be written to the `public` disk and
 * referenced by a stable URL under /storage, which meant anyone with the URL
 * could fetch them with no session, no CSRF token, and no role check, and the
 * URL leaked through page HTML, browser cache, and the Referer header. New
 * uploads go to the private `local` disk and are streamed only to an
 * authenticated office user or the resident the photo belongs to.
 */
class ResidentPhotoController extends Controller
{
    public function __invoke(Request $request, Resident $resident): Response
    {
        $user = $request->user();

        abort_unless($user, 403);
        abort_unless(
            // Officials hold the same read access as staff, and a resident may
            // always open their own photo; the ownership check below covers
            // the resident role, which has no module permission at all.
            $user->hasPermission('residents.view') || $resident->user_id === $user->id,
            403,
        );

        abort_if(blank($resident->photo), 404);

        return $this->stream($resident->photo);
    }

    /**
     * The signed-in resident's own photo, from the resident-only route group.
     */
    public function mine(Request $request): Response
    {
        $resident = $request->user()?->residentProfile;

        abort_unless($resident, 404);
        abort_if(blank($resident->photo), 404);

        return $this->stream($resident->photo);
    }

    /**
     * Stream the stored file, falling back to the public disk so photos taken
     * before this change keep rendering.
     *
     * The column only ever holds `residents/<hashed-name>.<ext>` (written by
     * `storeAs()` with `hashName()`), so anything else — traversal, absolute
     * paths, subdirectories, unexpected extensions — is rejected before any
     * disk is touched.
     */
    private function stream(string $path): Response
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');

        // Defence in depth: reject traversal, null bytes, absolute paths, and
        // anything outside the single hashed-name level under residents/.
        abort_if(str_contains($path, '..') || str_contains($path, "\0"), 404);
        abort_unless(str_starts_with($path, 'residents/'), 404);

        $base = basename($path);
        abort_if($base === '' || $base !== substr($path, strlen('residents/')), 404);
        abort_unless((bool) preg_match('/\A[A-Za-z0-9_\-]+\.[A-Za-z0-9]{2,5}\z/', $base), 404);

        foreach ([Storage::disk('local'), Storage::disk('public')] as $disk) {
            if ($disk->exists($path)) {
                $response = new BinaryFileResponse($disk->path($path));

                $mime = $disk->mimeType($path) ?: 'application/octet-stream';
                $imageMimes = ['image/jpeg', 'image/png', 'image/webp'];

                if (! in_array($mime, $imageMimes, true)) {
                    // A crafted upload must never render as markup in our
                    // origin: force non-images to download as opaque bytes.
                    $mime = 'application/octet-stream';
                    $response->headers->set('Content-Disposition', 'attachment; filename="'.$base.'"');
                } else {
                    $response->headers->set('Content-Disposition', 'inline; filename="'.$base.'"');
                }

                $response->headers->set('Content-Type', $mime);
                $response->headers->set('Cache-Control', 'private, no-store');
                $response->headers->set('X-Content-Type-Options', 'nosniff');

                return $response;
            }
        }

        abort(404);
    }
}
