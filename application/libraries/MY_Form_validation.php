<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * PHP 8.2+ compatibility for CI_Form_validation.
 */
#[\AllowDynamicProperties]
class MY_Form_validation extends CI_Form_validation
{
    public function __construct($rules = array())
    {
        parent::__construct($rules);
    }
}
