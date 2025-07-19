<?php

use Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\FrameworkExtensionTestCase;

$container->loadFromExtension('framework', [
    'workflows' => [
        'my_workflow' => [
            'type' => 'state_machine',
            'supports' => [
                FrameworkExtensionTestCase::class,
            ],
            'places' => [
                'first',
                'middle',
                'last',
            ],
            'transitions' => [
                'go' => [
                    'from' => [
                        'first',
                    ],
                    'to' => [
                        'middle',
                        'last',
                    ],
                ],
            ],
        ],
    ],
]);
