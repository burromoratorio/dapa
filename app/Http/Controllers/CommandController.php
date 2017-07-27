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
use App\GprmcComando;
//use App\Http\Controllers\PuertoController;
//Use Puerto;
class CommandController extends BaseController
{
  const ESTADO_PENDIENTE = 1;
  const ESTADO_EN_PROCESO = 2;
  const ESTADO_OK = 3;
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
  public function listen()
    {
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
        $estado   = $request->input('estado_comando_id');
        Log::info('recibido en dapa, IMEI:'.$imei.' rta:'.$comandoRta.' estado:'.$estado);
        //$comando  = GprmcComando::findOrFail($imei);
        
        /*if ($estado == static::ESTADO_EN_PROCESO) {
            
        }
        $comando->status    = $estado;
        $comando->fecha_rta = $estado;
        $comando->save();*/
        return "Update OK";
    }
   
}
