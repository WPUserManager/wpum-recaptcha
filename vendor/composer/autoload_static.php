<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit46ae109afce5ed96bdd890ef9e535c0b
{
    public static $prefixLengthsPsr4 = array (
        'R' => 
        array (
            'ReCaptcha\\' => 10,
        ),
        'C' => 
        array (
            'Composer\\Installers\\' => 20,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'ReCaptcha\\' => 
        array (
            0 => __DIR__ . '/..' . '/google/recaptcha/src/ReCaptcha',
        ),
        'Composer\\Installers\\' => 
        array (
            0 => __DIR__ . '/..' . '/composer/installers/src/Composer/Installers',
        ),
    );

    public static $classMap = array (
        'WPUM_Extension_Activation' => __DIR__ . '/..' . '/wp-user-manager/wpum-extension-activation/wpum-extension-activation.php',
        'WP_Requirements_Check' => __DIR__ . '/..' . '/wearerequired/wp-requirements-check/WP_Requirements_Check.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit46ae109afce5ed96bdd890ef9e535c0b::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit46ae109afce5ed96bdd890ef9e535c0b::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit46ae109afce5ed96bdd890ef9e535c0b::$classMap;

        }, null, ClassLoader::class);
    }
}
