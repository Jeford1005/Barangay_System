<?php

namespace App\Http\Controllers;

use App\Models\Official;
use Illuminate\View\View;

class ResidentOfficialsController extends Controller
{
    /**
     * Read-only directory of the officials serving the barangay.
     *
     * `officials` is a reference table: it supplies certificate signatures and
     * blotter officers and is not maintained from this application, so the
     * portal only ever reads it. Serving means Active or Elected — an
     * Active-only filter would hide every elected official — ordered by
     * position so the directory reads the same on every visit.
     */
    public function index(): View
    {
        $officials = Official::serving()
            ->orderBy('position')
            ->orderBy('last_name')
            ->get([
                'first_name',
                'middle_name',
                'last_name',
                'suffix',
                'office',
                'position',
                'status',
                'term_start',
                'term_end',
            ]);

        return view('resident.officials', ['officials' => $officials]);
    }
}
