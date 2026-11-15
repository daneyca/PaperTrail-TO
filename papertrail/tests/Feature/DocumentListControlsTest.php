<?php

namespace Tests\Feature;

use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class DocumentListControlsTest extends TestCase
{
    private function fields(): array
    {
        return [
            'fiscal_year' => ['label' => 'Fiscal year', 'empty' => 'All years', 'options' => [2026 => '2026']],
            'status' => ['label' => 'Status', 'empty' => 'All statuses', 'options' => ['accepted' => 'Accepted']],
            'date_from' => ['label' => 'Updated from', 'type' => 'date'],
        ];
    }

    private function parse(string $html): DOMXPath
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        return new DOMXPath($document);
    }

    public function test_navbar_search_keeps_page_filters_and_clear_filters_keeps_the_search_term(): void
    {
        $action = route('head-office.ppmp.index');
        $html = Blade::render('<x-document-list-controls :action="$action" :filters="$filters" :fields="$fields" /> @stack("modals")', [
            'action' => $action,
            'fields' => $this->fields(),
            'filters' => ['search' => 'Office supplies', 'fiscal_year' => '2026', 'status' => 'accepted', 'page' => '5'],
        ]);
        $xpath = $this->parse($html);
        $search = $xpath->query('//form[@data-list-search]')->item(0);

        $this->assertSame($action, $search->getAttribute('action'));
        $this->assertSame('Office supplies', $xpath->query('.//input[@name="search"]', $search)->item(0)->getAttribute('value'));
        $this->assertSame('accepted', $xpath->query('.//input[@name="status"]', $search)->item(0)->getAttribute('value'));
        $this->assertSame('2026', $xpath->query('.//input[@name="fiscal_year"]', $search)->item(0)->getAttribute('value'));
        $this->assertCount(0, $xpath->query('//input[@name="page"]'));
        $this->assertSame('2', trim($xpath->query('//*[contains(@class,"document-list-filter-count")]')->item(0)->textContent));
        $this->assertSame($action.'?search=Office+supplies', $xpath->query('//a[normalize-space()="Clear filters"]')->item(0)->getAttribute('href'));
        $this->assertSame('accepted', $xpath->query('//dialog//select[@name="status"]/option[@selected]')->item(0)->getAttribute('value'));
    }

    public function test_removing_one_chip_preserves_other_filters_and_resets_pagination(): void
    {
        $action = route('head-office.ppmp.index');
        $term = 'Office <script>alert(1)</script>';
        $html = Blade::render('<x-document-filter-summary :action="$action" :filters="$filters" :fields="$fields" :total="6" />', [
            'action' => $action,
            'fields' => $this->fields(),
            'filters' => ['search' => $term, 'fiscal_year' => '2026', 'status' => 'accepted', 'date_from' => '2026-11-01', 'page' => '5'],
        ]);
        $xpath = $this->parse($html);
        $link = $xpath->query('//a[@aria-label="Remove Status: Accepted"]')->item(0);
        parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $remaining);

        $this->assertSame(['search' => $term, 'fiscal_year' => '2026', 'date_from' => '2026-11-01'], $remaining);
        $this->assertCount(0, $xpath->query('//script'));
        $this->assertStringContainsString('Updated from: Nov 1, 2026', $html);
        $this->assertSame($action, $xpath->query('//a[normalize-space()="Clear all"]')->item(0)->getAttribute('href'));
    }

    public function test_ppmp_pages_render_one_navbar_search_and_show_the_filter_dialog_outside_the_table(): void
    {
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('canManageSvpDocuments')->andReturnFalse();
        Auth::setUser($user);

        foreach (['head-office', 'bac-secretariat'] as $area) {
            // Exercise the actual page sections without the database-backed account menu.
            $template = str_replace("@extends('layouts.dashboard')", '', file_get_contents(resource_path('views/'.$area.'/ppmp/index.blade.php')));
            $template .= '<nav>@yield("topbar-search")</nav><main>@yield("content")</main>@stack("modals")@stack("page-styles")';
            $html = Blade::render($template, [
                'filters' => ['status' => 'accepted', 'search' => 'Office supplies'],
                'summary' => array_fill_keys(['total', 'draft', 'submitted', 'reviewed', 'returned', 'accepted', 'budget', 'underReview'], 0),
                'documents' => new LengthAwarePaginator([], 0, 10),
                'statuses' => ['accepted_for_app_consolidation'],
                'documentTypes' => collect(['PPMP']), 'fiscalYears' => collect([2026]),
                'stages' => collect(['Accepted for APP Consolidation']), 'offices' => collect(),
            ]);
            $xpath = $this->parse($html);
            $this->assertCount(1, $xpath->query('//input[@type="search"]'));
            $this->assertCount(1, $xpath->query('//nav//form[@data-list-search]'));
            $this->assertCount(1, $xpath->query('//dialog[@data-list-filter-dialog]'));
            $this->assertCount(0, $xpath->query('//main//dialog'));
            $this->assertStringContainsString('No PPMPs match your search or filters', $html);
            $this->assertStringNotContainsString('ppmp-filter-toolbar', $html);
        }
    }
}
