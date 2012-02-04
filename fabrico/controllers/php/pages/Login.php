<?php

    inject(array(
        "pages/Page.php",
        "utils/view.php"
    ));

    /**
    * @package Fabrico\Controllers\Pages
    */

    class Login extends Page {
        
        public function __construct($router) {
            
        }
        public function run($req, $res) {
            if($req->fabrico->access->isLogged($req)) {
                header("Location: ".$req->fabrico->paths->url);
                exit();
            }
            $res->send(view("layout.html", array(
                "javascript" => $req->fabrico->assets->get("javascript"),
                "stylesheet" => $req->fabrico->assets->get("css"),
                "title" => "Login",
                "pageTitle" => "Login",
                "mainNav" => "",
                "data" => view("login.html"),
                "error" => $req->fabrico->access->loginError != "" ? view("errormessage.html", array("text" => $req->fabrico->access->loginError), $this) : ""
            )));
        }
    
    }

?>