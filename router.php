<?php
/** Servidor de desarrollo: php -S 0.0.0.0:8080 router.php */
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$segments = explode('/', $path);
foreach ($segments as $segment) {
    if ($segment === '..' || str_starts_with($segment, '.') || str_contains($segment, '\\')) {
        http_response_code(404); exit;
    }
}
$allowed = $path === '/' || in_array($path, ['/index.php','/login.php','/logout.php'], true)
    || (str_starts_with($path, '/api/') && str_ends_with($path, '.php'))
    || (str_starts_with($path, '/assets/') && preg_match('/\.(css|js|svg|png|jpe?g|gif|webp|ico|woff2?|ttf)$/iD', $path));
if (!$allowed) { http_response_code(404); exit; }
return false;
