<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class GprmcDesconexion extends Model
{
    protected $table 	= 'gprmc_desconexiones';
    protected $primaryKey = 'id';
    public $timestamps 	= false;
    protected $guarded = ['id'];
    protected $connection = 'baymax';
    /*
   public function entregas() {
       	return $this->hasMany('EntregaViaje');
    }*/
}
