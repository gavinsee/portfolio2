<?php

class Post {
  private $User;
  public function __construct(Authentication $ProvidedUser) {
    $this->User = $ProvidedUser;
  }
  
  public function get_all_post() {
    $DB_PDO = $this->User->DB_PDO;
    $query = "SELECT StudentCID as CID, Date, Collected, Registered, `Desc` FROM *table*Post WHERE StudentCID = '*cid*' ORDER BY ID DESC";
    $query = $this->User->prepare_query($query);
    $stmnt = $DB_PDO->query($query); // PDO Statement (stmnt) Object returned
    return $stmnt->fetchAll();
  }
}

?>
