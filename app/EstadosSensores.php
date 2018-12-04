<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class EstadosSensores extends Model
{
    protected $table        = 'estados_sensores';
    protected $primaryKey   = 'id';
    protected $connection   = 'moviles';
    public $timestamps      = true;
    protected $fillable     = array('movil_id', 'imei', 'iom','io');
    protected $dateFormat   = 'Y-m-d H:i:s';

    protected $dates = [
        'created_at',
        'updated_at'
    ];
}