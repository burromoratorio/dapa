<?php
namespace App\Http\Controllers;
use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
Use Log;
use stdClass;
use Storage;
use DB;
/*DDBB Principal*/
use App\ColaMensajes;
/*Helpers*/
use App\Helpers\MemVar;
use App\Helpers\HelpMen;
use GuzzleHttp\Client;

class CommandController extends BaseController
{
  const ESTADO_PENDIENTE = 1;
  const ESTADO_EN_PROCESO = 2;
  const ESTADO_OK = 3;
  const POSICION_ACTUAL=17;
  const CONFIGURAR_FRECUENCIAS=20;
  const CAMBIO_MODO_PRESENCIA_GPRS=22;
  const RESET_GPRMC=100;
  static $comandoDefinitions  = array("+GETGP"=>17,"+FR"=>20,"+ALV"=>20,"+RES"=>100,"+OUTS"=>22,"+VER"=>00);
  static $comandoGenerico     = array("+GEN"=>666); 
  public function index(Request $request) {
    //return "ok";
  }      //
  public function create(Request $request){
    $method = $request->method();
    $response = "";
    if ($request->isMethod('post')) {
	    $jsonReq = $request->json()->all();
      if(isset($jsonReq["cadena"])){
        try{
          //app()->Puerto->analizeReport($jsonReq['cadena']) ;
          //$response ="AT+GETGP?";//"$9\r\nAT+GETGP?\r\n";
          Log::info("cadena entrante en CommansController ::".$jsonReq['cadena']);
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
    $connection = new AMQPStreamConnection('192.168.0.228', 5672, 'siacadmin', 'siac2010');
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
  public function listen(){
        $connection = new AMQPStreamConnection('192.168.0.228', 5672, 'siacadmin', 'siac2010');
        $channel = $connection->channel();
        
        $channel->queue_declare( 'rpc_queue',false,false,false,false);
                  #queue ,#passive,#durable,#exclusive,#autodelete
        $channel->basic_qos(null,1, null );
            #prefetch size,#prefetch count,#global
        $channel->basic_consume('rpc_queue','', false,false,false,false,array($this, 'callback') );
                #queue,#consumer tag,#no local,#no ack,#exclusive,#no wait,#callback
        while(count($channel->callbacks)) {
            $channel->wait();
        }
        
        $channel->close();
        $connection->close();
    }

    /**
     * Executes when a message is received.
     *
     * @param AMQPMessage $req
     */
    public function callback(AMQPMessage $req) {
        
      $credentials = json_decode($req->body);
      $authResult = $this->auth($credentials);
       Log::info("Listen 2- On Callback ".print_r($credentials, true));
      /*
       * Creating a reply message with the same correlation id than the incoming message
       */
      $msg = new AMQPMessage(json_encode(array('status' => $authResult)),array('correlation_id' => $req->get('correlation_id'))  );
                        #message,                   #options
      Log::info("Listen 3- OnCallback retorna desde Auth ".$msg->body);                   
      /*
       * Publishing to the same channel from the incoming message
       */
      $req->delivery_info['channel']->basic_publish($msg,'',$req->get('reply_to') );
                        #message, #exchange,#routing key
      /*
       * Acknowledging the message
       */
      $req->delivery_info['channel']->basic_ack($req->delivery_info['delivery_tag']);
                           #delivery tag
    }

    /**
     * @param \stdClass $credentials
     * @return bool
     */
    private function auth(\stdClass $credentials) {
      switch( $credentials->key  ){
            case "KA":
              /*$formatFecha = date("Y-m-d h:i:s", date()); 
              $comando = GprmcComando::create([
                'imei'=>$credentials->imei,'mensaje'=>$comando,'fecha_mensaje'=>$formatFecha,'status'=>'1');*/
              $comando = GprmcComando::where('imei','=',$credentials->imei)->Where('status', '=', '1')->first();
              $resultado = (count($comando)>0)?$comando->mensaje:'no_comand';
              LOG::info("se obtuvo el comando::".$resultado);
              return $resultado;
            break;
            case "CM";

            break;
      } 
      /*if (($credentials->imei == '863835020075979') && ($credentials->key == 'KA')) {
          //return true;
          $data   = 'AT+GETGP?';
          return $data;
      } else {
          return false;
      }*/
    }
    public function update(Request $request, $imei) {
        $comandoRta = $request->input('rta');
        $estado     = $request->input('estado_comando_id');
        $estadotipo_posicion = 66;
        ///Log::info('recibido en dapa, IMEI:'.$imei.' rta:'.$comandoRta.' estado:'.$estado.' se obtuvo:'.$arrCmdRta[0]." de CMD_ID:".self::$comandoDefinitions[$arrCmdRta[0]]);
        $movil    = HelpMen::movilesMemoria($imei);
        $equipo_id= $movil->equipo_id;
        //voy a contar las ocurrencias de un determinado caracter en este caso el signi + 
        $contador   = mb_substr_count($comandoRta, "+");
        if($contador>1){
          //significa que vienen varios comandos concatenados tengo que limpiarlos
          //3-los comandos en rsp_id=2 e intentos <3 ->los seteo en pendiente rsp_id=1 y les sumo 1 al intentos, excepto al obtenido para rta
          $logcadena ="IMEI:".$imei.":::Comandos Concatenados:::".$comandoRta;
          HelpMen::report($equipo_id,$logcadena);
          DB::beginTransaction();
          try {
            $mensajeSinRta= ColaMensajes::where('modem_id', '=',$equipo_id)
                                  ->where('rsp_id','=',2)->where('intentos','=',5)
                                  ->update(['rsp_id'=>5]);
            $mensajeUP    = ColaMensajes::where('modem_id', '=',$equipo_id)
                                    ->where('rsp_id','=',2)->where('intentos','<',5)->where('cmd_id','<>',22)
                                    ->increment('intentos', 1, ['rsp_id'=>1]);
            DB::commit();
          }catch (\Exception $ex) {
            DB::rollBack();
            $errorSolo  = explode("Stack trace", $ex);
            $logcadena ="Error al procesar update de comandos ".$errorSolo[0];
            HelpMen::report($equipo_id,$logcadena);
          }
          return "AT+OK\r\n";
        }else{
          $arrCmdRta  = explode(":",$comandoRta);
          $commandoId = (isset(self::$comandoDefinitions[$arrCmdRta[0]]))?self::$comandoDefinitions[$arrCmdRta[0]]:self::$comandoGenerico["+GEN"];
          if($commandoId==22 || $arrCmdRta[0]=='+OUTS'){
            $mensaje  = $this->tratarOUTS($equipo_id,$arrCmdRta[1]);
          }else{
            $mensaje  = ColaMensajes::where('modem_id', '=',$equipo_id)
                                  ->where('rsp_id','=',2)
                                  ->where('cmd_id','=',$commandoId)->where('cmd_id','<>',22)
                                  ->orderBy('prioridad','DESC')
                                  ->get()->first(); 
          }
          $logcadena = "Respuesta IMEI:".$imei." - Equipo:".$equipo_id." rta:".$comandoRta." de CMD_ID:".$commandoId;
          HelpMen::report($equipo_id,$logcadena);
          if(!is_null($mensaje)){
            $mensaje->rsp_id      = 3;
            $mensaje->comando     = $arrCmdRta[0];
            $mensaje->respuesta   = $comandoRta;
            $mensaje->fecha_final = date("Y-m-d H:i:s");
            $mensaje->save();
            $logcadena = "actualizacion correcta devuelvo:AT+OK\r\n";
            HelpMen::report($equipo_id,$logcadena);
            return "AT+OK\r\n";
          }else{
            $logcadena = "No existe comando pendiente";
            HelpMen::report($equipo_id,$logcadena);
          }
        }
    }
    public function tratarOUTS($equipo_id,$valor){
      $OUTPendiente = null;
      $OUTPendiente = $this->OUTPendiente($equipo_id);
      if(!is_null($OUTPendiente)){
        if($OUTPendiente->auxiliar=='0,1' && $valor=='10'){//activar modo corte
          $logcadena = "modo corte activado equipo:".$equipo_id;
          HelpMen::report($equipo_id,$logcadena);
          $OUTPendiente->tipo_posicion  = 70;
        }
        if($OUTPendiente->auxiliar=='0,0' && $valor=='00'){//activar modo corte
          $logcadena = "modo corte desactivado equipo:".$equipo_id;
          HelpMen::report($equipo_id,$logcadena);
          $OUTPendiente->tipo_posicion  = 70;
        }
      }
      
      return $OUTPendiente;
    }
    public function OUTPendiente($equipo_id){
      $outMs    = null;
      $outMs    = ColaMensajes::where('modem_id', '=',$equipo_id)
                                  ->where('rsp_id','=',2)->where('cmd_id','=',22)
                                  ->where('tipo_posicion','=',69)
                                  ->orderBy('prioridad','DESC')
                                  ->get()->first(); 
      return $outMs;
    }
}
