<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\ValueTransformer;

use Symfony\Component\JsonEncoder\Exception\InvalidArgumentException;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\TypeIdentifier;

/**
 * Transforms DateTimeInterface to string during encoding.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @experimental
 */
final class DateTimeToStringValueTransformer implements ValueTransformerInterface
{
    public const FORMAT_KEY = 'date_time_format';

    public function transform(mixed $value, array $options = []): string
    {
        if (!$value instanceof \DateTimeInterface) {
            throw new InvalidArgumentException('The native value must implement the "\DateTimeInterface".');
        }

        return $value->format($options[self::FORMAT_KEY] ?? \DateTimeInterface::RFC3339);
    }

    /**
     * @return BuiltinType<TypeIdentifier::STRING>
     */
    public static function getJsonValueType(): BuiltinType
    {
        return Type::string();
    }
}
