<?php
/**
 * Early CORS handler for Browser Bridge REST API.
 * This file is loaded via require_once in main.php, BEFORE WordPress routing.
 * It handles OPTIONS preflight requests at the earliest possible point.
 */

// Only handle requests to our REST API endpoints
$request_uri = $_SERVER['REQUEST_URI'] ?? '';
if (strpos($request_uri, '/wp-json/ssp/') !== false) {
    // Send CORS headers immediately
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-SSP-Bridge-Token');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');

    // Handle OPTIONS preflight — return immediately
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('HTTP/1.1 204 No Content');
        exit;
    }
}
