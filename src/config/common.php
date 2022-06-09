<?php
return [
    'components' => [
        'shop' => [
            'deliveryHandlers'             => [
                \skeeks\cms\shop\pickpoint\PickpointDeliveryHandler::class
            ]
        ],
    ],
];