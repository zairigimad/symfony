<?php

return static function (mixed $stream, \Psr\Container\ContainerInterface $valueTransformers, \Symfony\Component\JsonEncoder\Decode\LazyInstantiator $instantiator, array $options): mixed {
    return \Symfony\Component\JsonEncoder\Decode\NativeDecoder::decodeStream($stream, 0, null);
};
