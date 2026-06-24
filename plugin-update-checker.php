<?php
/**
 * Plugin Update Checker Library 5.7
 * http://w-shadow.com/
 *
 * Copyright 2026 Janis Elsts
 * Released under the MIT license. See license.txt for details.
 */

require dirname(__FILE__) . '/load-v5p7.php';
require __DIR__ . '/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$updateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/michaelmuindek1/woocommerce-mpesa-gateway/',
    __FILE__,
    'woocommerce-mpesa-gateway'
);

$updateChecker->setBranch('main');