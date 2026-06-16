<?php 
class Tester
{

    public static function get()
    {
        Flight::json("respuesta API GET");
    }
    
    public static function post()
    {
        Flight::json("respuesta API POST");
    }
    
}
