<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/
$app->post('reportes-normales', 'NormalReportController@create');
$app->post('reportes-curvos', 'CurveReportController@create');
$app->post('alarmas', 'AlarmController@create');
$app->post('keep-alives', 'KeepAliveController@create');
//$app->post('comandos', 'CommandController@send');
$app->post('listen', 'CommandController@listen');
$app->get('moviles', 'NormalReportController@dameMoviles');
$app->get('equipos/{id}', 'EquipoController@findImei');
$app->patch('comandos/{imei}', 'CommandController@update');
$app->post('comandos/{imei}', 'CommandController@update');
//$app->post('vaciaMemoria', 'NormalReportController@vaciaMemoria');
$app->get('conectarRedis', 'RedisController@index');
$app->get('prueba', 'Controller@index');
$app->get('vaciaMemoria', 'Controller@limpiar');
/*$app->get('/', function () use ($app) {
    return $app->version();
});*/
