<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit806991d3945803a6b49c7e0bc51608be
{
    public static $prefixLengthsPsr4 = array (
        'S' => 
        array (
            'Samwilson\\PhpFlickr\\' => 20,
        ),
        'P' => 
        array (
            'Psr\\Cache\\' => 10,
        ),
        'O' => 
        array (
            'OAuth\\' => 6,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Samwilson\\PhpFlickr\\' => 
        array (
            0 => __DIR__ . '/..' . '/samwilson/phpflickr/src',
        ),
        'Psr\\Cache\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/cache/src',
        ),
        'OAuth\\' => 
        array (
            0 => __DIR__ . '/..' . '/carlos-mg89/oauth/src/OAuth',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit806991d3945803a6b49c7e0bc51608be::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit806991d3945803a6b49c7e0bc51608be::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit806991d3945803a6b49c7e0bc51608be::$classMap;

        }, null, ClassLoader::class);
    }
}
