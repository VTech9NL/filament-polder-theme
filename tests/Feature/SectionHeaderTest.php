<?php

use Illuminate\Support\Facades\Blade;

use function Orchestra\Testbench\package_path;

it('renders narrow sections with a long description and a header action', function (int $span) {
    $html = Blade::render(<<<'BLADE'
        <div style="display: grid; grid-template-columns: repeat(12, minmax(0, 1fr))">
            <div style="grid-column: span {{ $span }}">
                <x-filament::section heading="Contact information" description="A long description that must wrap within the space left beside the Edit action, even when this section occupies only a small part of the page.">
                    <x-slot name="afterHeader">
                        <x-filament::button size="sm" outlined>Edit</x-filament::button>
                    </x-slot>
                    Section content
                </x-filament::section>
            </div>
        </div>
        BLADE, ['span' => $span]);

    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    $content = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " fi-section-header-content ")]')->item(0);

    expect($content)->not->toBeNull()
        ->and($xpath->query('./div[contains(@class, "fi-section-header-text-ctn")]', $content)->length)->toBe(1)
        ->and($xpath->query('./div[contains(@class, "fi-section-header-after-ctn")]//button', $content)->length)->toBe(1)
        ->and($html)->toContain('grid-column: span '.$span, 'Edit', 'A long description');
})->with([4, 6]);

it('reserves the actions width and lets section text shrink at every breakpoint', function () {
    $css = file_get_contents(package_path('resources/css/theme.css'));

    expect($css)
        ->toMatch('/\.fi-section-header,\s*\.fi-section-header-content\s*\{\s*flex-wrap: nowrap;/')
        ->toMatch('/\.fi-section-header-text-ctn\s*\{[^}]*min-width: 0;[^}]*width: auto;[^}]*flex: 1;/s')
        ->toMatch('/\.fi-section-header-after-ctn\s*\{\s*flex-shrink: 0;\s*align-self: flex-start;\s*margin-inline-start: auto;/');
});
