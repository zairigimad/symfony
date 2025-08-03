<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class Latitude extends Constraint
{
    public const INVALID_LATITUDE_ERROR = '2f01c7bf-43ec-487c-a173-bcc305d3bbd1';

    protected const ERROR_NAMES = [
        self::INVALID_LATITUDE_ERROR => 'INVALID_LATITUDE_ERROR',
    ];

    public function __construct(
        public string $mode = 'strict',
        public string $message = 'This value must contain valid latitude coordinates.',
        ?array $groups = null,
        mixed $payload = null
    ) {
        parent::__construct(null, $groups, $payload);
    }
}
