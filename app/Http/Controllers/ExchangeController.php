<?php
namespace App\Http\Controllers;

//require_once __DIR__ . '/vendor/autoload.php';
use Illuminate\Http\Request;
use App\Http\Requests;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
Use Log;

class ExchangeController extends Controller
{
    public function send(){
	    $connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
		$channel 	= $connection->channel();
		$channel->exchange_declare('comandos', 'direct', false, false, false);
		$imei 		= '863835020075979';
		if(empty($imei)) $imei = "00";
		$data		= 'AT+GETGP?\r\n';
		if(empty($data)) $data = "Hello World!";
		$msg 		= new AMQPMessage($data);
		$channel->basic_publish($msg, 'comandos', $imei);
		Log::info(" [x] Sent ".$imei.':'.$data);
		$channel->close();
		$connection->close();
	    
	}
}
