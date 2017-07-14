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
$app->post('comandos', 'CommandController@create');
$app->get('moviles', 'PuertoController@dameMoviles');
//$app->patch('reenvios/{id}', 'ReenvioController@update');

/*$app->get('/', function () use ($app) {
    return $app->version();
});*/
