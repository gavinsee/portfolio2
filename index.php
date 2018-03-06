<?php

ini_set('display_errors',1);
error_reporting(E_ALL);
require_once('app/core/libraries/Authentication.php');

// Create list of non-authentication controllers
$non_auth = array("halls_info");

// set default controller and action
$controller = 'Post';
$action     = 'get';

// prepare to route the request
$controller = @$_GET["controller"];
$action = @$_GET["action"];


// is controller in $non_auth?
if(!in_array($controller, $non_auth, TRUE)) {
  $controller = $controller . '_Controller';
  try {
    $User = new Authentication();
    // check if the controller exits
    check_controller($controller);
    require_once('app/controllers/' .$controller.'.php');
    $controller = new $controller($action, $User);
    require_once('app/views/default.php');
  }
  catch(Exception $e) {
    require_once('app/views/error.php');
  } 
}


function check_controller($c) {
  if(!file_exists('app/controllers/' . $c . '.php')) {
    throw new Exception("invalid controller", 400);
  }
}

?>
