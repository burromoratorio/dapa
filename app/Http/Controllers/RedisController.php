<?php
namespace App\Http\Controllers;
use App\ColaMensajes;
use App\InstalacionSiac;
use App\Helpers\HelpMen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Lumen\Routing\Controller as BaseController;
use Exception;
use Illuminate\Support\Facades\Redis;

/**
 * Description of RedisController
 *
 * @author siacadmin
 */
class RedisController extends BaseController {
    public function index(Request $request) {
        try{
            $client = new \Predis\Client();
            $client->set('foo', 'bar');
            return 'foo stored as ' . $client->get('foo');
       }catch( \Exception $e){
            Log::error($e);
        }
    }
}
