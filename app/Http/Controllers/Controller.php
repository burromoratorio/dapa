<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;

use Illuminate\Http\Request;
use App\Helpers\HelpMen;

class Controller extends BaseController
{
    public function index(Request $request) {
        HelpMen::solicitarMoviles();
    }
}
