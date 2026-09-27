<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

/**
 * Guards two defects that shipped in the password-reset letter and would not be
 * caught by any behavioural test, because both only show up in a mail client:
 *
 *  1. A percentage border-radius on the full-width card table turned the whole
 *     letter into an oval and clipped the wordmark, the code and the footer
 *     against its curve.
 *  2. The seal was referenced with asset(), which builds an absolute URL from
 *     APP_URL. Locally that is http://localhost, a host that exists only on the
 *     server, so the recipient's client rendered a broken image. The seal is
 *     now inlined as a data URI so it cannot depend on where the app is hosted.
 */
class ResetEmailRenderingTest extends TestCase
{
    /**
     * @return array<int, string>
     */
    private function htmlEmailTemplates(): array
    {
        $templates = [];

        foreach (glob(resource_path('views/emails/*.blade.php')) ?: [] as $path) {
            $contents = (string) file_get_contents($path);

            if (str_contains($contents, '<html')) {
                $templates[] = basename($path);
            }
        }

        sort($templates);

        return $templates;
    }

    public function test_it_finds_the_html_email_templates_to_check(): void
    {
        // Guards the guard: if the glob ever stops matching, the assertions
        // below would pass on an empty set and prove nothing.
        $this->assertNotEmpty($this->htmlEmailTemplates());
        $this->assertContains('reset-password-code.blade.php', $this->htmlEmailTemplates());
    }

    public function test_no_email_card_uses_a_percentage_border_radius(): void
    {
        foreach ($this->htmlEmailTemplates() as $template) {
            $contents = (string) file_get_contents(resource_path("views/emails/{$template}"));

            // Only the card wrapper matters. A logo is meant to be circular, and
            // the header strip is allowed its own radius, so match the line that
            // also carries the max-width.
            preg_match('/max-width:\s*\d+px[^"]*border-radius:\s*([^;]+)/', $contents, $matches);

            if ($matches === []) {
                continue;
            }

            $this->assertStringNotContainsString(
                '%',
                $matches[1],
                "{$template} uses a percentage border-radius on the card, which turns it into an oval and clips its contents.",
            );
        }
    }

    public function test_no_email_references_the_seal_through_asset(): void
    {
        foreach ($this->htmlEmailTemplates() as $template) {
            $contents = (string) file_get_contents(resource_path("views/emails/{$template}"));

            $this->assertStringNotContainsString(
                "asset('images/",
                $contents,
                "{$template} builds an image URL from APP_URL, so the seal cannot load for the recipient.",
            );
        }
    }

    public function test_the_reset_email_renders_an_inlined_seal(): void
    {
        $html = view('emails.reset-password-code', [
            'code' => '483920',
            'expiresInMinutes' => 15,
            'userName' => 'Jeford Bitun',
        ])->render();

        $this->assertStringContainsString(
            'src="data:image/png;base64,',
            $html,
            'The seal must be inlined so it renders without a reachable web host.',
        );

        $this->assertStringNotContainsString(
            'http://localhost',
            $html,
            'The rendered letter must not depend on a localhost asset URL.',
        );

        // The code itself is the whole point of the letter.
        $this->assertStringContainsString('483920', $html);
        $this->assertStringContainsString('15 minutes', $html);
    }
}
