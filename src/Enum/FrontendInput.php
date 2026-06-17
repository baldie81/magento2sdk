<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Enum;

/**
 * Catalog input types for a product attribute — Magento's `frontend_input`
 * (the "Catalog Input Type for Store Owner" choices). Magento infers the
 * attribute's backend storage type from this value.
 */
enum FrontendInput: string
{
    case Text         = 'text';
    case Textarea     = 'textarea';
    case TextEditor   = 'texteditor';
    case Date         = 'date';
    case Datetime     = 'datetime';
    case Boolean      = 'boolean';
    case Select       = 'select';
    case Multiselect  = 'multiselect';
    case Price        = 'price';
    case MediaImage   = 'media_image';
    case Weight       = 'weight';
    case SwatchVisual = 'swatch_visual';
    case SwatchText   = 'swatch_text';

    /**
     * True for option-backed inputs (dropdown / multiselect / swatches) — the
     * ones whose values are option ids drawn from an option list.
     */
    public function isSelectable(): bool
    {
        return match ($this) {
            self::Select, self::Multiselect, self::SwatchVisual, self::SwatchText => true,
            default => false,
        };
    }

    /**
     * True for inputs that hold more than one selected option (multiselect).
     */
    public function isMultiValued(): bool
    {
        return $this === self::Multiselect;
    }
}
