<?php

// bootstrap.php

use Clubdeuce\TheatreCMS\Repositories\PersonRepository;
use Clubdeuce\TheatreCMS\Repositories\SeasonRepository;
use Clubdeuce\TheatreCMS\Repositories\UserRepository;
use Clubdeuce\TheatreCMS\Repositories\WorkRepository;
use DI\Container;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;


if( !defined('APP_ROOT') )
    define ('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/vendor/autoload.php';

$container = new Container(require __DIR__ . '/settings.php');

$container->set(EntityManager::class, static function (Container $c): EntityManager {
    /** @var array $settings */
    $settings = $c->get('settings');

    // Use the ArrayAdapter or the FilesystemAdapter depending on the value of the 'dev_mode' setting
    // You can substitute the FilesystemAdapter for any other cache you prefer from the symfony/cache library
    $cache = $settings['doctrine']['dev_mode'] ?
        new ArrayAdapter() :
        new FilesystemAdapter(directory: $settings['doctrine']['cache_dir']);

    $config = ORMSetup::createAttributeMetadataConfiguration(
        $settings['doctrine']['metadata_dirs'],
        $settings['doctrine']['dev_mode'],
        null,
        $cache
    );

    $connection = DriverManager::getConnection($settings['doctrine']['connection']);

    return new EntityManager($connection, $config);
});

$repositories = [
    PersonRepository::class,
    SeasonRepository::class,
    UserRepository::class,
    WorkRepository::class,
];

foreach($repositories as $repository) {
    $container->set($repository, static function (Container $c) use ($repository) {
        return new $repository($c->get(EntityManager::class));
    });
}

return $container;
