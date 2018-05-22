<?php
namespace App\Helpers;
use Log;
/************ class MemVar **********************/

class MemVar {
	 
	static $identifier  = 0;
	static $size 		= 0;
	static $key 		= "";

 
	public function __construct( $_key,$_permission,$_size ) {
	  	self::$key 		= $_key;
	  	self::$size 	= $_size;
	  	Log::info("kreando..".self::$key." tamanio:".self::$size);
	}
	
	public private function crear(){
		
	  	//self::$identifier = shmop_open(self::$key, "c", 0644,self::$size);
		self::$identifier = shmop_open(self::$key, "c", 0644, self::$size);
		if (!self::$identifier) {
		    Log::info("Couldn't create shared memory segment");
		}
		// Obtener tamaño del segmento de memoria compartida
		self::$size = shmop_size(self::$identifier);
		Log::info("SHM Block Size: " . self::$size . " has been created.");

	} 
	public function static setValue( $_valor ) {
		// Escribir una cadena de prueba en la memoria compartida
		$shm_bytes_written = shmop_write(self::$identifier, $_valor, 0);
		// @shm_put_var( self::$identifier , $_keyvar  , $_valor  );
	}
	 
	public function static getValue( ) {
		// Ahora vamos a leer la cadena de texto
		$my_string = shmop_read(self::$identifier, 0, self::$size);
		if (!$my_string) {
		    Log::info("Couldn't read from shared memory block");
		}
		Log::info("The data inside shared memory was: " . $my_string );
		return $my_string;
		//return @shm_get_var( self::$identifier , $_keyvar );
	}
	 
	public function static eliminar( ) {
		//Ahora vamos a eliminar y cerrar el segmento de memoria compartida
		if (!shmop_delete(self::$identifier)) {
		Log::info("couldn't mark shared memory block for deletion.");
		}

	}
	public function static close( ) {
		shmop_close(self::$identifier);
	}
}