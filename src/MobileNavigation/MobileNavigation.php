<?php

declare(strict_types=1);

namespace VTech9NL\PolderTheme\MobileNavigation;

use Closure;
use Filament\Actions\Action;
use VTech9NL\PolderTheme\MobileNavigation\Concerns\EvaluatesWithPageContext;

class MobileNavigation
{
    use EvaluatesWithPageContext;

    protected MobileNavigationStyle $style = MobileNavigationStyle::Floating;

    /**
     * @var array<MobileNavigationItem> | Closure
     */
    protected array|Closure $items = [];

    protected Action|Closure|null $centerAction = null;

    protected MobileNavigationButton $centerButton = MobileNavigationButton::Raised;

    protected bool|Closure $isVisible = true;

    protected bool|Closure $isIconsOnly = false;

    protected bool|Closure $hasBackButton = true;

    public static function make(): static
    {
        return app(static::class);
    }

    public function style(MobileNavigationStyle $style): static
    {
        $this->style = $style;

        return $this;
    }

    /**
     * @param  array<MobileNavigationItem> | Closure  $items
     */
    public function items(array|Closure $items): static
    {
        $this->items = $items;

        return $this;
    }

    public function centerAction(Action|Closure|null $action, MobileNavigationButton $button = MobileNavigationButton::Raised): static
    {
        $this->centerAction = $action;
        $this->centerButton = $button;

        return $this;
    }

    public function visible(bool|Closure $condition = true): static
    {
        $this->isVisible = $condition;

        return $this;
    }

    public function iconsOnly(bool|Closure $condition = true): static
    {
        $this->isIconsOnly = $condition;

        return $this;
    }

    public function backButton(bool|Closure $condition = true): static
    {
        $this->hasBackButton = $condition;

        return $this;
    }

    public function hasBackButton(): bool
    {
        return (bool) $this->evaluate($this->hasBackButton);
    }

    public function isIconsOnly(): bool
    {
        return (bool) $this->evaluate($this->isIconsOnly);
    }

    public function isVisible(): bool
    {
        return (bool) $this->evaluate($this->isVisible);
    }

    public function getStyle(): MobileNavigationStyle
    {
        return $this->style;
    }

    /**
     * @return array<MobileNavigationItem>
     */
    public function getItems(): array
    {
        return array_map(
            fn (MobileNavigationItem $item): MobileNavigationItem => $item->pageContext($this->pageContext),
            array_values($this->evaluate($this->items)),
        );
    }

    public function getCenterAction(): ?Action
    {
        return $this->evaluate($this->centerAction);
    }

    public function getCenterButton(): MobileNavigationButton
    {
        return $this->centerButton;
    }
}
