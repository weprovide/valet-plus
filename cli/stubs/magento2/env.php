<?php
return array (
  'backend' =>
  array (
    'frontName' => 'admin',
  ),
  'db' =>
  array (
    'connection' =>
    array (
      'indexer' =>
      array (
        'host' => '127.0.0.1',
        'dbname' => 'DBNAME',
        'username' => 'root',
        'password' => 'root',
        'active' => '1',
        'persistent' => null,
        'model' => 'mysql4',
        'engine' => 'innodb',
        'initStatements' => 'SET NAMES utf8;',
      ),
      'default' =>
      array (
        'host' => '127.0.0.1',
        'dbname' => 'DBNAME',
        'username' => 'root',
        'password' => 'root',
        'active' => '1',
        'model' => 'mysql4',
        'engine' => 'innodb',
        'initStatements' => 'SET NAMES utf8;',
      ),
    ),
    'table_prefix' => '',
  ),
  'crypt' =>
  array (
    'key' => 'JkeEumwvvQBCDxypLPBozvrpF2rFNhNL',
  ),
  'session' =>
  array (
    'save' => 'redis',
    'redis' =>
    array (
      'host' => '/tmp/redis.sock',
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
    ),
  ),
  'cache' =>
  array (
    'frontend' =>
    array (
      'default' =>
      array (
        'backend' => 'Cm_Cache_Backend_Redis',
        'backend_options' =>
        array (
          'server' => '/tmp/redis.sock',
          'port' => '6379',
          'database' => '2',
        ),
      ),
      'page_cache' =>
      array (
        'backend' => 'Cm_Cache_Backend_Redis',
        'backend_options' =>
        array (
          'server' => '/tmp/redis.sock',
          'port' => '6379',
          'database' => '1',
          'compress_data' => '0',
        ),
      ),
    ),
  ),
  'resource' =>
  array (
    'default_setup' =>
    array (
      'connection' => 'default',
    ),
  ),
  'x-frame-options' => 'SAMEORIGIN',
  'MAGE_MODE' => 'developer',
  'cache_types' =>
  array (
    'config' => 1,
    'layout' => 1,
    'block_html' => 1,
    'collections' => 1,
    'reflection' => 1,
    'db_ddl' => 1,
    'eav' => 1,
    'full_page' => 1,
    'config_integration' => 1,
    'config_integration_api' => 1,
    'target_rule' => 1,
    'translate' => 1,
    'config_webservice' => 1,
    'compiled_config' => 0,
    'customer_notification' => 1,
  ),
  'install' =>
  array (
    'date' => 'Wed, 19 Jul 2017 00:00:00 +0000',
  ),
  'queue' =>
  array (
    'amqp' =>
    array (
      'host' => '',
      'port' => '',
      'user' => '',
      'password' => '',
      'virtualhost' => '/',
      'ssl' => '',
    ),
  ),
  'directories' => 
  array (
    'document_root_is_pub' => true,
  ),
);
