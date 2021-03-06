<?php

    // Tired of chasing typos and finding them after a commit.
    // Run this from cmdline in basedir and quickly see if we've
    // got any parse errors in our application files.


    error_reporting(E_ERROR | E_WARNING | E_PARSE);
    ini_set('display_errors', '1');
    ini_set('log_errors', '0');

    include 'boot.php';

    App::init();

    $cmd = ((x(App::$config, 'system')) && (x(App::$config['system'], 'php_path')) && (strlen(App::$config['system']['php_path'])) ? App::$config['system']['php_path'] : 'php') . ' -l ';


    echo "Directory: include\n";
    $files = glob('include/*.php');
foreach ($files as $file) {
    echo exec($cmd . $file) . "\n";
    include_once($file);
}

    echo "Directory: include/dba\n";
    $files = glob('include/dba/*.php');
foreach ($files as $file) {
    echo exec($cmd . $file) . "\n";
    include_once($file);
}

//  echo "Directory: include/photo\n";
//  $files = glob('include/photo/*.php');
//  foreach($files as $file) {
//      echo exec($cmd . $file) . "\n";
//      include_once($file);
//  }


    echo "Directory: Code\n";
    $files = glob('Code/*/*.php');
foreach ($files as $file) {
    if ((strpos($file, 'SiteModule') === false) || (strpos($file, 'SiteWidget') === false)) {
        echo exec($cmd . $file) . "\n";
        include_once($file);
    }
}

    echo "Directory: Code/Module (sub-modules)\n";
    $files = glob('Code/Module/*/*.php');
foreach ($files as $file) {
    echo exec($cmd . $file) . "\n";
    include_once($file);
}

    echo "Directory: addon\n";
    $dirs = glob('addon/*');

foreach ($dirs as $dir) {
    $addon = basename($dir);
    $files = glob($dir . '/' . $addon . '.php');
    foreach ($files as $file) {
        echo exec($cmd . $file) . "\n";
        include_once($file);
    }
}

if (x(App::$config, 'system') && x(App::$config['system'], 'php_path')) {
    $phpath = App::$config['system']['php_path'];
} else {
    $phpath = 'php';
}

    echo "String files\n";

    echo 'util/strings.php' . "\n";
    include_once('util/strings.php');
    echo count(App::$strings) . ' strings' . "\n";

    $files = glob('view/*/strings.php');

foreach ($files as $file) {
    echo exec($cmd . $file) . "\n";
    passthru($phpath . ' util/typohelper.php ' . $file);
}
