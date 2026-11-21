@php
    use VTech9NL\PolderTheme\MobileNavigation\MobileNavigationItem;

    $sheetId ??= null;
    $hideLabel = ($iconsOnly ?? false) && ! $sheetId;

    $disabled = ($entry['type'] === MobileNavigationItem::TYPE_LINK) && ($entry['disabled'] ?? false);

    $classes = \Illuminate\Support\Arr::toCssClasses([
        $class,
        'polder-theme-active' => (! $disabled) && ($entry['active'] ?? false),
        'polder-theme-disabled' => $disabled,
    ]);
@endphp

@if ($disabled)
    <span class="{{ $classes }}" @if ($hideLabel) aria-label="{{ $entry['label'] }}" @endif @if (isset($entry['title'])) title="{{ $entry['title'] }}" @endif aria-disabled="true">
        @include('polder-theme::livewire.partials.mobile-navigation-entry-content')
    </span>
@elseif ($entry['type'] === MobileNavigationItem::TYPE_LINK)
    <a href="{{ $entry['url'] }}" class="{{ $classes }}" @if ($hideLabel) aria-label="{{ $entry['label'] }}" @endif @if ($sheetId) x-on:click="$dispatch('bottom-sheet:close', { id: @js($sheetId) })" @endif @if (isset($entry['title'])) title="{{ $entry['title'] }}" @endif @if ($entry['active'] ?? false) aria-current="page" @endif>
        @include('polder-theme::livewire.partials.mobile-navigation-entry-content')
    </a>
@elseif ($entry['type'] === MobileNavigationItem::TYPE_ACTION)
    <button type="button" class="{{ $classes }}" @if ($hideLabel) aria-label="{{ $entry['label'] }}" @endif @if (isset($entry['title'])) title="{{ $entry['title'] }}" @endif @if ($sheetId) x-on:click="$dispatch('bottom-sheet:close', { id: @js($sheetId) })" @endif wire:click="mountAction(@js($entry['key']))">
        @include('polder-theme::livewire.partials.mobile-navigation-entry-content')
    </button>
@else
    <button type="button" class="{{ $classes }}" @if ($hideLabel) aria-label="{{ $entry['label'] }}" @endif @if (isset($entry['title'])) title="{{ $entry['title'] }}" @endif x-on:click="$dispatch('bottom-sheet:open', { id: @js('polder-theme-'.$entry['key']) })" aria-haspopup="dialog">
        @include('polder-theme::livewire.partials.mobile-navigation-entry-content')
    </button>
@endif
