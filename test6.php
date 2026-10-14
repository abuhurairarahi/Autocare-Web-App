<?php
$_GET['action'] = 'approve';
$input = json_encode([
    'request_id' => 3,
    'mechanic_id' => 1
]);
file_put_contents('php://memory', $input);
// Wait, we can't easily mock file_get_contents('php://input') this way.
// Better to just modify the $_POST if possible, but the API uses php://input.
// Let's create a stream wrapper or just modify the script temporarily to accept $data.
