<?php

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Finder\Finder;

$app = new Silex\Application();

$default_config = array(
  'thumb.width'   => 120,
  'thumb.height'  => 120,
  'adapter'       => 'GD',
  'template.path' => __DIR__.'/../',
  'template'      => 'template.html.twig',
  'cache'         => sys_get_temp_dir(),
  'debug'         => false,
  'path'          => __DIR__.'/../images',
);

$app['config'] = $default_config;

// Load config file if present
if (defined('GALLERY_ROOT') && is_file(GALLERY_ROOT.'/gallery.ini')) {
  $config = parse_ini_file(GALLERY_ROOT.'/gallery.ini');
  $app['config'] = array_merge($app['config'], $config);
  
} 
elseif (is_file(__DIR__.'/../gallery.ini')) 
{
  $config = parse_ini_file(__DIR__.'/../gallery.ini');
  $app['config'] = array_merge($app['config'], $config);
}

$app['debug'] = $app['config']['debug'] ? true : false;



// Image manipulation
if (strtolower($app['config']['adapter']) == 'gd')
{
  $app['imagine'] = new Imagine\Gd\Imagine;
} 
elseif (strtolower($app['config']['adapter']) == 'imagick')
{
  $app['imagine'] = new Imagine\Imagick\Imagine;
} 
else 
{
  throw new Exception("Unsupported image adapter in configuration.");
}


// URL Generator
$app->register(new Silex\Provider\UrlGeneratorServiceProvider());


// Output cache
$app->register(new Silex\Provider\HttpCacheServiceProvider(), array(
    'http_cache.cache_dir' => $app['config']['cache'],
));



// Templating
$app->register(new Silex\Provider\SymfonyBridgesServiceProvider(), array());
$template_path = array($app['config']['template.path'], __DIR__);
if (defined('GALLERY_ROOT'))
{
  array_unshift($template_path, GALLERY_ROOT);
}

$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path'  => $template_path,
    'twig.cache' => $app['config']['cache'],
));




// Browser controller
$app->get('/images/{path}', function ($path) use ($app) {
  
  // Prepare and validate path - must exist and be a subdirectory of images
  if ($path)
  {
    $path = realpath($app['config']['path'].DIRECTORY_SEPARATOR.$path);
    if (strpos($path, $app['config']['path'].DIRECTORY_SEPARATOR) !== 0)
    {
      $app->abort(404, "Not found");
    }
  
  } else {
    $path = $app['config']['path'];
  }
  
  $relative_path = substr($path, strlen($app['config']['path'].DIRECTORY_SEPARATOR));
  
  // Get images in the given folder
  $finder = new Finder;
  $finder->files()
    ->in($path)
    ->depth(0)
    ->name('/\.png|\.gif|\.jpg/')
  ;
  
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
  $base_path = realpath($app['config']['path'].DIRECTORY_SEPARATOR);
  $full_path = realpath($app['config']['path'].DIRECTORY_SEPARATOR.$path);
  if (!$full_path)
  {
    $app->abort(404, "File not found.");
  }
  
  if (strpos($full_path, $base_path) !== 0)
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

return $app;