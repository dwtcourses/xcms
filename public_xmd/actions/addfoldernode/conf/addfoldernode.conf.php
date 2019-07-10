<?php

/*
Defines in this file
- THEMES_FOLDER: Where the XSparrow themes are into.
- DEFAULT_PROJECT: Name for default project folder. It will must exists for
 every version project
- PROJECT_CONFIG_FILENAME: File with info about the project to build
*/

use Ximdex\Runtime\App;

// themes folder
if (! defined('THEMES_FOLDER')) {
    define('THEMES_FOLDER', App::getValue('UrlFrontController') . '/actions/addfoldernode/themes');
}
if (! defined('SCHEMES_FOLDER')) {
    define('SCHEMES_FOLDER', '/schemes');
}
if (! defined('TEMPLATES_FOLDER')) {
    define('TEMPLATES_FOLDER', '/templates');
}

// Default project
if (! defined('DEFAULT_PROJECT')) {
    define('DEFAULT_PROJECT', 'default');
}

// Project config filename
if (! defined('PROJECT_CONFIG_FILENAME')) {
    define('PROJECT_CONFIG_FILENAME', 'build.xml');
}
