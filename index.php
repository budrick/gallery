<?php

// Single-script gallery

/* TODO:
 * Root folder needs to be configurable, not just "images"
 * Need to package this as a PHAR if possible to make it just gallery.phar, gallery.ini, and .htaccess
 * Add way to ascend to parent directory if possible
 */

require_once __DIR__.'/vendor/.composer/autoload.php';
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Finder\Finder;

$app = new Silex\Application();
$app['debug'] = true;
$app['config'] = array_merge(array(
  'thumb.width'  => 120,
  'thumb.height' => 120,
  'adapter'      => 'GD',
  'template'     => 'template.html.twig',
  'cache'        => 'cache',
  ),parse_ini_file(__DIR__.'/gallery.ini')
);

// Register services
if (strtolower($app['config']['adapter']) == 'gd')
{
  $app['imagine'] = new Imagine\GD\Imagine;
} elseif (strtolower($app['config']['adapter']) == 'imagick')
{
  $app['imagine'] = new Imagine\ImageMagick\Imagine;
} else {
  throw new Exception("Unsupported image adapter in configuration.");
}

$app->register(new Silex\Provider\UrlGeneratorServiceProvider());
$app->register(new Silex\Provider\HttpCacheServiceProvider(), array(
    'http_cache.cache_dir' => __DIR__.'/'.$app['config']['cache'],
));
$app->register(new Silex\Provider\SymfonyBridgesServiceProvider(), array());
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path'       => __DIR__,
));

// Browser controller
$app->get('/images/{path}', function ($path) use ($app) {
  
  // Prepare and validate path - must exist and be a subdirectory of images
  if ($path)
  {
    $path = realpath(__DIR__.'/images/'.$path);
    if (strpos($path, __DIR__.'/images/') !== 0)
    {
      $app->abort(404, "Not found");
    }
  
  } else {
    $path = __DIR__.'/images';
  }
  
  $relative_path = substr($path, strlen(__DIR__) + 1);
  
  
  $finder = new Finder;
  $finder->files()->in($path)->depth(0)->name('/\.png|\.gif|\.jpg/');
  
  $files = array();
  foreach($finder as $file)
  {
    $files[] = array(
      'thumbnail'        => $app['url_generator']->generate('thumbnail', array('path' => $relative_path.'/'.$file->getRelativePathname().'.jpg')), 
      'thumbnail_width'  => $app['config']['thumb.width'],
      'thumbnail_height' => $app['config']['thumb.height'],
      'url'              => $file->getRelativePathname(),
    );
  }
  
  // Folder list
  $folders = array();
  $finder = new Finder;
  $finder->directories()->in($path)->depth(0);
  foreach($finder as $folder)
  {
    $folders[] = $folder->getRelativePathname();
  }
  
  // Render response with cache headers
  $response = new Response;
  $response->setPublic();
  $response->setSharedMaxAge(10);
  $response->setContent($app['twig']->render($app['config']['template'], array(
    'images'   => $files,
    'folders' => $folders,
    'path'    => $relative_path,
  )));  
  return $response;
  
})
  ->value('path', '')
  ->assert('path', '.*')
;


// Thumbnail generator
$app->get('/thumbnails/{path}', function ($path) use ($app) {
  
  // Strip the final .jpg from the end
  $path = substr($path, 0, -4);
  
  // File must exist, and its resolved path must be beneath the images folder
  $full_path = realpath(__DIR__.'/'.$path);
  
  if (!$full_path)
  {
    $app->abort(404, "File not found.");
  }
  
  if (strpos($full_path, __DIR__.'/images/') !== 0)
  {
    $app->abort(403, "Access denied");
  }
  
  // Generate thumbnail
  try {
    $image = $app['imagine']->open($full_path)
      ->thumbnail(new Imagine\Image\Box($app['config']['thumb.width'],$app['config']['thumb.height']), Imagine\Image\ImageInterface::THUMBNAIL_OUTBOUND);
  } 
  catch (Imagine\Exception\Exception $e)
  {
    // TODO: log and handle exception nicely
    throw $e; 
  }

  // Generate response with cache control headers
  $response = new Response;
  $response->setContent($image);
  $response->headers->set('Content-Type', 'image/jpeg');
  $response->setPublic();
  $response->setSharedMaxAge(2592000);
  return $response;
})
  ->assert('path', '.*')
  ->bind('thumbnail');
;

$app['http_cache']->run();
// $app->run();
