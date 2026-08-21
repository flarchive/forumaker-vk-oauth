<?php

use FoF\OAuth\Extend\RegisterProvider;
use forumaker\Vk\Providers\Vk;
use Flarum\Extend;

return [
    new Extend\Locales(__DIR__ . '/resources/locale'),
    new RegisterProvider(Vk::class),

    (new Extend\Frontend('forum'))
        ->css(__DIR__ . '/resources/less/forum.less'),
];
