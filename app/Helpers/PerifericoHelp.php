<?php
namespace App\Helpers;
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
use App\Alarmas;
use App\Helpers\HelpMen;
use App\Helpers\RedisHelp;

use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Support\Facades\Redis;

/**
 * Description of PerifericoHelp
 *
 * @author siacadmin
 */
class PerifericoHelp {
  /*ANALISIS BIO*/
    public static function evaluaCampoAlaBIO($estadoArr,$movil,$fecha,$posicion_id,$rta){
        //$rta         = array("rta"=>0,"estado_movil_id"=>7,"tipo_alarma_id"=>0); 
        if($estadoArr[0]==0 && $estadoArr[0]!="X"){
            $rta['estado_movil_id']= 10;
            $rta['tipo_alarma_id'] = 4;
            HelpMen::report($movil->equipo_id,"\r\n ***PUERTA CONDUCTOR ABIERTA*** \r\n ");
        }
        if($estadoArr[0]==1 && $estadoArr[0]!="X"){
            $rta['estado_movil_id']= 10;
            $rta['tipo_alarma_id'] = 10;
            HelpMen::report($movil->equipo_id,"\r\n ***PUERTA CONDUCTOR CERRADA*** \r\n ");
        }
        if( $estadoArr[3]==1 && $estadoArr[3]!="X"){
            $rta['estado_movil_id']= 5;
            $rta['tipo_alarma_id'] = 12;
            HelpMen::report($movil->equipo_id,"\r\n ***MOVIL DESENGANCHADO*** \r\n ");
        }
        if( $estadoArr[3]==0 && $estadoArr[3]!="X"){
            $rta['estado_movil_id']= 5;
            $rta['tipo_alarma_id'] = 5;
            HelpMen::report($movil->equipo_id,"\r\n ***MOVIL ENGANCHADO*** \r\n ");
         }
        if( $estadoArr[1]==0 && $estadoArr[1]!="X" ){
            $rta['estado_movil_id']= 10;
            $rta['tipo_alarma_id'] = 9;
            HelpMen::report($movil->equipo_id,"\r\n ***COMPUERTA ABIERTA*** \r\n");
        }
        if( $estadoArr[1]==1 && $estadoArr[1]!="X" ){
            $rta['estado_movil_id']= 10;
            $rta['tipo_alarma_id'] = 11;
            HelpMen::report($movil->equipo_id,"\r\n ***COMPUERTA CERRADA*** \r\n");
        }
        if($rta["tipo_alarma_id"]>0){
            Alarmas::create(['posicion_id'=>$posicion_id,'movil_id'=>intval($movil->movilOldId),'tipo_alarma_id'=>$rta["tipo_alarma_id"],
                            'fecha_alarma'=>$fecha,'falsa'=>0,'nombre_estacion'=>'GSM0']);
            $rta["estado_movil_id"]=10;
            $rta["rta"]            = 1;
        }
        return $rta;
    }
    /*****si $perFieldWorkMode= 0 =>RESET no informo alertas de nada solo actualizo estado de movil****/
    public static function evaluaPanicoBIO($arrBIO,$posicion_id,$movil,$fecha,$rta){
        //$rta         = array("rta"=>0,"estado_movil_id"=>7,"tipo_alarma_id"=>0); 
        $keyPanico=array_search('P', $arrBIO);
        if( $keyPanico ){
            $rta['estado_movil_id']= 10;//estado "en alarma"
            $rta['tipo_alarma_id'] = 1;//panico
            HelpMen::report($movil->equipo_id,"***PANICO ACTIVADO*** \r\n");
            Alarmas::create(['posicion_id'=>$posicion_id,'movil_id'=>intval($movil->movilOldId),'tipo_alarma_id'=>$rta['tipo_alarma_id'] ,
                        'fecha_alarma'=>$fecha,'falsa'=>0,'nombre_estacion'=>'GSM0']);
        }
        return $rta;
    }
    /*I3: Desenganche=>0 = ENGANCHADO; 1 = DESENGANCHADO |I1: Compuerta=>0 = CERRADA; 1 = ABIERTA*/
    public static function cambiosBitBIO($bioArr,$sensorEstado,$movil,$rta){
        HelpMen::report($movil->equipo_id,"*Evaluando cambios bit BIO* \r\n ");
        $estadoArr = explode(',',$sensorEstado);
        if($estadoArr[0]=='BIO' && $estadoArr[1]){
            $estadoArr = str_split($estadoArr[1]);
            if( $estadoArr[3]==0 && $bioArr[3]==1 && $bioArr[3]!="X"){
                $rta["tipo_alarma_id"]=12;
                $rta["estado_movil_id"]=5;
                $rta["rta"]            = 1;
                HelpMen::report($movil->equipo_id,"\r\n ***MOVIL DESENGANCHADO*** \r\n ");
            }
            if( $estadoArr[3]==1 && $bioArr[3]==0 && $bioArr[3]!="X"){
                $rta["tipo_alarma_id"]=5;
                $rta["estado_movil_id"]=7;
                $rta["rta"]           = 1;
                HelpMen::report($movil->equipo_id,"\r\n ***MOVIL ENGANCHADO*** \r\n");
            }
            if( $estadoArr[1]==1 && $bioArr[1]==0 && $bioArr[1]!="X" ){
                $rta["tipo_alarma_id"]=9;
                $rta["estado_movil_id"]=10;
                $rta["rta"]           = 1;
                HelpMen::report($movil->equipo_id,"\r\n ***COMPUERTA ABIERTA*** \r\n");
            }
            if( $estadoArr[1]==0 && $bioArr[1]==1 && $bioArr[1]!="X"){
                $rta["tipo_alarma_id"]=11;
                $rta["estado_movil_id"]=7;
                $rta["rta"]           = 1;
                HelpMen::report($movil->equipo_id,"\r\n ***COMPUERTA CERRADA*** \r\n");
            }
        }
        return $rta;
    }
    /*******************************IOM***************************************************/
    /*I4: Desenganche=>0 = ENGANCHADO; 1 = DESENGANCHADO | I5: Antisabotaje=>0 = VIOLACION; 1 = NORMAL | I6: Compuerta=>0 = CERRADA; 1 = ABIERTA*/
    public static function cambiosInputIOM($iomArr,$sensorEstado,$movil,$rta){
        //$rta         = array("rta"=>0,"estado_movil_id"=>$estado_movil_id,"tipo_alarma_id"=>0); //alarma_id=7 (Normal)
        HelpMen::report($movil->equipo_id,"\r\n *Evaluando cambios IOM* \r\n ");
        $estadoArr = explode(',',$sensorEstado);
        if($estadoArr[0]=='IOM' && $estadoArr[1]){
            $estadoArr = str_split($estadoArr[1]);
            if( $estadoArr[3]==0 && $iomArr[3]==1 && $iomArr[3]!="X"){
                $rta["tipo_alarma_id"]=12;
                $rta["estado_movil_id"]=5;
                $rta["rta"]            = 1;
                HelpMen::report($movil->equipo_id,"\r\n ***MOVIL DESENGANCHADO*** \r\n ");
            }
            if( $estadoArr[3]==1 && $iomArr[3]==0 && $iomArr[3]!="X"){
                $rta["tipo_alarma_id"]=5;
                $rta["estado_movil_id"]=7;
                $rta["rta"]           = 1;
                HelpMen::report($movil->equipo_id,"\r\n ***MOVIL ENGANCHADO*** \r\n");
            }
            if( $estadoArr[5]==0 && $iomArr[5]==1 && $iomArr[5]!="X" ){
                $rta["tipo_alarma_id"]=9;
                $rta["estado_movil_id"]=10;
                $rta["rta"]           = 1;
                HelpMen::report($movil->equipo_id,"\r\n ***COMPUERTA ABIERTA*** \r\n");
            }
            if( $estadoArr[5]==1 && $iomArr[5]==0 && $iomArr[5]!="X"){
                $rta["tipo_alarma_id"]=11;
                $rta["estado_movil_id"]=7;
                $rta["rta"]           = 1;
                HelpMen::report($movil->equipo_id,"\r\n ***COMPUERTA CERRADA*** \r\n");
            }
            if($iomArr[4]==0 && $iomArr[4]!="X"){
                $rta["tipo_alarma_id"]=6;
                $rta["estado_movil_id"]=10;
                $rta["rta"]           = 1;
                HelpMen::report($movil->equipo_id,"\r\n ***ANTISABOTAJE ACTIVADO*** \r\n");
            }
            
        }
        HelpMen::report($movil->equipo_id,"\r\n *VALOR DE EVALUACION:*".$rta['rta']." \r\n ");
        return $rta;
    }
    /*****si $perFieldWorkMode= 0 =>RESET no informo alertas de nada solo actualizo estado de movil****/
    public static function evaluaNb($arrIOM,$posicion_id,$movil,$fecha,$rta){
        //$rta        = array("rta"=>0,"estado_movil_id"=>7,"tipo_alarma_id"=>0);
        $keyNB      = array_search('NB', $arrIOM);
        if( $keyNB ){
            $rta['estado_movil_id']= 10;//estado "en alarma"
            $rta['tipo_alarma_id'] = 32;//modo NB
            $rta["rta"]           = 1;
            HelpMen::report($movil->equipo_id,"***EQUIPO EN MODO SILENCIOSO*** \r\n");
            Alarmas::create(['posicion_id'=>$posicion_id,'movil_id'=>intval($movil->movilOldId),'tipo_alarma_id'=>$rta['tipo_alarma_id'],
                            'fecha_alarma'=>$fecha,'falsa'=>0,'nombre_estacion'=>'GSM0']);
        }
        return $rta;
    }
    public static function evaluaPanicoIOM($arrIOM,$perFieldWorkMode,$posicion_id,$movil,$fecha,$estadoArr,$rta){
        //$rta        = array("rta"=>0,"estado_movil_id"=>7,"tipo_alarma_id"=>0); 
        $keyPanico  = array_search('P', $arrIOM);
        if( $keyPanico ){
            $rta['estado_movil_id']= 10;//estado "en alarma"
            $rta['tipo_alarma_id'] = 1;//panico
            $rta["rta"]           = 1;
            HelpMen::report($movil->equipo_id,"***PANICO ACTIVADO*** \r\n");
            Alarmas::create(['posicion_id'=>$posicion_id,'movil_id'=>intval($movil->movilOldId),'tipo_alarma_id'=>$rta['tipo_alarma_id'] ,
                        'fecha_alarma'=>$fecha,'falsa'=>0,'nombre_estacion'=>'GSM0']);
        }
        //si está en modo alarmas darle bola al campo panico en ALA
        if($perFieldWorkMode== 4 && isset($estadoArr[0]) && $estadoArr[0]=="0" && $estadoArr[0]!="X"){ 
            $rta['estado_movil_id']= 10;//estado "en alarma"
            $rta['tipo_alarma_id'] = 1;//panico
            $rta["rta"]           = 1;
            HelpMen::report($movil->equipo_id,"***PANICO ACTIVADO*** \r\n");
            Alarmas::create(['posicion_id'=>$posicion_id,'movil_id'=>intval($movil->movilOldId),'tipo_alarma_id'=>$rta['tipo_alarma_id'] ,
                        'fecha_alarma'=>$fecha,'falsa'=>0,'nombre_estacion'=>'GSM0']);
        }
        return $rta;
    }
    public static function evaluaCampoAlaIOM($estadoArr,$movil,$rta){
        //log::error(print_r($estadoArr,true));
       // $rta         = array("rta"=>0,"estado_movil_id"=>7,"tipo_alarma_id"=>0); 
        if($estadoArr[1]=="0" && $estadoArr[1]!="X"){
            $rta['estado_movil_id']= 10;
            $rta['tipo_alarma_id'] = 4;
            HelpMen::report($movil->equipo_id,"\r\n ***PUERTA CONDUCTOR ABIERTA*** \r\n ");
        }
        if($estadoArr[1]=="1" && $estadoArr[1]!="X"){
            $rta['estado_movil_id']= 10;
            $rta['tipo_alarma_id'] = 10;
            HelpMen::report($movil->equipo_id,"\r\n ***PUERTA CONDUCTOR CERRADA*** \r\n ");
        }
        if($estadoArr[2]=="0" && $estadoArr[2]!="X"){
           $rta['estado_movil_id']= 10;
            $rta['tipo_alarma_id'] = 24;
            HelpMen::report($movil->equipo_id,"\r\n ***PUERTA ACOMPAÑANTE ABIERTA*** \r\n ");
        }
        if($estadoArr[2]=="1" && $estadoArr[2]!="X"){
            $rta['estado_movil_id']= 10;
            $rta['tipo_alarma_id'] = 25;
            HelpMen::report($movil->equipo_id,"\r\n ***PUERTA ACOMPAÑANTE CERRADA*** \r\n ");
        }
        if($estadoArr[7]=="0" && $estadoArr[7]!="X"){
            $rta['estado_movil_id']= 10;
            $rta['tipo_alarma_id'] = 3;
            HelpMen::report($movil->equipo_id,"\r\n ***MOTOR ENCENDIDO*** \r\n ");
        }
        if( $estadoArr[3]==1 && $estadoArr[3]!="X"){
            $rta['estado_movil_id']= 5;
            $rta['tipo_alarma_id'] = 12;
            HelpMen::report($movil->equipo_id,"\r\n ***MOVIL DESENGANCHADO*** \r\n ");
        }
        if( $estadoArr[3]==0 && $estadoArr[3]!="X"){
            $rta['estado_movil_id']= 5;
            $rta['tipo_alarma_id'] = 5;
            HelpMen::report($movil->equipo_id,"\r\n ***MOVIL ENGANCHADO*** \r\n ");
         }
        if( $estadoArr[5]==1 && $estadoArr[5]!="X" ){
            $rta['estado_movil_id']= 10;
            $rta['tipo_alarma_id'] = 9;
            HelpMen::report($movil->equipo_id,"\r\n ***COMPUERTA ABIERTA*** \r\n");
        }
        if( $estadoArr[5]==0 && $estadoArr[5]!="X" ){
            $rta['estado_movil_id']= 10;
            $rta['tipo_alarma_id'] = 11;
            HelpMen::report($movil->equipo_id,"\r\n ***COMPUERTA CERRADA*** \r\n");
        }
        return $rta;
    }
}
