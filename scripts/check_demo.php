<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=newtiffa_timesheet', 'root', '');
$stmt = $pdo->query('SELECT id, first_name, last_name, email, employee_code, active FROM users WHERE id = 1532');
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row) {
    print_r($row);
} else {
    echo "User ID 1532 not found\n";
    // Show max ID
    $max = $pdo->query('SELECT MAX(id) as m FROM users')->fetch(PDO::FETCH_ASSOC);
    echo "Max user ID: " . $max['m'] . "\n";
}
