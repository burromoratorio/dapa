<?php

namespace App\Helpers;


use Illuminate\Support\Facades\DB;
use Log;
use App\ColaMensajes;
use Exception;

/**
 * Description of CommandHelp
 *
 * @author Alan Moratorio
 */
class CommandHelp {
    public $equipo=null;
    const ESTADO_PENDIENTE = 1;
    const ESTADO_EN_PROCESO = 2;
    const ESTADO_OK = 3;
    const POSICION_ACTUAL=17;
    const CONFIGURAR_FRECUENCIAS=20;
    const CAMBIO_MODO_PRESENCIA_GPRS=22;
    const RESET_GPRMC=100;
    static $comandoDefinitions  = array("+GETGP"=>17,"+FR"=>20,"+ALV"=>20,"+RES"=>100,"+OUTS"=>22,"+VER"=>00,
          "+PER=IOM,HAS"=>134,"+PER=IOM,INI"=>106,"+PER=IOM,CMD_NORMAL"=>22,"+PER=IOM,CMD_CORTE"=>22,
          "+PER=IOM,CMD_BLOQINH"=>22,"+PER=IOM,CMD_ALARMAS"=>22,"+PER=IOM,CMD_RESET"=>22,"+PER=IOM,ERROR"=>22,
          "+PER=IOM,CFG_CORTE"=>112,"+PER=IOM,RES"=>115);
    static $comandoGenerico     = array("+GEN"=>666); 
    public function __construct($equipo_id) { 
        $this->equipo=$equipo_id;
    } 
    public function isMovilInTest(){
        $movilTest = false;
        if(DB::connection()->getDatabaseName()=='moviles'){
          config()->set('database.default', 'siac');
          $movilTest = DB::table('TEST_COMANDO')
                           ->select(DB::raw('count(*) as comandos'))
                           ->where('fin', '=', 0)->where('modem_id', '=',$this->equipo)
                           //->groupBy('modem_id')
                           ->get();
        }
        config()->set('database.default', 'moviles');
        //Log::error(print_r($movilTest[0],true));
        return $movilTest;
    }
    public function testInRedis($imei){
        
    }
    public function kaReportFrecuency($equipo_id,$aux){
        $kaNormal    = null;
        $kaNormal    = ColaMensajes::where('modem_id', '=',$equipo_id)
                                    ->where('rsp_id','<>',3)->where('rsp_id','<>',5)->where('comando','=','+KA')
                                    ->where('auxiliar','=',$aux)
                                    ->orderBy('prioridad','DESC')
                                    ->get()->first(); 
        return $kaNormal;
    }
    public function intentarComando($mensaje,$equipo_id){
        DB::beginTransaction();
        try {
            if($mensaje){
              $mensaje->rsp_id        = 2;
              $mensaje->tipo_posicion = 69;
              //$mensaje->fecha_final   = date("Y-m-d H:i:s");
              $mensaje->intentos      += 1;
              $mensaje->save();
            }
            $tr_id  = ($mensaje)?$mensaje->tr_id:1;
            //3-los comandos en rsp_id=2 e intentos <3 ->los seteo en pendiente rsp_id=1 y les sumo 1 al intentos, excepto al obtenido para rta
            $mensajeUP  = ColaMensajes::where('modem_id', '=',$equipo_id)
                                      ->where('rsp_id','=',2)->where('intentos','<',5)->where('cmd_id','<>',22)
                                      ->where('tr_id','<>',$tr_id)
                                      ->increment('intentos', 1, ['rsp_id'=>1]);
            DB::commit();
        }catch (\Exception $ex) {
          DB::rollBack();
          $errorSolo  = explode("Stack trace", $ex);
          $logcadena ="\r\n Error al procesar el KA ".$errorSolo[0]." \r\n";
          HelpMen::report($equipo_id,$logcadena);
        }
        return $mensaje;
    }
    public function decodificarComando($mensaje,$movil){
    //si el aux viene vacio....es una consulta mandar solo el ?
    //falta guardar en memoria el estado de velocidad max del equipo y tiempo de veloc max
    $auxParams  = explode(",",$mensaje->auxiliar);
    switch ($mensaje->cmd_id) {
        case 17:
          $cadenaComando  = "+GETGP?";
          break;
        case 20://frecuencias y velocidades
          if(isset($auxParams[1])){
            switch ($auxParams[1]) {
              case '6':
                $valorSet   = ($auxParams[2]=='' || is_null($auxParams[2]))?'?':"=0,".self::EN_MOVIMIENTO.",".$auxParams[2];
                $cadenaComando = "+FR".$valorSet;
                break;
              case '7':
                $valorSet   = ($auxParams[2]=='' || is_null($auxParams[2]))?'?':"=0,".self::EN_DETENIDO.",".$auxParams[2];
                $cadenaComando = "+FR".$valorSet;
                break;
              case '20':
                //seteo tiempo de reporte en veloc max ALV=t,v
                $valorSet   = ($auxParams[2]=='' || is_null($auxParams[2]))?'?':"=".$movil->velocidad_max.",".$auxParams[2];
                Log::info("valorset en:::".$valorset." el auxiliar:::".$mensaje->auxiliar);
                $cadenaComando = "+ALV".$valorSet;
                break;
              case '23':
                //seteo velocidad max max ALV=t,v
                $valorSet   = ($auxParams[2]=='' || is_null($auxParams[2]))?'?':"=".$auxParams[2].",".intval($movil->frec_rep_exceso_vel*60);
                $cadenaComando = "+ALV".$valorSet;
                break;
              case '2':
              //caso especial donde se consulta frecuencias
                $valorSet   = "?2,".$auxParams[2];
                $cadenaComando = "+FR".$valorSet;
                break;
              default:
                # code...
                break;
            }
          }else{
            $cadenaComando = "+FR?0,0";
          }

          break;
        case 22://modo corte(aux=1,2->0,1) y modo normal(aux=1,1->0,0)
            /*encontrar el tipo de instalacion...si tiene IOM resolver de otra manera*/
            if(!$movil->perif_io_id){ //moviles sin IOM 
                if(isset($auxParams[1]) && !is_null($auxParams[1]) && $auxParams[1]!=''){
                    $valor      = ($auxParams[1]=="2")?"1":"0";
                    $valorSet   = "=0,".$valor;
                }else{
                    $valorSet   = '?';
                }
                $cadenaComando = "+OUTS".$valorSet;
            }else{ //moviles con IOM
                switch ($auxParams[1]) {
                    case 0:
                        $valorSet="CMD_RESET";
                        break;
                    case 1:
                        $valorSet="CMD_NORMAL";
                        break;
                    case 2:
                        $valorSet="CMD_CORTE";
                        break;
                    case 3:
                        $valorSet="CMD_BLOQINH";
                        break;
                    case 4:
                        $valorSet="CMD_ALARMAS";
                        break;
                }
                $cadenaComando = "+PER=IOM,".$valorSet;
            }
          break;
        case 100://reset
          $cadenaComando = "+RES=".$auxParams[0];
          break;
        case 134: //conf.sensores que generan corte
            $cadenaComando="+PER=IOM,HAS,".$mensaje->auxiliar;
         break;
        case 106: //conf.sensores bloqueados
            $cadenaComando="+PER=IOM,INI,".$mensaje->auxiliar;
            break;
        case 112: //conf.sensores bloqueados
            $cadenaComando="+PER=IOM,CFG_CORTE,".$mensaje->auxiliar;
            break;
        case 115: //resetear periferico
            $cadenaComando="+PER=IOM,RES,".$mensaje->auxiliar;
            break;
        default:
          $cadenaComando  = "+GETGP?";
          break;
      }
      $logcadena ="\r\n Comando decodificado en KA->".$cadenaComando." \r\n";
      HelpMen::report($movil->equipo_id,$logcadena);
      return $cadenaComando;
    }
}
