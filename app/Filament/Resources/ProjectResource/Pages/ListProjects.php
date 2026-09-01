<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Actions\ExportProjectsAction;
use App\Filament\Resources\ProjectResource;
use App\Models\Project;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListProjects extends ListRecords
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            ExportProjectsAction::make()
                ->visible(fn () => ProjectResource::canViewAny()),
        ];
    }

    /**
     * Get tabs for filtering projects.
     *
     * Tabs: All | My Projects | <Division tabs> | Archived
     */
    public function getTabs(): array
    {
        $tabs = [];

        $tabs['all'] = Tab::make('All')
            ->modifyQueryUsing(fn (Builder $query) => $query->active())
            ->badge(fn () => Project::active()->count())
            ->icon('heroicon-o-rectangle-stack');

        $tabs['my-projects'] = Tab::make('My Projects')
            ->modifyQueryUsing(fn (Builder $query) => $query->active()->where('created_by', auth()->id()))
            ->badge(fn () => Project::active()->where('created_by', auth()->id())->count())
            ->icon('heroicon-o-user');

        $divisions = User::whereHas('projects', function (Builder $q) {
            $q->active();
        })
            ->whereNotNull('division')
            ->where('division', '!=', '')
            ->distinct()
            ->pluck('division')
            ->sort()
            ->values();

        foreach ($divisions as $division) {
            $slug = \Illuminate\Support\Str::slug($division);
            $tabs[$slug] = Tab::make($division)
                ->modifyQueryUsing(function (Builder $query) use ($division) {
                    return $query->active()->whereHas('owner', fn (Builder $q) => $q->where('division', $division));
                })
                ->badge(function () use ($division) {
                    return Project::active()->whereHas('owner', fn (Builder $q) => $q->where('division', $division))->count();
                })
                ->icon('heroicon-o-building-office');
        }

        $tabs['archived'] = Tab::make('Archived')
            ->modifyQueryUsing(fn (Builder $query) => $query->archived())
            ->badge(fn () => Project::archived()->count())
            ->icon('heroicon-o-archive-box');

        return $tabs;
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'all';
    }
}
