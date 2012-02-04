<?php

    inject(array(
        "pages/Page.php",
        "utils/view.php"
    ));

    /**
    * @package Fabrico\Controllers\Pages
    */
    class Home extends Page {
            
        public function __construct($router) {
            
        }
        public function run($req, $res) {
            $this->title = 'Welcome '.$req->fabrico->currentUser->username.'.';
            $this->pageTitle = 'Home';
            $this->response(view("home.html"), $req, $res);            
        }
    
    }

?>