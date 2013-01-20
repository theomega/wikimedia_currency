<?php
# vim:tw=80:ts=2:sw=2:colorcolumn=81:expandtab:nosmartindent
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

    $stmt = $this->prepare("INSERT INTO exchange_rates ".
                                  "(currency, rate) VALUES (?,?);");
     
    #Iterate on all rates and insert them into the table     
    $count=0;

    foreach($xml as $conversion) {
      $stmt->bind_param('sd', $conversion->currency, $conversion->rate);
      $stmt->execute();
      $count++;
    } 
    
    $stmt->close();

    return $count;
  }


  #Converts a foreign currency (in the format "JPY 3254") into the equivalent in 
  #USD (in the format "USD 6345")
  #
  #This function has a strange signature (as requested) based on a string as
  #and a string as output (which should be formated). It takes either an array
  #of strings or an single string.
  public function convertToUSDFromStr($s) {
    if(is_array($s)) {
      return array_map(array($this, 'convertToUSDFromStr'), $s);
    } else {
      $a = explode(" ", $s);
      if(count($a)!=2) {
        die('Invalid parameter format "'.$s.'"');
      }
      return 'USD '.sprintf('%01.2f', $this->convertToUSD($a[0], $a[1]));
    }
  }

  #Converts a foreign currency into the equivalent in USD
  #
  #This is a function which has a better suited signature, based on two
  #parameters and returning a single double, which is not truncated.
  public function convertToUSD($currency, $amount) {
    return $amount*$this->getRate($currency);
  }

  #Converts a amount of USD into the equivalent in a foreign currency
  public function convertFromUSD($amount, $currency) {
    return $amount/$this->getRate($currency);
  }

  #Helper function which simply returns the exchange rate for a give currency
  private function getRate($currency) {
    $stmt = $this->prepare("SELECT rate FROM exchange_rates WHERE ".
                           "currency=?;");
    $stmt->bind_param('s', $currency);
    $stmt->execute();
    $stmt->bind_result($rate);
    if($stmt->fetch()!=TRUE) {
      die("Could not get rate for currency '".$currency."'");
    }
    $stmt->close();

    return $rate;
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

  #Wrapper function on mysqli::prepare which checks for errors and dies in case
  #of an error
  private function prepare($query) {
    $s = $this->mysql->prepare($query);
    if($s==FALSE) {
      die('MySQL-Statement prepare "'.$query.'" failed: '.$this->mysql->error);
    }
    return $s;
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

$cc=new CurrencyConverter('localhost', 'wikimedia', 'wikimedia', 'wikimedia');

if(!isset($_GET['action'])) {
  die('Specify the "action" GET parameter');
}

$action=$_GET['action'];


if($action=='refresh') {
  echo('Refreshing the conversion data<br />');
  $count = $cc->refresh();
  echo('Inserted '.$count.' records in the database');
} else if ($action=='fromUSD') {
  if(!isset($_GET['currency'])) {
    die('Specify the "currency" parameter');
  }
  if(!isset($_GET['amount'])) {
    die('Specify the "amount" parameter');
  }
  echo $cc->convertFromUSD($_GET['amount'], $_GET['currency']);
} else if ($action=='test') {
  echo 'Single Test: <br/>';
  echo 'JPY 5000: '.$cc->convertToUSDFromStr('JPY 5000').'<br/>';
  echo 'Multiple Test: <br/>';
  echo 'array(JPY 5000, CZK 62.5): ';
  print_r($cc->convertToUSDFromStr(array( 'JPY 5000', 'CZK 62.5')));
}

?>
