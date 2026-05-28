<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Forms\Fields;

use Pepperfm\Flashboard\Contracts\Forms\FieldRenderer;

class FileUpload extends Field
{
    public const string ATTRIBUTE_ACCEPT = 'accept';
    public const string ATTRIBUTE_DIRECTORY = 'directory';
    public const string ATTRIBUTE_DISK = 'disk';
    public const string ATTRIBUTE_MAX_FILES = 'max_files';
    public const string ATTRIBUTE_MAX_SIZE = 'max_size';
    public const string ATTRIBUTE_MIMES = 'mimes';
    public const string ATTRIBUTE_MIME_TYPES = 'mime_types';
    public const string ATTRIBUTE_MULTIPLE = 'multiple';
    public const string ATTRIBUTE_PREVIEW = 'preview';
    public const string ATTRIBUTE_STORE_FILES = 'store_files';

    public const string REMOVE_REQUEST_SUFFIX = '__remove';

    public static function make(string $key, ?string $label = null): static
    {
        return parent::make($key, $label)->type(self::TYPE_FILE);
    }

    /**
     * @param list<string>|string|null $accept
     */
    public function accept(array|string|null $accept): static
    {
        return $this->attribute(self::ATTRIBUTE_ACCEPT, is_array($accept) ? implode(',', $accept) : $accept);
    }

    public function multiple(bool $condition = true): static
    {
        return $this->attribute(self::ATTRIBUTE_MULTIPLE, $condition);
    }

    public function maxSize(int $kilobytes): static
    {
        return $this->attribute(self::ATTRIBUTE_MAX_SIZE, $kilobytes);
    }

    public function maxFiles(int $files): static
    {
        return $this->attribute(self::ATTRIBUTE_MAX_FILES, $files);
    }

    /**
     * @param list<string> $mimes
     */
    public function mimes(array $mimes): static
    {
        return $this->attribute(self::ATTRIBUTE_MIMES, array_values($mimes));
    }

    /**
     * @param list<string> $mimeTypes
     */
    public function mimeTypes(array $mimeTypes): static
    {
        return $this->attribute(self::ATTRIBUTE_MIME_TYPES, array_values($mimeTypes));
    }

    public function disk(?string $disk): static
    {
        $this->attribute(self::ATTRIBUTE_DISK, $disk);

        return $disk === null ? $this : $this->storeFiles();
    }

    public function directory(?string $directory): static
    {
        $this->attribute(self::ATTRIBUTE_DIRECTORY, $directory);

        return $directory === null ? $this : $this->storeFiles();
    }

    public function storeFiles(bool $condition = true): static
    {
        return $this->attribute(self::ATTRIBUTE_STORE_FILES, $condition);
    }

    public function preview(bool $condition = true): static
    {
        return $this->attribute(self::ATTRIBUTE_PREVIEW, $condition);
    }

    protected function defaultRenderer(): ?FieldRenderer
    {
        return FieldRenderer::FileUpload;
    }
}
