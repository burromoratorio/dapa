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
    private static $sensoresIOMINI = array(0=>'inhibicion_pulsador_panico',1=>'inhibicion_puerta_conductor',2=>'inhibicion_puerta_acompaniante',3=>'inhibicion_desenganche',
            4=>'inhibicion_antisabotaje',5=>'inhibicion_compuerta',6=>'inhibicion_contacto',7=>'inhibicion_encendido',8=>'inhibicion_presencia_tablero',
            9=>'inhibicion_pulsador_tablero',10=>'inhibicion_llave_tablero',11=>'inhibicion_alimentacion_ppal');
    private static $sensoresIOM = array(0=>'sensor_pulsador_panico',1=>'sensor_puerta_conductor',2=>'sensor_puerta_acompaniante',3=>'sensor_desenganche',
            4=>'sensor_antisabotaje',5=>'sensor_compuerta',6=>'sensor_contacto',7=>'sensor_encendido',8=>'sensor_presencia_tablero',
            9=>'sensor_pulsador_tablero',10=>'sensor_llave_tablero',11=>'sensor_alimentacion_ppal');
    private static $sensoresBIO = array(0=>'sensor_puerta_conductor',1=>'sensor_compuerta',2=>'sensor_encendido',
                                      3=>'sensor_desenganche', 4=>'sensor_llave_tablero',5=>'sensor_pulsador_panico');

    private function __clone() {} //Prevent any copy of this object
    private function __wakeup() {}
    public function __construct() {}
    
    public static function setConsumer($perif_io_id){
        self::$consumer = Periferico::find($perif_io_id);
    }
    public static function getSensores($equipo_id){
        $periferico = Periferico::obtenerSensores($equipo_id);
        return $periferico;
    }
    public static function setSensores($tipo,$equipo_id,$sensores,$salidas,$restabManual){
        //DB::beginTransaction();
        try{
            $salidasArr = str_split($salidas);
            //Log::error("las salidas:".$salidas);
            $perif = self::getSensores($equipo_id);
            if($perif){
                Log::error("periferico ID:".$perif->perif_io_id);
                self::setConsumer($perif->perif_io_id);
                if($tipo=="IOM"){
                    self::setEntradas("IOM", $sensores);
                    self::setSalidas( $salidasArr);
                    self::$consumer->restablecimiento_manual=$restabManual;
                }else{
                    self::setEntradas( "BIO", $sensores);
                    self::setSalidasBIO( $salidasArr);
                }
                self::$consumer->save();
                HelpMen::report($equipo_id,"Actualizando datos de periferico");
                //DB::commit();
            }else{
                Log::error("NO SE SETEO EL PERIFERICOOOO");
                Log::error(print_r($perif,true));
            }
            
        }catch (\Exception $ex) {
            //DB::rollBack();
            $errorSolo  = explode("Stack trace", $ex);
            $logcadena ="ERROR SETEANDO SENSORES ".$errorSolo[0]." \r\n";
            Log::info($logcadena);
            HelpMen::report($equipo_id,$logcadena);
        }
    }
    public static function setEntradas($tipo, $sensores){
        foreach($sensores as $key=>$entrada){
            if($entrada=='X'){//verifico si el sensor viene con x es porque está inhibido...actualizo la info en la ddbb
                self::setSensorInhibido( $key);
                self::setEntrada($tipo,$key, $entrada);
                $sensores[$key]=1;
            }
        }
    }
    public static function setEntrada($tipo,$key,$valor){
        $entrada=($tipo=="IOM")?self::$sensoresIOM[$key]:self::$sensoresBIO[$key];
        self::$consumer->$entrada=$valor;
    }
    public static function setSalidas($salidas){
        self::$consumer->salida_corte=$salidas[0];
        self::$consumer->salida_frenos=$salidas[1];
        self::$consumer->salida_sirena=$salidas[2];
        self::$consumer->salida_auxiliar_1=$salidas[3];
        self::$consumer->salida_auxiliar_2=$salidas[4];
        //Log::error("TERMINANDO LAS ENTRADAS:::::::::::::::::");
    }
    public static function setSensorInhibido($key){
        $inihibir=self::$sensoresIOMINI[$key];
        self::$consumer->$inihibir=1;
    }
    /*BIO*/
    public static function setSalidasBIO($salidas){
        self::$consumer->salida_corte=$salidas[0];
        self::$consumer->salida_auxiliar_1=$salidas[1];
    }
    /* self::$consumer->sensor_pulsador_panico=$sensores[0];
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
*/
}
