<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Publication\Concerns\HandlesNaskahStudio;
use Filament\Pages\Page;

/**
 * NaskahStudioPage: word processor web bergaya WordCraft & LibreOffice Writer (Filament Panel).
 */
class NaskahStudioPage extends Page
{
    use HandlesNaskahStudio;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-pencil-square';

    protected static ?string $navigationLabel = 'Naskah Studio';

    protected static ?string $slug = 'naskah-studio';

    protected static ?string $title = 'Naskah Studio';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.naskah-studio';

    public function mount(): void
    {
        $this->bootHandlesNaskahStudio();
        $this->mountHandlesNaskahStudio(request()->query('document') !== null ? (int) request()->query('document') : null);
    }

    protected function getViewData(): array
    {
        return $this->getStudioViewData();
    }
}
