<?php
header('Content-type:application/json;charset=utf-8');
header('X-PHP-Response-Code: '.$e->getCode(), true, $e->getCode());
echo json_encode(array(
  "login" => @$User->authenticated || false,
  "error_message" => $e->getMessage(),
  "error" => true
));
?>
