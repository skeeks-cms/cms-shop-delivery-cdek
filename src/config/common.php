<?php
return [
    'components' => [
        'shop' => [
            'deliveryHandlers'             => [
                'cdek' => [
                    'class' => \skeeks\cms\shop\cdek\CdekDeliveryHandler::class
                ]
            ]
        ],
    ],
];