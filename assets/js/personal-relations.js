/** Estado de relaciones: IDs reales, sin inferencias de texto ni asignaciones profesionales implícitas. */
(function (root) {
  class PersonalRelationsModel {
    constructor(catalog) { this.catalog = catalog; this.load({}); }
    static id(value) { return value === null || value === undefined ? '' : String(value); }
    static key(value) { return String(value || '').trim().replace(/\s+/g, ' ').toLocaleUpperCase('es'); }
    row(collection, id) { return (this.catalog[collection] || []).find(row => String(row.id) === String(id)); }
    load(data) {
      this.state = {};
      for (const key of ['departamento_id','area_id','servicio_id','cargo_id','profesion_id','especialidad_id']) this.state[key] = PersonalRelationsModel.id(data[key]);
      const area = this.row('areas', this.state.area_id);
      if (area) this.state.departamento_id = PersonalRelationsModel.id(area.departamento_id);
      return this.state;
    }
    setDepartment(id) {
      this.state.departamento_id = PersonalRelationsModel.id(id);
      const area = this.row('areas', this.state.area_id);
      if (area && PersonalRelationsModel.id(area.departamento_id) !== this.state.departamento_id) { this.state.area_id = ''; this.state.servicio_id = ''; }
    }
    setArea(id) {
      this.state.area_id = PersonalRelationsModel.id(id);
      const area = this.row('areas', id);
      this.state.departamento_id = PersonalRelationsModel.id(area?.departamento_id);
      const service = this.row('servicios', this.state.servicio_id);
      if (!service || String(service.area_id) !== this.state.area_id) this.state.servicio_id = '';
    }
    setProfession(id) {
      this.state.profesion_id = PersonalRelationsModel.id(id);
      const specialty = this.row('especialidades', this.state.especialidad_id);
      if (!specialty || String(specialty.profesion_id) !== this.state.profesion_id) this.state.especialidad_id = '';
    }
    areas() { return (this.catalog.areas || []).filter(row => !this.state.departamento_id || String(row.departamento_id) === this.state.departamento_id); }
    services() { return (this.catalog.servicios || []).filter(row => this.state.area_id && String(row.area_id) === this.state.area_id); }
    specialties() { return (this.catalog.especialidades || []).filter(row => this.state.profesion_id && String(row.profesion_id) === this.state.profesion_id); }
    profiles(all = false) {
      const area = this.row('areas', this.state.area_id);
      return (this.catalog.cargos || []).filter(row => all || !area?.pmyo || (row.areas_pmyo || []).some(id => String(id) === this.state.area_id) || String(row.id) === this.state.cargo_id);
    }
    proposal() { return this.row('cargos', this.state.cargo_id) || null; }
    applyProfessionalProposal() {
      const proposal = this.proposal();
      if (!proposal?.profesion_propuesta_id || !this.row('profesiones', proposal.profesion_propuesta_id)) return false;
      this.setProfession(proposal.profesion_propuesta_id);
      const specialty = this.row('especialidades', proposal.especialidad_propuesta_id);
      this.state.especialidad_id = specialty && String(specialty.profesion_id) === this.state.profesion_id ? String(specialty.id) : '';
      return true;
    }
    applyOrigin(areaName, profileName) {
      const areas = (this.catalog.areas || []).filter(row => PersonalRelationsModel.key(row.nombre) === PersonalRelationsModel.key(areaName));
      const profiles = (this.catalog.cargos || []).filter(row => PersonalRelationsModel.key(row.nombre) === PersonalRelationsModel.key(profileName));
      if (areas.length === 1) this.setArea(areas[0].id);
      if (profiles.length === 1) this.state.cargo_id = String(profiles[0].id);
      return {area: areas.length === 1, profile: profiles.length === 1};
    }
    issues() {
      const issues = [], service = this.row('servicios', this.state.servicio_id), specialty = this.row('especialidades', this.state.especialidad_id);
      if (this.state.servicio_id && (!service || String(service.area_id) !== this.state.area_id)) issues.push('El servicio debe pertenecer al área seleccionada.');
      if (this.state.especialidad_id && (!specialty || String(specialty.profesion_id) !== this.state.profesion_id)) issues.push('La especialidad debe pertenecer a la profesión seleccionada.');
      return issues;
    }
  }
  root.PersonalRelationsModel = PersonalRelationsModel;
  if (typeof module !== 'undefined') module.exports = PersonalRelationsModel;
})(typeof window !== 'undefined' ? window : globalThis);
