<?php

require_once __DIR__ . '/../vendor/autoload.php';

$envPath = dirname(__DIR__);
if (file_exists($envPath . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable($envPath);
    $dotenv->load();
}

// Fallback to getenv() in case $_ENV is not populated
$dbHost = $_ENV['DB_HOST'] ?? getenv('DB_HOST');
$dbUser = $_ENV['DB_USER'] ?? getenv('DB_USER');
$dbPass = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD');
$dbName = $_ENV['DB_NAME'] ?? getenv('DB_NAME');

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
