<?php
namespace App\Helpers;
use Log;
/************ class MemVar **********************/

class MemVar {
 
var $identifier=0;
var $key = "";
var $size 	= 0;
 
	function __construct( $_key,$_permission,$_size ) {
	  
	  	$this->key 		= $_key;
	  	$this->size 	= (int)$_size;
	  	Log::info("kreando..".$this->key." tamanio:".$this->size);
	  	$this->identifier = shmop_open($this->key, "c", 420,256 );
	  	//$this->identifier = @shm_attach( $_key );0644, 100
	  	//exec("sudo -u www-data php -r 'shmop_open(0xee4, "w", 0770, 100);'");
	  	//shmop_open($_key, "c", $_permission, $_size);
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