<?php

// Single-script gallery

/* TODO:
 * Root folder needs to be configurable, not just "images"
 * Need to package this as a PHAR if possible to make it just gallery.phar, gallery.ini, and .htaccess
 * Add way to ascend to parent directory if possible
 */

require_once __DIR__.'/vendor/.composer/autoload.php';

$app = require __DIR__.'/src/app.php';

if (is_file(__DIR__.'/gallery.ini')) {
  $config = parse_ini_file(__DIR__.'/gallery.ini');
  $app['config'] = array_merge($app['config'], $config);
  // $app['debug'] = true;
}

$app['http_cache']->run();
// $app->run();
