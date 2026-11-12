const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const Model = require('../assets/js/personal-relations.js');
const catalog = {
  departamentos: [{id:'10',nombre:'Departamento A',activo:1},{id:'20',nombre:'Departamento B',activo:1}],
  areas: [{id:'1',nombre:'LABORATORIO',departamento_id:'10',pmyo:true,operativa_pmyo:true,dotacion_origen:14,activo:1},
          {id:'2',nombre:'FARMACIA',departamento_id:'20',pmyo:true,activo:1},
          {id:'3',nombre:'ENFERMERIA',departamento_id:null,pmyo:true,activo:1}],
  servicios: [{id:'100',nombre:'Servicio A',area_id:'1',activo:1},{id:'200',nombre:'Servicio B',area_id:'2',activo:1}],
  cargos: [{id:'7',nombre:'BIOLOGO',areas_pmyo:['1'],profesion_propuesta_id:'11',especialidad_propuesta_id:'22',activo:1},
           {id:'8',nombre:'TEC ENFERMERIA',areas_pmyo:['2','3'],profesion_propuesta_id:'12',activo:1},
           {id:'9',nombre:'INTERNOS ENFERMERIA',areas_pmyo:['3'],formacion:true,profesion_propuesta_id:null,activo:1}],
  profesiones: [{id:'11',nombre:'Biología',activo:1},{id:'12',nombre:'Técnico en enfermería',activo:1},{id:'13',nombre:'Medicina humana',activo:1},{id:'14',nombre:'Registro anterior',activo:0}],
  especialidades: [{id:'21',nombre:'Pediatría',profesion_id:'13',activo:1},{id:'22',nombre:'Especialidad de prueba',profesion_id:'11',activo:1}],
  tipos_documento: [{id:'1',nombre:'Documento de prueba',activo:1}], sexos: [{id:'1',nombre:'Dato de prueba',activo:1}], tipos_contrato: [{id:'1',nombre:'Contrato de prueba',activo:1}]
};
let count=0;
function check(ok,why){assert.ok(ok,why);count++;}
const model = new Model(catalog);
check(Object.values(model.state).every(value=>value===''),'Sin datos no debe elegir la primera opción.');
const persisted = {personal_id:42,departamento_id:'10',area_id:'1',servicio_id:'100',cargo_id:'7',profesion_id:'11',especialidad_id:'22'};
model.load(persisted);
check(model.state.area_id==='1' && model.state.servicio_id==='100','La carga conserva área y servicio.');
check(model.state.profesion_id==='11' && model.state.especialidad_id==='22','La carga conserva profesión y especialidad.');
model.setDepartment('20');
check(model.state.area_id==='' && model.state.servicio_id==='','Cambiar departamento elimina hijos incompatibles.');
check(model.state.profesion_id==='11','Cambiar departamento no inventa otra profesión.');
model.load(persisted); model.setArea('2');
check(model.state.departamento_id==='20' && model.state.servicio_id==='','El área carga su departamento real y retira un servicio ajeno.');
check(model.state.profesion_id==='11','Cambiar de área no cambia el título profesional.');
check(model.profiles().some(row=>row.id==='8'),'El filtro permite técnico de enfermería en farmacia según el mapa.');
model.state.servicio_id='100';check(model.issues().length===1,'Un servicio ajeno se detecta.');
model.setArea('3');check(model.state.departamento_id==='','Área funcional sin departamento no recibe uno supuesto.');
model.setProfession('12');check(model.state.especialidad_id==='','Cambiar profesión retira una especialidad ajena.');
model.load({cargo_id:'9'});check(!model.applyProfessionalProposal() && model.state.profesion_id==='','Internado no acredita una profesión.');
model.load({cargo_id:'7'});check(model.state.profesion_id==='','Elegir un cargo no aplica la profesión sin confirmación.');
check(model.applyProfessionalProposal() && model.state.profesion_id==='11' && model.state.especialidad_id==='22','La propuesta se aplica solo al solicitarla.');
model.load({});const origin=model.applyOrigin(' laboratorio ','BIOLOGO');
check(origin.area && origin.profile && model.state.area_id==='1' && model.state.cargo_id==='7','Origen coincide de forma exacta normalizando espacios y mayúsculas.');
check(model.state.profesion_id==='','Usar origen no acredita una profesión.');
check(!model.applyOrigin('LABORATORI','BIOLOGA').area,'No se usa coincidencia aproximada para homologar áreas.');
model.load({profesion_id:'13',especialidad_id:'22'});check(model.issues().length===1,'Se advierte la especialidad de otra profesión.');

// Ejecuta el controlador real con selectores que rechazan valores inexistentes, como el navegador.
class Element {
  constructor(id,tag='DIV',type=''){this.id=id;this.tagName=tag;this.type=type;this.children=[];this._value='';this.checked=false;this.disabled=false;this.hidden=false;this.textContent='';this.listeners={};}
  get options(){return this.children.flatMap(x=>x.tagName==='OPTGROUP'?x.options:[x]);}
  get value(){return this._value;}
  set value(value){const str=String(value??'');this._value=this.tagName==='SELECT'&&!this.options.some(x=>x.value===str)?'':str;}
  appendChild(el){this.children.push(el);return el;}
  replaceChildren(...els){this.children=els;this._value=this.options[0]?.value||'';}
  add(el){this.appendChild(el);}
  addEventListener(name,fn){this.listeners[name]=fn;}
}
class Option extends Element{constructor(text,value){super('', 'OPTION');this.textContent=text;this._value=String(value);}}
const elements = new Map();
function el(id,tag='DIV',type=''){const item=new Element(id,tag,type);elements.set(id,item);return item;}
const form=el('formNewWorker','FORM'), fields=new Map();
const selects={tipo_documento_id:'newWorkerTipoDoc',sexo_id:'newWorkerSexo',tipo_contrato_id:'newWorkerTipoContrato',departamento_id:'newWorkerDepartamento',area_id:'newWorkerArea',servicio_id:'newWorkerServicio',cargo_id:'newWorkerCargo',profesion_id:'newWorkerProfesion',especialidad_id:'newWorkerEspecialidad',activo:'activo',accion_asignacion:'workerAssignmentAction'};
for(const [name,id] of Object.entries(selects))fields.set(name,el(id,'SELECT'));
for(const name of ['nombre_completo_original','nombres','apellido_paterno','apellido_materno','fecha_ingreso','asignacion_actual_id','contrato_id','fecha_asignacion','fecha_fin_asignacion','motivo_asignacion']) fields.set(name,el(name,'INPUT'));
for(const id of ['workerShowAllProfiles','workerContractFields','workerEditorTitle','btnSubmitNewWorkerText','workerOriginCard','workerOriginName','workerOriginBiostar','workerOriginArea','workerOriginProfile','workerUseOrigin','workerEditorError','workerAreaHint','workerProfessionHint','workerApplyProfession','workerAssignmentStart','workerAssignmentEnd','workerAssignmentEndWrap']) if(!elements.has(id))el(id);
form.elements={namedItem:name=>fields.get(name)||null};
form.reset=()=>{for(const field of fields.values())field.value='';for(const field of elements.values())field.checked=false;};
fields.get('activo').replaceChildren(new Option('Pendiente',''),new Option('Activo','1'),new Option('Inactivo','0'));
fields.get('accion_asignacion').replaceChildren(...['corregir','conservar','trasladar','finalizar'].map(x=>new Option(x,x)));
const document={getElementById:id=>elements.get(id)||null,addEventListener(){},createElement:tag=>new Element('',tag.toUpperCase())};
const context=vm.createContext({document,window:{currentUser:{rol:'RRHH_ADMIN'}},PersonalRelationsModel:Model,Option,console,Date,Map,setTimeout,clearTimeout,fixture:catalog});
vm.runInContext(fs.readFileSync('assets/js/pages/personal.js','utf8'),context);
vm.runInContext('workerCatalogData=fixture; workerRelationModel=new PersonalRelationsModel(fixture); editingWorkerId=42;',context);
context.populateWorkerEditor({...persisted,activo:0,asignacion_actual_id:55,nombre_completo_original:'Dato original de prueba',fecha_asignacion:'2026-01-01'});
check(fields.get('departamento_id').value==='10' && fields.get('area_id').value==='1','El editor real carga padre e hijo sin borrarlos.');
check(fields.get('servicio_id').value==='100','El editor real conserva el servicio.');
check(fields.get('profesion_id').value==='11' && fields.get('especialidad_id').value==='22','El editor real conserva la especialidad al abrir la ficha.');
check(fields.get('activo').value==='0','Inactivo no se convierte en desconocido al editar.');
check(fields.get('asignacion_actual_id').value==='55','La edición conserva la versión de asignación.');
check(fields.get('accion_asignacion').value==='corregir','La corrección no depende de una casilla oculta.');
context.populateWorkerEditor({profesion_id:'14',activo:null});
check(fields.get('profesion_id').value==='14','Una profesión inactiva ya vinculada no desaparece al abrir.');
check(fields.get('activo').value==='','Un estado desconocido no se convierte en activo.');
check(fields.get('asignacion_actual_id').value==='0','La ficha sin asignación lleva su versión vacía explícita.');
context.populateWorkerEditor({});check(fields.get('area_id').value==='' && fields.get('profesion_id').value==='','Ficha vacía no toma valores del catálogo automáticamente.');
console.log(`OK: ${count} comprobaciones del modelo y el editor real de relaciones PMYO (DOM simulado).`);
