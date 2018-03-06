<?php

class Student {
  private $User;
  public $Forename;
  public $Surname;
  public $Room;
  public function __construct(Authentication $ProvidedUser) {
    $this->User = $ProvidedUser;
    $this->set_user_details();
  }

  public function get_login_token() {
    $token = $this->token_generator();
    $myusername = $this->User->username;
    $thisHall = $this->User->hall_abbr;
    $query = "INSERT INTO Universal_AppAuthorisationTokens(`username`, `created`, `lastused`, `token`, `HallPrefix`) VALUES('$myusername', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, '$token', '$thisHall')";
    $stmnt = $this->User->DB_PDO->query($query);
    return $token;
  }

  public function token_generator() {
    $uniqueTokenFound = false;
    while ($uniqueTokenFound==false) {
      $token = bin2hex(openssl_random_pseudo_bytes(32)); // 512 byte hash 
      $query = "SELECT token FROM Universal_AppAuthorisationTokens WHERE `token`='$token'";
      $stmnt = $this->User->DB_PDO->query($query);
      if($stmnt->rowCount() ==0){ /* I.E. unique token found */
        $uniqueTokenFound = true;
      }
    }
    return $token;
  }

  public function set_user_details() {
    $query = "SELECT Forename, Surname, Room FROM *table*Residents WHERE CID = *cid* LIMIT 1";
    $query = $this->User->prepare_query($query);
    $query = $this->User->DB_PDO->query($query);
    $row = $query->fetch(PDO::FETCH_ASSOC);
    $this->Forename = $row["Forename"];
    $this->Surname = $row["Surname"];
    $this->Room = $row['Room'];
  }

  public function submit_defect($location, $description) {
    $hallname = $this->User->hall_name;
	  $query = "INSERT INTO *table*DefectReport (Date, CID, Location, Description, Email) VALUES ('*date*','*cid*', ?, ?, '0')";
    $query = $this->User->prepare_query($query);
    $query = $this->User->DB_PDO->prepare($query);
    $stmnt = $query-> execute(array($location, $description));
    if(!$stmnt) { throw new Exception("Query not execute", 500); return false; } else { return true;}
  }

  public function email_defect($location, $description) {
    $this->User->set_email_system();
    $Email = $this->get_email_address();

    // Email Supervisors
    $to = array($this->User->hall_config["Maintenanceemail"], 'accommodation.defects@imperial.ac.uk');
    $to = array("kgs13@ic.ac.uk"); // [] To delete
    $subject = 'Defect Report Received';
    $from = $this->User->hall_config["Wardenemail"];
    $reply = $Email;
    $message = '<html><body>Dear Hall Supervisors,<BR><BR>A resident has submitted a defect report to the Wardening team:<BR><BR> ';
            $message .= $this->Forename.' '.$this->Surname.'<BR>Room: '.$this->Room.'<BR>Email:'.$Email;
	    $message .= '<BR><BR>'.$location.'<BR><BR>'.$description.'<BR><BR><i>Replying to this email will respond directly to the resident</i>';
    $this->User->smtp->send_message($from,$to,array(), $subject, $message, $reply);

    // Email Customer/Student
    $to = array($Email);
    $subject = 'Defect Report Received';
    $from =$this->User->hall_config["Wardenemail"] ;
    $reply = $this->User->hall_config["Maintenanceemail"];
    $message = '<html><body>Dear Resident,<BR><BR>Your defect report has been passed to the Hall Supervision team. You should receive a response soon. If the matter is urgent please call the Duty Phone out of hours.<BR><BR>More information about when defects are attended to is available here: http://www.imperial.ac.uk/study/campus-life/accommodation/current-residents/services/report/ ';
            $message .= $this->Forename.' '.$this->Surname.'<BR>Room: '.$this->Room.'<BR>Email:'.$Email;
            $message .= '<BR><BR>'.$location.'<BR><BR>'.$description.'<BR><BR>';
    $this->User->smtp->send_message($from,$to,array(), $subject,  $message, $reply);

  }

  
  public function get_email_address() {
    $conn = ldap_connect("unixldap.cc.ic.ac.uk") or function(){throw new Exception('LDAP Connection error', 404);};
    $dn = "o=Imperial College,c=GB";
    $filter="(|(uid=".$this->User->username."))";
    $justthese = array("mail");
    $sr=ldap_search($conn, $dn, $filter, $justthese);
    $info = ldap_get_entries($conn, $sr);
    //$this->email_address = $info[$info["count"]-1]["mail"][0];
    return $info[$info["count"]-1]["mail"][0];
  }

}

?>
