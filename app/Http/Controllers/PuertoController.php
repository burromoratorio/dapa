<?php
namespace App\Http\Controllers;
use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Http\Request;
Use Log;
use stdClass;
use Storage;
use DB;
use App\Http\Controllers\EquipoController;
use App\Http\Controllers\MovilController;

class PuertoController extends BaseController
{

    private static $_instance = null;
    private $cadena;
    static $moviles_activos = null;
    private function __clone() {} //Prevent any copy of this object
    private function __wakeup() {}
    private function __construct() { 
            
    } 
    private function setCadena($paquete){
        PuertoController->cadena=$paquete;
    }
    public static function setMovilesActivos($moviles){
        $this->moviles_activos = $moviles;
    }
    public function GreetMe(){
        Log::error('<br />Hello, this method is called by using a singleton object..');
    }
    public static function getImei($paquete=""){
        $this->setCadena($paquete);
        $imei="";
        if(PuertoController->cadena!=""){
            $arrCadena = explode(PuertoController->cadena,";");
            $imei = $arrCadena[0];
        }
        return $imei;
    }

}
