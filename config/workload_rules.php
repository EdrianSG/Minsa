<?php
/**
 * Configuración de Reglas de Carga Horaria Laboral (MINSA / Hospital Distrital de Pacasmayo)
 * 
 * Centraliza los parámetros laborales por tipo/régimen de personal sin quemar valores en el código.
 * Puede ser alimentado o extendido posteriormente desde una tabla de parámetros en BD.
 */

class WorkloadRules {
    /**
     * Reglas para Personal Asistencial (Médicos, Enfermeros, Obstetras, Técnicos asistenciales, etc.)
     */
    public static function getAsistencialRules() {
        return [
            'tipo'                     => 'ASISTENCIAL',
            'nombre'                   => 'Personal Asistencial',
            'horas_semanales_objetivo' => 36.0,
            'horas_semanales_maximas'  => 48.0, // Máximo legal de seguridad asistencial
            'horas_mensuales_objetivo' => 150.0, // Meta referencial MINSA
            'horas_mensuales_maximas'  => null,  // NULL = No bloquea por superar 150h
            'descanso_minimo_horas'    => 12.0,  // Configurable futuro
            'max_turnos_consecutivos'  => null,
            'max_turnos_nocturnos'     => null
        ];
    }

    /**
     * Reglas para Personal Administrativo (Administración, RR.HH., Informática, etc.)
     */
    public static function getAdministrativoRules() {
        return [
            'tipo'                     => 'ADMINISTRATIVO',
            'nombre'                   => 'Personal Administrativo',
            'horas_semanales_objetivo' => 40.0,
            'horas_semanales_maximas'  => 44.0, // Jornada legal administrativa estándar
            'horas_mensuales_objetivo' => null, // No aplica regla rígida de 150h asistencial
            'horas_mensuales_maximas'  => null,
            'descanso_minimo_horas'    => 12.0,
            'max_turnos_consecutivos'  => 6,
            'max_turnos_nocturnos'     => 0
        ];
    }

    /**
     * Determina el régimen a partir de la información del cargo, profesión o área del trabajador
     */
    public static function detectRegime($cargoNombre = '', $servicioNombre = '', $areaNombre = '') {
        $text = mb_strtolower($cargoNombre . ' ' . $servicioNombre . ' ' . $areaNombre, 'UTF-8');
        
        $adminKeywords = [
            'admin', 'contab', 'informát', 'sistemas', 'secretar', 'recursos humanos',
            'rrhh', 'asistente', 'abogado', 'legal', 'logística', 'tesorería', 'caja',
            'estadística', 'digitador', 'planeamiento', 'director'
        ];

        foreach ($adminKeywords as $kw) {
            if (mb_strpos($text, $kw) !== false) {
                return 'ADMINISTRATIVO';
            }
        }

        return 'ASISTENCIAL';
    }

    /**
     * Obtiene el conjunto de reglas para un trabajador dado
     */
    public static function getRulesForWorker($cargoNombre = '', $servicioNombre = '', $areaNombre = '') {
        $regime = self::detectRegime($cargoNombre, $servicioNombre, $areaNombre);
        if ($regime === 'ADMINISTRATIVO') {
            return self::getAdministrativoRules();
        }
        return self::getAsistencialRules();
    }
}
