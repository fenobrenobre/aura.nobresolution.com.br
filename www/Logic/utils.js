export async function fetchPublicConfig() {
    const r = await this.apiRequest('getPublicConfig', {}, false, 'GET');
    if (r.success) {
        this.googleClientId = r.googleClientId;
        this.publicRegistrationNotes = r.settings.registration_notes || '';
        this.publicTrialDays = r.settings.default_trial_days || 15;
        this.professions = r.professions || [];
        this.publicAnamnesisTemplates = r.anamnesisTemplates || [];
        this.publicReceiptTemplates = r.receiptTemplates || [];
        this.publicBudgetForms = r.budgetForms || [];
        this.customFieldOptions = r.customFieldOptions || [];
        
        this.customFieldOptions.sort((a, b) => {
            if (a.field_type < b.field_type) return -1;
            if (a.field_type > b.field_type) return 1;
            if (a.is_default && !b.is_default) return -1;
            if (!a.is_default && b.is_default) return 1;
            return a.option_value.localeCompare(b.option_value);
        });
        
        if (!this.currentUser) {
            this.$nextTick(() => {
                if (window.google && this.googleClientId) {
                    try {
                        google.accounts.id.initialize({ client_id: this.googleClientId, callback: handleGoogleCredentialResponse });
                        const container = document.getElementById('google-button-container');
                        if (container && !container.hasChildNodes()) {
                            google.accounts.id.renderButton(container, { theme: "outline", size: "large", width: "100%" });
                        }
                    } catch(e) {
                        console.warn("Google Sign-In error:", e);
                    }
                }
            });
        }
    } else {
        this.professions = [{id: 0, name: 'Erro ao carregar'}];
        this.customFieldOptions = [];
    }
    return r;
}

export function startClockUpdater() {
    if (this.clockInterval) clearInterval(this.clockInterval);
    this.clockInterval = setInterval(() => {
        if (!this.currentUser || !this.currentUser.timezone) {
            this.currentTimeString = 'Fuso horário não definido.';
            return;
        }
        try {
            const now = new Date();
            const options = { timeZone: this.currentUser.timezone, hour: '2-digit', minute: '2-digit', second: '2-digit', day: '2-digit', month: '2-digit', year: 'numeric' };
            const formattedDate = now.toLocaleString('pt-BR', options);
            this.currentTimeString = `${formattedDate.split(' ')[1]} (${this.currentUser.timezone.split('/')[1].replace('_', ' ')}) ${formattedDate.split(' ')[0]}`;
        } catch (e) {
            this.currentTimeString = 'Fuso horário inválido.';
            clearInterval(this.clockInterval);
        }
    }, 1000);
}

export function startTrialCountdown() {
    if (this.countdownInterval) clearInterval(this.countdownInterval);
    if (!this.currentUser || !this.currentUser.deactivationDate) {
        this.trialCountdown = null;
        return;
    }
    // Fix Safari date format
    const targetDateStr = this.currentUser.deactivationDate.replace(' ', 'T');
    const endDate = new Date(targetDateStr).getTime();
    
    const updateCountdown = () => {
        const now = new Date().getTime();
        const distance = endDate - now;
        if (distance < 0) {
            this.trialCountdown = "Período de teste expirado.";
            clearInterval(this.countdownInterval);
            return;
        }
        const days = Math.floor(distance / (1000 * 60 * 60 * 24));
        const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((distance % (1000 * 60)) / 1000);
        let countdownStr = 'Expira em: ';
        if (days > 0) countdownStr += `${days}d `;
        countdownStr += `${String(hours).padStart(2, '0')}h ${String(minutes).padStart(2, '0')}m ${String(seconds).padStart(2, '0')}s`;
        this.trialCountdown = countdownStr;
    };
    updateCountdown();
    this.countdownInterval = setInterval(updateCountdown, 1000);
}

export function getNewRegisterTemplate() {
    return {
        name: '',
        professionalName: '',
        email: '',
        password: '',
        cpf: '',
        phone: '',
        birthdate: '',
        gender: null,
        marital_status: null,
        profession: '',
        specialty: null, 
        professional_register_type: '',
        professional_register_number: '',
        professional_register_uf: '',
        referred_by: '',
        zip_code: '',
        street: '',
        street_number: '',
        neighborhood: '',
        city: '',
        state: '',
        address_complement: '',
        timezone: 'America/Sao_Paulo',
        system_version: 'Saude',
        anamnesis_template_id: null,
        default_budget_form_identifier: 'Odontologico',
        future_schedule_enabled: 0,
        isDocumentInvalid: false
    };
}

export function getNewUserTemplate() {
    return {
        id: null,
        name: '',
        professionalName: '',
        email: '',
        password: '',
        cpf: '',
        phone: '',
        birthdate: '',
        gender: null,
        marital_status: null,
        profession: '',
        specialty: null,
        professional_register: '',
        professional_register_type: '',
        professional_register_number: '',
        professional_register_uf: '',
        referred_by: '',
        zip_code: '',
        street: '',
        street_number: '',
        neighborhood: '',
        city: '',
        state: '',
        address_complement: '',
        timezone: 'America/Sao_Paulo',
        status: 'active',
        deactivationDate: '',
        isAdmin: 0,
        admin_password: '',
        weekly_schedule: null,
        disabled_dates: [],
        reminder_email_hours: ['24'],
        birthday_email_time: '09:00',
        finance_enabled: 1,
        finance_ledger_enabled: 1,
        finance_forecast_enabled: 1,
        default_receipt_template_id: null,
        future_schedule_enabled: 0,
        agenda_enabled: 1,
        memed_enabled: 0,
        enabled_payment_methods: null,
        missed_appointment_tolerance: 60
    };
}

export function getNewPatientTemplate() {
    return {
        id: null,
        name: '',
        nickname: '',
        gender: null,
        birthdate: '',
        birth_place: '',
        cpf: '',
        rg: '',
        health_insurance: '',
        insurance_number: '',
        health_insurance_odont: '',
        insurance_number_odont: '',
        marital_status: null,
        responsible_name: '',
        responsible_cpf: '',
        parentage_father: '',
        parentage_mother: '',
        referred_by: '',
        phone: '',
        phone2: '',
        email: '',
        instagram: '',
        zip_code: '',
        street: '',
        street_number: '',
        address_complement: '',
        neighborhood: '',
        city: '',
        state: '',
        photo: null,
        isDocumentInvalid: false
    };
}

export function getDefaultWeeklySchedule() {
    const default_shift = {
        "enabled": true, "start": "08:00", "end": "12:00",
        "enabled2": true, "start2": "14:00", "end2": "18:00"
    };
    const disabled_shift = {
        "enabled": false, "start": "08:00", "end": "12:00",
        "enabled2": false, "start2": "14:00", "end2": "18:00"
    };

    return {
        "0": disabled_shift,
        "1": default_shift,
        "2": default_shift,
        "3": default_shift,
        "4": default_shift,
        "5": default_shift,
        "6": disabled_shift
    };
}

export function formatDateForInput(dateTimeString) {
    if (!dateTimeString) return '';
    try {
        const date = new Date(dateTimeString.replace(' ', 'T'));
        if (isNaN(date)) return '';
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        return `${year}-${month}-${day}T${hours}:${minutes}`;
    } catch (e) {
        return '';
    }
}

export function formatEntryDate(dateStr) {
    if (!dateStr) return '---';
    try {
        const date = new Date(dateStr.replace(' ', 'T'));
        if (isNaN(date.getTime())) return dateStr;
        return date.toLocaleDateString('pt-BR') + ' ' + date.toLocaleTimeString('pt-BR', {hour: '2-digit', minute:'2-digit'});
    } catch (e) {
        return dateStr;
    }
}

export function formatDateForDisabledList(dateStr) {
    if (!dateStr) return '';
    try {
        const d = dateStr.includes('T') ? dateStr : dateStr + 'T00:00:00';
        const date = new Date(d);
        if (isNaN(date.getTime())) return dateStr;
        return date.toLocaleDateString('pt-BR', { year: 'numeric', month: '2-digit', day: '2-digit' });
    } catch (e) { return dateStr; }
}

export function formatCurrency(value) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value || 0);
}

export async function fetchAddressByZipCode(target) {
    let zipCode = '';
    if (target === 'register') zipCode = this.registerForm.zip_code;
    else if (target === 'user') zipCode = this.editingUser.zip_code;
    else if (target === 'profile') zipCode = this.editingProfile.zip_code;
    else if (target === 'patient') zipCode = this.editingPatient.zip_code;

    const cleanCep = (zipCode || '').replace(/\D/g, '');
    if (cleanCep.length === 8) {
        try {
            const response = await fetch(`https://viacep.com.br/ws/${cleanCep}/json/`);
            const data = await response.json();
            if (!data.erro) {
                if (target === 'register') {
                    this.registerForm.street = data.logradouro;
                    this.registerForm.neighborhood = data.bairro;
                    this.registerForm.city = data.localidade;
                    this.registerForm.state = data.uf;
                } else if (target === 'user') {
                    this.editingUser.street = data.logradouro;
                    this.editingUser.neighborhood = data.bairro;
                    this.editingUser.city = data.localidade;
                    this.editingUser.state = data.uf;
                } else if (target === 'profile') {
                    this.editingProfile.street = data.logradouro;
                    this.editingProfile.neighborhood = data.bairro;
                    this.editingProfile.city = data.localidade;
                    this.editingProfile.state = data.uf;
                } else if (target === 'patient') {
                    this.editingPatient.street = data.logradouro;
                    this.editingPatient.neighborhood = data.bairro;
                    this.editingPatient.city = data.localidade;
                    this.editingPatient.state = data.uf;
                }
            } else {
                this.showToast('Aviso', 'CEP não encontrado.', 'error');
            }
        } catch (error) {
            this.showToast('Erro de Rede', 'Falha ao buscar CEP.', 'error');
        }
    }
}

export function validateDocument(doc, context) {
    // Remove formatação para validar apenas números
    const cleanDoc = (doc || '').replace(/\D/g, '');
    let isValid = true;
    
    if (cleanDoc.length === 11) {
        // Validação simples de CPF (apenas tamanho e dígitos repetidos)
        if (/^(\d)\1+$/.test(cleanDoc)) isValid = false;
    } else if (cleanDoc.length === 14) {
        // Validação simples de CNPJ
        if (/^(\d)\1+$/.test(cleanDoc)) isValid = false;
    } else if (cleanDoc.length > 0) {
        // Se tem algo digitado mas não tem tamanho 11 ou 14, é inválido
        isValid = false;
    }

    // Atualiza o estado de erro no contexto fornecido
    if (context === 'registerForm') {
        this.registerForm.isDocumentInvalid = !isValid;
    } else if (context === 'editingUser') {
        this.editingUser.isDocumentInvalid = !isValid;
    } else if (context === 'editingPatient') {
        this.editingPatient.isDocumentInvalid = !isValid;
    }
}

export function formatCPF_CNPJ(value) {
    if (!value) return "";
    const doc = value.replace(/[^\d]/g, "");
    
    if (doc.length <= 11) {
        return doc
            .replace(/(\d{3})(\d)/, '$1.$2')
            .replace(/(\d{3})(\d)/, '$1.$2')
            .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
    } else {
        return doc.substring(0, 14)
            .replace(/(\d{2})(\d)/, '$1.$2')
            .replace(/(\d{3})(\d)/, '$1.$2')
            .replace(/(\d{3})(\d)/, '$1/$2')
            .replace(/(\d{4})(\d{1,2})$/, '$1-$2');
    }
}

export function formatCEP(value) {
    if (!value) return "";
    const cep = value.replace(/[^\d]/g, "");
    return cep.substring(0, 8)
        .replace(/(\d{5})(\d{1,3})$/, '$1-$2');
}

export function formatPhone(value) {
    if (!value) return "";
    let phone = value.replace(/[^\d]/g, "");
    
    if (phone.length > 11) {
        phone = phone.substring(0, 11);
    }

    if (phone.length === 11) {
        return phone
            .replace(/(\d{2})(\d)/, '$1-$2')
            .replace(/(\d{5})(\d{1,4})$/, '$1-$2');
    } else {
        return phone.substring(0, 10)
            .replace(/(\d{2})(\d)/, '$1-$2')
            .replace(/(\d{4})(\d{1,4})$/, '$1-$2');
    }
}

export function startDateTimeUpdater() {
    const el = document.getElementById('datetime-container');
    if (el) {
        const u = () => {
            const now = new Date();
            const dateOptions = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            const timeOptions = { hour: '2-digit', minute: '2-digit', second: '2-digit' };
            
            const dateStr = now.toLocaleDateString('pt-BR', dateOptions);
            const timeStr = now.toLocaleTimeString('pt-BR', timeOptions);
            
            // Mantém o fuso horário original se já carregado
            const currentHTML = el.innerHTML;
            const timezoneMatch = currentHTML.match(/Fuso:.*$/);
            let timezonePart = timezoneMatch ? timezoneMatch[0] : '';
            
            el.innerHTML = `<i class="fa-solid fa-calendar-alt mr-1"></i> ${dateStr}, ${timeStr} <br> <i class="fa-solid fa-globe mr-1"></i> ${timezonePart}`;
        };
        setInterval(u, 60000);
        u();
    }
}

export function exportUsersToExcel() {
    if (this.users.length === 0) return this.showToast('Aviso', "Não há contratantes para exportar.", 'error');
    const data = this.users.map(u => ({
        'Nome': u.name,
        'Email': u.email,
        'Profissão': u.profession,
        'Status': u.status,
        'Desativação Programada': u.deactivationDate ? new Date(u.deactivationDate.replace(' ','T')).toLocaleString('pt-BR') : ''
    }));
    const ws = XLSX.utils.json_to_sheet(data);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, "Contratantes");
    XLSX.writeFile(wb, "Lista_de_Contratantes.xlsx");
}

export function exportPatientsToExcel() {
    if (this.patients.length === 0) return this.showToast('Aviso', `Não há ${this.labels.patients.toLowerCase()} para exportar.`, 'error');
    const data = this.patients.map(p => ({
        'Nome': p.name,
        'Apelido': p.nickname,
        'Sexo': p.gender,
        'Data Nasc.': p.birthdate ? new Date(p.birthdate + 'T00:00:00').toLocaleDateString('pt-BR') : '',
        'CPF': p.cpf,
        'RG': p.rg,
        'Telefone': p.phone,
        'Email': p.email,
        'CEP': p.zip_code,
        'Endereço': `${p.street || ''}, ${p.street_number || ''} ${p.address_complement || ''}`,
        'Bairro': p.neighborhood,
        'Cidade': p.city,
        'Estado': p.state,
        'Indicado Por': p.referred_by,
        'Convênio Médico': p.health_insurance,
        'Nº Conv. Médico': p.insurance_number,
        'Convênio Odonto': p.health_insurance_odont,
        'Nº Conv. Odonto': p.insurance_number_odont,
        'Filiação (Mãe)': p.parentage_mother,
        'Filiação (Pai)': p.parentage_father,
        'Responsável': p.responsible_name,
        'CPF Resp.': p.responsible_cpf
    }));
    const ws = XLSX.utils.json_to_sheet(data);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, this.labels.patients);
    XLSX.writeFile(wb, `Lista_de_${this.labels.patients}.xlsx`);
}

export function exportPatientTabData(tab, dataObject) {
    if (!dataObject || !dataObject.id) {
        return this.showToast('Aviso', "Abra um paciente salvo antes de exportar.", 'error');
    }
    let data, filename, sheetName;
    const patientName = (dataObject.name || 'Paciente').replace(/[^a-zA-Z0-9]/g, '_');
    
    switch (tab) {
        case 'main':
            data = [{ 
                'Nome': dataObject.name, 
                'Apelido': dataObject.nickname, 
                'Celular': dataObject.phone, 
                'Telefone 2': dataObject.phone2, 
                'Email': dataObject.email, 
                'Instagram': dataObject.instagram, 
                'Sexo': dataObject.gender, 
                'Estado Civil': dataObject.marital_status, 
                'Data Nasc.': dataObject.birthdate, 
                'Indicado Por': dataObject.referred_by 
            }];
            filename = `Dados_Principais_${patientName}.xlsx`;
            sheetName = 'Dados Principais';
            break;
        case 'docs':
            data = [{ 
                'CPF': dataObject.cpf, 
                'RG': dataObject.rg, 
                'Local Nasc.': dataObject.birth_place, 
                'Convênio Médico': dataObject.health_insurance, 
                'Nº Conv. Médico': dataObject.insurance_number, 
                'Convênio Odonto': dataObject.health_insurance_odont,
                'Nº Conv. Odonto': dataObject.insurance_number_odont,
                'Filiação (Mãe)': dataObject.parentage_mother,
                'Filiação (Pai)': dataObject.parentage_father,
                'Responsável': dataObject.responsible_name, 
                'CPF Resp.': dataObject.responsible_cpf 
            }];
            filename = `Documentacao_${patientName}.xlsx`;
            sheetName = 'Documentação';
            break;
        case 'contact':
            data = [{ 
                'CEP': dataObject.zip_code, 
                'Rua': dataObject.street, 
                'Nº': dataObject.street_number, 
                'Bairro': dataObject.neighborhood, 
                'Cidade': dataObject.city, 
                'Estado': dataObject.state, 
                'Complemento': dataObject.address_complement 
            }];
            filename = `Endereco_${patientName}.xlsx`;
            sheetName = 'Endereço e Contato';
            break;
        case 'anamnesis':
            data = [{ 'Anamnese': dataObject.anamnesisContent || '' }];
            filename = `Anamnese_${patientName}.xlsx`;
            sheetName = 'Anamnese';
            break;
        case 'evolution':
            data = (dataObject.clinical_history || []).filter(e => e.entry_type === 'EVOLUTION').map(e => ({ 'Data': this.formatEntryDate(e.created_at), 'Evolução': e.content }));
            filename = `Evolucao_Clinica_${patientName}.xlsx`;
            sheetName = 'Evolução Clínica';
            break;
        case 'exams':
            data = (dataObject.clinical_history || []).filter(e => e.entry_type === 'EXAM').map(e => ({ 'Data': this.formatEntryDate(e.created_at), 'Exame': e.content }));
            filename = `Exames_${patientName}.xlsx`;
            sheetName = 'Exames';
            break;
        default: return;
    }
    if(!Array.isArray(data) || data.length === 0){
        return this.showToast('Aviso', `Não há dados para exportar na aba "${tab}".`, 'error');
    }
    const worksheet = XLSX.utils.json_to_sheet(data);
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, sheetName);
    XLSX.writeFile(workbook, filename);
}


export function generateBackup() {
    if (this.currentUser) window.location.href = `${this.API_URL}?action=backupUserData&userId=${this.currentUser.id}`;
}

export function isToday(date) {
    if (!date || !(date instanceof Date)) return false;
    return new Date().toDateString() === date.toDateString();
}

export function checkPasswordStrength(password) {
    let score = 0;
    const feedback = [];
    if (!password || password.length === 0) {
        this.passwordStrength = 0;
        this.passwordFeedback = '';
        return;
    }
    if (password.length < 8) feedback.push('Curta (mínimo 8)');
    else if (password.length >= 8 && password.length <= 11) score += 1;
    else score += 2;
    if (/[a-z]/.test(password)) score += 1;
    else feedback.push('Falta minúscula');
    if (/[A-Z]/.test(password)) score += 1;
    else feedback.push('Falta maiúscula');
    if (/\d/.test(password)) score += 1;
    else feedback.push('Falta número');
    if (/[^a-zA-Z\d]/.test(password)) score += 1;
    else feedback.push('Falta símbolo');
    if (score <= 2) {
        this.passwordStrength = 1;
        this.passwordFeedback = 'Fraca';
    } else if (score <= 4) {
        this.passwordStrength = 2;
        this.passwordFeedback = 'Média';
    } else if (score <= 5) {
        this.passwordStrength = 3;
        this.passwordFeedback = 'Forte';
    } else {
        this.passwordStrength = 4;
        this.passwordFeedback = 'Muito Forte';
    }
     if (this.passwordStrength < 3 && feedback.length > 0) {
         this.passwordFeedback += ` (${feedback.slice(0, 2).join(', ')})`;
     }
}

export function importPriceList(event) {
    const file = event.target.files[0];
    if (!file) return;
    const tempListData = { itemsToImport: [] };
    const reader = new FileReader();
    reader.onload = async (e) => {
        try {
            const data = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, { type: 'array' });
            const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
            const headerMap = {
                "nome do procedimento": "name", "nome": "name",
                "categoria": "category",
                "custo": "cost", "valor": "cost", "preço": "cost",
                "tipo medida": "unit", "unidade": "unit"
            };
            const rawJsonData = XLSX.utils.sheet_to_json(firstSheet, { header: 1, range: 0 });

            if (!rawJsonData || rawJsonData.length < 2) throw new Error('Planilha vazia ou sem dados.');

            const headerRow = rawJsonData[0].map(h => String(h || '').toLowerCase().trim());
            const jsonData = rawJsonData.slice(1).map(row => {
                 let item = {};
                 headerRow.forEach((header, index) => {
                     const mappedKey = headerMap[header];
                     if (mappedKey) {
                         item[mappedKey] = row[index];
                     }
                 });
                 return item;
            });


            const validItems = jsonData.filter(item => item.name && typeof item.cost === 'number');

            if (validItems.length !== jsonData.length) {
                this.showToast('Aviso', `Algumas linhas foram ignoradas por falta de nome ou custo numérico. ${validItems.length} itens válidos encontrados.`, 'error', 6000);
            }
            if (validItems.length === 0) {
                throw new Error('Nenhum item válido encontrado no arquivo. Verifique cabeçalhos (Nome, Categoria, Custo, Tipo medida) e se o custo é numérico.');
            }
            tempListData.itemsToImport = validItems;

            this.editingPriceList = {
                id: null,
                name: file.name.replace(/\.(xlsx?|csv)$/i, ''),
                itemsToImport: tempListData.itemsToImport
            };
            if (this.currentUser.isAdmin) {
                this.editingPriceList.make_global = false;
                this.editingPriceList.user_id = null;
            }
            this.showModal('price-list-modal');

        } catch (err) {
            if (document.getElementById('price-list-modal')?.classList.contains('flex')) {
                this.hideModal('price-list-modal');
            }
            this.showToast('Erro de Leitura', `Não foi possível ler o arquivo. Verifique o formato e cabeçalhos (Nome, Categoria, Custo, Tipo medida). Detalhe: ${err.message}`, 'error', 10000);
        }
    };
    reader.onerror = () => {
        if (document.getElementById('price-list-modal')?.classList.contains('flex')) {
            this.hideModal('price-list-modal');
        }
        this.showToast('Erro de Arquivo', 'Ocorreu um erro ao tentar carregar o arquivo.', 'error');
    };
    reader.readAsArrayBuffer(file);
    event.target.value = '';
}


export function downloadPriceListTemplate() {
    const templateData = [
        { "Nome do Procedimento": "Exemplo Procedimento 1", "Categoria": "Cirurgia", "Custo": 150.00, "Tipo medida": "Serviço" },
        { "Nome do Procedimento": "Exemplo Item 2", "Categoria": "Material", "Custo": 25.50, "Tipo medida": "Unidade" }
    ];
    const ws = XLSX.utils.json_to_sheet(templateData, { header: ["Nome do Procedimento", "Categoria", "Custo", "Tipo medida"] });
    ws['!cols'] = [ {wch:30}, {wch:20}, {wch:10}, {wch:15} ];
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, "Modelo");
    XLSX.writeFile(wb, "Modelo_Tabela_Precos.xlsx");
}

export function ensureValidSchedule(schedule) {
    const defaultSchedule = this.getDefaultWeeklySchedule(); 

    if (typeof schedule === 'string') {
        try { schedule = JSON.parse(schedule); } catch (e) { schedule = null; }
    }

    if (typeof schedule !== 'object' || schedule === null) {
        return defaultSchedule;
    }
    
    let validSchedule = {};
    for (let i = 0; i < 7; i++) {
        const dayKey = String(i);
        const dayData = schedule[dayKey];
        const defaultDayData = defaultSchedule[dayKey];

        if (dayData && typeof dayData === 'object') {
            const enabled = typeof dayData.enabled === 'boolean' ? dayData.enabled : defaultDayData.enabled;
            const start = (typeof dayData.start === 'string' && /^\d{2}:\d{2}$/.test(dayData.start)) ? dayData.start : defaultDayData.start;
            const end = (typeof dayData.end === 'string' && /^\d{2}:\d{2}$/.test(dayData.end)) ? dayData.end : defaultDayData.end;
            
            const enabled2 = typeof dayData.enabled2 === 'boolean' ? dayData.enabled2 : defaultDayData.enabled2;
            const start2 = (typeof dayData.start2 === 'string' && /^\d{2}:\d{2}$/.test(dayData.start2)) ? dayData.start2 : defaultDayData.start2;
            const end2 = (typeof dayData.end2 === 'string' && /^\d{2}:\d{2}$/.test(dayData.end2)) ? dayData.end2 : defaultDayData.end2;

            validSchedule[dayKey] = { enabled, start, end, enabled2, start2, end2 };
            
        } else {
             validSchedule[dayKey] = defaultDayData;
        }
    }
    return validSchedule;
}


export function isDateDisabled(date) {
    if (!this.currentUser || !Array.isArray(this.currentUser.disabled_dates)) return false;
    const dateString = date.toLocaleDateString('en-CA');
    return this.currentUser.disabled_dates.includes(dateString);
}

export function isTimeSlotEnabled(date, time) {
     if (!date || !time) return false;
    const dayOfWeek = date.getDay();
    const schedule = this.currentUser?.weekly_schedule ? this.currentUser.weekly_schedule[dayOfWeek] : null;
    
    if (!schedule || this.isDateDisabled(date)) { 
        return false; 
    }
    
     if (!/^\d{2}:\d{2}$/.test(time)) {
         return false;
     }
     
    const [slotHour, slotMinute] = time.split(':').map(Number);
    const slotTotalMinutes = slotHour * 60 + slotMinute;

    let inTurno1 = false;
    let inTurno2 = false;

    if (schedule.enabled) {
         if (!/^\d{2}:\d{2}$/.test(schedule.start) || !/^\d{2}:\d{2}$/.test(schedule.end)) {
         } else {
            const [startHour, startMinute] = schedule.start.split(':').map(Number);
            const [endHour, endMinute] = schedule.end.split(':').map(Number);
            const startTotalMinutes = startHour * 60 + startMinute;
            const endTotalMinutes = endHour * 60 + endMinute;
            inTurno1 = (slotTotalMinutes >= startTotalMinutes && slotTotalMinutes < endTotalMinutes);
         }
    }
    
    if (schedule.enabled2) {
         if (!/^\d{2}:\d{2}$/.test(schedule.start2) || !/^\d{2}:\d{2}$/.test(schedule.end2)) {
         } else {
            const [startHour2, startMinute2] = schedule.start2.split(':').map(Number);
            const [endHour2, endMinute2] = schedule.end2.split(':').map(Number);
            const startTotalMinutes2 = startHour2 * 60 + startMinute2;
            const endTotalMinutes2 = endHour2 * 60 + endMinute2;
            inTurno2 = (slotTotalMinutes >= startTotalMinutes2 && slotTotalMinutes < endTotalMinutes2);
         }
    }

    return inTurno1 || inTurno2;
}


export function addDisabledDate() {
    if (this.newDisabledDate && !this.editingProfile.disabled_dates.includes(this.newDisabledDate)) {
        this.editingProfile.disabled_dates.push(this.newDisabledDate);
        this.editingProfile.disabled_dates.sort();
        this.newDisabledDate = '';
    } else if (this.editingProfile.disabled_dates.includes(this.newDisabledDate)) {
        this.showToast('Aviso', 'Esta data já foi adicionada.', 'error');
    }
}


export function removeDisabledDate(dateToRemove) {
    this.editingProfile.disabled_dates = this.editingProfile.disabled_dates.filter(date => date !== dateToRemove);
}

export function getDefaultOptionValue(fieldType, fallbackValue) {
    if (!Array.isArray(this.customFieldOptions)) {
        return fallbackValue;
    }
    
    const fallbackOption = this.customFieldOptions.find(opt =>
        opt.field_type === fieldType && opt.option_value === fallbackValue
    );
    if (fallbackOption) {
        return fallbackOption.option_value;
    }

    const defaultOption = this.customFieldOptions.find(opt =>
        opt.field_type === fieldType && opt.is_default === true
    );
    if (defaultOption) {
        return defaultOption.option_value;
    }

    const firstOption = this.customFieldOptions.find(opt => opt.field_type === fieldType);
    if (firstOption) {
        return firstOption.option_value;
    }

    return fallbackValue;
}

export function getOptionsByType(type) {
    if (!Array.isArray(this.customFieldOptions)) return [];

    let options = this.customFieldOptions.filter(opt => opt.field_type === type);

    if (type === 'payment_method' && this.currentUser && Array.isArray(this.currentUser.enabled_payment_methods)) {
        const enabledIds = this.currentUser.enabled_payment_methods.map(String); 
        
        if (enabledIds.length > 0) {
            options = options.filter(opt => {
                if (opt.is_default && opt.is_global) {
                    return true; 
                }
                return enabledIds.includes(String(opt.id));
            });
        }
    }


    if (type === 'payment_method') {
        options.sort((a, b) => {
            if (a.option_value === 'à vista') return -1;
            if (b.option_value === 'à vista') return 1;
            const matchA = a.option_value.match(/parcelado (\d+)x/i);
            const matchB = b.option_value.match(/parcelado (\d+)x/i);
            if (matchA && matchB) {
                return parseInt(matchA[1]) - parseInt(matchB[1]);
            }
            return a.option_value.localeCompare(b.option_value);
        });
    } else {
        options.sort((a, b) => {
             if (a.is_default && !b.is_default) return -1;
             if (!a.is_default && b.is_default) return 1;
             return a.option_value.localeCompare(b.option_value);
        });
    }
    return options;
}

export function getServiceStatusClass(status) {
    const statusInProgress = this.getDefaultOptionValue('service_status', 'Em Atendimento');
    const statusWaitingList = this.getDefaultOptionValue('service_status', 'Agenda Espera/Não Resolvidos');
    const statusFinalized = this.getDefaultOptionValue('service_status', 'Finalizado');
    
    const statusFuture = 'AGENDA FUTURA'; 
    const statusAgendado = 'AGENDADO';

    switch (status) {
        case statusInProgress: return 'bg-blue-100 text-blue-800 status-uppercase';
        case statusWaitingList: return 'bg-yellow-100 text-yellow-800 status-uppercase';
        case statusFinalized: return 'bg-red-100 text-red-800 status-uppercase';
        case statusAgendado: return 'bg-cyan-100 text-cyan-800 status-uppercase';
        case statusFuture: return 'bg-purple-100 text-purple-800 status-uppercase'; 
        case 'Em Atendimento': return 'bg-blue-100 text-blue-800 status-uppercase';
        case 'Agenda Espera/Não Resolvidos': return 'bg-yellow-100 text-yellow-800 status-uppercase';
        case 'Em Tratamento/Agendado': return 'bg-yellow-100 text-yellow-800 status-uppercase';
        case 'Finalizado': return 'bg-red-100 text-red-800 status-uppercase';
        case 'AGENDADO': return 'bg-cyan-100 text-cyan-800 status-uppercase';
        case 'AGENDA FUTURA': return 'bg-purple-100 text-purple-800 status-uppercase';
        default: return 'bg-gray-100 text-gray-800 status-uppercase';
    }
 }
 
export function getPaymentStatusClass(status) {
    const statusPaid = this.getDefaultOptionValue('payment_status', 'Pago(Total)');
    const statusPartial = this.getDefaultOptionValue('payment_status', 'Pago(Parcial)');
    const statusOpen = this.getDefaultOptionValue('payment_status', 'Em Aberto');

    switch (status) {
        case statusPaid: return 'bg-green-100 text-green-800 status-uppercase';
        case statusPartial: return 'bg-yellow-100 text-yellow-800 status-uppercase';
        case statusOpen: return 'bg-orange-100 text-orange-800 status-uppercase';
        case 'Pago(Total)': return 'bg-green-100 text-green-800 status-uppercase';
        case 'Pago(Parcial)': return 'bg-yellow-100 text-yellow-800 status-uppercase';
        case 'Em Aberto': return 'bg-orange-100 text-orange-800 status-uppercase';
        default: return 'bg-gray-100 text-gray-800 status-uppercase';
    }
 }

 export function showBudgetList() {
    this.activeView = 'budgets';
    this.activeBudgetTab = 'list';
    this.isSidebarOpen = false;
}

export async function openPatientQuickView(patientId) {
    if (!patientId) return;

    let patient = this.patients.find(p => p.id == patientId);

    if (patient) {
        if (patient.phone && patient.email) {
            this.quickViewPatient = { ...patient };
            this.showModal('patient-quick-view-modal');
            return;
        }
    }

    this.isLoading = true;
    try {
        const res = await this.apiRequest('getPatientDetails', { patientId }, false, 'GET');
        if (res.success && res.patient) {
            this.quickViewPatient = res.patient;
            this.showModal('patient-quick-view-modal');
        } else {
            this.showToast('Erro', res.error || 'Não foi possível carregar os dados do paciente.', 'error');
        }
    } finally {
        this.isLoading = false;
    }
}

export function getFutureScheduleItemClass(dateString) {
    if (!dateString) return '';
    
    try {
        const itemDate = new Date(dateString + 'T00:00:00');
        if (isNaN(itemDate.getTime())) return '';

        const today = new Date();
        today.setHours(0, 0, 0, 0);

        const in15Days = new Date(today.getTime() + 15 * 24 * 60 * 60 * 1000);

        if (itemDate <= in15Days) {
            return 'bg-red-50 hover:bg-red-100';
        }

        return '';
    } catch (e) {
        return '';
    }
}

export async function fetchEntryPaymentMethods() {
    this.entryPaymentMethods = [];
    const res = await this.apiRequest('getEntryPaymentMethods', {}, false, 'GET');
    if (res.success) {
        this.entryPaymentMethods = res.methods;
    } else {
        this.showToast('Erro', 'Falha ao carregar formas de pagamento da baixa.', 'error');
    }
}