<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use BezhanSalleh\FilamentShield\Resources\RoleResource;
use BezhanSalleh\FilamentShield\Support\Utils;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Phpsa\FilamentPasswordReveal\Password;
use RalphJSmit\Filament\Components\Forms\Timestamps;
use Spatie\Permission\Models\Permission;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationGroup = 'Administration';

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->reactive(),
                        TextInput::make('email')
                            ->required()
                            ->email()
                            ->unique(User::class, 'email', fn ($record) => $record),
                        Toggle::make('reset_password')
                            ->columnSpan('full')
                            ->reactive()
                            ->dehydrated(false)
                            ->hiddenOn('create'),
                        Password::make('password')
                            ->visible(fn (string $operation, Forms\Get $get) => $operation === 'create' || $get('reset_password') == true)
                            ->generatable()
                            ->copyable(true)
                            ->passwordLength(12)
                            ->columnSpan('full')
                            ->rules(config('filament-breezy.password_rules', 'max:8'))
                            ->required(),
                        CheckboxList::make('roles')
                            ->columnSpan('full')
                            ->reactive()
                            ->relationship('roles', 'name', function (Builder $query) {
                                if (! auth()->user()->hasRole('super_admin')) {
                                    return $query->where('name', '<>', 'super_admin');
                                }

                                return $query->orderBy('id');
                            })
                            ->getOptionLabelFromRecordUsing(function ($record) {
                                return Str::of($record->name)->headline();
                            })
                            ->columns(4),
                        FileUpload::make('avatar_url')->label('Avatar')->image()->directory('avatar_urls')->columnSpan(2),
                        ...Timestamps::make(),
                    ])->columns(['md' => 2]),
                Forms\Components\Section::make('Permissions')
                    ->description('Users with roles have permission to completely manage resources based on the permissions set under the Roles Menu. To limit a user\'s access to specific resources disable thier roles and assign them individual permissions below.')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Tabs::make('Permissions')
                            ->tabs([
                                Tab::make(__('filament-shield::filament-shield.resources'))
                                    ->visible(fn (): bool => (bool) Utils::isResourceEntityEnabled())
                                    ->badge(RoleResource::getResourceTabBadgeCount())
                                    ->schema([
                                        Forms\Components\Grid::make()
                                            ->schema(RoleResource::getResourceEntitiesSchema())
                                            ->columns(FilamentShieldPlugin::get()->getGridColumns()),
                                    ]),
                                RoleResource::getTabFormComponentForPage(),
                                RoleResource::getTabFormComponentForWidget(),
                                RoleResource::getTabFormComponentForCustomPermissions(),
                            ])
                            ->columnSpan('full'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email'),
                TextColumn::make('roles.name')
                    ->badge()
                    ->formatStateUsing(function ($state) {
                        return Str::of($state)->headline();
                    }),
            ])
            ->filters([
                SelectFilter::make('roles')->relationship('roles', 'name'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
            ]);
    }
    
    public static function getRelations(): array
    {
        return [
            //
        ];
    }
    
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
