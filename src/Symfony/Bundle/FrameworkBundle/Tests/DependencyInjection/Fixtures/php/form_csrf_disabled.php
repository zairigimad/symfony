<?php

$container->loadFromExtension('framework', [
    'csrf_protection' => false,
    'form' => [
        'csrf_protection' => true,
    ],
]);
