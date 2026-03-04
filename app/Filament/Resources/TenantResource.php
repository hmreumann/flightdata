<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\CentralOnlyResource;
use App\Filament\Resources\TenantResource\Pages;
use App\Filament\Resources\TenantResource\RelationManagers;
use App\Models\Plan;
use App\Models\Tenant;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TenantResource extends Resource
{
    use CentralOnlyResource;

    protected static ?string $model = Tenant::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationLabel = 'Tenant Management';

    public static function form(Schema $schema): Schema
    {
        $isCreate = $schema->getOperation() === 'create';

        return $schema->components([
            TextInput::make('id')
                ->label('ID')
                ->required($isCreate)
                ->disabled(! $isCreate)
                ->dehydrated($isCreate)
                ->regex('/^[a-z0-9-]+$/')
                ->unique(Tenant::class, 'id', ignoreRecord: true)
                ->helperText('Becomes the subdomain')
                ->maxLength(63),

            TextInput::make('name')
                ->required()
                ->maxLength(255),

            Select::make('plan_id')
                ->label('Plan')
                ->options(Plan::query()->pluck('name', 'id'))
                ->searchable()
                ->nullable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable(),

                TextColumn::make('name')
                    ->searchable(),

                TextColumn::make('plan.name')
                    ->label('Plan')
                    ->badge()
                    ->placeholder('—'),

                TextColumn::make('domains_count')
                    ->label('Domains')
                    ->counts('domains'),

                TextColumn::make('created_at')
                    ->date()
                    ->sortable(),
            ]);
    }

    public static function getRelationManagers(): array
    {
        return [
            RelationManagers\DomainsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }
}
