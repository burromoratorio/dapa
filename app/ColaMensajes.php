<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ColaMensajes extends Model
{
    protected $table        = 'COLA_MENSAJES';
    public $timestamps      = false;
    protected $connection   = 'siac';
    protected $primaryKey   = 'tr_id';    
    protected $fillable     = array('modem_id','usuario_id','nombre_estacion','prioridad','cmd_id','rsp_id',
                                    'auxiliar','fecha_inicio','fecha_final','comando','respuesta');
    protected $dateFormat   = 'Y-m-d H:i:s';

    protected $dates = ['fecha_final','fecha_final'];

      
}
