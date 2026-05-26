<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Contracts\Resources;

use Pepperfm\Flashboard\Contracts\Actions\ActionContract;
use Pepperfm\Flashboard\Contracts\Detail\DetailContract;
use Pepperfm\Flashboard\Contracts\Extensions\ActionExtensionContract;
use Pepperfm\Flashboard\Contracts\Extensions\PayloadExtensionContract;
use Pepperfm\Flashboard\Contracts\Extensions\QueryExtensionContract;
use Pepperfm\Flashboard\Contracts\Extensions\RuntimeHookContract;
use Pepperfm\Flashboard\Contracts\Forms\FormContract;
use Pepperfm\Flashboard\Contracts\Navigation\NavigationItemContract;
use Pepperfm\Flashboard\Contracts\Pages\PageDefinitionContract;
use Pepperfm\Flashboard\Contracts\Resources\Relations\RelationDefinitionContract;
use Pepperfm\Flashboard\Contracts\Tables\TableActionContract;
use Pepperfm\Flashboard\Contracts\Tables\TableContract;
use Pepperfm\Flashboard\Core\Forms\Builders\Form;

abstract class Resource
{
    public const string DEFAULT_SUFFIX = 'Resource';

    /**
     * @return class-string<\Illuminate\Database\Eloquent\Model>
     */
    abstract public static function model(): string;

    public static function key(): string
    {
        $class = class_basename(static::class);
        $baseName = str($class)->before(static::DEFAULT_SUFFIX)->value();
        $value = $baseName === '' ? $class : $baseName;

        return str($value)->snake()->value();
    }

    public static function name(): string
    {
        return str(static::key())->headline()->value();
    }

    public static function navigationLabel(): string
    {
        return static::name();
    }

    public static function navigationGroup(): ?string
    {
        return null;
    }

    public static function navigationIcon(): ?string
    {
        return null;
    }

    public static function routeBasePath(): string
    {
        return static::key();
    }

    public static function query(): \Illuminate\Database\Eloquent\Builder
    {
        $model = static::model();

        return $model::query();
    }

    public static function table(TableContract $table): TableContract
    {
        return $table;
    }

    public static function form(FormContract $form): FormContract
    {
        return $form;
    }

    public static function detail(DetailContract $detail): DetailContract
    {
        return static::infolist($detail);
    }

    public static function infolist(DetailContract $detail): DetailContract
    {
        return $detail;
    }

    /**
     * @return list<ActionContract|TableActionContract|array<string, mixed>>
     */
    public static function actions(): array
    {
        return [];
    }

    /**
     * @return list<RelationDefinitionContract>
     */
    public static function relations(): array
    {
        return [];
    }

    /**
     * @return list<class-string<PageDefinitionContract>>
     */
    public static function pages(): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    public static function middleware(): array
    {
        return [];
    }

    public static function isVisibleInNavigation(): bool
    {
        return true;
    }

    public static function canAccess(?\Illuminate\Contracts\Auth\Authenticatable $user = null): bool
    {
        return true;
    }

    public static function policy(): ?string
    {
        return null;
    }

    /**
     * @return array<string, string>
     */
    public static function actionAbilityMap(): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    public static function fieldAbilityMap(): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    public static function relationAbilityMap(): array
    {
        return [];
    }

    /**
     * @return list<QueryExtensionContract>
     */
    public static function queryExtensions(): array
    {
        return [];
    }

    /**
     * @return list<PayloadExtensionContract>
     */
    public static function payloadExtensions(): array
    {
        return [];
    }

    /**
     * @return list<ActionExtensionContract>
     */
    public static function actionExtensions(): array
    {
        return [];
    }

    /**
     * @return list<RuntimeHookContract>
     */
    public static function runtimeHooks(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public static function creationRules(): array
    {
        return static::resolvedFormRules();
    }

    /**
     * @return array<string, mixed>
     */
    public static function updateRules(\Illuminate\Database\Eloquent\Model $record): array
    {
        return static::resolvedFormRules($record);
    }

    /**
     * @return array<string, mixed>
     */
    public static function formRules(?\Illuminate\Database\Eloquent\Model $record = null): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function resolvedFormRules(?\Illuminate\Database\Eloquent\Model $record = null): array
    {
        $rules = static::formRules($record);
        if ($rules !== []) {
            return $rules;
        }

        return (array) (static::form(Form::make())->toArray()['rules'] ?? []);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function mutateFormDataBeforeSave(array $data, ?\Illuminate\Database\Eloquent\Model $record = null): array
    {
        return $data;
    }

    /**
     * @param mixed $recordKey
     */
    public static function resolveRecord(mixed $recordKey): ?\Illuminate\Database\Eloquent\Model
    {
        return static::query()->find($recordKey);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function afterSave(\Illuminate\Database\Eloquent\Model $record, array $data): void
    {
    }

    public static function navigationItem(NavigationItemContract $item): NavigationItemContract
    {
        return $item
            ->label(static::navigationLabel())
            ->icon(static::navigationIcon())
            ->group(static::navigationGroup())
            ->visible(static::isVisibleInNavigation());
    }
}
