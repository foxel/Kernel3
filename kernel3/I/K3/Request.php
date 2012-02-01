<?php

interface I_K3_Request
{
    public function get($varName, $source = K3_Request::ALL, $default = null);
}
