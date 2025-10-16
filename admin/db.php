<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "myDB";

if(session_status() === PHP_SESSION_NONE){
    session_start();
}

$conn = new mysqli($servername, $username, $password);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if ($conn->query($sql) !== TRUE) {
    die("Error creating database: " . $conn->error);
}
$conn->close();

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection to database failed: " . $conn->connect_error);
}

$table = "CREATE TABLE IF NOT EXISTS tbl_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  email VARCHAR(191) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  designation VARCHAR(50),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($table) === false) {
    die("Error creating table: " . $conn->error);
  
} 

