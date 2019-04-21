<?php

namespace app\push\controller;

use think\Controller;

class Push extends Controller
{
    function index()
    {
        return $this->fetch();
    }
}