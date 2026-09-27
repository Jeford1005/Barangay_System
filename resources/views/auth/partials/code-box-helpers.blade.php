{{-- Shared code-box helpers for the forgot-password dialog (login page) and the
     standalone reset page. Rendered inline on purpose: it must work without a
     Vite build (CDN fallback) and stay present in the served HTML. --}}
<script>
    // Factory over a page's code boxes + hidden field. Returns the shared
    // sync/extract/apply/clipboard functions used by both password-reset UIs.
    function createCodeBoxController(boxes, codeField) {
        function syncCode() {
            codeField.value = boxes.map((box) => box.value).join('');
        }

        // Extracts a STANDALONE 6-character code token from arbitrary text.
        // Ordinary words must never be mistaken for a code — "6-character"
        // contains "CHARAC", "reset code" contains "RESETC", and "SYSTEM" is
        // a clean 6-letter word — so tokens must be exactly six characters
        // from the unambiguous alphabet, and real codes always contain at
        // least two digits (the server guarantees it; English words never
        // contain digits).
        function extractCode(raw) {
            const tokens = String(raw || '').toUpperCase().match(/[23456789ABCDEFGHJKMNPQRSTUVWXYZ]+/g) || [];
            const sixChar = tokens.filter((token) => token.length === 6);

            return sixChar.find((token) => (token.match(/\d/g) || []).length >= 2) ?? sixChar[0] ?? null;
        }

        function applyCode(raw) {
            const code = extractCode(raw);

            if (!code) return false;

            code.split('').forEach((ch, i) => {
                if (boxes[i]) boxes[i].value = ch;
            });

            syncCode();
            boxes[code.length - 1].focus();

            return true;
        }

        async function readCodeFromClipboard() {
            if (!navigator.clipboard || !window.isSecureContext) return null;

            try {
                return await navigator.clipboard.readText();
            } catch {
                return null; // permission denied / nothing readable
            }
        }

        return { syncCode, extractCode, applyCode, readCodeFromClipboard };
    }
</script>
