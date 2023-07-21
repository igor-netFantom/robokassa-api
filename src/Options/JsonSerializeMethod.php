<?php

declare(strict_types=1);

namespace netFantom\RobokassaApi\Options;

trait JsonSerializeMethod
{
    public function jsonSerialize(): array
    {
        return array_filter((array)$this, static fn($value) => !is_null($value));
    }
}