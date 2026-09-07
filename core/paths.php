<?php

if (!defined('NGR_ROOT')) {
    define('NGR_ROOT', dirname(__DIR__));
}

// Keep legacy relative includes and file paths working after pages were grouped.
set_include_path(NGR_ROOT . PATH_SEPARATOR . get_include_path());
chdir(NGR_ROOT);
