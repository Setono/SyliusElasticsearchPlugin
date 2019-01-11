<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\Formatter;

final class StringFormatter implements StringFormatterInterface
{
    /**
     * {@inheritdoc}
     */
    public function formatToLowercaseWithoutSpaces(string $input): string
    {
        return mb_strtolower(str_replace([' ', '-'], '_', $input));
    }
}
