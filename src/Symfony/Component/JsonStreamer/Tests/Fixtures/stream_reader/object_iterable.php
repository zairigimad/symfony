<?php

/**
 * @return iterable<int|string,Symfony\Component\JsonStreamer\Tests\Fixtures\Model\ClassicDummy>
 */
return static function (string|\Stringable $string, \Psr\Container\ContainerInterface $valueTransformers, \Symfony\Component\JsonStreamer\Read\Instantiator $instantiator, array $options): mixed {
    $providers['iterable<int|string,Symfony\Component\JsonStreamer\Tests\Fixtures\Model\ClassicDummy>'] = static function ($data) use ($options, $valueTransformers, $instantiator, &$providers) {
        $iterable = static function ($data) use ($options, $valueTransformers, $instantiator, &$providers) {
            foreach ($data as $k => $v) {
                yield $k => $providers['Symfony\Component\JsonStreamer\Tests\Fixtures\Model\ClassicDummy']($v);
            }
        };
        return $iterable($data);
    };
    $providers['Symfony\Component\JsonStreamer\Tests\Fixtures\Model\ClassicDummy'] = static function ($data) use ($options, $valueTransformers, $instantiator, &$providers) {
        return $instantiator->instantiate(\Symfony\Component\JsonStreamer\Tests\Fixtures\Model\ClassicDummy::class, \array_filter(['id' => $data['id'] ?? '_symfony_missing_value', 'name' => $data['name'] ?? '_symfony_missing_value'], static function ($v) {
            return '_symfony_missing_value' !== $v;
        }));
    };
    return $providers['iterable<int|string,Symfony\Component\JsonStreamer\Tests\Fixtures\Model\ClassicDummy>'](\Symfony\Component\JsonStreamer\Read\Decoder::decodeString((string) $string));
};
