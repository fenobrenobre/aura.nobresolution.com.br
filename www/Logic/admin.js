export async function fetchAdminSettings() { 
    const r = await this.apiRequest('getAdminSettings', {}, false, 'GET'); 
    if (r.success) { 
        this.adminSettings.trialDays = r.settings.default_trial_days || 15; 
        this.adminSettings.registrationNotes = r.settings.registration_notes || ''; 
        this.adminSettings.welcomeEmailTemplate = r.settings.welcome_email_template || ''; 
        this.adminSettings.data_retention_history = r.settings.data_retention_history || '12';
        this.adminSettings.data_retention_agenda = r.settings.data_retention_agenda || '12';
        this.adminSettings.data_retention_budgets = r.settings.data_retention_budgets || '12';
        this.adminSettings.adminNotificationEmail = r.settings.admin_notification_email || ''; 
        
        // ** NOVOS CAMPOS: Configuração de Templates Padrão **
        this.adminSettings.default_atestado_template_id = r.settings.default_atestado_template_id || '';
        this.adminSettings.default_declaracao_template_id = r.settings.default_declaracao_template_id || '';

        // Garante que a lista de templates esteja carregada para os dropdowns
        if (!this.prescriptionTemplates || this.prescriptionTemplates.length === 0) {
            await this.fetchPrescriptionTemplates();
        }
    } 
}

export async function saveAdminSettings() { 
    const payload = { 
        settings: {
            trialDays: this.adminSettings.trialDays,
            registrationNotes: this.adminSettings.registrationNotes,
            welcomeEmailTemplate: this.adminSettings.welcomeEmailTemplate,
            data_retention_history: this.adminSettings.data_retention_history,
            data_retention_agenda: this.adminSettings.data_retention_agenda,
            data_retention_budgets: this.adminSettings.data_retention_budgets,
            adminNotificationEmail: this.adminSettings.adminNotificationEmail,
            
            // ** NOVOS CAMPOS **
            default_atestado_template_id: this.adminSettings.default_atestado_template_id,
            default_declaracao_template_id: this.adminSettings.default_declaracao_template_id
        }
    };
    const r = await this.apiRequest('saveAdminSettings', payload); 
    if (r.success) this.showToast('Sucesso', 'Configurações salvas!', 'success'); 
}

export async function fetchProfessions() { 
    const r = await this.apiRequest('getProfessions', {}, false, 'GET'); 
    if (r.success) this.professions = r.professions; 
}

export function openProfessionModal(p) { 
    this.editingProfession = p ? { ...p } : { id: null, name: '' }; 
    this.showModal('profession-modal'); 
}

export async function saveProfession() {
    const payload = { ...this.editingProfession };
    const r = await this.apiRequest('saveProfession', payload);
    if (r.success) {
         this.fetchProfessions();
         this.hideModal('profession-modal');
         this.showToast('Sucesso', 'Profissão salva.', 'success');
         if (!this.currentUser || this.currentUser.isAdmin) await this.fetchPublicConfig();
    }
}

export async function deleteProfession(id) {
     this.showConfirmModal('Tem certeza? A profissão será removida de todos os usuários que a utilizam.', async () => {
        const r = await this.apiRequest('deleteProfession', { id });
        if (r.success) {
             this.fetchProfessions();
             this.hideConfirmModal();
             this.showToast('Sucesso', 'Profissão excluída.', 'success');
             if (!this.currentUser || this.currentUser.isAdmin) await this.fetchPublicConfig();
        } else {
             this.hideConfirmModal();
        }
     });
}

// --- ESPECIALIDADES ---

export async function fetchSpecialties(professionId) {
    if (!professionId) {
        this.specialties = [];
        return;
    }
    
    const r = await this.apiRequest('getSpecialties', { professionId }, false, 'GET');
    if (r.success) {
        this.specialties = r.specialties;
    } else {
        this.specialties = [];
    }
}

export function manageSpecialties(profession) {
    this.activeProfessionForSpecialties = profession;
    this.fetchSpecialties(profession.id);
    this.showModal('admin-manage-specialties-modal');
}

export function openSpecialtyModal(specialty) {
    if (specialty) {
        this.editingSpecialty = { ...specialty };
    } else {
        this.editingSpecialty = { id: null, name: '', profession_id: this.activeProfessionForSpecialties.id };
    }
    this.showModal('specialty-modal');
}

export async function saveSpecialty() {
    const payload = { ...this.editingSpecialty };
    const r = await this.apiRequest('saveSpecialty', payload);
    if (r.success) {
        this.showToast('Sucesso', 'Especialidade salva.', 'success');
        this.hideModal('specialty-modal');
        this.fetchSpecialties(this.activeProfessionForSpecialties.id);
    }
}

export async function deleteSpecialty(id) {
    this.showConfirmModal('Tem certeza que deseja excluir esta especialidade?', async () => {
        const r = await this.apiRequest('deleteSpecialty', { id });
        if (r.success) {
            this.showToast('Sucesso', 'Especialidade excluída.', 'success');
            this.fetchSpecialties(this.activeProfessionForSpecialties.id);
        }
        this.hideConfirmModal();
    });
}

// -------------------------

export async function fetchCustomFieldOptions() { 
    const r = await this.apiRequest('getCustomFieldOptions', {}, false, 'GET');
    if (r.success) {
        this.customFieldOptions = r.options;
    } else {
        this.customFieldOptions = [];
    }
    return r;
}

export function openCustomFieldOptionModal(option, fieldType) { 
    if (option) { 
        this.editingCustomFieldOption = { ...option }; 
        if (option.field_type === 'payment_method') {
            this.editingCustomFieldOption.make_global = !!option.is_global;
            this.editingCustomFieldOption.assign_to_user_id = option.is_global ? null : option.user_id;
        }
    } else { 
        this.editingCustomFieldOption = { 
            id: null, 
            field_type: fieldType, 
            option_value: '', 
            is_default: false,
            make_global: true, 
            assign_to_user_id: null
        }; 
    } 
    this.showModal('custom-field-option-modal'); 
}

export async function saveCustomFieldOption() { 
    const payload = { ...this.editingCustomFieldOption }; 
    
    if (payload.field_type === 'payment_method') {
        if (payload.make_global) {
            payload.assign_to_user_id = null;
        }
    } else {
        delete payload.make_global;
        delete payload.assign_to_user_id;
    }
    delete payload.fee_percentage;

    const r = await this.apiRequest('saveCustomFieldOption', payload); 
    if (r.success && r.option) { 
        const index = this.customFieldOptions.findIndex(opt => opt.id === r.option.id); 
        if (index > -1) { 
            this.customFieldOptions.splice(index, 1, r.option); 
        } else { 
            this.customFieldOptions.push(r.option); 
        } 
        await this.fetchPublicConfig(); 
        this.hideModal('custom-field-option-modal'); 
        this.showToast('Sucesso', 'Opção salva.', 'success'); 
    } 
}

export async function deleteCustomFieldOption(id) { 
    const optionToDelete = this.customFieldOptions.find(opt => opt.id === id); 
    if (!optionToDelete) return; 
    if (optionToDelete.is_default) { 
        this.showToast('Aviso', 'Opções padrão do sistema não podem ser excluídas.', 'error'); 
        return; 
    } 
    this.showConfirmModal(`Tem certeza que deseja excluir a opção "${optionToDelete.option_value}"? Esta ação pode afetar registros existentes.`, async () => { 
        const r = await this.apiRequest('deleteCustomFieldOption', { id }); 
        if (r.success) { 
            this.customFieldOptions = this.customFieldOptions.filter(opt => opt.id !== id); 
            await this.fetchPublicConfig(); 
            this.hideConfirmModal(); 
            this.showToast('Sucesso', 'Opção excluída.', 'success'); 
        } else { 
            this.hideConfirmModal(); 
        } 
    }); 
}

// --- RECOMENDAÇÕES ---

export async function fetchRecommendationTemplates(arg) { 
    const r = await this.apiRequest('getRecommendationTemplates', {}, false, 'GET'); 
    if (r.success) this.recommendationTemplates = r.templates; 
}

export function openRecommendationModal(t) { 
    if (t) { 
        this.editingRecommendation = { ...t, make_global: !!t.is_global, assign_to_user_id: t.is_global ? null : t.user_id }; 
    } else { 
        this.editingRecommendation = { id: null, title: '', content: '', make_global: true, assign_to_user_id: null }; 
    } 
    this.showModal('recommendation-template-modal'); 
}

export async function saveRecommendationTemplate() { 
    const payload = { ...this.editingRecommendation }; 
    if (payload.make_global) payload.assign_to_user_id = null;
    
    const r = await this.apiRequest('saveRecommendationTemplate', payload); 
    if (r.success) { 
        this.fetchRecommendationTemplates(); 
        this.hideModal('recommendation-template-modal'); 
        this.showToast('Sucesso', 'Recomendação salva.', 'success'); 
    } 
}

export async function deleteRecommendationTemplate(id) { 
    this.showConfirmModal('Excluir esta recomendação?', async () => { 
        const r = await this.apiRequest('deleteRecommendationTemplate', { id }); 
        if (r.success) { 
            this.fetchRecommendationTemplates(); 
            this.hideConfirmModal(); 
            this.showToast('Sucesso', 'Recomendação excluída.', 'success'); 
        } else { 
            this.hideConfirmModal(); 
        } 
    }); 
}

// --- PRESCRIÇÃO TEMPLATES (Usado para carregar os modelos nos dropdowns de config) ---
export async function fetchPrescriptionTemplates(arg) {
    const r = await this.apiRequest('getPrescriptionTemplates', {}, false, 'GET');
    if (r.success) this.prescriptionTemplates = r.templates;
}

export function openPrescriptionTemplateModal(t) {
    if (t) {
        this.editingPrescriptionTemplate = { ...t, make_global: !!t.is_global, assign_to_user_id: t.is_global ? null : t.user_id };
    } else {
        this.editingPrescriptionTemplate = { id: null, title: '', content: '', type: 'receita', make_global: true, assign_to_user_id: null };
    }
    this.showModal('prescription-template-modal');
}

export async function savePrescriptionTemplate() {
    const payload = { ...this.editingPrescriptionTemplate };
    if (payload.make_global) payload.assign_to_user_id = null;

    const r = await this.apiRequest('savePrescriptionTemplate', payload);
    if (r.success) {
        this.fetchPrescriptionTemplates();
        this.hideModal('prescription-template-modal');
        this.showToast('Sucesso', 'Modelo de prescrição salvo.', 'success');
    }
}

export async function deletePrescriptionTemplate(id) {
    this.showConfirmModal('Excluir este modelo?', async () => {
        const r = await this.apiRequest('deletePrescriptionTemplate', { id });
        if (r.success) {
            this.fetchPrescriptionTemplates();
            this.hideConfirmModal();
            this.showToast('Sucesso', 'Modelo excluído.', 'success');
        } else {
            this.hideConfirmModal();
        }
    });
}

// --- MEDICAMENTOS ---
export async function fetchMedicines(mode, search = '') {
    // Mode pode ser 'admin' ou 'user'
    const r = await this.apiRequest('getMedicines', { search }, false, 'GET');
    if (r.success) {
        this.medicines = r.medicines;
    }
}

export function openMedicineModal(med) {
    if (med) {
        this.editingMedicine = { ...med, make_global: !!med.is_global, assign_to_user_id: med.is_global ? null : med.user_id };
    } else {
        this.editingMedicine = { id: null, name: '', instructions: '', presentation: '', default_route: '', default_duration: '', make_global: true, assign_to_user_id: null };
    }
    this.showModal('medicine-modal');
}

export async function saveMedicine() {
    const payload = { ...this.editingMedicine };
    if (payload.make_global) payload.assign_to_user_id = null;

    const r = await this.apiRequest('saveMedicine', payload);
    if (r.success) {
        this.fetchMedicines('admin');
        this.hideModal('medicine-modal');
        this.showToast('Sucesso', 'Medicamento salvo.', 'success');
    }
}

export async function deleteMedicine(id) {
    this.showConfirmModal('Excluir este medicamento?', async () => {
        const r = await this.apiRequest('deleteMedicine', { id, adminId: this.currentUser.id });
        if (r.success) {
            this.fetchMedicines('admin');
            this.hideConfirmModal();
            this.showToast('Sucesso', 'Medicamento excluído.', 'success');
        } else {
            this.hideConfirmModal();
        }
    });
}

// --- EXAMES ---
export async function fetchExams(mode, search = '') {
    const r = await this.apiRequest('getExams', { search }, false, 'GET');
    if (r.success) {
        this.exams = r.exams;
    }
}

export function openExamModal(exam) {
    if (exam) {
        this.editingExam = { ...exam, make_global: !!exam.is_global, assign_to_user_id: exam.is_global ? null : exam.user_id };
    } else {
        this.editingExam = { id: null, name: '', description: '', make_global: true, assign_to_user_id: null };
    }
    this.showModal('exam-modal');
}

export async function saveExam() {
    const payload = { ...this.editingExam };
    if (payload.make_global) payload.assign_to_user_id = null;

    const r = await this.apiRequest('saveExam', payload);
    if (r.success) {
        this.fetchExams('admin');
        this.hideModal('exam-modal');
        this.showToast('Sucesso', 'Exame salvo.', 'success');
    }
}

export async function deleteExam(id) {
    this.showConfirmModal('Excluir este exame?', async () => {
        const r = await this.apiRequest('deleteExam', { id, adminId: this.currentUser.id });
        if (r.success) {
            this.fetchExams('admin');
            this.hideConfirmModal();
            this.showToast('Sucesso', 'Exame excluído.', 'success');
        } else {
            this.hideConfirmModal();
        }
    });
}