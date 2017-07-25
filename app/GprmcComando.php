<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class GprmcComando extends Model
{
    protected $table 	= 'gprmc_comandos';
    protected $primaryKey = 'id';
    public $timestamps 	= true;
    protected $guarded = ['id'];
    protected $connection = 'baymax';
    /*
   public function entregas() {
       	return $this->hasMany('EntregaViaje');
    }*/
}
