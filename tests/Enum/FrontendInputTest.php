<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Tests\Enum;

use Baldie81\Magento2SDK\Enum\FrontendInput;
use PHPUnit\Framework\TestCase;

final class FrontendInputTest extends TestCase
{
    public function testSelectableInputs(): void
    {
        self::assertTrue(FrontendInput::Select->isSelectable());
        self::assertTrue(FrontendInput::Multiselect->isSelectable());
        self::assertTrue(FrontendInput::SwatchText->isSelectable());
        self::assertFalse(FrontendInput::Text->isSelectable());
        self::assertFalse(FrontendInput::Price->isSelectable());
    }

    public function testMultiValuedInputs(): void
    {
        self::assertTrue(FrontendInput::Multiselect->isMultiValued());
        self::assertFalse(FrontendInput::Select->isMultiValued());
    }
}
