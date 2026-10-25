<?php

header('Content-Type: application/json');

echo json_encode([
    'success' => true,
    'message' => 'CAE AAR API connected successfully',
    'server_time' => date('Y-m-d H:i:s')
]);