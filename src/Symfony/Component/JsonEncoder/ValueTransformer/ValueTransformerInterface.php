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

use Symfony\Component\TypeInfo\Type;

/**
 * Transforms a native value so it's ready to be JSON encoded during encoding
 * and to other way around during decoding.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @experimental
 */
interface ValueTransformerInterface
{
    /**
     * @param array<string, mixed> $options
     */
    public function transform(mixed $value, array $options = []): mixed;

    public static function getJsonValueType(): Type;
}
