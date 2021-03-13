<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

comcal_Database::delete_tables();
