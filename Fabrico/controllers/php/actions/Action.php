<?php

    inject(array(
        "tools/view.php",
        "presenters/Presenter.php"
    ));

    /**
    * @package Fabrico\Controllers\Actions
    */
    class Action {
    
        protected $controller;
        protected $model;
        protected $fields = array();
        protected $req;
        protected $res;
        
        public $events;
        
        public function __construct($router) {
            $this->controller = $router->matchedRule->controller;
            $this->model = $router->matchedRule->model;
            $this->events = (object) array();
        }
        public function run($req, $res) {
            $this->req = $req;
            $this->res = $res;
        }
        public function __toString() {
            return "Action";
        }
        public function view($template, $data = array()) {
            $searchIn = array();
            $searchIn []= $this->model->name."/".$this;
            $searchIn []= $this->controller."/".$this;
            $searchIn []= ViewConfig::$searchIn[count(ViewConfig::$searchIn)-1]."/".$this;
            return view($template, $data, $searchIn);
        }
        protected function getPresenter($field) {
            $properties = array(
                "controller" => $this->controller,
                "model" => $this->model,
                "req" => $this->req,
                "res" => $this->res
            );
            return PresenterFactory::get($field, $properties);
        }
    
    }

?>