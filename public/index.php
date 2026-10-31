<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
require __DIR__ . '/../src/Store.php';
require __DIR__ . '/../src/Api.php';
try { (new Api(new Store(__DIR__ . '/../data')))->run(); }
catch (Throwable $e) { http_response_code(500); echo json_encode(['error'=>'Erro interno da API']); error_log((string)$e); }
