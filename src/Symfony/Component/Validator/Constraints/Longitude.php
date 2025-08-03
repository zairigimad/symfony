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
final class Longitude extends Constraint
{
    public const INVALID_LONGITUDE_ERROR = '2984c3a9-702d-40bb-b53e-74d81de37ea2';

    protected const ERROR_NAMES = [
        self::INVALID_LONGITUDE_ERROR => 'INVALID_LONGITUDE_ERROR',
    ];

    public function __construct(
        public string $mode = 'strict',
        public string $message = 'This value must contain valid longitude coordinates.',
        ?array $groups = null,
        mixed $payload = null
    ) {
        parent::__construct([], $groups, $payload);
    }
}
