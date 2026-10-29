<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * PHP 8.2+ compatibility: CI_Router assigns $uri dynamically.
 * @see https://github.com/bcit-ci/CodeIgniter/pull/6173
 */
#[\AllowDynamicProperties]
class MY_Router extends CI_Router
{
}
