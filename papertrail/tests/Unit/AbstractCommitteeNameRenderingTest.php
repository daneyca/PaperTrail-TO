<?php

namespace Tests\Unit;

use App\Models\AbstractQuotation;
use DOMDocument;
use DOMXPath;
use Tests\TestCase;

class AbstractCommitteeNameRenderingTest extends TestCase
{
    public function test_saved_names_replace_empty_or_stale_names_in_preview_and_print(): void
    {
        foreach (['head-office', 'bac-secretariat'] as $context) {
            foreach (['show', 'print', 'edit'] as $mode) {
                foreach (['', 'Outdated Name'] as $oldName) {
                    $abstract = (new AbstractQuotation([
                        'committee_json' => ['bac_member_2' => ['name' => 'Rogelio A. Layo', 'account_code' => 'BACMEM-002', 'designation' => 'BAC Member II']],
                        'document_html' => '<table id="abstractEditor"><tr><td><div class="abstract-committee-grid"><div class="abstract-committee-person is-editing-signatory"><span class="abstract-cell abstract-committee-name" data-committee-role="bac_member_2">'.$oldName.'</span></div></div></td></tr></table>',
                    ]))->setRelation('items', collect());
                    $html = view($context.'.abstracts.partials.abstract-excel-form', [
                        'abstract' => $abstract, 'sourceDocument' => null, 'sourceRfq' => null,
                        'sourceResolution' => null, 'mode' => $mode, 'signatoryUsers' => collect(),
                    ])->render();
                    $dom = new DOMDocument();
                    $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
                    $xpath = new DOMXPath($dom);
                    $name = $xpath->query('//span[@data-committee-role="bac_member_2"]')->item(0);
                    $this->assertSame('Rogelio A. Layo', $name->textContent, $context.' '.$mode);
                    if ($mode !== 'edit') {
                        $this->assertStringNotContainsString('is-editing-signatory', $name->parentNode->getAttribute('class'));
                    }
                }
            }
        }
    }

    public function test_legacy_html_names_are_kept_when_no_structured_names_exist(): void
    {
        $abstract = (new AbstractQuotation(['document_html' => '<table id="abstractEditor"><tr><td><span data-committee-role="bac_chairperson">Saved Legacy Name</span></td></tr></table>']))->setRelation('items', collect());
        foreach (['head-office', 'bac-secretariat'] as $context) {
            $html = view($context.'.abstracts.partials.abstract-excel-form', ['abstract' => $abstract,
                'sourceDocument' => null, 'sourceRfq' => null, 'sourceResolution' => null, 'mode' => 'show'])->render();
            $this->assertStringContainsString('Saved Legacy Name</span>', $html);
        }
    }
}
