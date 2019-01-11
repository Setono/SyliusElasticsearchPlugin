<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\Formatter;

interface StringFormatterInterface
{
    /**
     * @param string $input
     *
     * @return string
     */
    public function formatToLowercaseWithoutSpaces(string $input): string;
}
