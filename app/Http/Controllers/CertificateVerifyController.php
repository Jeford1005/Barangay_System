<?php

namespace App\Http\Controllers;

use App\Models\CertificateIssuance;
use App\Services\CertificateVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CertificateVerifyController extends Controller
{
    /**
     * Public certificate check — the target of the QR printed on
     * certificates. Guest-only (no auth middleware) and throttled at the
     * router (throttle:30,1) to slow control-number enumeration.
     *
     * Always answers HTTP 200 with a self-contained page, never 404: an
     * unknown number shows a not-found result, and a forged/malformed
     * token shows an invalid result without revealing whether the number
     * exists. The token is checked BEFORE the lookup so a forged token
     * leaks nothing about the issuance.
     *
     * Only non-personal facts are shown (control number, certificate
     * type, status, issued date) — never the recipient name, address, or
     * purpose — so a scanned QR cannot be used to harvest resident PII.
     *
     * Nothing is written to AuditLog here: this is a read-only public
     * check, and logging the control number per scan would pile PII into
     * the audit trail (AuditLog::record maps control_number to a
     * certificate subject) while adding no accountability for anonymous
     * visitors.
     */
    public function show(Request $request, string $control_number, string $token)
    {
        $validator = Validator::make(
            ['control_number' => $control_number, 'token' => $token],
            [
                'control_number' => [
                    'required',
                    'string',
                    'min:1',
                    'max:'.CertificateVerification::CODE_MAX,
                    'regex:/^[A-Za-z0-9-]+$/',
                ],
                'token' => [
                    'required',
                    'string',
                    // Total length is fixed at TOKEN_LENGTH for both the
                    // current `v1`+14-hex format and honored legacy bare-16-hex
                    // tokens — keep in lockstep with CertificateVerification.
                    'size:'.CertificateVerification::TOKEN_LENGTH,
                    'regex:/^(?:v1[0-9a-fA-F]{14}|[0-9a-fA-F]{16})$/',
                ],
            ]
        );

        $status = 'invalid';
        $issuance = null;

        if (! $validator->fails() && CertificateVerification::isValid($control_number, $token)) {
            $issuance = CertificateIssuance::query()
                ->with('document')
                ->where('control_number', $control_number)
                ->first();

            $status = $issuance === null
                ? 'unknown'
                : ($issuance->status === 'Voided' ? 'void' : 'valid');
        }

        return response()->view('certificates.verify', [
            'status' => $status,
            'controlNumber' => $control_number,
            'issuance' => $issuance,
        ], 200);
    }
}
