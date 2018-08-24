-- Update all dev settings (css merge, js merge, asset minification, static signing) to false
UPDATE `core_config_data`
SET `value` = 0
WHERE (`path` REGEXP '^dev.*')
AND `value` = 1;

-- Update sphinx search engine to mysql
UPDATE `core_config_data`
SET `value` = 'mysql2'
WHERE `value` = 'sphinx';

-- Update base_urls from www to non www
UPDATE `core_config_data`
SET `value` = replace(`value`, 'https://www.', 'https://')
WHERE (`path` REGEXP '^web/.*/base_url$');

-- Update base_urls file extensions to .test
UPDATE `core_config_data`
SET `value` = replace(replace(replace(`value`, '.be', '.test'), '.de', '.test'), '.nl', '.test')
WHERE (`path` REGEXP '^web/.*/base_url$');