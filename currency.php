<?php
# vim:tw=80:ts=2:sw=2:colorcolumn=81:nosmartindent
error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', 'On');

class CurrencyConverter {
  private $mysql;

  public function __construct($mhost, $muser, $mpass, $mdb) {
 	  $this->mysql = new mysqli($mhost, $muser, $mpass, $mdb);	

    if ($this->mysql->connect_errno) {
      die("Failed to connect to MySQL: (".$this->mysql->connect_errno.") ".
          $this->mysql->connect_error);
    }
  } 

  public function refresh() {
     #Construct XML tree (involves the downloading)
     $xml = $this->loadXML('http://toolserver.org/~kaldari/rates.xml');
     
     #Delete data from mysql-table
     $this->query("TRUNCATE TABLE exchange_rates;");

		 $stmt = $this->mysql->prepare("INSERT INTO exchange_rates ".
																	"(currency, rate) VALUES (?,?);");
     
     if($stmt==FALSE) {
       die('MySQL prepare has failed: '.$this->mysql->error);
     }
  
     #Iterate on all rates and insert them into the table     
     $count=0;
		 foreach($xml as $conversion) {
			$stmt->bind_param('sd', $conversion->currency, $conversion->rate);
      $stmt->execute();
      $count++;
    } 
    return $count;
  }

  #Wrapper function on mysqli::query which checks for errors and dies if an
  #error has occured.
  private function query($query) {
    $r = $this->mysql->query($query);
    if($r==FALSE) {
      die('MySQL-Statement "'.$query.'" failed: '.$this->mysql->error);
    }
    return $r;
  }

	#Wrapper function on libxmls loading to check for errors and die in case of an
  #error
  private function loadXML($url) {
    $sxe = new SimpleXMLElement($url, NULL, True);
	  if ($sxe === false) {
			echo "Failed loading XML\n";
			foreach(libxml_get_errors() as $error) {
				echo "\t", $error->message;
			}
      die();
		}
		return $sxe;
	}

}

if(!isset($_GET['action'])) {
  die('Specify the "action" GET parameter');
}

$action=$_GET['action'];

if($action=='refresh') {
  echo('Refreshing the conversion data<br />');
  $cc=new CurrencyConverter('localhost', 'wikimedia', 'wikimedia', 'wikimedia');	
  $count = $cc->refresh();
  echo('Inserted '.$count.' records in the database');
}

?>
