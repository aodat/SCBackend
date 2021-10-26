<?php 
namespace Libs;
class DHL
{
    private $config;
    function __construct() {
        $this->config = [];
    }
    public function createPickup(){}
    public function cancelPickup(){}
    public function printLabel(){}
    public function createShipment(){}
    public function shipmentArray(){}
    public function bindJsonFile(){}
}