<?php
return [
    'backend' => [
        'frontName' => 'admin'
    ],
    'cache' => [
        'frontend' => [
            'default' => [
                'id_prefix' => 'da7_'
            ],
            'page_cache' => [
                'id_prefix' => 'da7_'
            ]
        ],
        'allow_parallel_generation' => false
    ],
    'http_cache_hosts' => [
        [
            'host' => '127.0.0.1',
            'port' => '6181'
        ]
    ],
    'remote_storage' => [
        'driver' => 'file'
    ],
    'queue' => [
        'consumers_wait_for_messages' => 1
    ],
    'crypt' => [
        'key' => 'CRYPT'
    ],
    'db' => [
        'table_prefix' => '',
        'connection' => [
            'default' => [
                'host' => 'localhost',
                'dbname' => 'DBNAME',
                'username' => 'root',
                'password' => 'root',
                'model' => 'mysql4',
                'engine' => 'innodb',
                'initStatements' => 'SET NAMES utf8;',
                'active' => '1',
                'driver_options' => [
                    1014 => false
                ]
            ]
        ]
    ],
    'resource' => [
        'default_setup' => [
            'connection' => 'default'
        ]
    ],
    'x-frame-options' => 'SAMEORIGIN',
    'MAGE_MODE' => 'developer',
    'session' => [
        'save' => 'REDIS',
        'redis' => [
            'host' => '127.0.0.1',
            'port' => '6379',
            'password' => '',
            'timeout' => '2.5',
            'persistent_identifier' => '',
            'database' => '0',
            'compression_threshold' => '2048',
            'compression_library' => 'gzip',
            'log_level' => '1',
            'max_concurrency' => '6',
            'break_after_frontend' => '5',
            'break_after_adminhtml' => '30',
            'first_lifetime' => '600',
            'bot_first_lifetime' => '60',
            'bot_lifetime' => '7200',
            'disable_locking' => '0',
            'min_lifetime' => '60',
            'max_lifetime' => '2592000',
            'sentinel_master' => '',
            'sentinel_servers' => '',
            'sentinel_connect_retries' => '5',
            'sentinel_verify_master' => '0'
        ]
    ],
    'lock' => [
        'provider' => 'db'
    ],
    'directories' => [
        'document_root_is_pub' => true
    ],
    'cache_types' => [
        'config' => 1,
        'layout' => 1,
        'block_html' => 1,
        'collections' => 1,
        'reflection' => 1,
        'db_ddl' => 1,
        'compiled_config' => 1,
        'eav' => 1,
        'customer_notification' => 1,
        'config_integration' => 1,
        'config_integration_api' => 1,
        'full_page' => 1,
        'config_webservice' => 1,
        'translate' => 1
    ],
    'downloadable_domains' => [
        'DOMAIN'
    ],
    'install' => [
        'date' => 'Fri, 30 Jun 2023 08:43:35 +0000'
    ]
];
