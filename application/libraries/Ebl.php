<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Ebl {

    public function __construct($param = array()) {}

    public function index(){

    }

    public function load(){
        include_once APPPATH . '/third_party/ebl/configuration.php';
        include_once APPPATH . '/third_party/ebl/skypay.php';

        return new skypay($configArray);
    }
}
