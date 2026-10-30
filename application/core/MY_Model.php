<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * PHP 8.2+ compatibility base model.
 */
#[\AllowDynamicProperties]
class MY_Model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }
}
