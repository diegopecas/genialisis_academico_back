<?php
require 'vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

echo "Probando JWT...\n";

$key = 'test_key';
$payload = ['user' => 'test'];

$token = JWT::encode($payload, $key, 'HS256');
echo "Token: " . $token . "\n";

$decoded = JWT::decode($token, new Key($key, 'HS256'));
echo "Decoded: " . json_encode($decoded) . "\n";

echo "¡JWT funciona correctamente!";