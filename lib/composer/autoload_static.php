<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit4ab86d084fbb853035aa21a1c65371e1
{
    public static $files = array (
        '32dcc8afd4335739640db7d200c1971d' => __DIR__ . '/..' . '/symfony/polyfill-apcu/bootstrap.php',
    );

    public static $prefixLengthsPsr4 = array (
        'S' => 
        array (
            'Symfony\\Polyfill\\Apcu\\' => 22,
            'Symfony\\Component\\Cache\\' => 24,
        ),
        'P' => 
        array (
            'Psr\\SimpleCache\\' => 16,
            'Psr\\Log\\' => 8,
            'Psr\\Cache\\' => 10,
            'Payline\\' => 8,
        ),
        'M' => 
        array (
            'Monolog\\' => 8,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Symfony\\Polyfill\\Apcu\\' => 
        array (
            0 => __DIR__ . '/..' . '/symfony/polyfill-apcu',
        ),
        'Symfony\\Component\\Cache\\' => 
        array (
            0 => __DIR__ . '/..' . '/symfony/cache',
        ),
        'Psr\\SimpleCache\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/simple-cache/src',
        ),
        'Psr\\Log\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/log/Psr/Log',
        ),
        'Psr\\Cache\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/cache/src',
        ),
        'Payline\\' => 
        array (
            0 => __DIR__ . '/..' . '/monext/payline-sdk/src/Payline',
        ),
        'Monolog\\' => 
        array (
            0 => __DIR__ . '/..' . '/monolog/monolog/src/Monolog',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit4ab86d084fbb853035aa21a1c65371e1::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit4ab86d084fbb853035aa21a1c65371e1::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}
