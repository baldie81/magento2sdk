<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Support;

/**
 * Fluent builder for a product media gallery entry (the inner "entry" body).
 *
 *   MediaEntry::image($base64, 'image/jpeg', 'front.jpg')
 *       ->label('Front')->roles(['image', 'small_image', 'thumbnail']);
 */
final class MediaEntry implements Arrayable
{
    /** @var array<string,mixed> */
    private array $fields = [];

    public static function make(string $mediaType = 'image'): self
    {
        $entry = new self();
        $entry->fields['media_type'] = $mediaType;

        return $entry;
    }

    /**
     * An image entry from raw base64 content.
     */
    public static function image(string $base64, string $mimeType, string $fileName): self
    {
        return self::make('image')->content($base64, $mimeType, $fileName);
    }

    public function content(string $base64, string $mimeType, string $fileName): self
    {
        $this->fields['content'] = [
            'base64_encoded_data' => $base64,
            'type'                => $mimeType,
            'name'                => $fileName,
        ];

        return $this;
    }

    public function label(string $label): self
    {
        $this->fields['label'] = $label;

        return $this;
    }

    /**
     * The image roles this entry fills (image / small_image / thumbnail / …).
     *
     * @param list<string> $roles
     */
    public function roles(array $roles): self
    {
        $this->fields['types'] = array_values($roles);

        return $this;
    }

    public function position(int $position): self
    {
        $this->fields['position'] = $position;

        return $this;
    }

    public function disabled(bool $disabled = true): self
    {
        $this->fields['disabled'] = $disabled;

        return $this;
    }

    public function set(string $field, mixed $value): self
    {
        $this->fields[$field] = $value;

        return $this;
    }

    public function toArray(): array
    {
        return $this->fields;
    }
}
