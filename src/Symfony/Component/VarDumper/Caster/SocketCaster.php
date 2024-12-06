<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Caster;

use Symfony\Component\VarDumper\Cloner\Stub;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class SocketCaster
{
    public static function castSocket(\Socket $h, array $a, Stub $stub, bool $isNested): array
    {
        if (\PHP_VERSION_ID >= 80300 && socket_atmark($h)) {
            $a[Caster::PREFIX_VIRTUAL.'atmark'] = true;
        }

        if (!$lastError = socket_last_error($h)) {
            return $a;
        }

        static $errors;

        if (!$errors) {
            $errors = get_defined_constants(true)['sockets'] ?? [];
            $errors = array_flip(array_filter($errors, static fn ($k) => str_starts_with($k, 'SOCKET_E'), \ARRAY_FILTER_USE_KEY));
        }

        $a[Caster::PREFIX_VIRTUAL.'last_error'] = new ConstStub($errors[$lastError], socket_strerror($lastError));

        return $a;
    }
}
