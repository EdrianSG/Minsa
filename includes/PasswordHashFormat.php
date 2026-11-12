<?php
/** Formatos verificables por password_verify; no comprueba ni cambia la contraseña. */
final class PasswordHashFormat {
    public static function supported(mixed $hash): bool {
        if (!is_string($hash)) return false;
        if (password_get_info($hash)['algo'] !== null) return true;
        // PHP verifica también bcrypt de Python/otras bibliotecas ($2a$/$2b$),
        // aunque password_get_info los describa como unknown.
        return preg_match('/^\$2[aby]\$(?:0[4-9]|[12][0-9]|3[01])\$[.\/A-Za-z0-9]{53}$/D',$hash) === 1;
    }
}
