#!/usr/bin/env bash
# Servidor local con reutilización de conexiones ODBC, sin modificar /etc.
set -euo pipefail
project_root="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
source_odbc_dir="${ODBCSYSINI:-/etc}"
source_odbc_file="${ODBCINSTINI:-odbcinst.ini}"
runtime_odbc_dir="$(mktemp -d "${TMPDIR:-/tmp}/sgh-odbc.XXXXXXXX")"
trap 'rmdir "$runtime_odbc_dir" 2>/dev/null || true' EXIT
python3 - "$source_odbc_dir/$source_odbc_file" "$runtime_odbc_dir/odbcinst.ini" <<'PY'
import configparser
import os
import sys
config = configparser.ConfigParser(interpolation=None, strict=False)
config.optionxform = str
if not config.read(sys.argv[1]):
    raise SystemExit('No se encontró la configuración de unixODBC.')
drivers = [name for name in config.sections() if name.startswith('ODBC Driver ') and name.endswith(' for SQL Server')]
if not drivers:
    raise SystemExit('No se encontró el controlador Microsoft ODBC de SQL Server.')
if not config.has_section('ODBC'):
    config.add_section('ODBC')
config.set('ODBC', 'Pooling', 'Yes')
for driver in drivers:
    config.set(driver, 'CPTimeout', '120')
with open(sys.argv[2], 'x') as target:
    config.write(target)
os.chmod(sys.argv[2], 0o600)
PY
trap 'rm -f -- "$runtime_odbc_dir/odbcinst.ini"; rmdir -- "$runtime_odbc_dir"' EXIT
export ODBCSYSINI="$runtime_odbc_dir"
export ODBCINSTINI=odbcinst.ini
export PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-8}"
cd -- "$project_root"
php -S "${1:-127.0.0.1:8080}" router.php
