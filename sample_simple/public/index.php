<?php
/**
 * Application web "entry-point".
 *
 * This file could be as simple as:
 *		require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'app.php');
 *
 * but then each installation would have to be modified, even as it moves.
 * Instead, this file looks for a definitons file which is not tracked and
 * is installation dependent.
 *
 * @author       name <email@cachecrew.com>
 */

// Default, if prior test fails assume parent dir contains app.php (as with symlink)
require_once(dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'app.php');
