<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

Comcal_Database::delete_tables();
