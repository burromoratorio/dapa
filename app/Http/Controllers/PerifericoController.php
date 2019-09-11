<?php
namespace App\Http\Controllers;

use App\Periferico;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Lumen\Routing\Controller as BaseController;

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
    public static function setSensores($equipo_id,$sensores,$salidas,$modo,$restabManual){
        DB::beginTransaction();
        try{
            $salidasArr = str_split($salidas);
            Log::info(print_r($salidas,true));
            $perif = self::getSensores($equipo_id);
            $consumer = Periferico::find($perif->perif_io_id);
            $consumer->sensor_pulsador_panico=$sensores[0];
            $consumer->sensor_puerta_conductor=$sensores[1];
            $consumer->sensor_puerta_acompaniante=$sensores[2];
            $consumer->sensor_desenganche=$sensores[3];
            $consumer->sensor_antisabotaje=$sensores[4];
            $consumer->sensor_compuerta=$sensores[5];
            $consumer->sensor_encendido=$sensores[7];
            $consumer->sensor_presencia_tablero=$sensores[8];
            $consumer->sensor_lave_tablero=$sensores[10];
            

            $consumer->salida_corte=$salidasArr[0];
            $consumer->salida_frenos=$salidasArr[1];
            $consumer->salida_sirena=$salidasArr[2];
            $consumer->salida_auxiliar_1=$salidasArr[3];
            $consumer->salida_auxiliar_2=$salidasArr[4];  
            $consumer->restablecimiento_manual=$restabManual;
            $consumer->save();
            Log::info("Actualizando PerifericoController:::iddd:".$perif->perif_io_id);
            DB::commit();
        }catch (\Exception $ex) {
            DB::rollBack();
            $errorSolo  = explode("Stack trace", $ex);
            $logcadena ="Error al procesar update de comandos ".$errorSolo[0]." \r\n";
            Log::info($logcadena);
        }
    }
}
