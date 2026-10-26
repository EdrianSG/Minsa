<?php
/**
 * API Endpoint: /api/auth/login.php
 * Sistema de Gestión de RR.HH. - Hospital Distrital de Pacasmayo
 * 
 * Procesa la autenticación de usuarios mediante POST JSON o formulario
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'ok' => false,
        'mensaje' => 'Método no permitido. Utilice POST.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Obtener parámetros desde JSON o POST tradicional
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$identificador = trim($input['identificador'] ?? $input['usuario'] ?? $input['username'] ?? $input['dni'] ?? '');
$password      = $input['password'] ?? $input['clave'] ?? $input['contrasena'] ?? '';
$redirect      = trim($input['redirect'] ?? 'index.php');

// Validar entrada
if ($identificador === '' || $password === '') {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'mensaje' => 'Debe ingresar su usuario o DNI y contraseña.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Ejecutar autenticación
$resultado = Auth::login($identificador, $password);

if ($resultado['ok']) {
    // Sanitizar URL de redirección interna
    if (!preg_match('/^index\.php(?:\?[^\r\n]*)?$/D', $redirect)) {
        $redirect = 'index.php';
    }

    echo json_encode([
        'ok'           => true,
        'mensaje'      => $resultado['mensaje'],
        'redirect_url' => $redirect,
        'usuario'      => $resultado['usuario']
    ], JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(401);
    echo json_encode([
        'ok'      => false,
        'mensaje' => $resultado['mensaje']
    ], JSON_UNESCAPED_UNICODE);
}
