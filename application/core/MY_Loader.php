<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * PHP 8.2+ compatibility: CI_Loader assigns loaded components dynamically.
 * @see https://github.com/bcit-ci/CodeIgniter/pull/6173
 */
#[\AllowDynamicProperties]
class MY_Loader extends CI_Loader
{
}
