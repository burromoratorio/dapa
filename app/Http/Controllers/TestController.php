<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
namespace App\Http\Controllers;
use App\ColaMensajes;
use App\InstalacionSiac;
use App\Helpers\HelpMen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Lumen\Routing\Controller as BaseController;
use Exception;
use App\Helpers\CommandHelp;
use App\Helpers\RedisHelp;
/**
 * Description of TestController
 *
 * @author siacadmin
 */
class TestController extends BaseController
{
    public $equipo=null;
    public $comandosPendientes=0;
    public function index(Request $request) {
      //return "ok";
    }      //
    public function create($equipo_id,$pendientes){
        $this->equipo=$equipo_id;
        $this->comandosPendientes=$pendientes;
    }
    public static function tratarTest($mensajePendiente){
        $mensaje        = $mensajePendiente;
        $commandHelp    = new CommandHelp($this->equipo);
       //si está en test ejecuto uno a uno por prioridad
        $logcadena ="\r\n Ejecutando Test Equipo::".$this->equipo." Comandos en test:".$esEnTest[0]->comandos." \r\n";
        HelpMen::report($this->equipo,$logcadena);
        if($this->comandosPendientes==15){//=>no se envió ningundo arranco por poner KA=15s
            $comandoAEnviar15 = $commandHelp->kaReportFrecuency($this->equipo,15);
            $mensaje          = ($comandoAEnviar15)?$comandoAEnviar15:$mensaje;
        }elseif($this->comandosPendientes==1){//si es el ultimo pendiente paso el equipo a KA=45s
            $comandoAEnviar45 = $commandHelp->kaReportFrecuency($this->equipo,45);
            $mensaje          = ($comandoAEnviar45)?$comandoAEnviar45:$mensaje;
        }else{
            $mensaje= $mensajePendiente;
        }
        return $mensaje;
    }
    public static function testStartup(Request $request, $imei){
        echo "zarazaaaaa";
        Log::info("entrando acaaa. imei:".$imei);
        if ($request->isMethod('post')) {
            $jsonReq      = $request->json()->all();
            if(isset($jsonReq["tr"])){
                RedisHelp::setTestMovil($imei, $jsonReq["tr"]);
                Log::info($jsonReq["tr"]);
                $movil=RedisHelp::lookForMovil($imei);
                Log::info("se cargó esto en el redis:::".$movil->test);
                Log::info("Ultimo comando enviado:::".$movil->lastCommand);
            }
        }
    }
}
