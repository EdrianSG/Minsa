<?php
require_once __DIR__.'/../../includes/api_bootstrap.php';
apiGuard('personal',['POST'],['JEFE_RRHH']);
require_once __DIR__.'/../../includes/PersonalRepository.php';
header('Content-Type: application/json; charset=utf-8');
$data=json_decode(file_get_contents('php://input'),true) ?: $_POST;
if (!is_array($data)) apiError(400,'Solicitud inválida.');
// Solo registrar contrato cuando se ha proporcionado información contractual real.
$data['registrar_contrato']=!empty($data['tipo_contrato_id']) || !empty($data['numero_contrato']) || !empty($data['contrato_inicio']) || !empty($data['contrato_fin']);
try {
    echo json_encode(PersonalRepository::save(apiRequireDatabase(),$data,(int)Auth::getCurrentUser()['usuario_id']),JSON_UNESCAPED_UNICODE);
} catch (PersonalRelationConflict $e) { apiError(409,$e->getMessage()); }
catch (InvalidArgumentException $e) { apiError(400,$e->getMessage()); }
catch (PDOException $e) { error_log($e->getMessage()); apiError(409,'No se guardó la ficha. Verifique documentos, códigos duplicados y referencias.'); }
catch (Throwable $e) { error_log($e->getMessage()); apiError(500,'No se pudo guardar la ficha; cambios revertidos.'); }
