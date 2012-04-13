<?php
require_once __DIR__.'/vendor/.composer/autoload.php';
use Symfony\Component\Finder\Finder;

@unlink('gallery.phar');

$finder = new Finder;
$finder->files()
  ->ignoreVCS(true)
  ->name('*.php')
  ->name('*.twig')
  ->name('*.ini')
  ->exclude('Tests')
  ->exclude(__DIR__.'/test')
  ->notName('compile.php')
  ->in(__DIR__)
;
  
$phar = new Phar('gallery.phar', 0, 'gallery.phar');
// $phar = $phar->convertToExecutable(Phar::TAR, Phar::GZ);
$phar->setSignatureAlgorithm(Phar::SHA1);

$phar->startBuffering();

foreach($finder as $file)
{
  $path = str_replace(__DIR__.DIRECTORY_SEPARATOR, '', $file->getRealPath());
  $content = file_get_contents($file);
  $phar->addFromString($path, $content);
  echo $path.PHP_EOL;
  
}

// $phar->setMetadata(array('bootstrap' => 'index.php'));

$phar->setStub(<<<'EOF'
<?php
Phar::mapPhar('gallery.phar');
define('GALLERY_ROOT', __DIR__);
require_once 'phar://gallery.phar/vendor/.composer/autoload.php';
require_once 'phar://gallery.phar/index.php';

__HALT_COMPILER();
');
EOF
);

// save the phar archive to disk
$phar->stopBuffering();

rename('gallery.phar', 'build/gallery.phar');
copy('gallery.ini', 'build/gallery.ini');
copy('basic.html.twig', 'build/basic.html.twig');
