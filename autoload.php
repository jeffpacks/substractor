<?php
spl_autoload_register(function ($class) {

  $prefix = 'jeffpacks\\substractor\\';
  $baseDir = __DIR__ . '/src/';

  $length = strlen($prefix);
  if (strncmp($prefix, $class, $length) !== 0) {
    return;
  }

  $file = $baseDir . str_replace('\\', '/', substr($class, $length)) . '.php';

  if (!file_exists($file)) {
    die("Unable to load file [$file]\n");
  }

  require $file;

});