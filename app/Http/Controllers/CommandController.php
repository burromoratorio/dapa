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
  static $comandoDefinitions = array("+GETGP"=>17,"+FR"=>20,"+ALV"=>20,"+RES"=>100,"+OUTS"=>22,"+VER"=>00);
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
        $arrCmdRta  = explode(":",$comandoRta);
        //$comandoDefinitions
        Log::info('recibido en dapa, IMEI:'.$imei.' rta:'.$comandoRta.' estado:'.$estado.' se obtuvo:'.$arrCmdRta[0]." de CMD_ID:".self::$comandoDefinitions[$arrCmdRta[0]]);
        $movil    = HelpMen::movilesMemoria($imei);
        $equipo_id= $movil->equipo_id;
        $mensaje  = ColaMensajes::where('modem_id', '=',$equipo_id)
                                ->where('rsp_id','=',2)
                                ->where('cmd_id','=',self::$comandoDefinitions[$arrCmdRta[0]])
                                ->orderBy('prioridad','DESC')
                                ->get()->first(); 
        if(!is_null($mensaje)){
          $mensaje->rsp_id      = 3;
          $mensaje->comando     = $arrCmdRta[0];
          $mensaje->respuesta   = $comandoRta;
          $mensaje->fecha_final = date("Y-m-d H:i:s");
          $mensaje->save();
          return "AT+OK\r\n";
        }else{
          Log::info("No existe comando pendiente");
        }
    }
    /*public function compruebaMovilMC($imei,$shmid){
    //Movil Binary Search 
    MemVar::initIdentifier($shmid);
    $memoMoviles    = MemVar::GetValue();
    $memoMoviles    = json_decode($memoMoviles);
    $encontrado     = app()->Puerto::binarySearch($memoMoviles, 0, count($memoMoviles) - 1, $imei);
    return $encontrado;
    
  } 
  protected function obtenerMoviles() {
      Log::error("Buscando moviles en code.siacseguridad.com");
      // Create a client with a base URI
      $client = new Client(['base_uri' => 'http://code.siacseguridad.com:8080/api/']);
      // Send a request to https://foo.com/api/test
      $response = $client->request('GET', 'equipos/1');

      return $response;
  }
  public function movilesMemoria($imei){
    Log::error(print_r($imei, true));
    $requestApi   = '0';
    $mcRta        = '0';
    $mcRta2       = '0';
    $movil        = false;
    $shmid        = MemVar::OpenToRead('moviles.dat');
    if($shmid!='0'){
        Log::info("Verificando validez IMEI ".$imei);
        $mcRta        = $this->compruebaMovilMC($imei,$shmid);
      if($mcRta==false){
        Log::info("El IMEI ".$imei." no está en la memoria");
        $requestApi= '1';
      }else{
        $mcRta2    = '1';
        $movil     = $mcRta;
        Log::info("::::::::Procesando:".$imei."- Movil_id:".$movil->movil_id."-MovilOld_id:".$movil->movilOldId."::::::::");
      }
    }else{
        $requestApi   = '1';
        Log::info("No existe el shmid->voy a crear nuevo segmento");
    }
    if($requestApi   == '1'){
        $apiRta   = $this->obtenerMoviles();
        if($apiRta->getStatusCode()=="200" && $apiRta->getReasonPhrase()=="OK"){
          $length   = strlen($apiRta->getBody());
          $largo    = (int)$length;
          //Log::error("Content-Length:::".strlen($apiRta->getBody()));
          MemVar::VaciaMemoria();
          $memvar = MemVar::Instance('moviles.dat');
          $memvar->init('moviles.dat',$largo);
          $memvar->setValue( $apiRta->getBody() );
          $shmid  = MemVar::OpenToRead('moviles.dat');
          $movil  = $this->compruebaMovilMC($imei,$shmid);
        }else{
          Log::error("Bad Response :: code:".$code." reason::".$reason);
        }
    }
  return $movil;
  }*/
   
}
