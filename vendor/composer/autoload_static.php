<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit22b8145dca94aa67e0134b27670db18a
{
    public static $prefixLengthsPsr4 = array (
        'P' => 
        array (
            'Picqer\\Barcode\\' => 15,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Picqer\\Barcode\\' => 
        array (
            0 => __DIR__ . '/..' . '/picqer/php-barcode-generator/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit22b8145dca94aa67e0134b27670db18a::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit22b8145dca94aa67e0134b27670db18a::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit22b8145dca94aa67e0134b27670db18a::$classMap;

        }, null, ClassLoader::class);
    }
}
