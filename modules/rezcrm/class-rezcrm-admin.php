<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RZPZ_CRM_Admin {

    public static function init(): void {
        // Menu registreres af class-rekruttering.php (under Rekruttering)
    }

    public static function render_page(): void {
        require_once __DIR__ . '/views/admin-rezcrm.php';
    }
}
