<?php
namespace App\Http\Controllers;
use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Http\Request;
Use Log;
use stdClass;
use Storage;
use DB;
//use App\Http\Controllers\PuertoController;
//Use Puerto;
class CommandController extends BaseController
{
  public function index(Request $request) {
    return "ok";
  }      //
  public function create(Request $request){
    $method = $request->method();
    $response = "";
    if ($request->isMethod('post')) {
	    $jsonReq = $request->json()->all();
      if(isset($jsonReq["cadena"])){
        try{
          //app()->Puerto->analizeReport($jsonReq['cadena']) ;
          $response ="AT+GETGP?";//"$9\r\nAT+GETGP?\r\n";
          Log::error("cadena entrante en CommansController ::".$jsonReq['cadena']);
        }catch(Exception $e){
          Log::error($e);
        }
        return $response;
      }else{
        return "ERROR:Json mal formado!";
        Log::error("Error:json mal formado, ver palabra clave");
      }
    }else{
      return "ERROR:Metodo no permitido";
      Log::error("Error:metodo no permitido,utilizar POST");
    }
  }
  public function send(Request $request){
    $connection = new AMQPStreamConnection('192.168.1.228', 5672, 'siacadmin', 'siac2010');
    $channel    = $connection->channel();
    $channel->exchange_declare('comandos', 'direct', false, false, false);
    $imei       = '863835020075979';
    $jsonReq    = $request->json()->all();
    if(isset($jsonReq["cadena"])){
      $data     = $jsonReq['cadena'];
    }
    //$data   = 'AT+GETGP?';
    $msg        = new AMQPMessage($data);
    $channel->basic_publish($msg, 'comandos', $imei);
    
    Log::info(" [x] enviado ".$imei.':'.$data);
    
    $channel->close();
    $connection->close();
      
  }
   
}
