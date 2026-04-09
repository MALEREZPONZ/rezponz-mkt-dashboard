<?php
/**
 * Autoloader for vendored dependencies (generated without Composer CLI).
 * Packages: dompdf/dompdf, phenx/php-font-lib, phenx/php-svg-lib, sabberworm/php-css-parser, masterminds/html5
 */

if ( defined( 'RZPA_VENDOR_LOADED' ) ) return;
define( 'RZPA_VENDOR_LOADED', true );

$vendorDir = __DIR__;

// ── PSR-4 namespaces ──────────────────────────────────────────────────────────
$psr4 = [
    'Dompdf\\'          => $vendorDir . '/dompdf/dompdf/src/',
    'FontLib\\'         => $vendorDir . '/phenx/php-font-lib/src/FontLib/',
    'Svg\\'             => $vendorDir . '/phenx/php-svg-lib/src/Svg/',
    'Sabberworm\\CSS\\' => $vendorDir . '/sabberworm/php-css-parser/src/',
    'Masterminds\\'     => $vendorDir . '/masterminds/html5/src/',
];

spl_autoload_register( function ( $class ) use ( $psr4 ) {
    foreach ( $psr4 as $prefix => $dir ) {
        $len = strlen( $prefix );
        if ( strncmp( $prefix, $class, $len ) !== 0 ) {
            continue;
        }
        $relative = substr( $class, $len );
        $file     = $dir . str_replace( '\\', DIRECTORY_SEPARATOR, $relative ) . '.php';
        if ( file_exists( $file ) ) {
            require $file;
            return true;
        }
    }
    return false;
}, true, false );

// ── Classmap (dompdf lib/Cpdf.php) ──────────────────────────────────────────
$classmap = [
    'Dompdf\\Cpdf'   => $vendorDir . '/dompdf/dompdf/lib/Cpdf.php',
    'Masterminds\\HTML5' => $vendorDir . '/masterminds/html5/src/HTML5.php',
];

spl_autoload_register( function ( $class ) use ( $classmap ) {
    if ( isset( $classmap[ $class ] ) && file_exists( $classmap[ $class ] ) ) {
        require $classmap[ $class ];
        return true;
    }
    return false;
}, true, false );
