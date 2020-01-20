<?php
namespace App\Http\Controllers;

use App\Periferico;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Lumen\Routing\Controller as BaseController;
use App\Helpers\HelpMen;


/**
 * Encargado de administrar la tabla perif_io
 * bits IO y IOM
 * @author Alan Ramirez Moratorio
 */
class PerifericoController extends BaseController 
{
    private static $consumer = null;

    private function __clone() {} //Prevent any copy of this object
    private function __wakeup() {}
    public function __construct() {}
    
    public static function setConsumer($perif_io_id){
        self::$consumer = Periferico::find($perif_io_id);
        Log::error(print_r(self::$consumer,true));
    }
    public static function getSensores($equipo_id){
        $periferico = Periferico::obtenerSensores($equipo_id);
        return $periferico;
    }
    public static function setSensores($equipo_id,$sensores,$salidas,$restabManual){
        DB::beginTransaction();
        try{
            $salidasArr = str_split($salidas);
            Log::error("las salidas:".$salidas);
            $perif = self::getSensores($equipo_id);
            if($perif){
                Log::error("periferico ID:".$perif->perif_io_id);
                self::setConsumer($perif->perif_io_id);
                self::setEntradas( $sensores);
                self::setSalidas( $salidasArr);
                self::$consumer->restablecimiento_manual=$restabManual;
                self::$consumer->save();
                
                HelpMen::report($equipo_id,"Actualizando datos de periferico");
                DB::commit();
            }else{
                Log::error("NO SE SETEO EL PERIFERICOOOO");
                Log::error(print_r($perif,true));
            }
            
        }catch (\Exception $ex) {
            DB::rollBack();
            $errorSolo  = explode("Stack trace", $ex);
            $logcadena ="ERROR SETEANDO SENSORES ".$errorSolo[0]." \r\n";
            Log::info($logcadena);
        }
    }
    public static function setEntradas($sensores){
        self::$consumer->sensor_pulsador_panico=$sensores[0];
        self::$consumer->sensor_puerta_conductor=$sensores[1];
        self::$consumer->sensor_puerta_acompaniante=$sensores[2];
        self::$consumer->sensor_desenganche=$sensores[3];
        self::$consumer->sensor_antisabotaje=$sensores[4];
        self::$consumer->sensor_compuerta=$sensores[5];
        self::$consumer->sensor_contacto=$sensores[6];
        self::$consumer->sensor_encendido=$sensores[7];
        self::$consumer->sensor_presencia_tablero=$sensores[8];
        self::$consumer->sensor_pulsador_tablero=$sensores[9];
        self::$consumer->sensor_llave_tablero=$sensores[10];
        self::$consumer->sensor_alimentacion_ppal=$sensores[11];
    }
    public static function setSalidas($salidas){
        self::$consumer->salida_corte=$salidas[0];
        self::$consumer->salida_frenos=$salidas[1];
        self::$consumer->salida_sirena=$salidas[2];
        self::$consumer->salida_auxiliar_1=$salidas[3];
        self::$consumer->salida_auxiliar_2=$salidas[4];
    }
    
}
