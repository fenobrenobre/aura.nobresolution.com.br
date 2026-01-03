// user.js - Gerenciamento de Usuários e Perfil

export async function fetchUsers() { 
    const r = await this.apiRequest('getUsers', {}, false, 'GET'); 
    if (r.success) this.users = r.users; 
}

// --- ADMIN: ABRIR MODAL DE USUÁRIO ---
export async function openUserModal(user) { 
    // Mescla dados existentes com o template padrão para garantir que todos os campos existam
    this.editingUser = user ? { ...this.getNewUserTemplate(), ...user } : this.getNewUserTemplate(); 
    
    // --- INICIALIZAÇÃO DE OBJETOS COMPLEXOS ---
    this.editingUser.weekly_schedule = this.ensureValidSchedule(this.editingUser.weekly_schedule); 
    this.editingUser.disabled_dates = Array.isArray(this.editingUser.disabled_dates) ? this.editingUser.disabled_dates : []; 
    this.editingUser.reminder_email_hours = Array.isArray(this.editingUser.reminder_email_hours) ? this.editingUser.reminder_email_hours : ['24'];
    this.editingUser.birthday_email_time = this.editingUser.birthday_email_time ? this.editingUser.birthday_email_time.substring(0, 5) : '09:00';
    this.editingUser.enabled_payment_methods = Array.isArray(this.editingUser.enabled_payment_methods) ? [...this.editingUser.enabled_payment_methods.map(String)] : [];

    // --- VALORES PADRÃO (Funcionalidades - Switches) ---
    // Garante que null vire 0 ou 1 conforme padrão desejado
    this.editingUser.finance_enabled = this.editingUser.finance_enabled ?? 1;
    this.editingUser.finance_ledger_enabled = this.editingUser.finance_ledger_enabled ?? 1;
    this.editingUser.finance_forecast_enabled = this.editingUser.finance_forecast_enabled ?? 1;
    this.editingUser.agenda_enabled = this.editingUser.agenda_enabled ?? 1;
    this.editingUser.birthday_list_enabled = this.editingUser.birthday_list_enabled ?? 0;
    this.editingUser.waiting_list_enabled = this.editingUser.waiting_list_enabled ?? 0;
    this.editingUser.future_schedule_enabled = this.editingUser.future_schedule_enabled ?? 0;
    this.editingUser.odontogram_enabled = this.editingUser.odontogram_enabled ?? 0;
    this.editingUser.memed_enabled = this.editingUser.memed_enabled ?? 0;
    
    // --- VALORES PADRÃO (Configurações Gerais) ---
    this.editingUser.timezone = this.editingUser.timezone || 'America/Sao_Paulo';
    this.editingUser.system_version = this.editingUser.system_version || 'Saude';
    this.editingUser.appointment_slot_minutes = this.editingUser.appointment_slot_minutes || 30;
    this.editingUser.missed_appointment_tolerance = this.editingUser.missed_appointment_tolerance ?? 60;
    
    this.editingUser.default_receipt_template_id = this.editingUser.default_receipt_template_id ?? null;
    this.editingUser.default_budget_form_identifier = this.editingUser.default_budget_form_identifier ?? null;
    this.editingUser.default_price_list_id = this.editingUser.default_price_list_id ?? null;
    this.editingUser.anamnesis_template_id = this.editingUser.anamnesis_template_id ?? null;
    this.editingUser.specialty = this.editingUser.specialty || null;

    // --- VALORES PADRÃO (Comunicações / E-mail) ---
    this.editingUser.schedule_email_enabled = this.editingUser.schedule_email_enabled ?? 0;
    this.editingUser.schedule_email_template = this.editingUser.schedule_email_template || '';
    this.editingUser.reminder_email_enabled = this.editingUser.reminder_email_enabled ?? 0;
    this.editingUser.reminder_email_template = this.editingUser.reminder_email_template || '';
    this.editingUser.future_schedule_email_enabled = this.editingUser.future_schedule_email_enabled ?? 0;
    this.editingUser.future_schedule_email_template = this.editingUser.future_schedule_email_template || '';
    this.editingUser.birthday_email_enabled = this.editingUser.birthday_email_enabled ?? 0;
    this.editingUser.birthday_email_template = this.editingUser.birthday_email_template || '';

    // --- VALORES PADRÃO (Integrações) ---
    this.editingUser.google_client_id = this.editingUser.google_client_id || '';
    this.editingUser.google_client_secret = this.editingUser.google_client_secret || '';
    this.editingUser.google_calendar_enabled = this.editingUser.google_calendar_enabled ?? 0;

    // --- DECOMPOSIÇÃO DO REGISTRO PROFISSIONAL ---
    this.editingUser.professional_register_type = '';
    this.editingUser.professional_register_number = '';
    this.editingUser.professional_register_uf = '';

    if (this.editingUser.professional_register) {
        const reg = String(this.editingUser.professional_register).trim();
        // Regex para tentar extrair TIPO: NUMERO/UF
        const match = reg.match(/^([A-Za-z]+)[:\s]+([0-9]+)(?:[\/]([A-Za-z]{2}))?$/);
        
        if (match) {
            this.editingUser.professional_register_type = match[1] || '';
            this.editingUser.professional_register_number = match[2] || '';
            this.editingUser.professional_register_uf = match[3] || '';
        } else {
            // Fallback simples se não bater no regex
            this.editingUser.professional_register_number = reg.replace(/[^0-9]/g, '');
            const regLower = reg.toLowerCase();
            if (regLower.includes('crm')) this.editingUser.professional_register_type = 'CRM';
            else if (regLower.includes('cro')) this.editingUser.professional_register_type = 'CRO';
            else if (regLower.includes('crefito')) this.editingUser.professional_register_type = 'CREFITO';
            else if (regLower.includes('crn')) this.editingUser.professional_register_type = 'CRN';
            else if (regLower.includes('crp')) this.editingUser.professional_register_type = 'CRP';
            else if (regLower.includes('crf')) this.editingUser.professional_register_type = 'CRF';
        }
    }

    // --- PREPARAÇÃO DE UI ---
    this.editingUser.password = ''; // Limpa senha por segurança
    this.editingUser.admin_password = ''; // Limpa senha administrativa
    this.passwordStrength = 0; 
    this.passwordFeedback = ''; 
    
    // Formatação de data para input
    if (this.editingUser.deactivationDate) { 
        this.editingUser.deactivationDate = this.formatDateForInput(this.editingUser.deactivationDate); 
    } 
    
    // Switch de Status
    this.isUserActive = this.editingUser.status === 'active';

    this.userPhotoPreview = user ? user.photo : null; 
    this.userPhotoFile = null; 
    this.logoPreview = user ? user.logo : null; 
    this.logoFile = null; 
    this.newDisabledDate = ''; 
    
    // Reseta navegação de abas
    this.activeUserTab = 'main'; 
    this.activeAdminUserCustomTab = 'system'; 
    
    // --- CARREGAMENTO DE DEPENDÊNCIAS ---
    if (!this.professions || !this.professions.length) await this.fetchProfessions();
    if (!this.customFieldOptions || !this.customFieldOptions.length) await this.fetchCustomFieldOptions();
    
    await this.fetchBudgetForms(); 
    await this.fetchAllPriceListsAdmin(); 

    // Carrega especialidades baseado na profissão
    this.specialties = [];
    if (this.editingUser.profession) {
        await this.updateSpecialtiesForUser();
    }
    
    // Carrega templates específicos do usuário para popular os selects
    await this.fetchUserAnamnesisTemplates(this.editingUser.id || null); 
    await this.fetchUserReceiptTemplates(this.editingUser.id || null);
    
    // Carrega métodos de pagamento
    this.userPaymentMethods = [];
    if (this.editingUser.id) {
        await this.fetchUserPaymentMethods(this.editingUser.id);
    }
    
    this.showModal('user-modal'); 
}

// Atualiza lista de especialidades ao mudar profissão no modal de usuário
export async function updateSpecialtiesForUser() {
    const professionName = this.editingUser.profession;
    this.specialties = [];
    
    if (!professionName) return;

    const profession = this.professions.find(p => p.name === professionName);
    
    if (profession) {
        const r = await this.apiRequest('getSpecialties', { professionId: profession.id }, false, 'GET');
        if (r.success) {
            this.specialties = r.specialties;
        }
    } else {
        this.editingUser.specialty = null;
    }
}

// --- ADMIN: SALVAR USUÁRIO ---
export async function saveUser() {
    const fd = new FormData();

    // Recompõe o registro profissional (Tipo: Numero/UF)
    let combinedRegister = '';
    const rType = this.editingUser.professional_register_type ? this.editingUser.professional_register_type.toUpperCase().trim() : '';
    const rNum = this.editingUser.professional_register_number ? this.editingUser.professional_register_number.trim() : '';
    const rUf = this.editingUser.professional_register_uf ? this.editingUser.professional_register_uf.toUpperCase().trim() : '';

    if (rType && rNum) {
        combinedRegister = `${rType}: ${rNum}`;
        if (rUf) combinedRegister += `/${rUf}`;
    } else {
        combinedRegister = rNum;
    }

    // Atualiza status baseado no switch UI
    this.editingUser.status = this.isUserActive ? 'active' : 'inactive';

    // Monta o FormData
    for (const k in this.editingUser) { 
        // Ignora campos auxiliares
        if (k === 'professional_register_type' || k === 'professional_register_number' || k === 'professional_register_uf') continue;
        if (k === 'professional_register') continue; // Será adicionado manualmente
        if (k === 'isDocumentInvalid') continue;
        
        const value = this.editingUser[k]; 
        
        if (k !== 'photo' && k !== 'logo' && value !== null && value !== undefined) { 
            // Serializa objetos/arrays para JSON
            if (k === 'weekly_schedule' || k === 'disabled_dates' || k === 'reminder_email_hours' || k === 'enabled_payment_methods') {
                fd.append(k, JSON.stringify(value)); 
            } else if (k === 'birthday_email_time') {
                fd.append(k, String(value).substring(0, 5));
            } else { 
                fd.append(k, value); 
            } 
        } 
    }
    
    fd.append('professional_register', combinedRegister);

    // Anexa arquivos se houver
    if (this.userPhotoFile) fd.append('photo', this.userPhotoFile); 
    if (this.logoFile) fd.append('logo', this.logoFile);
    
    const r = await this.apiRequest('saveUser', fd, true);
    
    if (r.success && r.data) {
        this.showToast('Sucesso', 'Usuário salvo com sucesso!', 'success');
        
        // Atualiza objeto local com retorno do servidor
        r.data.weekly_schedule = this.ensureValidSchedule(r.data.weekly_schedule);
        r.data.disabled_dates = Array.isArray(r.data.disabled_dates) ? r.data.disabled_dates : [];
        
        // Mantém senha localmente se necessário, ou limpa
        const currentPassword = this.editingUser.password;
        this.editingUser = { ...this.editingUser, ...r.data };
        // this.editingUser.password = currentPassword; 
        
        if (this.editingUser.deactivationDate) { 
            this.editingUser.deactivationDate = this.formatDateForInput(this.editingUser.deactivationDate); 
        }
        
        this.editingUser.reminder_email_hours = Array.isArray(r.data.reminder_email_hours) ? r.data.reminder_email_hours : ['24'];
        this.editingUser.enabled_payment_methods = Array.isArray(r.data.enabled_payment_methods) ? r.data.enabled_payment_methods.map(String) : [];
        
        this.userPhotoFile = null; 
        this.userPhotoPreview = r.data.photo; 
        this.logoFile = null; 
        this.logoPreview = r.data.logo;
        
        this.fetchUsers(); // Atualiza a tabela
    }
}

export async function deleteUser(id) { 
    this.showConfirmModal('Tem certeza que deseja excluir este contratante? A ação é permanente e removerá todos os dados associados.', async () => { 
        const r = await this.apiRequest('deleteUser', { id }); 
        if (r.success) { 
            this.fetchUsers(); 
            this.showToast('Excluído', 'Usuário removido.', 'success'); 
        } 
        this.hideConfirmModal(); 
    }); 
}

// --- USUÁRIO: GERENCIAMENTO DE PERFIL PRÓPRIO ---

export async function updateSpecialtiesForProfile() {
    const professionName = this.editingProfile.profession;
    this.specialties = [];
    
    if (!professionName) return;

    const profession = this.professions.find(p => p.name === professionName);
    
    if (profession) {
        const r = await this.apiRequest('getSpecialties', { professionId: profession.id }, false, 'GET');
        if (r.success) {
            this.specialties = r.specialties;
        }
    } else {
         this.editingProfile.specialty = null;
    }
}

export function openProfileForEditing() { 
    // Clona currentUser para editingProfile, garantindo deep copy de objetos
    this.editingProfile = { 
        ...this.currentUser, 
        password: '', 
        weekly_schedule: JSON.parse(JSON.stringify(this.ensureValidSchedule(this.currentUser.weekly_schedule))), 
        disabled_dates: Array.isArray(this.currentUser.disabled_dates) ? [...this.currentUser.disabled_dates] : [],
        reminder_email_hours: Array.isArray(this.currentUser.reminder_email_hours) ? [...this.currentUser.reminder_email_hours] : ['24'],
        birthday_email_time: this.currentUser.birthday_email_time ? this.currentUser.birthday_email_time.substring(0, 5) : '09:00',
        enabled_payment_methods: Array.isArray(this.currentUser.enabled_payment_methods) ? [...this.currentUser.enabled_payment_methods.map(String)] : [],
        
        // Garante campos de configuração
        finance_enabled: this.currentUser.finance_enabled ?? 1,
        agenda_enabled: this.currentUser.agenda_enabled ?? 1,
        waiting_list_enabled: this.currentUser.waiting_list_enabled ?? 0,
        future_schedule_enabled: this.currentUser.future_schedule_enabled ?? 0,
        birthday_list_enabled: this.currentUser.birthday_list_enabled ?? 0,
        memed_enabled: this.currentUser.memed_enabled ?? 0,
        odontogram_enabled: this.currentUser.odontogram_enabled ?? 0,
        
        professional_register_type: '',
        professional_register_number: '',
        professional_register_uf: ''
    }; 

    if (this.editingProfile.profession) {
        this.updateSpecialtiesForProfile();
    }

    // Decomposição do Registro Profissional (Mesma lógica do Admin)
    if (this.editingProfile.professional_register) {
        const reg = String(this.editingProfile.professional_register).trim();
        const match = reg.match(/^([A-Za-z]+)[:\s]+([0-9]+)(?:[\/]([A-Za-z]{2}))?$/);
        
        if (match) {
            this.editingProfile.professional_register_type = match[1] || '';
            this.editingProfile.professional_register_number = match[2] || '';
            this.editingProfile.professional_register_uf = match[3] || '';
        } else {
             this.editingProfile.professional_register_number = reg.replace(/[^0-9]/g, '');
             const regLower = reg.toLowerCase();
             if (regLower.includes('crm')) this.editingProfile.professional_register_type = 'CRM';
             else if (regLower.includes('cro')) this.editingProfile.professional_register_type = 'CRO';
             else if (regLower.includes('crefito')) this.editingProfile.professional_register_type = 'CREFITO';
             else if (regLower.includes('crn')) this.editingProfile.professional_register_type = 'CRN';
             else if (regLower.includes('crp')) this.editingProfile.professional_register_type = 'CRP';
             else if (regLower.includes('crf')) this.editingProfile.professional_register_type = 'CRF';
        }
    }

    this.userPhotoPreview = this.currentUser.photo; 
    this.userPhotoFile = null; 
    this.logoPreview = this.currentUser.logo; 
    this.logoFile = null; 
    this.newDisabledDate = ''; 
    this.passwordStrength = 0; 
    this.passwordFeedback = ''; 
    
    if ((!this.customFieldOptions || !this.customFieldOptions.length) && this.currentUser.isAdmin) {
        this.fetchCustomFieldOptions(); 
    }
    
    this.fetchUserAnamnesisTemplates(); 
    this.fetchUserReceiptTemplates();
    this.userPaymentMethods = [];
    this.fetchUserPaymentMethods(this.editingProfile.id); // Busca métodos pessoais
}

export async function saveProfile() {
    const fd = new FormData(); 
    fd.append('userId', this.editingProfile.id);

    // Recompõe Registro Profissional
    let combinedRegister = '';
    const rType = this.editingProfile.professional_register_type ? this.editingProfile.professional_register_type.toUpperCase().trim() : '';
    const rNum = this.editingProfile.professional_register_number ? this.editingProfile.professional_register_number.trim() : '';
    const rUf = this.editingProfile.professional_register_uf ? this.editingProfile.professional_register_uf.toUpperCase().trim() : '';

    if (rType && rNum) {
        combinedRegister = `${rType}: ${rNum}`;
        if (rUf) combinedRegister += `/${rUf}`;
    } else {
        combinedRegister = rNum;
    }
    this.editingProfile.professional_register = combinedRegister;

    for (const key in this.editingProfile) { 
        if (key === 'professional_register_type' || key === 'professional_register_number' || key === 'professional_register_uf') continue;
        
        if (key === 'password' && !this.editingProfile.password) continue; 
        if (key === 'password' && this.editingProfile.password && this.editingProfile.password.startsWith('$2y$')) continue; 
        if (['photo', 'logo'].includes(key) && !this.editingProfile[key]) continue; 
        
        if (this.editingProfile[key] !== null && this.editingProfile[key] !== undefined) { 
            if (key === 'weekly_schedule' || key === 'disabled_dates' || key === 'reminder_email_hours' || key === 'enabled_payment_methods') {
                fd.append(key, JSON.stringify(this.editingProfile[key])); 
            } else if (key === 'birthday_email_time') {
                fd.append(key, String(this.editingProfile[key]).substring(0, 5));
            } else { 
                fd.append(key, this.editingProfile[key]); 
            } 
        } 
    }
    
    fd.append('professional_register', combinedRegister);
    
    if (this.editingProfile.password && !this.editingProfile.password.startsWith('$2y$')) { 
        fd.append('password', this.editingProfile.password); 
    }
    if (this.userPhotoFile) fd.append('photo', this.userPhotoFile); 
    if (this.logoFile) fd.append('logo', this.logoFile);
    
    let r = await this.apiRequest('updateProfile', fd, true); 
    
    // Lógica para confirmação de senha em caso de sessão sensível
    if (!r.success && r.error && (r.error.includes('SENHA ATUAL') || r.error.includes('Sessão expirada'))) {
        const currentPass = prompt("Sessão instável ou expirada. Para confirmar sua identidade e salvar as alterações, digite sua SENHA ATUAL de login:");
        
        if (currentPass) {
            fd.append('current_password', currentPass);
            r = await this.apiRequest('updateProfile', fd, true);
        }
    }

    if (r.success && r.data) { 
        this.showToast('Sucesso!', 'Configurações salvas com sucesso!', 'success'); 
        this.startSession(r.data); 
    }
}

// --- FORMAS DE PAGAMENTO (ADMIN E USER) ---

export async function fetchUserPaymentMethods(targetUserId = null) {
    this.userPaymentMethods = [];
    const params = {};
    if (targetUserId) params.userId = targetUserId;
    
    const res = await this.apiRequest('getUserPaymentMethods', params, false, 'GET');
    if (res.success) {
        this.userPaymentMethods = res.methods;
        
        // Sincroniza seleção se necessário (para Admin ou User Settings)
        if (this.activeView === 'settings' && this.editingProfile && this.editingProfile.enabled_payment_methods === null) {
            this.editingProfile.enabled_payment_methods = res.methods.map(m => String(m.id));
        }
        
        if (this.activeAdminView === 'users' && this.editingUser && this.editingUser.enabled_payment_methods === null) {
            this.editingUser.enabled_payment_methods = res.methods.map(m => String(m.id));
        }
    }
}

export function openUserPaymentMethodModal(method) {
    if (method && method.is_global) {
        this.editingUserPaymentMethod = {
            id: null,
            option_value: method.option_value + " (Cópia)",
            originalIsGlobal: true
        };
    } else if (method) {
        this.editingUserPaymentMethod = { ...method, originalIsGlobal: false };
    } else {
        this.editingUserPaymentMethod = { id: null, option_value: '', originalIsGlobal: false };
    }
    this.showModal('user-payment-method-modal');
}

export async function saveUserPaymentMethod() {
    const payload = { ...this.editingUserPaymentMethod };
    delete payload.originalIsGlobal;
    delete payload.is_global;
    delete payload.fee_percentage; 
    
    // Se for Admin editando um usuário específico
    if (this.currentUser.isAdmin && this.editingUser && this.editingUser.id && this.activeAdminView === 'users') {
        payload.userId = this.editingUser.id;
    }

    const res = await this.apiRequest('saveUserPaymentMethod', payload);
    if (res.success && res.method) {
        this.showToast('Sucesso', 'Forma de pagamento salva.', 'success');
        this.hideModal('user-payment-method-modal');
        
        // Atualiza lista local
        const index = this.userPaymentMethods.findIndex(m => m.id === res.method.id);
        if (index > -1) {
            this.userPaymentMethods.splice(index, 1, res.method);
        } else {
            this.userPaymentMethods.push(res.method);
            
            // Auto-seleciona o novo método
            if (this.activeView === 'settings' && this.editingProfile.enabled_payment_methods !== null) {
                this.editingProfile.enabled_payment_methods.push(String(res.method.id));
            }
            if (this.activeAdminView === 'users' && this.editingUser.enabled_payment_methods !== null) {
                this.editingUser.enabled_payment_methods.push(String(res.method.id));
            }
        }
        
        // Se Admin global, atualiza config pública
        if(!this.currentUser.isAdmin || (this.currentUser.isAdmin && this.activeAdminView === 'users')) {
            // Nenhuma ação extra necessária
        } else {
             await this.fetchPublicConfig();
        }
    }
}

export async function deleteUserPaymentMethod(id) {
    const method = this.userPaymentMethods.find(m => m.id === id);
    if (!method || method.is_global || method.is_default) {
        this.showToast('Erro', 'Este método não pode ser excluído.', 'error');
        return;
    }

    this.showConfirmModal(`Tem certeza que deseja excluir o método "${method.option_value}"? Esta ação não pode ser desfeita.`, async () => {
        const payload = { id };
        if (this.currentUser.isAdmin && this.editingUser && this.editingUser.id && this.activeAdminView === 'users') {
            payload.userId = this.editingUser.id;
        }

        const res = await this.apiRequest('deleteUserPaymentMethod', payload);
        if (res.success) {
            this.showToast('Sucesso', 'Método de pagamento excluído.', 'success');
            this.userPaymentMethods = this.userPaymentMethods.filter(m => m.id !== id);
            
            // Remove da seleção
            if (this.activeView === 'settings' && this.editingProfile.enabled_payment_methods) {
                this.editingProfile.enabled_payment_methods = this.editingProfile.enabled_payment_methods.filter(mId => mId != id);
            }
            if (this.activeAdminView === 'users' && this.editingUser.enabled_payment_methods) {
                this.editingUser.enabled_payment_methods = this.editingUser.enabled_payment_methods.filter(mId => mId != id);
            }
            
            if(!this.currentUser.isAdmin) await this.fetchPublicConfig();
        }
        this.hideConfirmModal();
    });
}

// --- MANUTENÇÃO / LIMPEZA DE DADOS ---

export function promptCleanup(type) {
    this.maintenanceAuth = { mode: 'simple', action: type, admin_password: '', login_password: '' };
    this.showModal('maintenance-auth-modal');
}

export function promptFinancialCleanup(scope) {
    this.maintenanceAuth = { mode: 'financial', action: scope, admin_password: '', login_password: '' };
    this.showModal('maintenance-auth-modal');
}

export async function performMaintenance() {
    if (!this.maintenanceAuth.admin_password) {
        this.showToast('Erro', 'Senha administrativa é obrigatória.', 'error');
        return;
    }
    if (this.maintenanceAuth.mode === 'financial' && !this.maintenanceAuth.login_password) {
        this.showToast('Erro', 'Senha de login é obrigatória para esta ação.', 'error');
        return;
    }

    let endpoint = '';
    let payload = { admin_password: this.maintenanceAuth.admin_password };

    if (this.maintenanceAuth.mode === 'financial') {
        endpoint = 'cleanupFinancial';
        payload.login_password = this.maintenanceAuth.login_password;
        payload.scope = this.maintenanceAuth.action;
    } else {
        if (this.maintenanceAuth.action === 'clinical') {
            endpoint = 'cleanupClinicalHistory';
            payload.retention_period = this.maintenance.clinicalPeriod;
        } else if (this.maintenanceAuth.action === 'receipts') {
            endpoint = 'cleanupReceipts';
            payload.retention_period = this.maintenance.receiptPeriod;
            payload.target = this.maintenance.receiptTarget;
        }
    }

    const res = await this.apiRequest(endpoint, payload);

    if (res.success) {
        this.showToast('Limpeza Concluída', res.message, 'success', 5000);
        this.hideModal('maintenance-auth-modal');
        
        if (this.maintenanceAuth.action === 'receipts' || this.maintenanceAuth.action === 'ledger') {
            this.fetchLedgerEntries();
            if (this.activeView === 'financeiro_recibos') {
                this.fetchLedgerEntriesForReceipts();
                this.fetchGeneratedReceipts();
            }
        }
        if (this.maintenanceAuth.action === 'forecast') {
            this.fetchForecastEntries();
        }
        if (this.maintenanceAuth.action === 'clinical') {
             if (this.activeView === 'agenda') this.fetchAppointments();
             if (this.activeView === 'active_services') this.fetchActiveServices();
        }
    } else {
        this.showToast('Falha', res.error, 'error');
    }
}

// --- FUNÇÕES DE AGENDA E DATAS (COMPARTILHADO) ---

export function addDisabledDate() {
    if (this.newDisabledDate) {
        // Determina qual objeto estamos editando (Admin->User ou User->Profile)
        const target = this.editingUser && this.activeAdminView === 'users' ? this.editingUser : this.editingProfile;
        
        if (!target.disabled_dates) target.disabled_dates = [];
        if (!target.disabled_dates.includes(this.newDisabledDate)) {
            target.disabled_dates.push(this.newDisabledDate);
            target.disabled_dates.sort();
        }
        this.newDisabledDate = '';
    }
}

export function removeDisabledDate(date) {
    const target = this.editingUser && this.activeAdminView === 'users' ? this.editingUser : this.editingProfile;
    if (target.disabled_dates) {
        target.disabled_dates = target.disabled_dates.filter(d => d !== date);
    }
}

// --- HELPERS E TEMPLATES ---

export function getNewUserTemplate() {
    return {
        id: null,
        name: '',
        email: '',
        professionalName: '',
        cpf: '',
        phone: '',
        profession: '',
        specialty: null,
        zip_code: '',
        street: '',
        street_number: '',
        neighborhood: '',
        city: '',
        state: '',
        status: 'active',
        isAdmin: 0,
        // Padrões de sistema
        finance_enabled: 1,
        agenda_enabled: 1,
        waiting_list_enabled: 0,
        future_schedule_enabled: 0,
        birthday_list_enabled: 0,
        memed_enabled: 0,
        odontogram_enabled: 0,
        system_version: 'Saude',
        weekly_schedule: this.ensureValidSchedule(null),
        disabled_dates: [],
        enabled_payment_methods: []
    };
}

export function ensureValidSchedule(schedule) {
    const defaultShift = { enabled: true, start: "08:00", end: "12:00", enabled2: true, start2: "14:00", end2: "18:00" };
    const disabledShift = { enabled: false, start: "08:00", end: "12:00", enabled2: false, start2: "14:00", end2: "18:00" };
    
    if (!schedule || typeof schedule !== 'object') {
        return {
            "0": { ...disabledShift },
            "1": { ...defaultShift },
            "2": { ...defaultShift },
            "3": { ...defaultShift },
            "4": { ...defaultShift },
            "5": { ...defaultShift },
            "6": { ...disabledShift }
        };
    }
    
    // Garante integridade
    for (let i = 0; i <= 6; i++) {
        if (!schedule[i]) {
            schedule[i] = (i === 0 || i === 6) ? { ...disabledShift } : { ...defaultShift };
        }
    }
    return schedule;
}

export function formatDateForInput(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    const offset = date.getTimezoneOffset() * 60000;
    const localISOTime = new Date(date.getTime() - offset).toISOString().slice(0, 16);
    return localISOTime;
}

// Lógica de confirmação ao desativar módulos (Lista de Espera / Agenda Futura)
export function handleWaitingListChange(e) {
    // Detecta alvo correto
    const target = this.editingUser && this.activeAdminView === 'users' ? this.editingUser : this.editingProfile;
    
    if (target.waiting_list_enabled == 0) {
        target.waiting_list_enabled = 1; // Reverte visualmente até confirmar
        
        this.showConfirmModal(
            "ATENÇÃO: Ao desativar a Agenda de Espera, a Agenda Futura também será desativada.\n\nAlém disso, TODOS os agendamentos pendentes na Agenda de Espera e na Agenda Futura serão EXCLUÍDOS PERMANENTEMENTE.\n\nDeseja confirmar esta ação?",
            async () => {
                this.hideConfirmModal();
                const res = await this.apiRequest('clearWaitingListAndFutureData', { userId: target.id });
                if (res.success) {
                    this.showToast('Dados Limpos', 'Registros de espera e agenda futura removidos.', 'success');
                    target.waiting_list_enabled = 0;
                    target.future_schedule_enabled = 0;
                } else {
                    this.showToast('Erro', res.error || 'Falha ao limpar dados.', 'error');
                    target.waiting_list_enabled = 1;
                }
            },
            'bg-red-600 hover:bg-red-700',
            'Sim, Desativar e Limpar'
        );
    }
}

export function handleFutureScheduleChange(e) {
    const target = this.editingUser && this.activeAdminView === 'users' ? this.editingUser : this.editingProfile;

    if (target.future_schedule_enabled == 0) {
        target.future_schedule_enabled = 1;
        
        this.showConfirmModal(
            "ATENÇÃO: Ao desativar a Agenda Futura, TODOS os agendamentos pendentes nela serão EXCLUÍDOS PERMANENTEMENTE.\n\nDeseja confirmar esta ação?",
            async () => {
                this.hideConfirmModal();
                const res = await this.apiRequest('clearFutureScheduleData', { userId: target.id });
                if (res.success) {
                    this.showToast('Dados Limpos', 'Registros da agenda futura removidos.', 'success');
                    target.future_schedule_enabled = 0;
                } else {
                    this.showToast('Erro', res.error || 'Falha ao limpar dados.', 'error');
                    target.future_schedule_enabled = 1;
                }
            },
            'bg-red-600 hover:bg-red-700',
            'Sim, Desativar e Limpar'
        );
    }
}