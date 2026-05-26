<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Persistence;

use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\Eloquent\Model;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Hooks\RuntimeHookDispatcher;
use Pepperfm\Flashboard\Core\Forms\Builders\Form;

#[Singleton]
final readonly class ResourceFormPersister
{
    public function __construct(
        private RuntimeHookDispatcher $runtimeHookDispatcher,
    ) {
    }

    /**
     * @param class-string<Resource> $resourceClass
     */
    public function create(string $resourceClass, array $payload): Model
    {
        $this->runtimeHookDispatcher->dispatch($resourceClass, 'resource.create.before', [
            'payload' => $payload,
        ]);

        $form = $resourceClass::form(Form::make());
        $data = $form->mutateData($payload);
        $data = $resourceClass::mutateFormDataBeforeSave($data, null);
        $modelClass = $resourceClass::model();
        $record = new $modelClass();
        $record->forceFill($data);
        $record->save();

        $form->runAfterSave($record, $data);
        $resourceClass::afterSave($record, $data);
        $this->runtimeHookDispatcher->dispatch($resourceClass, 'resource.create.after', [
            'payload' => $data,
            'record' => $record->attributesToArray(),
        ]);

        return $record;
    }

    /**
     * @param class-string<Resource> $resourceClass
     */
    public function update(string $resourceClass, Model $record, array $payload): Model
    {
        $this->runtimeHookDispatcher->dispatch($resourceClass, 'resource.update.before', [
            'payload' => $payload,
            'record' => $record->attributesToArray(),
        ]);

        $form = $resourceClass::form(Form::make());
        $data = $form->mutateData($payload, $record);
        $data = $resourceClass::mutateFormDataBeforeSave($data, $record);
        $record->forceFill($data);
        $record->save();

        $form->runAfterSave($record, $data);
        $resourceClass::afterSave($record, $data);
        $this->runtimeHookDispatcher->dispatch($resourceClass, 'resource.update.after', [
            'payload' => $data,
            'record' => $record->attributesToArray(),
        ]);

        return $record;
    }

    /**
     * @param class-string<Resource> $resourceClass
     */
    public function delete(string $resourceClass, Model $record): void
    {
        $recordKey = $record->getKey();

        $this->runtimeHookDispatcher->dispatch($resourceClass, 'resource.delete.before', [
            'record_key' => $recordKey,
        ]);

        $record->delete();

        $this->runtimeHookDispatcher->dispatch($resourceClass, 'resource.delete.after', [
            'record_key' => $recordKey,
        ]);
    }
}
