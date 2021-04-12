<?php
return [
    'service_manager' => [
        'factories' => [
            \zPetr\Cache\CacheListener::class => \zPetr\Cache\CacheListenerFactory::class,
        ],
    ],

    'zpetr-api-tools-cache' => [
        //    'controllers' => [
        //        /*
        //         * You can provide a wildcard along with configured HTTP methods within
        //         * a controller configuration in order to configure all non listed HTTP
        //         * methods for this controller.
        //         */
        //        'my-controller-name' => [
        //            'get' => [
        //                'key' => [
        //                      // cache key prefixes
        //                ],
        //                'tag' => [
        //                      // cache key tags
        //                ]
        //            ],
        //            '*' => [
        //                ...
        //            ],
        //        ],
        //
        //        /*
        //         * Regular configuration.
        //         */
        //        'home::index' => [ // route name
        //            'get' => [ // Http method (wildcard '*' supported as whatever method)
        //                  'key' => [
        //                      'home',
        //                      'index'
        //                  ],
        //                  'tag' => [
        //                      'home'
        //                  ]
        //            ],
        //        ],
        //        'index::index' => [ // controller / action names
        //            'get' => [...],
        //        ],
        //        'index' => [ // controller name
        //            'get' => [...],
        //        ],
        //        '~.*::index~' => [ // regex
        //            'get' => [...],
        //        ],
        //    ],
        //
        //    /*
        //     * Whether to enable http cache.
        //     */
        //    'enable' => true,
        //
        //    /*
        //     * Never cache these HTTP status codes.
        //     * Defaults to all others than 200.
        //     */
        //    'http_codes_black_list' => [],
        //
        //    /*
        //     * Delimiter used to mark a controller name as being a regexp.
        //     * If you don't want to use regexps in your config set this
        //     * to null to avoid inutil parsing.
        //     * Regexp wins over wildcard.
        //     */
        //    'regex_delimiter' => '~',
    ],
];