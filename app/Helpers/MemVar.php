<?php
namespace App\Helpers;
use Log;
/************ class MemVar **********************
class MemVar {
 
	var $identifier="";
	var $key = "";
	var $shm_size=0;
	function MemVar( $_key ) {

		$this->key 			= $_key;
		$this->identifier 	= shmop_open($_key, "w", 0644, 100);
		
		//$this->$identifier = @shm_attach( $_key );
	}

	function setValue( $_valor ) {
		if ($this->$identifier) {
		  	Log::info("en setValue:".$_keyvar. "el valor:".$_valor);
			shmop_write($this->$identifier, $_valor, 0);	
			$this->shm_size 	= shmop_size($this->identifier);
		} else {
		   #you need to create it with shmop_open using "c" only
		}
		
	}

	function getValue( $_keyvar ) {
		$my_string = shmop_read($this->identifier, 0, $this->shm_size);
		
		return $my_string;
	}

	function eliminar( ) {
	@shm_remove( $this->$identifier  );
	}

	function close( ) {
	@shm_detach( $this->$identifier );
	}
}
*/
class MemVar {
 
var $identifier=0;
var $key = "";
var $size 	= 0;
 
function __construct( $_key,$_permission,$_size ) {
  
  	$this->key = $_key;
  	Log::info("kreando..".$this->key);
  	//$this->identifier = @shm_attach( $_key );0644, 100
  	  	//exec("sudo -u www-data php -r 'shmop_open(0xee4, "w", 0770, 100);'");

  	$this->identifier = shmop_open($this->key, "c", 0644, 100);//shmop_open($_key, "c", $_permission, $_size);
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
}