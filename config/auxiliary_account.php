<?php
/**
 * Configuración de la Cuenta Auxiliar Temporal (Modo Contingencia / Offline)
 * Hospital Distrital de Pacasmayo
 *
 * Permite acceder al sistema sin requerir conexión a Microsoft SQL Server,
 * operando con los respaldos y snapshots locales de resiliencia.
 */

$localConfig = is_file(__DIR__ . '/database.local.php') ? (include __DIR__ . '/database.local.php') : [];
if (!is_array($localConfig)) {
    $localConfig = [];
}

return [
    'enabled'              => true,
    // Usuario auxiliar principal
    'username'             => getenv('AUX_USER') ?: ($localConfig['aux_user'] ?? 'auxiliar'),
    // Clave auxiliar principal
    'password'             => getenv('AUX_PASS') ?: ($localConfig['aux_pass'] ?? 'Auxiliar2026!'),

    // Permitir acceso de emergencia con 'admin' cuando no hay conexión a SQL Server
    'allow_admin_fallback' => true,
    'admin_offline_pass'   => getenv('ADMIN_OFFLINE_PASS') ?: ($localConfig['admin_offline_pass'] ?? 'Admin2026!'),

    // Perfil institucional de la cuenta auxiliar
    'usuario_id'           => 999999,
    'personal_id'          => null,
    'nombre_corto'         => 'Admin Auxiliar',
    'nombre_completo'      => 'Administrador de Contingencia (Modo Auxiliar)',
    'cargo'                => 'Administrador de Emergencia / Respaldo',
    'rol'                  => 'ADMIN_RRHH',
    'rol_nombre'           => 'Administrador Auxiliar (Contingencia)',
    'servicio_id'          => null,
    'area'                 => 'Hospital Distrital de Pacasmayo',
    'servicio'             => 'Hospital Distrital de Pacasmayo (Contingencia)',
    'correo'               => 'contingencia@hospitalpacasmayo.gob.pe'
];
