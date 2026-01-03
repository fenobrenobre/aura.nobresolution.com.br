export async function fetchPatients(searchTerm = '') {
    const r = await this.apiRequest('getPatients', { search: searchTerm }, false, 'GET');
    if (r.success) {
        this.patients = r.patients;
        // Atualiza checklist de aniversários sempre que buscar pacientes
        if (this.updateBirthdayChecklist) {
            this.updateBirthdayChecklist();
        }
    } else {
        this.patients = [];
    }
}

export function searchPatients() {
    if (this.searchTimeout) clearTimeout(this.searchTimeout);
    this.searchTimeout = setTimeout(() => {
        this.fetchPatients(this.patientSearchTerm);
    }, 300);
}

export function openPatientModal(patient) {
    this.editingPatient = patient ? { ...this.getNewPatientTemplate(), ...patient } : this.getNewPatientTemplate();
    this.patientPhotoPreview = patient ? patient.photo : null;
    this.patientPhotoFile = null;
    this.activePatientTab = 'main';
    
    // ** AJUSTE: Usa o valor do próprio campo para a busca inicial **
    this.patientReferredBySearchQuery = patient ? patient.referred_by : '';
    this.patientReferredBySearchResults = [];
    
    this.patientResponsibleSearchQuery = patient ? patient.responsible_name : '';
    this.patientResponsibleSearchResults = [];
    this.patientFatherSearchQuery = patient ? patient.parentage_father : '';
    this.patientFatherSearchResults = [];
    this.patientMotherSearchQuery = patient ? patient.parentage_mother : '';
    this.patientMotherSearchResults = [];

    if (!this.customFieldOptions.length) this.fetchPublicConfig();
    this.showModal('patient-modal');
}

export function getNewPatientTemplate() {
    return {
        id: null,
        name: '',
        cpf: '',
        rg: '',
        birthdate: '',
        phone: '',
        phone2: '',
        email: '',
        gender: null,
        marital_status: null,
        zip_code: '',
        street: '',
        street_number: '',
        neighborhood: '',
        city: '',
        state: '',
        address_complement: '',
        health_insurance: '',
        insurance_number: '',
        health_insurance_odont: '',
        insurance_number_odont: '',
        nickname: '',
        instagram: '',
        birth_place: '',
        responsible_name: '',
        responsible_cpf: '',
        parentage_father: '',
        parentage_mother: '',
        referred_by: ''
    };
}

export async function savePatient(dataObject, isClinicalSave = false, saveMeasures = false) {
    const fd = new FormData();
    const patientFields = ['id', 'name', 'nickname', 'gender', 'birthdate', 'birth_place', 'cpf', 'rg', 'health_insurance', 'insurance_number', 'health_insurance_odont', 'insurance_number_odont', 'marital_status', 'responsible_name', 'responsible_cpf', 'parentage_father', 'parentage_mother', 'referred_by', 'phone', 'phone2', 'email', 'instagram', 'zip_code', 'street', 'street_number', 'address_complement', 'neighborhood', 'city', 'state'];
    
    const currentTab = this.activePatientTab;

    if (dataObject.id) {
         fd.append('id', dataObject.id);
    }

    for (const key in dataObject) {
        if (key === 'id' || key === 'photo' || key === 'clinical_history' || key === 'isDocumentInvalid' || key === 'patientAppointments' || key === 'patientServices' || key === 'patientReceipts' || key === 'prescriptions') continue;
        
        if (key === 'patientReferredBySearchQuery' || key === 'patientReferredBySearchResults') continue;
        if (key === 'patientResponsibleSearchQuery' || key === 'patientResponsibleSearchResults') continue;
        if (key === 'patientFatherSearchQuery' || key === 'patientFatherSearchResults') continue;
        if (key === 'patientMotherSearchQuery' || key === 'patientMotherSearchResults') continue;

        // Se for clínico, processa measures explicitamente abaixo apenas se saveMeasures for true
        if (key.startsWith('measure_') && (!isClinicalSave || !saveMeasures)) continue;

        const value = dataObject[key];
        let apiFieldName = key;

        if (key === 'anamnesisContent') apiFieldName = 'anamnesis';

        const isPatientField = patientFields.includes(key);
        const isAnamnesisField = (key === 'anamnesisContent');
        const isMeasureField = key.startsWith('measure_');

        if (value !== null && value !== undefined) {
            if (!isClinicalSave && isPatientField) {
                 fd.append(apiFieldName, value);
            } else if (isClinicalSave) {
                if (isAnamnesisField) {
                    fd.append(apiFieldName, value);
                } else if (isMeasureField && saveMeasures) {
                    fd.append(apiFieldName, value);
                }
            }
        }
    }

    if (isClinicalSave) {
        // ** LÓGICA UNIFICADA DE MEDIDAS **
        // Só processa texto automático de medidas se a ação for "Salvar Medidas"
        if (saveMeasures) {
            let measuresText = [];
            const d = dataObject;
            
            if (d.measure_height) measuresText.push(`Altura ${d.measure_height} cm`);
            if (d.measure_weight) measuresText.push(`Peso ${d.measure_weight} Kg`);
            if (d.measure_abd_circ) measuresText.push(`Circunferência Abdominal ${d.measure_abd_circ} cm`);
            if (d.measure_pa) measuresText.push(`Pressão Arterial ${d.measure_pa} mmHg`);
            if (d.measure_fr) measuresText.push(`Frequência respiratória ${d.measure_fr} ipm`);
            if (d.measure_fc) measuresText.push(`Frequência Cardíaca ${d.measure_fc} bpm`);
            if (d.measure_gc) measuresText.push(`Glicemia Capilar ${d.measure_gc} mg/dL`);

            let finalEvolutionText = this.newEvolutionEntry ? this.newEvolutionEntry.trim() : '';

            if (measuresText.length > 0) {
                const measuresString = `Dados aferidos nesta consulta: ${measuresText.join(', ')}.`;
                // Evita duplicar
                if (!finalEvolutionText.includes(measuresString)) {
                    if (finalEvolutionText !== '') finalEvolutionText += "\n\n";
                    finalEvolutionText += measuresString;
                }
            }

            if (finalEvolutionText !== '') {
                fd.append('new_evolution_entry', finalEvolutionText);
            }
        } else {
            // Se NÃO for salvar medidas, salva apenas o texto de evolução digitado manualmente
            if (this.newEvolutionEntry && this.newEvolutionEntry.trim() !== '') {
                fd.append('new_evolution_entry', this.newEvolutionEntry.trim());
            }
        }
        
        if (this.newExamEntry && this.newExamEntry.trim() !== '') {
            fd.append('new_exam_entry', this.newExamEntry.trim());
        }
    }

    if (!isClinicalSave && this.patientPhotoFile) {
        fd.append('photo', this.patientPhotoFile);
    }
    
    if (this.currentUser.isAdmin) {
        fd.append('adminId', this.currentUser.id);
        if (dataObject.user_id) fd.append('userId', dataObject.user_id);
    } else {
        fd.append('userId', this.currentUser.id);
    }

    const r = await this.apiRequest('savePatient', fd, true);

    if (r.success && r.data) {
        this.editingPatient.id = r.data.id; 

        if (isClinicalSave) {
            if (saveMeasures) {
                this.showToast('Sucesso!', 'Medidas salvas e registradas na evolução.', 'success');
            } else {
                this.showToast('Sucesso!', 'Dados clínicos salvos.', 'success');
            }
            
            const updatedPatientResponse = await this.apiRequest('getPatientDetails', { patientId: r.data.id }, false, 'GET');
            
            if (updatedPatientResponse.success) {
                const serverData = updatedPatientResponse.patient;
                
                this.editingClinicalData.clinical_history = serverData.clinical_history;
                const latestAnamnesis = serverData.clinical_history.find(e => e.entry_type === 'ANAMNESE');
                this.editingClinicalData.anamnesisContent = latestAnamnesis ? (latestAnamnesis.content ?? '') : '';
                
                this.editingClinicalData.measure_height = serverData.measure_height;
                this.editingClinicalData.measure_weight = serverData.measure_weight;
                this.editingClinicalData.measure_abd_circ = serverData.measure_abd_circ;
                this.editingClinicalData.measure_pa = serverData.measure_pa;
                this.editingClinicalData.measure_fr = serverData.measure_fr;
                this.editingClinicalData.measure_fc = serverData.measure_fc;
                this.editingClinicalData.measure_gc = serverData.measure_gc;

            } else {
                 this.showToast('Aviso', 'Dados salvos, mas erro ao recarregar histórico.', 'error');
            }
            this.newEvolutionEntry = '';
            this.newExamEntry = '';
        } else {
            this.showToast('Sucesso!', `${this.labels.patient} salvo com sucesso!`, 'success');
            if (!this.patients.some(p => p.id === r.data.id)) {
                this.fetchPatients();
            } else {
                const index = this.patients.findIndex(p => p.id === r.data.id);
                if (index !== -1) {
                    this.patients[index] = { ...this.patients[index], ...r.data };
                }
            }
            if (currentTab === 'main') this.activePatientTab = 'docs';
            else if (currentTab === 'docs') this.activePatientTab = 'contact';
            else if (currentTab === 'contact') this.hideModal('patient-modal');
        }
    }
}

export async function saveMeasurements() {
    await this.savePatient(this.editingClinicalData, true, true);
}

export function formatPA() {
    let val = this.editingClinicalData.measure_pa;
    if (!val) return;
    val = val.replace(/[^0-9]/g, '');
    if (val.length >= 2 && val.length <= 7) {
         if (!this.editingClinicalData.measure_pa.includes('/')) {
             if (val.length === 4) this.editingClinicalData.measure_pa = val.slice(0,2) + '/' + val.slice(2);
             else if (val.length === 5) this.editingClinicalData.measure_pa = val.slice(0,3) + '/' + val.slice(3);
             else if (val.length === 6) this.editingClinicalData.measure_pa = val.slice(0,3) + '/' + val.slice(3);
         }
    }
}

export async function openClinicalModal(patient, targetTab = 'anamnesis') {
    if (!patient || !patient.id) return; 

    const r = await this.apiRequest('getPatientDetails', { patientId: patient.id }, false, 'GET');
    if (r.success) {
        this.editingClinicalData = r.patient;
        const latestAnamnesis = r.patient.clinical_history.find(e => e.entry_type === 'ANAMNESE');
        this.editingClinicalData.anamnesisContent = latestAnamnesis ? (latestAnamnesis.content ?? '') : '';
        
        if (this.editingClinicalData.measure_height === null) this.editingClinicalData.measure_height = '';
        if (this.editingClinicalData.measure_weight === null) this.editingClinicalData.measure_weight = '';
        if (this.editingClinicalData.measure_abd_circ === null) this.editingClinicalData.measure_abd_circ = '';
        if (this.editingClinicalData.measure_pa === null) this.editingClinicalData.measure_pa = '';
        if (this.editingClinicalData.measure_fr === null) this.editingClinicalData.measure_fr = '';
        if (this.editingClinicalData.measure_fc === null) this.editingClinicalData.measure_fc = '';
        if (this.editingClinicalData.measure_gc === null) this.editingClinicalData.measure_gc = '';

        this.newEvolutionEntry = '';
        this.newExamEntry = '';
        
        // ** CORREÇÃO: Define a aba ativa com base no parâmetro (padrão 'anamnesis') **
        this.activeClinicalTab = targetTab;
        
        this.patientBudgets = []; 
        this.patientAppointments = [];
        this.patientServices = [];
        this.patientReceipts = { pending: [], generated: [] };
        
        // Se a aba for específica que requer dados, carrega
        if (targetTab === 'budgets') this.fetchPatientBudgets(patient.id);
        if (targetTab === 'appointments') this.fetchPatientAppointments(patient.id);
        if (targetTab === 'receipts') {
            this.fetchPatientReceipts(patient.id);
            this.fetchUserReceiptTemplates();
        }
        if (targetTab === 'prescriptions' || targetTab === 'documents') this.fetchPatientPrescriptions(patient.id);

        this.showModal('clinical-modal');
    }
}

export async function openClinicalModalByPatientId(patientId) {
    const patient = this.patients.find(p => p.id == patientId);
    if(patient) {
        this.openClinicalModal(patient);
    } else {
        const r = await this.apiRequest('getPatientDetails', { patientId: patientId }, false, 'GET');
        if (r.success && r.patient) {
            if (!this.patients.some(p => p.id == r.patient.id)) {
                 this.patients.push(r.patient);
            }
            this.openClinicalModal(r.patient);
        } else {
            this.showToast('Erro', 'Paciente não encontrado para abrir dados clínicos.', 'error');
        }
    }
}


export function deleteSelectedPatients() {
    const count = this.selectedPatients.length;
    if (count === 0) return;
    const message = `Tem certeza que deseja excluir ${count} ${count > 1 ? this.labels.patients.toLowerCase() : this.labels.patient.toLowerCase()} selecionado(s)? Esta ação é permanente e removerá TODOS os dados associados.`;
    this.showConfirmModal(message, async () => {
        const r = await this.apiRequest('deletePatients', { patientIds: this.selectedPatients });
        if (r.success) {
            this.selectedPatients = [];
            this.fetchPatients();
            this.showToast('Sucesso!', `${this.labels.patients} excluídos.`, 'success');
        }
        this.hideConfirmModal();
    });
}

export function getPatientName(patientId) {
    const patient = this.patients.find(p => p.id == patientId);
    return patient ? patient.name : `[${this.labels.patient} ID: ${patientId}]`;
}

export function getPatientFinanceStatus(patientId) {
    if (!patientId || !this.patients) return false;
    const patient = this.patients.find(p => p.id == patientId);
    return patient ? (patient.has_pending_finance === true || patient.has_pending_finance == 1) : false;
}

export function isPatientBirthday(patientId) {
    if (!patientId || !this.patients) return false;
    const patient = this.patients.find(p => p.id == patientId);
    if (!patient || !patient.birthdate) return false;
    
    try {
        const parts = patient.birthdate.split('-');
        if (parts.length < 3) return false;
        const bMonth = parseInt(parts[1], 10);
        const bDay = parseInt(parts[2], 10);
        
        const today = new Date();
        return (today.getMonth() + 1) === bMonth && today.getDate() === bDay;
    } catch(e) {
        return false;
    }
}

export function updateBirthdayChecklist() {
    this.birthdayChecklist = {};
    const today = new Date();
    const tMonth = today.getMonth() + 1;
    const tDay = today.getDate();

    const list = this.patients || [];
    list.forEach(p => {
        if (p.birthdate) {
            try {
                const parts = p.birthdate.split('-');
                if (parts.length === 3) {
                    const bMonth = parseInt(parts[1], 10);
                    const bDay = parseInt(parts[2], 10);
                    if (bMonth === tMonth && bDay === tDay) {
                        this.birthdayChecklist[p.id] = true;
                    }
                }
            } catch (e) {}
        }
    });
}

export async function fetchBirthdays() {
    this.birthdayList = [];
    const res = await this.apiRequest('getBirthdays', {}, false, 'GET');
    if (res.success) {
        this.birthdayList = res.birthdays;
    } else {
        this.showToast('Erro', 'Não foi possível carregar a lista de aniversariantes.', 'error');
    }
}

export function calculateAge(birthdateString) {
    if (!birthdateString) return null;
    try {
        const birthDate = new Date(birthdateString + 'T00:00:00');
        if (isNaN(birthDate.getTime())) return null;
        const today = new Date();
        let age = today.getFullYear() - birthDate.getFullYear();
        const monthDifference = today.getMonth() - birthDate.getMonth();
        if (monthDifference < 0 || (monthDifference === 0 && today.getDate() < birthDate.getDate())) {
            age--;
        }
        return age;
    } catch (e) {
        return null;
    }
}

export async function fetchPatientAppointments(patientId) {
    if (!patientId) return;
    this.patientAppointments = [];
    const res = await this.apiRequest('getPatientAppointments', { patientId }, false, 'GET');
    if (res.success) {
        this.patientAppointments = res.appointments;
        if (this.editingClinicalData && this.editingClinicalData.id == patientId) {
            this.editingClinicalData.patientAppointments = res.appointments;
        }
    } else {
        this.showToast('Erro', 'Falha ao buscar histórico de agendamentos.', 'error');
    }
}

export function getAppointmentStatusLabel(appt) {
    // PRIORIDADE 1: Status "Atendido" gravado no banco
    if (appt.status === 'Atendido') {
        return { label: 'ATENDIDO', class: 'bg-green-100 text-green-700 status-uppercase' };
    }
    
    if (appt.status === 'Cancelado') return { label: 'CANCELADO', class: 'bg-gray-200 text-gray-700 status-uppercase' };
    if (appt.status === 'Não Compareceu') return { label: 'NÃO COMPARECEU', class: 'bg-orange-100 text-orange-800 status-uppercase' };

    const statusFinalizado = this.getDefaultOptionValue('service_status', 'Finalizado');
    const statusEmTratamento = this.getDefaultOptionValue('service_status', 'Em Tratamento/Agendado');
    const statusEmAtendimento = this.getDefaultOptionValue('service_status', 'Em Atendimento');
    
    // PRIORIDADE 2: Inferência pelo Serviço
    if (appt.service_status === statusFinalizado || appt.service_status === statusEmTratamento || appt.service_status === 'TRATAMENTO FINALIZADO') {
        return { label: 'ATENDIDO', class: 'bg-green-100 text-green-700 status-uppercase' };
    }
    
    if (appt.service_status === statusEmAtendimento) return { label: 'EM ATENDIMENTO', class: 'bg-blue-100 text-blue-700 status-uppercase' };

    const startTime = new Date(appt.start_time.replace(' ', 'T'));
    const now = new Date();
    
    const toleranceMinutes = this.currentUser?.missed_appointment_tolerance ?? 60;
    const toleranceTime = new Date(startTime.getTime() + toleranceMinutes * 60000);

    if (now > toleranceTime && !appt.service_status) {
         return { label: 'NÃO COMPARECEU', class: 'bg-orange-100 text-orange-800 status-uppercase' };
    }
    
    return { label: 'AGENDADO', class: 'bg-blue-100 text-blue-700 status-uppercase' };
}

export async function fetchPatientServices(patientId) {
    if (!patientId) return;
    this.patientServices = [];
    const res = await this.apiRequest('getPatientServices', { patientId }, false, 'GET');
    if (res.success) {
        this.patientServices = res.services;
        if (this.editingClinicalData && this.editingClinicalData.id == patientId) {
            this.editingClinicalData.patientServices = res.services;
        }
    } else {
        this.showToast('Erro', 'Falha ao buscar histórico de atendimentos.', 'error');
    }
}

export async function sendReminderEmail(appointmentId, patientName) {
    this.showConfirmModal(`Deseja enviar um e-mail de confirmação deste agendamento para ${patientName}?`, async () => {
        this.hideConfirmModal();
        const payload = { appointmentId: appointmentId };
        const res = await this.apiRequest('sendAppointmentReminderEmail', payload);
        if (res.success) {
            this.showToast('Sucesso', res.message || 'E-mail de lembrete enviado.', 'success');
        }
    }, 'bg-blue-600 hover:bg-blue-700');
}

export async function sendBirthdayEmails() {
    const selectedIds = this.selectedBirthdays;
    if (selectedIds.length === 0) {
        this.showToast('Aviso', 'Nenhum aniversariante foi selecionado.', 'error');
        return;
    }
    const message = `Tem certeza que deseja enviar o e-mail de feliz aniversário para ${selectedIds.length} paciente(s) selecionado(s)?`;
    this.showConfirmModal(message, async () => {
        this.hideConfirmModal();
        const payload = { patientIds: selectedIds };
        const res = await this.apiRequest('sendBirthdayEmail', payload);
        if (res.success) {
            this.showToast('Sucesso', res.message || 'E-mails enviados.', 'success');
        } else if (res.error && res.message) {
            this.showToast('Envio Parcial', res.message, 'error', 10000);
        }
        this.selectedBirthdays = [];
    }, 'bg-blue-600 hover:bg-blue-700');
}

export function searchPatientsForReferredBy() {
    // ** CORREÇÃO: Usa o valor digitado no próprio campo como termo de busca **
    // Se o usuário digita "João", busca por João. Se não seleciona, "João" fica salvo no v-model.
    const term = this.editingPatient.referred_by;
    this.patientReferredBySearchQuery = term; // Mantém compatibilidade visual se necessário

    if (!term || term.length < 2) {
        this.patientReferredBySearchResults = [];
        return;
    }
    
    // Usa lista local para agilidade (assumindo que this.patients tem todos ou muitos)
    // Se a lista for muito grande, fazer debounce API call
    this.patientReferredBySearchResults = this.patients.filter(p => p.name.toLowerCase().includes(term.toLowerCase())).slice(0, 5);
}

export function selectPatientForReferredBy(patient) {
    // ** CORREÇÃO: Grava o nome e limpa a lista de sugestões **
    this.editingPatient.referred_by = patient.name;
    this.patientReferredBySearchResults = [];
}

export function searchPatientsForResponsible() {
    const term = this.patientResponsibleSearchQuery;
    if (!term || term.length < 2) {
        this.patientResponsibleSearchResults = [];
        return;
    }
    this.patientResponsibleSearchResults = this.patients.filter(p => p.name.toLowerCase().includes(term.toLowerCase())).slice(0, 5);
}

export function selectPatientForResponsible(patient) {
    this.editingPatient.responsible_name = patient.name;
    // ** NOVA FUNCIONALIDADE: Puxa o CPF do responsável **
    if (patient.cpf) {
        this.editingPatient.responsible_cpf = patient.cpf;
    }
    this.patientResponsibleSearchResults = [];
    this.patientResponsibleSearchQuery = patient.name;
}

export function searchPatientsForFather() {
    const term = this.patientFatherSearchQuery;
    if (!term || term.length < 2) {
        this.patientFatherSearchResults = [];
        return;
    }
    this.patientFatherSearchResults = this.patients.filter(p => p.name.toLowerCase().includes(term.toLowerCase()) && p.gender === 'Masculino').slice(0, 5);
}

export function selectPatientForFather(patient) {
    this.editingPatient.parentage_father = patient.name;
    this.patientFatherSearchResults = [];
    this.patientFatherSearchQuery = patient.name;
}

export function searchPatientsForMother() {
    const term = this.patientMotherSearchQuery;
    if (!term || term.length < 2) {
        this.patientMotherSearchResults = [];
        return;
    }
    this.patientMotherSearchResults = this.patients.filter(p => p.name.toLowerCase().includes(term.toLowerCase()) && p.gender === 'Feminino').slice(0, 5);
}

export function selectPatientForMother(patient) {
    this.editingPatient.parentage_mother = patient.name;
    this.patientMotherSearchResults = [];
    this.patientMotherSearchQuery = patient.name;
}

export function exportPatientsToExcel() {
    // ... Implementação simplificada ou chamada a lib externa
    // Como no finance.js, usando XLSX
    if (!this.patients || this.patients.length === 0) return;
    
    const data = this.patients.map(p => ({
        Nome: p.name,
        Celular: p.phone,
        Email: p.email,
        CPF: p.cpf,
        Nascimento: p.birthdate ? new Date(p.birthdate).toLocaleDateString('pt-BR') : ''
    }));
    
    const ws = XLSX.utils.json_to_sheet(data);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, "Pacientes");
    XLSX.writeFile(wb, "Pacientes.xlsx");
}

export function handlePhotoUpload(event, type) {
    const file = event.target.files[0];
    if (!file) return;
    
    if (file.size > 2 * 1024 * 1024) {
        this.showToast('Erro', 'A imagem deve ter no máximo 2MB.', 'error');
        return;
    }

    const reader = new FileReader();
    reader.onload = (e) => {
        if (type === 'patient') {
            this.patientPhotoPreview = e.target.result;
            this.patientPhotoFile = file;
        } else if (type === 'user') {
            this.userPhotoPreview = e.target.result;
            this.userPhotoFile = file;
        } else if (type === 'register') {
            this.userPhotoPreview = e.target.result;
            this.registerForm.photoFile = file; // Ajuste para registro
        }
    };
    reader.readAsDataURL(file);
}

// ** NOVA FUNÇÃO: Reagendar diretamente do Histórico (sem lista de espera) **
export async function rescheduleMissedAppointmentDirectly(appt) {
    if (!appt) return;

    // 1. Marca o agendamento atual como "Não Compareceu" (se ainda não estiver ou finalizado)
    if (appt.status !== 'Não Compareceu' && appt.status !== 'Atendido' && appt.status !== 'Cancelado') {
        const payload = { 
            id: appt.id,
            patient_id: appt.patient_id,
            title: appt.title,
            notes: (appt.notes || '').includes('[NÃO COMPARECEU]') ? appt.notes : `[NÃO COMPARECEU] ${appt.notes || ''}`.trim(),
            status: 'Não Compareceu',
            start_time: appt.start_time, 
            end_time: appt.end_time,
            force: true,
            skip_waiting_list: true // Garante que não duplica para espera
        };
        await this.apiRequest('saveAppointment', payload);
        this.fetchPatientAppointments(appt.patient_id); // Atualiza a lista no modal
    }

    // 2. Prepara e abre o modal de agendamento NOVO
    // Usa os dados do agendamento antigo como base, mas para uma nova data (hoje)
    this.editingAppointment = {
        id: null, // Novo
        patient_id: appt.patient_id,
        patient_name: appt.patient_name,
        title: appt.title,
        notes: '',
        date: new Date().toLocaleDateString('en-CA'),
        start_time: '08:00',
        end_time: '08:30',
        status: 'Agendado'
    };
    
    // Configura a busca de paciente preenchida
    this.patientSearchQuery = appt.patient_name;
    this.patientSearchResults = [];
    
    // Esconde o modal clínico para focar no agendamento (opcional, mas recomendado para evitar sobreposição excessiva)
    // O usuário pediu para "não sair" em orçamentos, mas aqui é um fluxo de agenda. 
    // Se não fecharmos, o modal de agendamento abre por cima (z-index 50 vs 40).
    // Vamos manter o clínico aberto por baixo.
    
    this.showModal('appointment-modal');
}