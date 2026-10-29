<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * PHP 8.2+ compatibility: controllers receive core objects as dynamic properties.
 * Application controllers should extend MY_Controller.
 * @see https://github.com/bcit-ci/CodeIgniter/pull/6173
 */
#[\AllowDynamicProperties]
class MY_Controller extends CI_Controller
{
}
