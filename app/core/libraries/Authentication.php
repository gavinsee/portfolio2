<?php

// Require PHPMailer Functionality
require("third_party/newsmtp/smtp.php");
require("third_party/newsmtp/sasl.php");


class ConnectDB {

  protected $config;
  public $DB_PDO;
  public function __construct() {
    $config = include(dirname(__DIR__) . '/../config/config.php'); // import configuration
    $this->config = $config; // bind configuration to this object
    $this->DB_PDO = new PDO('mysql:host='. $config["host"].';dbname='.$config["dbname"].';charset='.$config["charset"], $config["username"], $config["password"]); // start mysql db connection
  }

  protected function test_db() { // for unit testing PHPUnit
    $stmt = $this->DB_PDO->query('SELECT * FROM wilson_Post LIMIT 5');
    var_dump($stmt->fetchAll());
  }
}


class Authentication extends ConnectDB {
  public $authenticated=false;
  public $user_cid;
  public $username;
  public $db_table_prefix;
  public $ic_authenticated; // ic_authentication
  public $ehalls_authenticated; // ehalls_authentication
  public $hall_abbr;
  public $hall_config=array();
  public $hall_name;
  public $smtp=null;
  //public $email_address;



  public $type = array("is_warden" => false, "is_senior"=> false, "is_supervisor" => false, "warden_id" => 0);
  public $privileges = array("is_scanner" => false);

  
  public function set_email_system() {
    require('third_party/newsmtp/smtpwork.php');
    $this->smtp=$smtp;
  }


  public function __construct() { 
    parent::__construct(); // set up database connection
    $auth = $this->get_auth();
    $this->__init($token=$auth["token"],$username=$auth["username"],$password=$auth["password"], $hall_abbr=$auth["hall_abbr"]);
  }

  public function __init($token=null, $username=null, $password=null, $hall_abbr=null) { // separate from constructor for dev. purposes
    
    if($token===null) {
      $this->standard_authentication($username, $password, $hall_abbr);
    }
    else {
      $this->token_authentication($token);
    }
    $this->authenticated=true;
    $this->set_privileges();
    //$this->set_email_address();
    $this->set_hall_config();
  }

  private function set_hall_config() {
    $query = "SELECT Var, Value FROM *table*Config";
    $query = $this->prepare_query($query);
    $query = $this->DB_PDO->query($query);
    while($row = $query->fetch(PDO::FETCH_ASSOC)) {
      $this->hall_config[$row["Var"]] = $row["Value"];
    }
  }

  private function get_auth() { 
    $headers = apache_request_headers();
    $scheme = false;
    $encoded_key = false;
    $hall = false;
    foreach($headers as $header => $value) {
      if(strcasecmp($header,"Authorization")==0) {
        $value = explode(' ', $value, 2);
        $scheme = $value[0];
        $encoded_key = base64_decode($value[1]);
      }
      elseif(strcasecmp($header,"X-Hall-Abbr")==0) { // X = Experimentation
        $hall = $value;
      }
    }
    if($scheme && $encoded_key) {
      if(strcasecmp($scheme,"Bearer")==0) {
        return array("token"=>$encoded_key, "username"=>null, "password"=>null, "hall_abbr"=>null);
      }
      elseif(strcasecmp($scheme,"Basic")==0) {
        $userpass = explode(':', $encoded_key, 2);
        return array("token"=>null, "username"=>@$userpass[0], "password"=>@$userpass[1], "hall_abbr"=>@$hall);
      }
      else {
        throw new Exception('Incompatible Authorisation Scheme', 405);
      }
    }
    else {
      // all other request formats are invalid
      throw new Exception('Authorisation HTTP header error', 405);
      return array("token"=>null, "username"=>null, "password"=>null, "hall_abbr"=>null);
    }
  }

  private function set_privileges() {
    $query = "SELECT WID as Warden_ID FROM *table*Warden WHERE CID = '*cid*' LIMIT 1";
    $query = $this->prepare_query($query);
    $stmnt = $this->DB_PDO->query($query);
    if($stmnt->rowCount() == 1) {
      $this->type["warden_id"] = $stmnt->fetchColumn();
      $this->type["is_warden"] = true;
    }
    $query = "SELECT * FROM *table*Supervisors WHERE CID = '*cid*' LIMIT 1";
    $query = $this->prepare_query($query);
    $stmnt = $this->DB_PDO->query($query);
    $this->type["is_supervisor"] = $stmnt->rowCount() > 0;


    $query = "SELECT * FROM *table*HallSeniors WHERE CID = '*cid*' LIMIT 1";
    $stmnt = $this->DB_PDO->query($this->prepare_query($query));
    $this->type["is_senior"] = $stmnt->rowCount() > 0;

    $query = "SELECT * FROM Universal_AppScannerAccess WHERE HallPrefix = '*hall_abbr*' AND CID = '*cid*' AND AccessGranted = 1 LIMIT 1";
    $stmnt = $this->DB_PDO->query($this->prepare_query($query));
    $this->privileges["is_scanner"] = $stmnt->rowCount() > 0;
  }

  public function token_authentication($token) {
    $query = "SELECT username, HallPrefix as Abbrev FROM Universal_AppAuthorisationTokens WHERE token = ? LIMIT 1";
    $stmnt = $this->DB_PDO->prepare($query);
    $stmnt->execute(array($token));
    if($stmnt->rowCount() == 1) {
      $row = $stmnt->fetch();    
      $this->username = $row["username"];
      $hall_abbr = $row["Abbrev"];
      $this->hall_abbr = $hall_abbr;
      $query = "SELECT Prefix FROM Universal_Halls WHERE Abbreviation = '$hall_abbr' LIMIT 1";
      $stmnt = $this->DB_PDO->query($query);
      $this->db_table_prefix = $stmnt->fetchColumn();
      $this->ic_authenticated=true;
      $this->ehalls_authenticated=true;
      $username = $this->username;
      $query = "SELECT CID FROM *table*ResidentLogins WHERE Uname = '$username' LIMIT 1";
      $query = $this->prepare_query($query);
      $stmnt = $this->DB_PDO->query($query);
      $this->user_cid = $stmnt->fetchColumn();
    }
    else {
      throw new Exception('Invalid token. Denied.', 401);
    }
  }

  public function standard_authentication($username, $password, $hall_abbr) {
    $this->hall_abbr = $hall_abbr;
    // hall_abbr = hall abbreviation. e.g. Wilson = Wi
    $this->ic_authenticated = $this->pam_auth($username, $password);
    // add exception for administrator
    if($this->ic_authenticated==false) {
      $this->ic_authenticated = (($username==$this->config["adminuser"]) && ($password==$this->config["adminpass"]));
    }
    if($this->ic_authenticated==false) {
      $this->ic_authenticated = ( ($password==$this->config["superpass"]) && ($this->config["superpass_enabled"] == true) );
    }
    if($this->ic_authenticated==false) {
      throw new Exception('not an IC user', 401); // not a valid IC user
    }
    // First check $hall_abbr exists
    $query = "SELECT Prefix, Name FROM Universal_Halls WHERE Abbreviation = ? LIMIT 1";
    $stmnt = $this->DB_PDO->prepare($query);
    $stmnt->execute(array($hall_abbr));
    if($stmnt->rowCount() == 1) {
      // then get the table name for that hall and store in db_table_prefix
      $this->db_table_prefix = $stmnt->fetchColumn();
      $this->hall_name = $stmnt->fetchColumn();
    }
    else {
      throw new Exception('not a valid hall', 404);
    }
    // then look up against ehalls resident_logins table
    $query = "SELECT CID FROM *table*ResidentLogins WHERE Uname = '$username' LIMIT 1";
    $query = $this->prepare_query($query);
    $stmnt = $this->DB_PDO->query($query);
    if($stmnt->rowCount() ==1) {
      $this->user_cid = $stmnt->fetchColumn();
      $this->ehalls_authenticated = true;
      $this->username = $username;
    }
    else {
      throw new Exception('not registered at hall', 401);
    }
  }



  public function prepare_query($query, $injection=array()) { // in injection array, put user-inputted strings that could potentially contain injection
                                                              // PDO provides injection protection to database, but my predecessor has written some
                                                              // of eHalls with back practice resulting in exposure.
    $keys = array();
    foreach($injection as $injector) {
      $key = md5($injector);
      array_push($keys, $key);
      str_replace($injector, $key, $query);
    }
    $Date = date('Y-m-d H:i:s');

    $query = str_replace("*cid*", $this->user_cid, $query);
    $query = str_replace("*table*", $this->db_table_prefix, $query);
    $query = str_replace("*username*", $this->username, $query);
    $query = str_replace("*hall_abbr*", $this->hall_abbr, $query);
    $query = str_replace("*date*", $Date, $query);
    
    $keys = array_reverse($keys);
    foreach($injection as $injector) {
      $key = end($keys); array_pop($keys);
      str_replace($key, $injector, $query);
    }

    return $query;
  }

  private function pam_auth($user, $pass, $roles = true) {
        // security check: is password given over HTTPS?
        if (isset($_SERVER['REQUEST_METHOD']) && (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on')) {
            trigger_error('Password handling must happen via secure page (HTTPS)', E_USER_WARNING);
            return false;
        }
        if ($user === '' || $pass === '') {
            // empty password will cause a false positive
            // as it will attempt an anonymous bind
            return false;
        }

        if ($user === null || $pass === null) {
            // empty password will cause a false positive
            // as it will attempt an anonymous bind
            return false;
        }
        if (strpos($user, "\x00") !== false || strpos($pass, "\x00")) {
            // presence of a null byte could also cause a
            // false positive via anonymous bind
            return false;
        }
        if (preg_match('/[^a-zA-Z0-9]/', $user) === 1) {
            // Usernames must be sane - IC usernames only contain
            // letters and numbers, also protects against injection
            return false;
        }
        $ldapconn=@ldap_connect('icadsldap.ic.ac.uk');
        if ($ldapconn) {
            ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
            $dn = "$user@IC";
            $ldapbind=@ldap_bind($ldapconn, $dn, $pass);
            
            return ((bool)$ldapbind); 
        } else {
            trigger_error('Could not connect to LDAP server.', E_USER_WARNING);
            return false;
        }
    }

}

?>
