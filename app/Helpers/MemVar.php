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
    public static function Instance()
    {
        private $identifier = 0;
        private $key 		= "";
    	private $size  		= 0;
       	private  static $inst= null;
        if (self::$inst === null) {
           self:: $inst = new MemVar();
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
    public function init($_key,$_size){
    	Log::info("creando singleton");
    	$this->key 		= $_key;
  		$this->size 	= $_size;
  		Log::info("varibles, key:".$this->key." size:".$this->size);
    }
}
/*class BookSingleton {
    private $author = 'Gamma, Helm, Johnson, and Vlissides';
    private $title  = 'Design Patterns';
    private static $book = NULL;
    private static $isLoanedOut = FALSE;

    private function __construct() {
    }

    static function borrowBook() {
      if (FALSE == self::$isLoanedOut) {
        if (NULL == self::$book) {
           self::$book = new BookSingleton();
        }
        self::$isLoanedOut = TRUE;
        return self::$book;
      } else {
        return NULL;
      }
    }

    function returnBook(BookSingleton $bookReturned) {
        self::$isLoanedOut = FALSE;
    }

    function getAuthor() {return $this->author;}

    function getTitle() {return $this->title;}

    function getAuthorAndTitle() {
      return $this->getTitle() . ' by ' . $this->getAuthor();
    }
  }/*