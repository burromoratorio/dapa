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
    private function __clone() {} //Prevent any copy of this object
    private function __wakeup() {}
    public function __construct() {}
    public static function getSensores($equipo_id){
        $periferico = Periferico::obtenerSensores($equipo_id);
        return $periferico;
    }
    public static function setSensores($equipo_id,$sensores,$salidas,$restabManual){
        DB::beginTransaction();
        try{
            $salidasArr = str_split($salidas);
            HelpMen::report($equipo_id,"las salidas:".$salidas);
            $perif = self::getSensores($equipo_id);
            $consumer = Periferico::find($perif->perif_io_id);
            $consumer = self::setEntradas($consumer, $sensores);
            $consumer = self::setSalidas($consumer, $salidasArr);
            $consumer->restablecimiento_manual=$restabManual;
            $consumer->save();
            Log::error("SET SENSORESandOOOOOOOOOOOOOOOOOOOOOOO::::::::::::::::::::::::::::::");
            HelpMen::report($equipo_id,"Actualizando datos de periferico");
            DB::commit();
        }catch (\Exception $ex) {
            DB::rollBack();
            $errorSolo  = explode("Stack trace", $ex);
            $logcadena ="ERROR SETEANDO SENSORES ".$errorSolo[0]." \r\n";
            Log::info($logcadena);
        }
    }
    public static function setEntradas($consumer,$sensores){
        $consumer->sensor_pulsador_panico=$sensores[0];
        $consumer->sensor_puerta_conductor=$sensores[1];
        $consumer->sensor_puerta_acompaniante=$sensores[2];
        $consumer->sensor_desenganche=$sensores[3];
        $consumer->sensor_antisabotaje=$sensores[4];
        $consumer->sensor_compuerta=$sensores[5];
        $consumer->sensor_contacto=$sensores[6];
        $consumer->sensor_encendido=$sensores[7];
        $consumer->sensor_presencia_tablero=$sensores[8];
        $consumer->sensor_pulsador_tablero=$sensores[9];
        $consumer->sensor_llave_tablero=$sensores[10];
        $consumer->sensor_alimentacion_ppal=$sensores[11];
        return $consumer;
    }
    public static function setSalidas($consumer,$salidas){
        $consumer->salida_corte=$salidas[0];
        $consumer->salida_frenos=$salidas[1];
        $consumer->salida_sirena=$salidas[2];
        $consumer->salida_auxiliar_1=$salidas[3];
        $consumer->salida_auxiliar_2=$salidas[4];
    return $consumer;
    }
    
}
