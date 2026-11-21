<?php

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Illuminate\Support\Str;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use VTech9NL\PolderTheme\Livewire\MobileNavigationBar;
use VTech9NL\PolderTheme\MobileNavigation\MobileNavigation;
use VTech9NL\PolderTheme\MobileNavigation\MobileNavigationItem;
use VTech9NL\PolderTheme\PolderThemePlugin;
use VTech9NL\PolderTheme\Tests\Fixtures\Team;
use VTech9NL\PolderTheme\Tests\Fixtures\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->user = User::query()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com', 'password' => 'secret']);
    $this->team = Team::query()->create(['name' => 'Analytical Engine']);
    $this->user->teams()->attach($this->team);

    actingAs($this->user);

    $this->ran = null;

    Filament::getPanel('app')->getPlugin(PolderThemePlugin::ID)->mobileNavigation(
        MobileNavigation::make()
            ->items([
                MobileNavigationItem::link('home')->label('Home')->url(fn (): string => Dashboard::getUrl()),
                MobileNavigationItem::action(fn (): Action => Action::make('scan')->label('Scan')->action(fn () => $this->ran = 'scan')),
                MobileNavigationItem::sheet('tools')->label('Tools')->items([
                    MobileNavigationItem::action(fn (): Action => Action::make('export')->label('Export')->action(fn () => $this->ran = 'export')),
                ]),
                MobileNavigationItem::sheet('more')->navigation(),
                MobileNavigationItem::sheet('account')->userMenu(),
            ])
            ->centerAction(fn (?string $routeName, array $routeParameters): Action => Action::make('logActivity')
                ->action(function () use ($routeName, $routeParameters): void {
                    $this->ran = [
                        'panel' => Filament::getCurrentPanel()?->getId(),
                        'tenant' => Filament::getTenant()?->getKey(),
                        'routeName' => $routeName,
                        'routeParameters' => $routeParameters,
                    ];
                })),
    );
});

it('renders the bar on a panel page and remembers that page', function () {
    get('/app/'.$this->team->getKey())
        ->assertOk()
        ->assertSee('polder-theme-mobile-navigation')
        ->assertSee('filament.app.pages.dashboard');
});

it('runs the app centre action with the panel, tenant and page it was mounted on', function () {
    Filament::setCurrentPanel('app');
    Filament::setTenant($this->team, isQuiet: true);

    $bar = livewire(MobileNavigationBar::class, [
        'routeName' => 'filament.app.pages.dashboard',
        'routeParameters' => ['tenant' => (string) $this->team->getKey()],
    ]);

    Filament::setCurrentPanel('compact');
    Filament::setTenant(null, isQuiet: true);

    $bar->callAction(MobileNavigationBar::CENTER_ACTION);

    expect($this->ran)->toBe([
        'panel' => 'app',
        'tenant' => $this->team->getKey(),
        'routeName' => 'filament.app.pages.dashboard',
        'routeParameters' => ['tenant' => (string) $this->team->getKey()],
    ]);
});

it('does not let the browser change the panel, tenant or page it was mounted on', function () {
    Filament::setCurrentPanel('app');
    Filament::setTenant($this->team, isQuiet: true);

    livewire(MobileNavigationBar::class)->set('tenantKey', 999);
})->throws(CannotUpdateLockedPropertyException::class);

it('refuses a tenant the user does not belong to', function () {
    Filament::setCurrentPanel('app');
    Filament::setTenant(Team::query()->create(['name' => 'Difference Engine']), isQuiet: true);

    $bar = livewire(MobileNavigationBar::class);
    Filament::setTenant(null, isQuiet: true);

    $bar->call('mountAction', MobileNavigationBar::CENTER_ACTION)->assertForbidden();

    expect($this->ran)->toBeNull();
});

it('refuses a signed-out request', function () {
    Filament::setCurrentPanel('app');
    Filament::setTenant($this->team, isQuiet: true);

    $bar = livewire(MobileNavigationBar::class);
    auth()->logout();

    $bar->call('mountAction', MobileNavigationBar::CENTER_ACTION)->assertForbidden();

    expect($this->ran)->toBeNull();
});

it('shows the configured items and marks the current page', function () {
    get('/app/'.$this->team->getKey())
        ->assertOk()
        ->assertSeeInOrder(['Home', 'Scan', 'Tools', 'More', 'Account'])
        ->assertSee('aria-current="page"', escape: false);
});

it('runs an action item from the bar and from a custom sheet', function () {
    Filament::setCurrentPanel('app');
    Filament::setTenant($this->team, isQuiet: true);

    livewire(MobileNavigationBar::class)->callAction('polderItem1');
    expect($this->ran)->toBe('scan');

    livewire(MobileNavigationBar::class)->callAction('polderItem2_0');
    expect($this->ran)->toBe('export');
});

it('lists the panel navigation in the More sheet', function () {
    Filament::setCurrentPanel('app');
    Filament::setTenant($this->team, isQuiet: true);

    $more = collect(livewire(MobileNavigationBar::class)->get('items'))->firstWhere('label', 'More');

    expect(collect($more['groups'])->pluck('items')->flatten(1)->pluck('label'))->toContain('Dashboard');
});

it('offers the user menu and the other tenants in the Account sheet', function () {
    $this->user->teams()->attach(Team::query()->create(['name' => 'Difference Engine']));

    $html = get('/app/'.$this->team->getKey())->assertOk()->getContent();
    $bar = substr($html, strpos($html, 'class="polder-theme-mobile-navigation '));

    expect($bar)
        ->toContain(__('filament-panels::layout.actions.logout.label'))
        ->toContain('Difference Engine');
});

it('hides the bar where the app says so', function () {
    $navigation = Filament::getPanel('app')->getPlugin(PolderThemePlugin::ID)->getMobileNavigation();

    $navigation->visible(fn (?string $routeName): bool => $routeName !== 'filament.app.pages.dashboard');
    get('/app/'.$this->team->getKey())->assertOk()->assertDontSee('polder-theme-mobile-navigation');

    $navigation->visible(fn (?string $routeName): bool => $routeName === 'filament.app.pages.dashboard');
    get('/app/'.$this->team->getKey())->assertOk()->assertSee('polder-theme-mobile-navigation');
});

it('accepts file uploads only into the fields of its actions', function () {
    Filament::setCurrentPanel('app');
    Filament::setTenant($this->team, isQuiet: true);

    livewire(MobileNavigationBar::class)->call('_startUpload', 'notAField', [], false)->assertForbidden();
});

it('renders no bar on a panel without mobile navigation', function () {
    get('/compact')->assertOk()->assertDontSee('polder-theme-mobile-navigation');
});

it('marks a custom sheet active from its links unless explicitly overridden', function () {
    Filament::setCurrentPanel('app');
    Filament::setTenant($this->team, isQuiet: true);
    $sheet = MobileNavigationItem::sheet('earnings')->items([
        MobileNavigationItem::link('tips')->isActiveWhen(fn (?string $routeName): bool => $routeName === 'tips'),
    ]);
    Filament::getPanel('app')->getPlugin(PolderThemePlugin::ID)->getMobileNavigation()->items([$sheet]);

    livewire(MobileNavigationBar::class, ['routeName' => 'tips'])->assertSet('items.0.active', true);
    livewire(MobileNavigationBar::class, ['routeName' => 'other'])->assertSet('items.0.active', false);
    $sheet->isActiveWhen(false);
    livewire(MobileNavigationBar::class, ['routeName' => 'tips'])->assertSet('items.0.active', false);
});

it('uses an explicit active condition for every kind of sheet', function (string $source) {
    Filament::setCurrentPanel('app');
    Filament::setTenant($this->team, isQuiet: true);
    $sheet = MobileNavigationItem::sheet('profile');
    match ($source) {
        'items' => $sheet->items([]),
        'navigation' => $sheet->navigation(),
        'userMenu' => $sheet->userMenu(),
        'view' => $sheet->view('filament::components.loading-indicator'),
    };
    Filament::getPanel('app')->getPlugin(PolderThemePlugin::ID)->getMobileNavigation()->items([$sheet]);

    livewire(MobileNavigationBar::class)->assertSet('items.0.active', false);
    $sheet->isActiveWhen(fn (?string $routeName): bool => $routeName === 'profile');
    livewire(MobileNavigationBar::class, ['routeName' => 'profile'])->assertSet('items.0.active', true);
})->with(['items', 'navigation', 'userMenu', 'view']);

it('renders disabled links with a title and no href in the bar and custom sheets', function (bool $inSheet) {
    $item = MobileNavigationItem::link('room')->url('/blocked-room')->isActiveWhen(true)
        ->disabled(fn (?string $routeName): bool => $routeName === 'filament.app.pages.dashboard')
        ->title(fn (array $routeParameters): string => 'Blocked for '.$routeParameters['tenant']);
    Filament::getPanel('app')->getPlugin(PolderThemePlugin::ID)->getMobileNavigation()
        ->items([$inSheet ? MobileNavigationItem::sheet('tools')->items([$item]) : $item]);

    $html = get('/app/'.$this->team->getKey())->assertOk()->getContent();
    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $nodes = (new DOMXPath($dom))->query('//*[@aria-disabled="true"]');
    expect($nodes)->toHaveCount(1);
    $entry = $nodes->item(0);
    expect($entry->nodeName)->toBe('span')
        ->and($entry->hasAttribute('href'))->toBeFalse()
        ->and($entry->getAttribute('title'))->toBe('Blocked for '.$this->team->getKey())
        ->and($entry->getAttribute('class'))->toContain('polder-theme-disabled')->not->toContain('polder-theme-active');
})->with([false, true]);

it('renders escaped titles on each entry type and keeps enabled links', function () {
    Filament::getPanel('app')->getPlugin(PolderThemePlugin::ID)->getMobileNavigation()->items([
        MobileNavigationItem::link('room')->url('/room')->disabled(false)->title('Room "ready"'),
        MobileNavigationItem::action(fn (): Action => Action::make('scan'))->title('Scan title'),
        MobileNavigationItem::sheet('tools')->title('Tools title'),
    ]);

    get('/app/'.$this->team->getKey())->assertOk()
        ->assertSee('href="/room"', escape: false)
        ->assertSee('title="Room &quot;ready&quot;"', escape: false)
        ->assertSee('title="Scan title"', escape: false)
        ->assertSee('title="Tools title"', escape: false)
        ->assertDontSee('aria-disabled="true"', escape: false);
});

it('marks a sheet active when a child URL matches the current page', function () {
    Filament::getPanel('app')->getPlugin(PolderThemePlugin::ID)->getMobileNavigation()->items([
        MobileNavigationItem::sheet('earnings')->items([
            MobileNavigationItem::link('tips')->url(fn (): string => Dashboard::getUrl()),
        ]),
    ]);

    $html = get('/app/'.$this->team->getKey())->assertOk()->getContent();
    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $entry = (new DOMXPath($dom))->query('//button[@aria-haspopup="dialog"]')->item(0);
    expect($entry->getAttribute('class'))->toContain('polder-theme-active');
});

it('hides the mobile header toggle only when navigation is visible on the current page', function (string $state) {
    $plugin = Filament::getPanel('app')->getPlugin(PolderThemePlugin::ID);
    if ($state === 'absent') {
        $plugin->mobileNavigation(null);
    } else {
        $plugin->getMobileNavigation()->visible(fn (?string $routeName, array $routeParameters): bool => $state === 'visible' && $routeName === 'filament.app.pages.dashboard'
            && $routeParameters['tenant'] === (string) $this->team->getKey());
    }

    $html = get('/app/'.$this->team->getKey())->assertOk()->getContent();
    $header = Str::betweenFirst($html, 'class="polder-theme-breadcrumb-bar"', 'class="fi-header');

    expect($header)->toContain('polder-theme-sidebar-toggle')
        ->not->toContain('fi-user-menu-trigger');
    expect(str_contains($header, 'polder-theme-sidebar-toggle-desktop-only'))->toBe($state === 'visible');
})->with(['visible', 'hidden', 'absent']);

it('renders an escaped icon badge on links actions and sheets with page-aware colours', function (string $type) {
    $item = match ($type) {
        'link' => MobileNavigationItem::link('messages')->url('/messages'),
        'action' => MobileNavigationItem::action(fn (): Action => Action::make('messages')),
        'sheet' => MobileNavigationItem::sheet('messages'),
    };
    $item->badge(fn (?string $routeName): string => $routeName === 'filament.app.pages.dashboard' ? '<12>' : 'wrong page')
        ->badgeColor(fn (array $routeParameters): string => $routeParameters['tenant'] === (string) $this->team->getKey() ? 'danger' : 'gray');
    Filament::getPanel('app')->getPlugin(PolderThemePlugin::ID)->getMobileNavigation()->items([$item]);

    $html = get('/app/'.$this->team->getKey())->assertOk()->getContent();
    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $badge = (new DOMXPath($dom))->query('//span[contains(@class, "polder-theme-mobile-navigation-icon")]/span[contains(@class, "polder-theme-mobile-navigation-badge")]');

    expect($badge)->toHaveCount(1);
    expect(trim($badge->item(0)->textContent))->toBe('<12>');
    expect($badge->item(0)->getAttribute('class'))->toContain('fi-color-danger');
    expect($html)->toContain('&lt;12&gt;')->not->toContain('<12>');
})->with(['link', 'action', 'sheet']);

it('omits empty and zero badges and uses primary for a visible badge by default', function (string|int|null $value) {
    Filament::getPanel('app')->getPlugin(PolderThemePlugin::ID)->getMobileNavigation()->items([
        MobileNavigationItem::link('messages')->url('/messages')->badge($value),
    ]);

    $response = get('/app/'.$this->team->getKey())->assertOk();

    if ($value === 5) {
        $dom = new DOMDocument;
        @$dom->loadHTML($response->getContent());
        $badge = (new DOMXPath($dom))->query('//span[contains(@class, "polder-theme-mobile-navigation-badge")]');
        expect($badge)->toHaveCount(1);
        expect($badge->item(0)->getAttribute('class'))->toContain('fi-color-primary');
    } else {
        $response->assertDontSee('polder-theme-mobile-navigation-badge');
    }
})->with([null, 0, '0', '', 5]);

it('renders each sheet as a bottom sheet element that the items open and close', function () {
    $html = get('/app/'.$this->team->getKey())->assertOk()->getContent();
    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    $sheets = $xpath->query('//div[@data-bottom-sheet-id]');

    expect(collect($sheets)->map(fn (DOMElement $sheet): string => $sheet->getAttribute('data-bottom-sheet-id'))->all())
        ->toBe(['polder-theme-polderItem2', 'polder-theme-polderItem3', 'polder-theme-polderItem4']);

    foreach ($sheets as $sheet) {
        expect($sheet->hasAttribute('x-bottom-sheet:element'))->toBeTrue()
            ->and($sheet->getAttribute('data-bottom-sheet-initial-height'))->toBe('content')
            ->and($sheet->hasAttribute('wire:ignore.self'))->toBeTrue()
            ->and($sheet->hasAttribute('data-bottom-sheet-overlay'))->toBeFalse()
            ->and($xpath->query('./div[@data-bottom-sheet-content]', $sheet)->length)->toBe(1);
    }

    expect($xpath->query('//button[@aria-haspopup="dialog"]')->item(0)->getAttribute('x-on:click'))
        ->toBe("\$dispatch('bottom-sheet:open', { id: 'polder-theme-polderItem2' })");
    expect($xpath->query('//*[@data-bottom-sheet-id="polder-theme-polderItem2"]//button[contains(@class, "polder-theme-mobile-navigation-tile")]')->item(0)->getAttribute('x-on:click'))
        ->toBe("\$dispatch('bottom-sheet:close', { id: 'polder-theme-polderItem2' })");
    expect($xpath->query('//*[@data-bottom-sheet-id="polder-theme-polderItem3"]//a')->item(0)->getAttribute('x-on:click'))
        ->toBe("\$dispatch('bottom-sheet:close', { id: 'polder-theme-polderItem3' })");
    expect($xpath->query('//*[@data-bottom-sheet-id="polder-theme-polderItem4"]//*[contains(@class, "fi-dropdown-list")]')->item(0)->getAttribute('x-on:click'))
        ->toContain("\$dispatch('bottom-sheet:close', { id: 'polder-theme-polderItem4' })");
    expect($html)->not->toContain('polder-theme-mobile-navigation-sheet-backdrop')
        ->not->toContain('polder-theme-mobile-navigation-sheet-handle')
        ->not->toContain('x-on:click="sheet');
});
