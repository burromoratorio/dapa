<?php
namespace App\Helpers;
use Log;
/************ class MemVar **********************/
/*
class MemVar {
 
var $identifier	=0;
var $key 		= "";
var $size 		= 0;
 
function __construct( $_key,$_permission,$_size ) {
  
  	$this->key 		= $_key;
  	$this->size 	= $_size;
  	Log::info("kreando..".$this->key." tamanio:".$this->size);
  	//$this->identifier = shmop_open($this->key, "c", 0644,$this->size);

  	$this->identifier = shmop_open($_key, "c", 0644, $this->size);
	if (!$this->identifier) {
	    Log::info("Couldn't create shared memory segment");
	}
	// Obtener tamaño del segmento de memoria compartida
	$this->size = shmop_size($this->identifier);
	Log::info("SHM Block Size: " . $this->size . " has been created.");
}
 
function setValue( $_valor ) {
	// Escribir una cadena de prueba en la memoria compartida
	$shm_bytes_written = shmop_write($this->identifier, $_valor, 0);
	// @shm_put_var( $this->identifier , $_keyvar  , $_valor  );
}
 
function getValue( ) {
	// Ahora vamos a leer la cadena de texto
	$my_string = shmop_read($this->identifier, 0, $this->size);
	if (!$my_string) {
	    Log::info("Couldn't read from shared memory block");
	}
	Log::info("The data inside shared memory was: " . $my_string );
	return $my_string;
	//return @shm_get_var( $this->identifier , $_keyvar );
}
 
function eliminar( ) {
	//Ahora vamos a eliminar y cerrar el segmento de memoria compartida
	if (!shmop_delete($this->identifier)) {
	Log::info("couldn't mark shared memory block for deletion.");
	}

}
function close( ) {
	shmop_close($this->identifier);
}
}*/
/**
 * Singleton class
 *
 */
final class MemVar
{
    /**
     * Call this method to get singleton
     *
     * @return UserFactory
     */
    private static $identifier = null;
    private static $key  = '';
    private static $size = 0;
    private static $shm_key = null;

    public static function Instance()
    {
        static $inst = null;
        if ($inst === null) {
            $inst = new MemVar();
            
        }
        Log::info("creando singleton");
        return $inst;
    }

    /**
     * Private ctor so nobody else can instantiate it
     *
     */
    private function __construct()
    {

    }
    public function init($k,$s){
    	self::$key 	= $k;
    	self::$size = $s;
    	self::$shm_key = ftok('/bin/ls', 't');
    	Log::info("Seteando valores. key:". self::$shm_key."  size:".self::$size);
    	self::$identifier = shmop_open(self::$shm_key, "c", 0644, self::$size);
		if ( !is_null(self::$identifier) ){
			Log::info("se creo el siguiente identif:".self::$identifier);
		    return true;
		}else{
			Log::info("Couldn't create shared memory segment");
		    return false;
		}
	}
	public function setValue( $v ) {
		$shm_bytes_written = shmop_write(self::$identifier, $v, 0);
		return $shm_bytes_written;
	}
	public static function GetValue( ) {
		$my_string 	= "";
		if ( !is_null(self::$identifier) ){
			$my_string = shmop_read(self::$identifier, 0, self::$size);
			Log::info("The data inside shared memory was: " . $my_string );
		}
		return $my_string;
	}
	public static function Eliminar( ) {
		Log::info("The identifier::::::::: " . self::$identifier );
		if ( !is_null(self::$identifier) ){
			if (!shmop_delete(self::$identifier)) {
				Log::info("couldn't mark shared memory block for deletion.");
			}else{
				Log::info("MC eliminada ....limpiando variables.");
				self::$identifier 	= null;
				self::$key 			= '';
    			self::$size 		= 0;
			}
		}
	}
}
