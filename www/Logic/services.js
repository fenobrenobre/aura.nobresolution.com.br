// --- ATENDIMENTOS (SERVIÇOS) ---

export async function startServiceFromAppointment(appointmentId) {
    const res = await this.apiRequest('startServiceFromAppointment', { appointmentId });
    if (res.success) {
        this.showToast('Sucesso', 'Atendimento iniciado.', 'success');
        this.hideModal('appointment-modal');
        this.activeView = 'active_services';
        this.fetchActiveServices();
        this.fetchAllServices();
        this.fetchAppointments();
    }
}

export async function fetchActiveServices() {
    const res = await this.apiRequest('getActiveServices', {}, false, 'GET');
    if (res.success) {
        this.activeServices = res.services;
    } else {
        this.activeServices = [];
    }
}

export async function finishService(service, modalIdToClose = null) {
    this.serviceToFinish = service;
    this.modalToCloseAfterFinish = modalIdToClose;

    // ** LÓGICA CONDICIONAL **
    if (this.currentUser.waiting_list_enabled == 1) {
        // Se Agenda de Espera ATIVA -> Fluxo padrão (Reagendar ou Espera)
        this.showModal('finish-service-modal');
    } else {
        // Se Agenda de Espera DESATIVADA -> Novo Fluxo Direto (Atendimento ou Tratamento)
        this.showModal('finish-service-direct-modal');
    }
}

// Ponte para localizar o serviço ativo a partir de um agendamento
export function findAndFinishService(appt, modalIdToClose = null) {
    if (appt.active_service_id) {
        const serviceObj = {
            id: appt.active_service_id,
            patient_id: appt.patient_id,
            patient_name: appt.patient_name,
            appt_title: appt.title,
            appt_notes: appt.notes
        };
        this.finishService(serviceObj, modalIdToClose);
    } else {
        const service = this.activeServices.find(s => s.appointment_id == appt.id);
        
        if (service) {
             service.appt_title = appt.title;
             service.appt_notes = appt.notes;
             this.finishService(service, modalIdToClose);
        } else {
            this.showToast('Erro', 'Não foi possível localizar o atendimento ativo vinculado. Tente recarregar a página.', 'error');
        }
    }
}

// ** LÓGICA 1: COM AGENDA DE ESPERA (Waiting List Enabled) **
export async function confirmFinishService(reschedule) {
    if (!this.serviceToFinish) return;
    
    const service = this.serviceToFinish;
    
    // Status possíveis
    const statusFinalizado = this.getDefaultOptionValue('service_status', 'Finalizado');
    const statusEspera = this.getDefaultOptionValue('service_status', 'Agenda Espera/Não Resolvidos');
    
    // SE REAGENDAR: O ciclo fecha aqui ("Finalizado"), pois o paciente já vai para a agenda futura/principal.
    // SE NÃO REAGENDAR: O paciente fica pendente ("Agenda Espera"), pois não houve resolução de horário.
    const targetStatus = reschedule ? statusFinalizado : statusEspera;
    
    const res = await this.apiRequest('updateActiveService', {
        id: service.id,
        service_status: targetStatus,
    });

    if (res.success) {
        
        if (!reschedule) {
            // Envia para a Lista de Espera
            const reason = "Atendimento finalizado sem reagendamento imediato.";
            await this.apiRequest('addToWaitingList', {
                userId: this.currentUser.id,
                patientId: service.patient_id,
                reason: reason,
                serviceId: service.id
            });
            this.showToast('Info', 'Atendimento movido para Agenda Espera/Não Resolvidos.', 'info');
        } else {
            this.showToast('Sucesso', 'Atendimento finalizado.', 'success');
        }

        this.fetchActiveServices();
        this.fetchAllServices();
        this.fetchAppointments(); 
        this.fetchWaitingList(); 
        
        this.hideModal('finish-service-modal');
        
        if (this.modalToCloseAfterFinish) {
            this.hideModal(this.modalToCloseAfterFinish);
        }
        
        if (reschedule) {
             this.showToast('Info', 'Abrindo tela de reagendamento...', 'info');
             
             this.serviceAwaitingReschedule = { 
                id: service.id, 
                patient_id: service.patient_id, 
                patient_name: service.patient_name,
                appt_title: service.appt_title,
                appt_notes: service.appt_notes
             };
             
             this.reschedulingPatient = { serviceId: null, origin: null };

            const patientObj = { id: service.patient_id, name: service.patient_name };
            this.openAppointmentModal(null, null, null, patientObj);
            
            this.$nextTick(() => {
                if (service.appt_title) this.editingAppointment.title = service.appt_title;
                if (service.appt_notes) this.editingAppointment.notes = service.appt_notes;
            });
        }
    } else {
        this.showToast('Erro', res.error || 'Falha ao finalizar atendimento.', 'error');
    }
}

// ** LÓGICA 2: SEM AGENDA DE ESPERA (Novo Modal Direto) **
export async function confirmFinishDirect(type) {
    if (!this.serviceToFinish) return;
    const service = this.serviceToFinish;
    
    let targetStatus;
    let successMessage;

    if (type === 'treatment') {
        targetStatus = 'TRATAMENTO FINALIZADO';
        successMessage = 'Tratamento finalizado com sucesso!';
    } else {
        targetStatus = this.getDefaultOptionValue('service_status', 'Finalizado');
        successMessage = 'Atendimento finalizado com sucesso.';
    }

    const res = await this.apiRequest('updateActiveService', {
        id: service.id,
        service_status: targetStatus
    });

    if (res.success) {
        this.showToast('Sucesso', successMessage, 'success');
        this.hideModal('finish-service-direct-modal');
        
        if (this.modalToCloseAfterFinish) {
            this.hideModal(this.modalToCloseAfterFinish);
        }

        this.fetchActiveServices();
        this.fetchAllServices();
        this.fetchAppointments();
        // Não atualiza WaitingList aqui pois a função está desativada
    } else {
        this.showToast('Erro', res.error || 'Falha ao atualizar status.', 'error');
    }
}

export async function handleRescheduleCancellation() {
    if (!this.serviceAwaitingReschedule || !this.serviceAwaitingReschedule.id) return;

    const service = this.serviceAwaitingReschedule;
    
    const reason = "Atendimento finalizado, mas reagendamento cancelado na tela de novo agendamento.";
    const res = await this.apiRequest('addToWaitingList', {
        patientId: service.patient_id,
        reason: reason,
        serviceId: service.id
    });

    if (res.success) {
        this.showToast('Aviso', `Reagendamento não concluído. Paciente adicionado à Agenda de Espera para controle.`, 'warning', 5000);
        this.fetchWaitingList();
    } 
    
    this.serviceAwaitingReschedule = null;
    
    this.fetchActiveServices();
    this.fetchAllServices();
    this.fetchAppointments(); 
}


export async function fetchWaitingList() {
    if (this.currentUser && this.currentUser.waiting_list_enabled == 1) {
        const res = await this.apiRequest('getWaitingList', {}, false, 'GET');
        if (res.success) { this.waitingList = res.waitingList; }
    } else {
        this.waitingList = [];
    }
}

export async function handleAddToWaitingList(isAuto = false, customReason = null, serviceId = null) {
    let patientIdToAdd = null;
    let patientNameToAdd = null;
    let associatedServiceId = serviceId;

    if (isAuto) {
        if (!this.reschedulingPatient || !this.reschedulingPatient.id) {
            return;
        }
        patientIdToAdd = this.reschedulingPatient.id;
        patientNameToAdd = this.reschedulingPatient.name;
        associatedServiceId = this.reschedulingPatient.serviceId || serviceId; 
        this.reschedulingPatient = { serviceId: null, origin: null };
    } else {
        patientIdToAdd = this.manualWaitingList.patientId;
        patientNameToAdd = this.getPatientName(patientIdToAdd);
        associatedServiceId = this.manualWaitingList.serviceId || null;
    }
    
    const reason = customReason || `Adicionado à espera.`;
    
    const res = await this.apiRequest('addToWaitingList', { 
        patientId: patientIdToAdd, 
        reason: reason, 
        serviceId: associatedServiceId 
    });

    if (res.success) {
        if(!isAuto) {
            this.showToast('Feito', `${patientNameToAdd} foi adicionado(a) à Agenda Espera/Não Resolvidos.`, 'success');
        }
        this.fetchWaitingList();
    } else {
        // Silencioso se for auto
    }
}

export async function scheduleFromWaitingList(patient) {
    this.reschedulingPatient = { 
        id: patient.id, 
        name: patient.name, 
        origin: 'waitingList', 
        serviceId: patient.service_id || null,
        waitingListId: patient.waiting_list_id || null,
        oldAppointmentId: null 
    };

    if (patient.reason && patient.reason.includes('Não compareceu ao agendamento de')) {
        try {
            const datePart = patient.reason.split('de ')[1]; 
            if (datePart) {
                const res = await this.apiRequest('getPatientAppointments', { patientId: patient.id }, false, 'GET');
                if (res.success && res.appointments) {
                    const [d, m, y_hm] = datePart.split('/');
                    const [y, hm] = y_hm.split(' ');
                    const targetPrefix = `${y}-${m}-${d} ${hm}`;
                    
                    const foundAppt = res.appointments.find(a => {
                        return a.status === 'Não Compareceu' && a.start_time.startsWith(targetPrefix);
                    });
                    
                    if (foundAppt) {
                        this.reschedulingPatient.oldAppointmentId = foundAppt.id;
                    }
                }
            }
        } catch (e) {
            console.error("Erro ao vincular agendamento antigo:", e);
        }
    }

    this.openAppointmentModal(null, null, null, { id: patient.id, name: patient.name });
}

export function openStandaloneServiceModal(patient = null) {
    if (patient) {
        this.newStandaloneService = { 
            patient_id: patient.id, 
            description: '' 
        };
        this.standaloneServicePatientSearch = patient.name;
        this.standaloneServicePatientResults = [];
    } else {
        this.newStandaloneService = { patient_id: null, description: '' };
        this.standaloneServicePatientSearch = '';
        this.standaloneServicePatientResults = [...this.patients];
    }
    this.showModal('standalone-service-modal');
}

export function searchPatientsForStandaloneService() {
    if (!this.standaloneServicePatientSearch) {
        this.standaloneServicePatientResults = [...this.patients];
        return;
    }
    const searchTerm = this.standaloneServicePatientSearch.toLowerCase();
    this.standaloneServicePatientResults = this.patients.filter(p => 
        p.name.toLowerCase().startsWith(searchTerm) ||
        p.name.toLowerCase().includes(' ' + searchTerm)
    );
}

export function selectPatientForStandaloneService(patient) {
    this.newStandaloneService.patient_id = patient.id;
    this.standaloneServicePatientSearch = patient.name;
    this.standaloneServicePatientResults = [];
}

export async function createStandaloneService() {
    if (!this.newStandaloneService.patient_id || !this.newStandaloneService.description) {
        return this.showToast('Erro', 'Por favor, selecione um paciente e adicione uma descrição.', 'error');
    }
    const payload = { ...this.newStandaloneService };
    const res = await this.apiRequest('createActiveService', payload);
    if (res.success) {
        this.showToast('Sucesso', 'Atendimento avulso criado.', 'success');
        this.hideModal('standalone-service-modal');
        this.fetchActiveServices();
        this.fetchAllServices();
        if (this.activeView === 'patients' && this.activeClinicalTab === 'services' && this.editingClinicalData.id == payload.patient_id) {
            this.fetchPatientServices(payload.patient_id);
        }
    }
}

export function openAddToWaitingListModal() {
    this.manualWaitingList = { patientSearch: '', patientId: null, reason: '', searchResults: [] };
    this.manualWaitingList.searchResults = [...this.patients];
    this.showModal('add-to-waiting-list-modal');
}

export function searchPatientsForWaitingList() {
    if (!this.manualWaitingList.patientSearch) {
        this.manualWaitingList.searchResults = [...this.patients];
        return;
    }
    const searchTerm = this.manualWaitingList.patientSearch.toLowerCase();
    this.manualWaitingList.searchResults = this.patients.filter(p => 
        (
            p.name.toLowerCase().startsWith(searchTerm) ||
            p.name.toLowerCase().includes(' ' + searchTerm)
        ) || 
        (p.cpf && p.cpf.includes(searchTerm))
    );
}

export function selectPatientForWaitingList(patient) {
    this.manualWaitingList.patientId = patient.id;
    this.manualWaitingList.patientSearch = patient.name;
    this.manualWaitingList.searchResults = [];
}

export async function handleManualAddToWaitingList() {
    if (!this.manualWaitingList.patientId) {
        return this.showToast('Erro', `Selecione um ${this.labels.patient}.`, 'error');
    }
    const res = await this.apiRequest('addToWaitingList', { patientId: this.manualWaitingList.patientId, reason: this.manualWaitingList.reason || null, serviceId: null }); 
    if (res.success) {
        this.showToast('Sucesso', `${this.getPatientName(this.manualWaitingList.patientId)} adicionado(a) à Agenda Espera/Não Resolvidos.`, 'success');
        this.hideModal('add-to-waiting-list-modal');
        this.fetchWaitingList();
    }
}

export function finishTreatmentFromWaitingList(item) {
    this.itemToFinishTreatment = item;
    this.finishTreatmentReason = '';
    this.showModal('finish-treatment-reason-modal');
}

export async function confirmFinishTreatmentFromWaitingList() {
    if (!this.finishTreatmentReason.trim()) {
        this.showToast('Erro', 'Por favor, informe o motivo do término do tratamento.', 'error');
        return;
    }
    const item = this.itemToFinishTreatment;
    this.hideModal('finish-treatment-reason-modal');
    
    // Chama a função de exclusão passando o motivo
    await this.deleteFromWaitingList(item.id, true, false, item.service_id, item.waiting_list_id, this.finishTreatmentReason);
}

export async function deleteFromWaitingList(patientId, isFinishing = false, isMoving = false, serviceId = null, waitingListId = null, finishReason = null) {
    const patientName = this.getPatientName(patientId);
    
    const payload = { patientId: patientId };
    if (waitingListId) {
        payload.waiting_list_id = waitingListId;
    } else if (serviceId) {
        payload.serviceId = serviceId;
    }
    
    if (!isFinishing) {
        if (isMoving) {
            const res = await this.apiRequest('removeFromWaitingList', payload);
            if (res.success) {
                this.fetchWaitingList();
                this.fetchAllServices();
            }
            return;
        }

        this.showConfirmModal(`Tem certeza que deseja remover ${patientName} da Agenda Espera/Não Resolvidos? (Isso NÃO finaliza o tratamento).`, async () => {
            const res = await this.apiRequest('removeFromWaitingList', payload); 
            if (res.success) {
                this.showToast('Sucesso', `${patientName} removido(a) da agenda.`, 'success');
                this.fetchWaitingList();
                this.fetchAllServices();
            }
            this.hideConfirmModal();
        });
        return;
    }

    // Se for finalizar (isFinishing = true), removemos da lista primeiro
    const resRemove = await this.apiRequest('removeFromWaitingList', payload);
    
    if (!resRemove.success) {
        return;
    }
    
    const statusWaitingList = this.getDefaultOptionValue('service_status', 'Agenda Espera/Não Resolvidos');
    const statusTratamentoFinalizado = 'TRATAMENTO FINALIZADO'; 
    
    let serviceToFinish = serviceId ? this.allServices.find(s => s.id == serviceId) : null;

    // Se não achou pelo ID, tenta achar pelo paciente (menos preciso, mas fallback)
    if (!serviceToFinish) {
        serviceToFinish = this.allServices
            .filter(s => s.patient_id == patientId && s.service_status === statusWaitingList)
            .sort((a, b) => new Date(b.start_date.replace(' ', 'T')) - new Date(a.start_date.replace(' ', 'T')))[0];
    }

    if (serviceToFinish) {
        // Monta a nova descrição com o motivo
        let newDescription = serviceToFinish.description;
        if (finishReason) {
             newDescription += ` | Motivo do Término: ${finishReason}`;
        }

        const resUpdate = await this.apiRequest('updateActiveService', {
            id: serviceToFinish.id,
            service_status: statusTratamentoFinalizado, 
            description: newDescription
        });
        
        if (resUpdate.success) {
            if (!isMoving) {
                this.showToast('Sucesso', `${patientName} removido(a) da espera e tratamento #${serviceToFinish.id} marcado como 'TRATAMENTO FINALIZADO'.`, 'success');
            }
        } else {
             if (!isMoving) {
                this.showToast('Aviso', `${patientName} removido(a) da espera, mas houve erro ao atualizar o status do tratamento #${serviceToFinish.id}.`, 'warning');
            }
        }
    } else {
        if (!isMoving) {
            this.showToast('Sucesso', `${patientName} removido(a) da espera. (Nenhum tratamento ativo encontrado para atualizar status)`, 'success');
        }
    }

    this.fetchWaitingList();
    this.fetchAllServices();
}

export async function fetchAllServices() {
    const params = {
        page: this.historicalServicesPagination.currentPage,
        limit: this.historicalServicesPagination.itemsPerPage
    };
    
    const res = await this.apiRequest('getAllServices', params, false, 'GET');
    if (res.success) {
         this.allServices = res.services;
         // Se a API retornar totais, poderíamos atualizar a paginação aqui
    } else {
         this.allServices = [];
    }
}

export function sortHistoricalServices(column) {
    if (this.serviceSortBy === column) {
        this.serviceSortOrder = this.serviceSortOrder === 'asc' ? 'desc' : 'asc';
    } else {
        this.serviceSortBy = column;
        this.serviceSortOrder = (column === 'end_date' || column === 'start_date') ? 'desc' : 'asc';
    }
}

export function openEditHistoricalServiceModal(service) {
    if (!service || !service.id) return;
    
    if (service.service_status === this.getDefaultOptionValue('service_status', 'Em Atendimento')) {
        this.showToast('Aviso', 'Este atendimento ainda está ativo. Finalize-o primeiro pela lista de "Atendimentos Ativos".', 'error', 5000);
        return;
    }

    this.editingHistoricalService = JSON.parse(JSON.stringify(service));
    this.showModal('edit-historical-service-modal');
}

export async function updateHistoricalService() {
    const payload = {
        id: this.editingHistoricalService.id,
        description: this.editingHistoricalService.description,
        service_status: this.editingHistoricalService.service_status
    };

    const res = await this.apiRequest('updateActiveService', payload);

    if (res.success) {
        this.showToast('Sucesso', 'Atendimento atualizado.', 'success');
        this.hideModal('edit-historical-service-modal');
        this.fetchAllServices(); 
        
        if (payload.service_status === this.getDefaultOptionValue('service_status', 'Em Atendimento')) {
            this.fetchActiveServices();
        }
    } else {
        this.showToast('Erro', res.error || 'Falha ao atualizar atendimento.', 'error');
    }
}

// ** FUNÇÕES ADICIONAIS: HISTÓRICO DE PACIENTE **
export async function fetchPatientServices(patientId) {
    const res = await this.apiRequest('getPatientServices', { patientId }, false, 'GET');
    if (res.success) {
        this.patientServices = res.services;
    } else {
        this.patientServices = [];
    }
}

export async function fetchPatientServicesHistory(patientId) {
    const res = await this.apiRequest('getPatientServices', { patientId }, false, 'GET');
    if (res.success) {
        this.patientServicesHistory = res.services;
    } else {
        this.patientServicesHistory = [];
    }
}

// ** NOVAS FUNÇÕES PARA ATESTADOS E COMPROVANTES (com lógica de preferência e salvamento) **

// Renomeado de openCertificateOptionsModal para coincidir com user_5.php
export async function openCertificateOptionsModal(service, type) {
    // Reutiliza o objeto editingHistoricalService como container temporário
    this.editingHistoricalService = {
        ...service,
        certType: type, // 'atestado' ou 'comprovante' (corrigido 'declaracao' para coincidir)
        certActivity: '',
        certDays: ''
    };
    
    // Garante que os campos personalizados (atividades) estejam carregados
    if (!this.customFieldOptions || !this.customFieldOptions.some(opt => opt.field_type === 'activity_type')) {
        this.fetchCustomFieldOptions(); 
    }
    
    this.showModal('certificate-options-modal');
}

export async function generateCertificateDoc() {
    const data = this.editingHistoricalService;
    
    // 1. Identificar o ID do Template (Hierarquia: Usuário > Global > Nome)
    let templateId = null;
    let templateNameFallback = '';

    if (data.certType === 'atestado') {
        // Prioridade 1: Preferência do Usuário Logado (definida em Configurações > Sistema)
        if (this.currentUser && this.currentUser.default_atestado_template_id) {
            templateId = this.currentUser.default_atestado_template_id;
        } 
        // Prioridade 2: Preferência Global do Sistema (Configurada no Admin)
        else if (this.publicConfig && this.publicConfig.defaultTemplates && this.publicConfig.defaultTemplates.atestado) {
            templateId = this.publicConfig.defaultTemplates.atestado;
        }
        // Fallback
        templateNameFallback = 'Atestado Odontológico';
        
    } else { // Declaração/Comprovante
        // Prioridade 1: Preferência do Usuário Logado
        if (this.currentUser && this.currentUser.default_declaracao_template_id) {
            templateId = this.currentUser.default_declaracao_template_id;
        } 
        // Prioridade 2: Preferência Global do Sistema
        else if (this.publicConfig && this.publicConfig.defaultTemplates && this.publicConfig.defaultTemplates.declaracao) {
            templateId = this.publicConfig.defaultTemplates.declaracao;
        }
        // Fallback
        templateNameFallback = 'Comprovante de Comparecimento';
    }

    // Busca Templates (forçada se necessário)
    if (!this.prescriptionTemplates || this.prescriptionTemplates.length === 0) {
        await this.fetchPrescriptionTemplates();
    }

    let template = null;
    if (templateId) {
        template = this.prescriptionTemplates.find(t => t.id == templateId);
    }

    // Tenta achar pelo nome de fallback se não achou por ID
    if (!template) {
        template = this.prescriptionTemplates.find(t => t.title.toLowerCase() === templateNameFallback.toLowerCase());
    }
    
    if (!template) {
        this.showToast('Erro', `Modelo de documento "${templateNameFallback}" não encontrado. Configure nas suas preferências (Configurações > Sistema).`, 'error');
        return;
    }
    
    // 2. Preparar Dados
    let content = template.content || '';
    
    // Buscar paciente para detalhes (CPF, etc)
    let patient = this.patients.find(p => p.id == data.patient_id);
    if (!patient) {
        patient = { name: data.patient_name || 'Paciente', cpf: '---' };
    }
    
    const start = new Date(data.start_date);
    const end = data.end_date ? new Date(data.end_date) : new Date(); 
    
    const startTime = start.toLocaleTimeString('pt-BR', {hour: '2-digit', minute:'2-digit'});
    const endTime = end.toLocaleTimeString('pt-BR', {hour: '2-digit', minute:'2-digit'});
    const dateStr = start.toLocaleDateString('pt-BR');
    
    // 3. Substituições
    content = content.replace(/#Atividade/g, data.certActivity ? `${data.certActivity}` : '________________');
    content = content.replace(/#PessoaNomeCompleto/g, patient.name);
    content = content.replace(/#PessoaDocumento/g, patient.cpf ? `(CPF: ${patient.cpf})` : '');
    content = content.replace(/#AtendimentoHorarioInicio/g, startTime);
    content = content.replace(/#AtendimentoHorarioTermino/g, endTime);
    content = content.replace(/#AtendimentoData/g, dateStr);
    
    if (data.certType === 'atestado') {
        const days = parseInt(data.certDays);
        const repousoText = (days && days > 0) ? `permanecer em repouso por ${days} dia(s)` : 'retornar às atividades normais';
        content = content.replace(/#Repouso/g, repousoText);
    }
    
    // 4. Salvar no Banco de Dados (Histórico) [NOVA ETAPA CORRIGIDA]
    // Utiliza 'savePrescription' que é a rota correta no api.php
    this.isLoading = true;
    let newDocId = null;

    try {
        const payload = {
            userId: this.currentUser.id,
            patientId: data.patient_id, 
            type: data.certType === 'comprovante' ? 'declaracao' : data.certType, 
            items: [], 
            content: content,
            recommendations: ''
        };

        const saveRes = await this.apiRequest('savePrescription', payload);
        
        if (saveRes.success) {
            this.showToast('Sucesso', 'Documento salvo no histórico.', 'success');
            newDocId = saveRes.id;
            
            // Atualiza a lista de documentos se ela estiver visível no momento
            if (this.activeClinicalTab === 'documents' && this.editingClinicalData && this.editingClinicalData.id == data.patient_id) {
                setTimeout(() => {
                    this.fetchPatientPrescriptions(data.patient_id);
                }, 500);
            }
        } else {
            console.error('Erro ao salvar documento:', saveRes.error);
            this.showToast('Aviso', 'Documento gerado, mas houve erro ao salvar no histórico.', 'warning');
        }
    } catch (e) {
        console.error('Erro de rede ao salvar:', e);
    } finally {
        this.isLoading = false;
    }

    // 5. Imprimir
    const printData = {
        user: this.currentUser,
        patient: { name: patient.name, id: data.patient_id }, 
        document: {
            id: newDocId, // ID real do banco (se salvo) ou null
            type: data.certType === 'comprovante' ? 'declaracao' : data.certType,
            final_content: content,
            created_at: new Date().toISOString()
        }
    };

    sessionStorage.setItem('certificateToPrint', JSON.stringify(printData));
    const win = window.open('certificate_print.html', '_blank');
    
    if (!win) {
         this.showToast('Erro', 'Pop-up bloqueado. Permita pop-ups para imprimir.', 'error');
    }
    
    this.hideModal('certificate-options-modal');
}
// ... (Mantenha todo o código existente acima)

// ** NOVA FUNÇÃO: Adicione isto ao FINAL do arquivo services.js **

export async function generateCertificateFromHistory(service, type) {
    if (!service || !this.editingClinicalData) return;

    const patient = this.editingClinicalData;
    
    // Tratamento de data seguro para navegadores (Safari/Firefox exigem T no ISO)
    let dateStr = service.start_date || ''; 
    let timeStr = '00:00';

    if (service.start_date) {
        try {
            // Converte '2023-10-10 10:00:00' para '2023-10-10T10:00:00' para parsing correto
            const isoDate = service.start_date.replace(' ', 'T'); 
            const dateObj = new Date(isoDate);
            
            if (!isNaN(dateObj)) {
                dateStr = dateObj.toLocaleDateString('pt-BR');
                timeStr = dateObj.toLocaleTimeString('pt-BR', {hour: '2-digit', minute:'2-digit'});
            }
        } catch (e) { console.error("Erro conversão data", e); }
    }
    
    let content = '';
    let docTypeLabel = '';

    if (type === 'atestado') {
        docTypeLabel = 'Atestado Retroativo';
        content = `<p>Atesto para os devidos fins que o(a) Sr(a). <strong>${patient.name}</strong>`;
        if (patient.cpf) content += `, inscrito(a) no CPF sob nº ${patient.cpf}`;
        content += `, esteve sob meus cuidados profissionais no dia <strong>${dateStr}</strong> (Início às ${timeStr}), necessitando de afastamento de suas atividades na referida data.</p>`;
    } else {
        docTypeLabel = 'Declaração de Comparecimento';
        content = `<p>Declaro para os devidos fins que o(a) Sr(a). <strong>${patient.name}</strong>`;
        if (patient.cpf) content += `, inscrito(a) no CPF sob nº ${patient.cpf}`;
        content += `, compareceu a este consultório/clínica no dia <strong>${dateStr}</strong> às <strong>${timeStr}</strong> para realização de consulta/procedimento.</p>`;
    }

    this.showConfirmModal(`Gerar ${docTypeLabel} referente ao dia ${dateStr}?`, async () => {
        this.hideConfirmModal();
        
        this.isLoading = true;
        let newDocId = null;
        try {
            const saveRes = await this.apiRequest('savePrescription', {
                userId: this.currentUser.id,
                patient_id: patient.id,
                type: type === 'atestado' ? 'Atestado' : 'Declaração',
                final_content: content,
                active: 1
            });
            
            if (saveRes.success) {
                this.showToast('Sucesso', 'Documento gerado e salvo no histórico.', 'success');
                newDocId = saveRes.id;
                
                if (this.activeClinicalTab === 'documents') {
                     this.fetchPatientPrescriptions(patient.id);
                }
            }
        } catch(e) {
            console.error(e);
            this.showToast('Erro', 'Falha ao salvar documento.', 'error');
        } finally {
            this.isLoading = false;
        }
        
        const printData = {
            user: this.currentUser,
            patient: { ...patient }, 
            document: {
                id: newDocId,
                type: type === 'atestado' ? 'Atestado' : 'Declaração',
                final_content: content,
                created_at: new Date().toISOString()
            }
        };

        sessionStorage.setItem('certificateToPrint', JSON.stringify(printData));
        const win = window.open('certificate_print.html', '_blank');
        if (!win) this.showToast('Erro', 'Pop-up bloqueado.', 'error');
    });
}