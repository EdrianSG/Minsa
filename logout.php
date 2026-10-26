<?php
/**
 * Script de Cierre de Sesión Seguro
 * Hospital Distrital de Pacasmayo
 */

require_once __DIR__ . '/includes/auth.php';

Auth::logout();

header('Location: login.php?msg=logout');
exit;
