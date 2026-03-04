<?php

namespace App\Filament\Concerns;

trait CentralOnlyResource
{
    public static function shouldRegisterNavigation(array $parameters = []): bool
    {
        return ! tenancy()->initialized;
    }

    public static function canAccess(): bool
    {
        return ! tenancy()->initialized;
    }
}
