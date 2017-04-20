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
    private function __clone() {} //Prevent any copy of this object
    private function __wakeup() {}
    private function __construct() { 
           
    } //Prevent any oustide instantiation of this class
    
    public static function getInstance()
    {
        if( !is_object(self::$_instance) )  //or if( is_null(self::$_instance) ) or if( self::$_instance == null )
            self::$_instance = new PuertoController();
        return self::$_instance;
    }
    ///Now we are all done, we can now proceed to have any other method logic we want
    //a simple method to echo something
    public function GreetMe()
    {
        Log::error('<br />Hello, this method is called by using a singleton object..');
    }
    public function getImei($paquete=""){
        $this->cadena = $paquete;
        $imei="";
        if($this->cadena!=""){
            $arrCadena = explode($this->cadena,";");
            $imei = $arrCadena[0];
        }
        return $imei;
    }

}
