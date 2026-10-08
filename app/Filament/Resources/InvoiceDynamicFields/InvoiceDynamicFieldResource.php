<?php

namespace App\Filament\Resources\InvoiceDynamicFields;

use App\Filament\Resources\InvoiceDynamicFields\Pages\CreateInvoiceDynamicField;
use App\Filament\Resources\InvoiceDynamicFields\Pages\EditInvoiceDynamicField;
use App\Filament\Resources\InvoiceDynamicFields\Pages\ListInvoiceDynamicFields;
use App\Filament\Resources\InvoiceDynamicFields\Schemas\InvoiceDynamicFieldForm;
use App\Filament\Resources\InvoiceDynamicFields\Tables\InvoiceDynamicFieldsTable;
use App\Models\InvoiceDynamicGroup;
use App\Models\Merchant;
use App\Models\PermissionModule;
use App\Models\User;
use App\Support\UiModules;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InvoiceDynamicFieldResource extends Resource
{
    protected static ?string $model = InvoiceDynamicGroup::class;

    protected static ?string $slug = 'invoice-templates';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::DocumentText;

    protected static string|\UnitEnum|null $navigationGroup = 'Configurations';

    protected static ?string $navigationLabel = 'Invoice Templates';

    protected static ?string $modelLabel = 'Invoice Template';

    protected static ?string $pluralModelLabel = 'Invoice Templates';

    protected static ?int $navigationSort = 7;

    public static function canViewAny(): bool
    {
        $user = Filament::auth()->user();

        $guard = Filament::getCurrentPanel()->getAuthGuard();

        if (! $user) {
            return false;
        }

        if (! PermissionModule::isEnabledForCurrentMerchant('invoice_templates')) {
            return false;
        }

        return $user->hasPermissionTo('invoice_templates.view', $guard);
    }

    public static function canCreate(): bool
    {
        $user = Filament::auth()->user();
        $guard = Filament::getCurrentPanel()->getAuthGuard();

        if (! $user) {
            return false;
        }

        return $user->hasPermissionTo('invoice_templates.create', $guard);
    }

    public static function canEdit($record): bool
    {
        $user = Filament::auth()->user();
        $guard = Filament::getCurrentPanel()->getAuthGuard();

        if (! $user) {
            return false;
        }

        return $user->hasPermissionTo('invoice_templates.update', $guard);
    }

    public static function canDelete($record): bool
    {
        $user = Filament::auth()->user();
        $guard = Filament::getCurrentPanel()->getAuthGuard();

        if (! $user) {
            return false;
        }

        return $user->hasPermissionTo('invoice_templates.delete', $guard);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return UiModules::enabled('invoice_dynamic_fields')
            && in_array(Filament::getCurrentPanel()?->getId(), ['merchant', 'user'], true);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Filament::auth()->user();

        if ($user instanceof Merchant) {
            return $query->where('merchant_id', $user->id);
        }

        if ($user instanceof User && $user->merchant_id) {
            return $query->where('merchant_id', $user->merchant_id);
        }

        return $query->whereRaw('1 = 0');
    }

    public static function form(Schema $schema): Schema
    {
        return InvoiceDynamicFieldForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InvoiceDynamicFieldsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoiceDynamicFields::route('/'),
            'create' => CreateInvoiceDynamicField::route('/create'),
            'edit' => EditInvoiceDynamicField::route('/{record}/edit'),
        ];
    }

    public static function resolveMerchantId(): ?string
    {
        $user = Filament::auth()->user();

        if ($user instanceof Merchant) {
            return $user->id;
        }

        if ($user instanceof User) {
            return $user->merchant_id;
        }

        return null;
    }
}
