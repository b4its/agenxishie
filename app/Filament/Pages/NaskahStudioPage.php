<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Publication\Concerns\HandlesNaskahStudio;
use Filament\Notifications\Notification;
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

    public function mount(?int $document = null, ?int $project = null, ?string $stage = null): void
    {
        $this->bootHandlesNaskahStudio();
        $targetDocId = $document ?? (request()->query('document') !== null ? (int) request()->query('document') : null);
        $targetProjId = $project ?? (request('project') !== null ? (int) request('project') : null);
        $targetStage = $stage ?? request('stage');

        $this->mountHandlesNaskahStudio($targetDocId, $targetProjId, $targetStage);
    }

    protected function getViewData(): array
    {
        return $this->getStudioViewData();
    }

    protected function notify(string $title, string $body = '', string $type = 'success'): void
    {
        $n = Notification::make()->title($title);
        if ($body !== '') {
            $n->body($body);
        }
        match ($type) {
            'warning' => $n->warning(),
            'danger' => $n->danger(),
            'info' => $n->info(),
            default => $n->success(),
        };
        $n->send();
    }
}
