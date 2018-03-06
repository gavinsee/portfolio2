<?php

// keep it simple for now
require_once(dirname(__DIR__) . '/models/student.php');
require_once(dirname(__DIR__) . '/core/libraries/Controllers.php');


class Student_Controller extends Generic_Auth_Controller {
  public $student_model;

  public function __construct($action, Authentication $ProvidedUser) { // POD = PHP Data Object
    $this->User = $ProvidedUser;
    $this->student_model = new Student($ProvidedUser);
    if( (strcasecmp($_SERVER['REQUEST_METHOD'], 'post')==0) && ($action == 'defect')) {
      $this->submit_defect();
    }
    elseif( (strcasecmp($_SERVER['REQUEST_METHOD'], 'GET')==0) && ($action == 'login')) {
      $this->login();
    }
    else {
      throw new Exception("No valid action specified or invalid HTTP request", 405);
    }
  }

  public function login() {
    if($this->User->authenticated===true) {
      $this->response_stack['username'] = $this->User->username;
      $this->response_stack['token'] = $this->student_model->get_login_token();
      $this->response_stack['forename'] = $this->student_model->Forename;
      $this->response_stack['hall'] = $this->User->hall_abbr;
      $this->response_stack['type'] = $this->User->type;
      $this->response_stack['privileges'] = $this->User->privileges;

      $usertype_arr = $this->User->type;
      $this->response_stack['usertype'] = 'STUDENT';
      if($usertype_arr['is_warden']==true) {
        $this->response_stack['usertype'] = 'WARDEN';
      }
      if($usertype_arr['is_senior']==true) {
        $this->response_stack['usertype'] = 'HALLSENIOR';
      }
      if($this->User->privileges['is_scanner']==true) {
        $this->response_stack['usertype'] = 'BARCODEACCESS';
      }
      if($usertype_arr['is_supervisor']==true) {
        $this->response_stack['usertype'] = 'SUPERVISOR';
      }
      return true;
    }
    else { return false; }
  }
    
  public function submit_defect() {
    // [] to be written
    $request_data = json_decode(file_get_contents("php://input"), true); // instead of json_decode, use parse_str?
    if(isset($request_data["location"]) && isset($request_data["description"])) {
      $location = $request_data["location"];
      $description = $request_data["description"];
      $description .= "\n<br />\n<br />\nDefect Report using eHalls app (G.S.)";
      $this->student_model->submit_defect($location, $description);
      $this->student_model->email_defect($location, $description);
      $this->response_stack['success'] = true;
    } else { throw new Exception("Location and/or Description error ", 404); }
  }

  public function get_request() {
    // Interface declares this must be implemented
    return $this->student_model->Forename . ' ' . $this->student_model->Surname; 
  }


}
?>
