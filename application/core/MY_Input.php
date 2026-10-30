<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * PHP 8.2+ compatibility for CI_Input.
 * Prevents deprecation notice when REQUEST_METHOD is null (e.g. in CLI).
 */
#[\AllowDynamicProperties]
class MY_Input extends CI_Input
{
    public function method($upper = FALSE)
    {
        $method = (string)$this->server('REQUEST_METHOD');
        if ($method === '') {
            $method = 'cli';
        }

        return ($upper) ? strtoupper($method) : strtolower($method);
    }
}

