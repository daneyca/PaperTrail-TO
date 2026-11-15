<?php

namespace Tests\Feature;

use App\Services\DocumentListFilterService;
use DOMDocument;
use DOMXPath;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class NavbarFilterFormTest extends TestCase
{
    private function parse(string $html): DOMXPath
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        return new DOMXPath($document);
    }

    private function render(string $template, array $data = [], array $query = []): string
    {
        $this->app->instance('request', Request::create('http://localhost/records', 'GET', $query));

        return Blade::render($template.'<nav>@yield("topbar-search")@yield("topbar-filters")</nav>@stack("modals")', $data);
    }

    public function test_received_resolutions_use_one_navbar_search_and_preserve_the_original_options(): void
    {
        $template = str_replace("@extends('layouts.dashboard')", '', file_get_contents(resource_path('views/head-office/resolutions/index.blade.php')));
        $html = $this->render($template.'<main>@yield("content")</main>', [
            'filters' => ['search' => 'Office supplies', 'status' => 'awaiting', 'date_from' => '2026-11-01'],
            'summary' => ['received' => 0, 'awaiting' => 0, 'postingRequired' => 0, 'readyForRfq' => 0],
            'statuses' => ['awaiting', 'acknowledged'],
            'resolutions' => new LengthAwarePaginator([], 0, 10),
        ]);
        $xpath = $this->parse($html);
        $this->assertCount(1, $xpath->query('//input[@type="search"]'));
        $this->assertCount(1, $xpath->query('//nav//button[@data-open-list-filters]'));
        $this->assertCount(0, $xpath->query('//main//form'));
        $this->assertSame('Search Received BAC Resolutions...', $xpath->query('//nav//input[@name="search"]')->item(0)->getAttribute('placeholder'));
        $this->assertSame(route('head-office.resolutions.index'), $xpath->query('//nav//form')->item(0)->getAttribute('action'));
        $this->assertSame('awaiting', $xpath->query('//dialog//select[@name="status"]/option[@selected]')->item(0)->getAttribute('value'));
        $this->assertSame('2026-11-01', $xpath->query('//dialog//input[@name="date_from"]')->item(0)->getAttribute('value'));
        $this->assertCount(3, $xpath->query('//dialog//select[@name="status"]/option'));
    }

    public function test_applying_or_removing_filters_keeps_the_returned_tab_and_resets_all_paginators(): void
    {
        $html = $this->render(<<<'BLADE'
            <x-navbar-filter-form action="/documents" label="Returned documents">
                <input type="hidden" name="tab" value="returned">
                <label>Search<input type="search" name="search" value="Paper &amp; ink"></label>
                <label>Status<select name="status"><option value="">All statuses</option><option value="returned" selected>Returned</option></select></label>
                <a href="/documents?tab=returned">Clear</a>
            </x-navbar-filter-form>
            BLADE, [], ['tab' => 'returned', 'page' => 5, 'documents_page' => 3]);
        $xpath = $this->parse($html);
        $this->assertCount(2, $xpath->query('//input[@name="tab" and @value="returned"]'));
        $this->assertCount(0, $xpath->query('//input[@name="page" or @name="documents_page"]'));
        $link = $xpath->query('//a[@aria-label="Remove Status: Returned"]')->item(0);
        parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $remaining);
        $this->assertSame(['tab' => 'returned', 'search' => 'Paper & ink', 'status' => ''], $remaining);
        $clear = $xpath->query('//a[normalize-space()="Clear filters"]')->item(0);
        parse_str(parse_url($clear->getAttribute('href'), PHP_URL_QUERY), $clearQuery);
        $this->assertSame(['tab' => 'returned', 'search' => 'Paper & ink'], $clearQuery);
    }

    public function test_report_filters_do_not_replace_an_existing_global_search_with_an_unsupported_keyword_field(): void
    {
        $html = $this->render(<<<'BLADE'
            <x-navbar-filter-form action="/reports" label="Budget reports">
                <label>Fiscal year<select name="fiscal_year"><option value="">All years</option><option value="2026" selected>2026</option></select></label>
                <label>Date from<input type="date" name="date_from" value="2026-11-01"></label>
            </x-navbar-filter-form>
            BLADE);
        $xpath = $this->parse($html);
        $this->assertCount(0, $xpath->query('//input[@name="search"]'));
        $this->assertCount(1, $xpath->query('//nav//button[@data-open-list-filters]'));
        $this->assertSame('2026', $xpath->query('//dialog//select[@name="fiscal_year"]/option[@selected]')->item(0)->getAttribute('value'));
    }

    public function test_nested_labels_text_fields_and_nonempty_all_values_keep_their_meaning(): void
    {
        $definition = app(DocumentListFilterService::class)->describe(<<<'HTML'
            <label><span>Office</span><select name="office"><option value="all">All offices</option><option value="2" selected>Office &amp; Records</option></select></label>
            <label for="user">User name</label><input id="user" name="user" type="text" value="Alex" maxlength="50">
            <label>Status<select name="status"><option value="">Pending only</option><option value="all" selected>All assigned</option></select></label>
            <label>Scope<select disabled name="scope"><option value="office">This office</option></select></label>
            HTML, [], '/audit');
        $this->assertSame('Office', $definition['fields']['office']['label']);
        $this->assertSame('Office & Records', $definition['fields']['office']['options'][2]);
        $this->assertSame('all', $definition['fields']['office']['neutral']);
        $this->assertSame('', $definition['fields']['status']['neutral']);
        $this->assertSame('all', $definition['values']['status']);
        $this->assertSame('text', $definition['fields']['user']['type']);
        $this->assertSame('50', $definition['fields']['user']['maxlength']);
        $this->assertArrayNotHasKey('scope', $definition['fields']);
    }

    public function test_year_selection_for_creating_an_app_stays_an_ordinary_form(): void
    {
        $html = $this->render('<x-document-filter-card action="/app/create" data-app-year-form><select name="fiscal_year"><option>2026</option></select><button>Create APP</button></x-document-filter-card>');
        $xpath = $this->parse($html);
        $this->assertCount(1, $xpath->query('//form[@data-app-year-form]'));
        $this->assertCount(0, $xpath->query('//dialog'));
    }

    public function test_rfq_accounts_and_signature_filters_keep_their_original_field_values(): void
    {
        $cases = [
            ['head-office/rfqs/index.blade.php', ['search' => 'Supplies', 'fiscal_year' => '2026', 'status' => 'issued'], [
                'fiscalYears' => collect([2026]), 'statuses' => ['draft', 'issued'],
            ]],
            ['admin/users/index.blade.php', ['search' => 'Alex', 'role' => 'head_office', 'office' => 'Municipal'], [
                'roles' => ['head_office'], 'offices' => ['Municipal'],
            ]],
            ['signature-requests/index.blade.php', ['search' => 'Supplies', 'document_type' => 'rfq', 'status' => 'all'], [
                'documentTypes' => collect(['rfq']), 'documentTypeLabel' => fn ($type) => strtoupper($type),
                'statuses' => ['pending' => 'Pending', 'signed' => 'Signed'],
            ]],
        ];
        foreach ($cases as [$path, $filters, $data]) {
            $source = file_get_contents(resource_path('views/'.$path));
            preg_match('/<(x-navbar-filter-form|x-document-filter-card)\b(?:"[^"]*"|\x27[^\x27]*\x27|[^\x27">])*?>[\s\S]*?<\/\1>/', $source, $match);
            $this->assertNotEmpty($match, $path);
            $html = $this->render($match[0], ['filters' => $filters] + $data, $filters);
            $xpath = $this->parse($html);
            $this->assertCount(1, $xpath->query('//nav//input[@type="search"]'), $path);
            $this->assertCount(1, $xpath->query('//nav//button[@data-open-list-filters]'), $path);
            foreach ($filters as $name => $value) {
                $node = $name === 'search'
                    ? $xpath->query('//nav//input[@name="search"]')->item(0)
                    : $xpath->query('//dialog//select[@name="'.$name.'"]/option[@selected]')->item(0);
                $this->assertNotNull($node, $path.': '.$name);
                $this->assertSame($value, $node->getAttribute('value'), $path.': '.$name);
            }
        }
    }

    public function test_search_preserves_explicit_all_selections_and_removing_a_chip_restores_its_all_option(): void
    {
        $html = $this->render(<<<'BLADE'
            <x-navbar-filter-form action="/records" label="Records">
                <input type="search" name="search" value="Paper">
                <label>Fiscal year<select name="fiscal_year"><option value="" selected>All years</option><option value="2026">2026</option></select></label>
                <label>Status<select name="status"><option value="">All statuses</option><option value="issued" selected>Issued</option></select></label>
            </x-navbar-filter-form>
            BLADE);
        $xpath = $this->parse($html);
        $this->assertSame('', $xpath->query('//nav//input[@name="fiscal_year"]')->item(0)->getAttribute('value'));
        $link = $xpath->query('//a[@aria-label="Remove Status: Issued"]')->item(0);
        parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $remaining);
        $this->assertSame(['search' => 'Paper', 'fiscal_year' => '', 'status' => ''], $remaining);
        $this->assertSame('1', trim($xpath->query('//*[contains(@class,"document-list-filter-count")]')->item(0)->textContent));
    }
}
