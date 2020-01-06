<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;

use Illuminate\Http\Request;
use App\Helpers\HelpMen;
use App\Helpers\RedisHelp;

class Controller extends BaseController
{
    public function index(Request $request) {
        HelpMen::solicitarMoviles();
        $client = new \Predis\Client();
        RedisHelp::lookForMovil($client,'351687030455512');
    }

    public function limpiar(Request $request){
        $method   = $request->method();
        $rta="nada";
        if ($request->isMethod('post')) {
            $jsonReq      = $request->json()->all();
            if(isset($jsonReq["cadena"]) && $jsonReq["cadena"]=="Mig"){
                $rta=RedisHelp::limpiarBase();
            }
        }
        return $rta;
    }
}
