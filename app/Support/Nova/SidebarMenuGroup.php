<?php

namespace App\Support\Nova;

use Laravel\Nova\Menu\MenuGroup as NovaMenuGroup;

/**
 * Группа меню Nova с фиксированным key для localStorage (nova.navigation.{key}.collapsed),
 * чтобы раскрытие не зависело от языка интерфейса (стандартный key() — md5 от переведённых строк).
 */
class SidebarMenuGroup extends NovaMenuGroup
{
    protected ?string $persistentNavigationKey = null;

    /**
     * @return $this
     */
    public function persistCollapseKey(string $key): static
    {
        $this->persistentNavigationKey = $key;

        return $this;
    }

    public function key(): string
    {
        if ($this->persistentNavigationKey !== null) {
            return $this->persistentNavigationKey;
        }

        return parent::key();
    }
}
