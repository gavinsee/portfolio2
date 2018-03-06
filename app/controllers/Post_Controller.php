<?php

// keep it simple for now
require_once(dirname(__DIR__) . '/models/post.php');
require_once(dirname(__DIR__) . '/core/libraries/Controllers.php');


class Post_Controller  extends Generic_Auth_Controller {
  public $post_model;

  public function __construct($action, Authentication $ProvidedUser) { // POD = PHP Data Object
    parent::__construct();
    $this->User = $ProvidedUser;
    $this->post_model = new Post($ProvidedUser);
    if($action == 'get') {
      $this->get_post();
    }
  }
    
  public function get_post() {
    $all_post = $this->post_model->get_all_post();
    $this->response_stack['post'] = $all_post;
  }

  public function get_request() {
    $this->get_post();
  }

}
?>
