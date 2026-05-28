<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3307;dbname=newtiffa_timesheet', 'root', '');
$rows = $pdo->query('SELECT id, user_code, device_id, target_phones, is_active FROM wa_config')->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);
