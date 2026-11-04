<?php
/**
 * API 404 handler
 * Returns consistent JSON 404 response for nonexistent API endpoints
 */

require_once __DIR__ . '/../../src/Response.php';

Response::notFound('API endpoint not found');
