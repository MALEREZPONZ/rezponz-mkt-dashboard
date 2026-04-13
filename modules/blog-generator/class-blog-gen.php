<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * RZPA_Blog_Gen
 *
 * Bootstrap-klasse for Blog Generator modulet.
 * Initialiserer DB, REST API og admin-menu.
 */
class RZPA_Blog_Gen {

    public static function init(): void {
        RZPA_Blog_Gen_API::init();
        RZPA_Blog_Gen_Admin::init();
    }
}
