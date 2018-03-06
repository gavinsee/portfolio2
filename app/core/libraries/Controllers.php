<?php



abstract class Controller {
  public $response_stack = array();
  public function get_response() {
    return $this->response_stack;
  }
}


interface Auth_Controller { // Auth_Controller = Authenticated Controller = Controller which required authentication
    public function __construct($action, Authentication $Authentication);
    public function get_request();
}

abstract class Generic_Auth_Controller extends Controller implements Auth_Controller { // Generic Authenticated Controoller
  public $User;
  public function __construct($action, Authentication $Authentication) {
    $this->response_stack["login"] = $Authentication->authenticated;
  }
}



?>
