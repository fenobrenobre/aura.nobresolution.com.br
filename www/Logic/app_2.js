async saveMeasurements() {
            // 1. Pega os dados que estão na tela
            const p = this.editingClinicalData; 

            // 2. Inicia o texto com os dados básicos
            let textoMedidas = `DADOS VITAIS: Peso: ${p.measure_weight || '?'}kg, Altura: ${p.measure_height || '?'}cm`;
            
            // 3. Lógica do IMC (Calcula somente se tiver peso e altura)
            if (p.measure_weight && p.measure_height) {
                const alturaMetros = p.measure_height / 100;
                const imc = (p.measure_weight / (alturaMetros * alturaMetros)).toFixed(2);
                
                // Define a classificação do IMC
                let classif = "";
                if (imc < 18.5) classif = "Abaixo do peso";
                else if (imc < 24.9) classif = "Peso normal";
                else if (imc < 29.9) classif = "Sobrepeso";
                else classif = "Obesidade";
                
                // Adiciona IMC e Classificação ao texto
                textoMedidas += `, IMC: ${imc} (${classif})`;
            }
            
            // 4. Concatena os outros sinais vitais se estiverem preenchidos
            if(p.measure_pa) textoMedidas += `, PA: ${p.measure_pa}`;
            if(p.measure_fc) textoMedidas += `, FC: ${p.measure_fc}bpm`;
            if(p.measure_fr) textoMedidas += `, FR: ${p.measure_fr}ipm`;
            if(p.measure_gc) textoMedidas += `, Glicemia: ${p.measure_gc}mg/dL`;
            if(p.measure_abd_circ) textoMedidas += `, Circ. Abd: ${p.measure_abd_circ}cm`;
            
            textoMedidas += "."; // Finaliza a frase

            // 5. INSERE O TEXTO NA EVOLUÇÃO (ANAMNESE)
            // Adiciona data e hora para manter o histórico organizado
            const dataHoje = new Date().toLocaleDateString('pt-BR');
            const horaAgora = new Date().toLocaleTimeString('pt-BR', {hour: '2-digit', minute:'2-digit'});
            const linhaNova = `\n[${dataHoje} ${horaAgora}] - ATUALIZAÇÃO SINAIS VITAIS:\n${textoMedidas}`;
            
            // Garante que o campo de texto não seja nulo antes de adicionar
            if(!this.editingClinicalData.anamnesisContent) {
                this.editingClinicalData.anamnesisContent = "";
            }
            this.editingClinicalData.anamnesisContent += linhaNova;

            // Se existir o campo de "Nova Evolução" (aba Evolução), preenche lá também
            if (typeof this.newEvolutionEntry !== 'undefined') {
                this.newEvolutionEntry = textoMedidas;
            }

            // 6. SALVA NO BANCO DE DADOS
            // Tenta chamar a função savePatient que deve estar disponível via ...patient
            if (typeof this.savePatient === 'function') {
                await this.savePatient(this.editingClinicalData, true);
                this.showToast('Sucesso', 'Medidas calculadas e salvas na evolução.', 'success');
            } else {
                // Caso não tenha savePatient importado (fallback), tenta uma chamada direta
                const r = await this.apiRequest('savePatient', this.editingClinicalData);
                if (r.success) {
                    this.showToast('Sucesso', 'Medidas salvas.', 'success');
                }
            }
        },

        // --- MÉTODOS LOCAIS E UTILITÁRIOS (Antigo app_2.js) ---

        async initSession() {
            try {
                const res = await this.apiRequest('initSession', {}, false, 'GET');
                if (res.success && res.csrf_token) {
                    sessionStorage.setItem('csrf_token', res.csrf_token);
                    console.log("Sessão (re)inicializada via AJAX.");
                }
            } catch (e) {
                console.warn("Falha ao inicializar sessão:", e);
            }
        },
        
        // --- FUNÇÃO DE REAGENDAMENTO (FALTOU) ---
        rescheduleMissedToWaitingList(appt) {
             // 1. Prepara o objeto de paciente para reagendamento
            this.reschedulingPatient = {
                id: appt.patient_id,
                name: appt.patient_name || this.editingClinicalData.name, // Fallback se estiver dentro do modal clínico
                serviceId: appt.service_id || null,
                origin: 'missed',
                oldAppointmentId: appt.id
            };
            
            // 2. Abre o modal de Agenda Futura
            this.openFutureScheduleModal();
        },

        // ** OVERRIDES PARA ADMIN E BUSCAS ESPECÍFICAS **
        
        // --- NOVO FLUXO DE CARTAS / "OUTROS" ---
        async openLettersSelectionModal(clinicalData) {
            this.editingClinicalData = clinicalData;
            this.isLoading = true;
            
            // Se ainda não carregou os templates, carrega agora
            if (!this.prescriptionTemplates || this.prescriptionTemplates.length === 0) {
                await this.fetchPrescriptionTemplates();
            }
            
            this.isLoading = false;
            this.showModal('letters-selection-modal');
        },

        getLettersTemplates() {
            if (!this.prescriptionTemplates) return [];
            // CORREÇÃO REFORÇADA: Filtra modelos do tipo 'outros' OU 'atestado'.
            return this.prescriptionTemplates.filter(t => 
                t.type === 'outro' || t.type === 'atestado'
            );
        },

        selectLetterTemplate(template) {
            this.hideModal('letters-selection-modal');
            
            // Configura o formulário para o tipo do modelo (outros/atestado)
            this.prescriptionForm.patient_id = this.editingClinicalData.id;
            this.prescriptionForm.type = template.type; 
            this.prescriptionForm.items = [];
            this.prescriptionForm.recommendations = '';
            
            // Insere o conteúdo do template escolhido como um item para edição
            this.prescriptionForm.items.push({
                name: template.title,
                presentation: '', 
                route: '', 
                instructions: template.content, // O texto do modelo vai aqui
                duration: ''
            });

            // Abre o modal de edição (Gerador)
            this.showModal('prescription-generator-modal');
        },
        // --- FIM DO FLUXO DE CARTAS ---

        async searchMedicines(query) {
            this.medicineSearchQuery = query;
            
            // Limpa se tiver menos de 3 caracteres
            if (query.length < 3) { 
                this.medicines = []; 
                return; 
            }

            // LÓGICA INTELIGENTE: Verifica o tipo de prescrição
            if (this.prescriptionForm.type === 'exame') {
                // --- MODO EXAME ---
                await this.fetchExams(query);
                
                this.medicines = this.exams.map(ex => ({
                    id: ex.id,
                    name: ex.name,
                    presentation: '',
                    instructions: ex.description || '',
                    source: 'local_exam'
                }));
                
            } else if (this.prescriptionForm.type === 'atestado' || this.prescriptionForm.type === 'outro') {
                // --- MODO ATESTADO / CARTA / OUTROS ---
                // Busca modelos já cadastrados do tipo Atestado ou Outros.
                
                if (!this.prescriptionTemplates || this.prescriptionTemplates.length === 0) {
                   await this.fetchPrescriptionTemplates();
                }

                const term = query.toLowerCase();
                const filteredTemplates = this.prescriptionTemplates.filter(t => 
                    (t.type === 'atestado' || t.type === 'outro') && 
                    t.title.toLowerCase().includes(term)
                );
                
                this.medicines = filteredTemplates.map(t => ({
                    id: t.id,
                    name: t.title,
                    presentation: 'Modelo', 
                    instructions: t.content,
                    source: 'letter_template'
                }));
            
            } else {
                // --- MODO RECEITA (Padrão) ---
                // Busca medicamentos
                await this.fetchMedicines(query);
            }
        },
        
        // Sobrescreve para garantir carregamento dos templates ao abrir o modal
        openPrescriptionGenerator(clinicalData, type) {
            this.prescriptionForm.patient_id = clinicalData.id;
            this.prescriptionForm.type = type; 
            this.prescriptionForm.items = [];
            this.prescriptionForm.recommendations = '';
            
            // Limpa variáveis temporárias
            this.tempPrescriptionItem = { name: '', presentation: '', route: '', instructions: '', duration: '' };
            this.medicines = [];
            this.medicineSearchQuery = '';
            
            // Carrega dependências
            this.fetchPrescriptionTemplates();
            this.fetchRecommendationTemplates();
            
            this.showModal('prescription-generator-modal');
        },
        
        async fetchMedicines(arg) {
            const isAdmin = arg === 'admin';
            const search = typeof arg === 'string' && !isAdmin ? arg : '';
            const endpoint = isAdmin ? 'getAdminMedicines' : 'getMedicines';
            const params = isAdmin ? { adminId: this.currentUser.id } : { search }; 
            
            const r = await this.apiRequest(endpoint, params, false, 'GET');
            if (r.success) {
                this.medicines = isAdmin ? r.items : r.medicines;
            }
        },
        async fetchExams(arg) {
            const isAdmin = arg === 'admin';
            const search = typeof arg === 'string' && !isAdmin ? arg : '';
            const endpoint = isAdmin ? 'getAdminExams' : 'getExams';
            const params = isAdmin ? { adminId: this.currentUser.id } : { search }; 
            
            const r = await this.apiRequest(endpoint, params, false, 'GET');
            if (r.success) {
                this.exams = isAdmin ? r.items : r.exams;
            }
        },
        async fetchPrescriptionTemplates(arg) {
            const isAdmin = arg === 'admin';
            const endpoint = isAdmin ? 'getAdminPrescriptionTemplates' : 'getPrescriptionTemplates';
            const params = isAdmin ? { adminId: this.currentUser.id } : {}; 
            
            const r = await this.apiRequest(endpoint, params, false, 'GET');
            if (r.success) {
                this.prescriptionTemplates = isAdmin ? r.items : r.templates;
            }
        },

        // --- NAVEGAÇÃO DE ABAS (CADASTRO) ---
        nextRegisterTab() {
            if (this.activeRegisterTab === 'rules') {
                this.activeRegisterTab = 'main';
            } else if (this.activeRegisterTab === 'main') {
                if (!this.isRegisterTabMainValid) {
                    this.showToast('Campos Pendentes', 'Por favor, preencha todos os campos obrigatórios (*) da aba "Dados Principais". Verifique se a senha é forte.', 'error', 6000);
                    return;
                }
                this.activeRegisterTab = 'docs';
            } else if (this.activeRegisterTab === 'docs') {
                if (!this.isRegisterTabDocsValid) {
                    this.showToast('Campos Pendentes', 'Por favor, preencha todos os campos obrigatórios (*) da aba "Documentação".', 'error');
                    return;
                }
                this.activeRegisterTab = 'contact';
            } else if (this.activeRegisterTab === 'contact') {
                if (!this.isRegisterTabContactValid) {
                    this.showToast('Campos Pendentes', 'Por favor, preencha todos os campos obrigatórios (*) da aba "Endereço/Contato". Verifique o CEP.', 'error');
                    return;
                }
                this.activeRegisterTab = 'custom';
            }
        },
        prevRegisterTab() {
            if (this.activeRegisterTab === 'custom') {
                this.activeRegisterTab = 'contact';
            } else if (this.activeRegisterTab === 'contact') {
                this.activeRegisterTab = 'docs';
            } else if (this.activeRegisterTab === 'docs') {
                this.activeRegisterTab = 'main';
            } else if (this.activeRegisterTab === 'main') {
                this.activeRegisterTab = 'rules';
            }
        },

        // --- PAGINAÇÃO GENÉRICA ---
        prevPage() {
            if (this.pagination.currentPage > 1) {
                this.pagination.currentPage--;
            }
        },
        nextPage() {
            if (this.pagination.currentPage < this.totalPages) {
                this.pagination.currentPage++;
            }
        },
        
        activeServices_prevPage() {
            if (this.activeServicesPagination.currentPage > 1) {
                this.activeServicesPagination.currentPage--;
            }
        },
        activeServices_nextPage() {
            if (this.activeServicesPagination.currentPage < this.activeServicesTotalPages) {
                this.activeServicesPagination.currentPage++;
            }
        },
        historical_prevPage() {
            if (this.historicalServicesPagination.currentPage > 1) {
                this.historicalServicesPagination.currentPage--;
            }
        },
        historical_nextPage() {
            if (this.historicalServicesPagination.currentPage < this.historicalServicesTotalPages) {
                this.historicalServicesPagination.currentPage++;
            }
        },
        
        budget_prevPage() {
            if (this.budgetPagination.currentPage > 1) {
                this.budgetPagination.currentPage--;
            }
        },
        budget_nextPage() {
            if (this.budgetPagination.currentPage < this.budgetTotalPages) {
                this.budgetPagination.currentPage++;
            }
        },
        
        receipt_prevPage(type) {
            if (type === 'pending' && this.receiptPaginationPending.currentPage > 1) {
                this.receiptPaginationPending.currentPage--;
                this.fetchLedgerEntriesForReceipts();
            } else if (type === 'generated' && this.receiptPaginationGenerated.currentPage > 1) {
                this.receiptPaginationGenerated.currentPage--;
                this.fetchGeneratedReceipts();
            }
        },
        receipt_nextPage(type) {
            if (type === 'pending' && this.receiptPaginationPending.currentPage < this.pendingReceipts.totalPages) {
                this.receiptPaginationPending.currentPage++;
                this.fetchLedgerEntriesForReceipts();
            } else if (type === 'generated' && this.receiptPaginationGenerated.currentPage < this.generatedReceipts.totalPages) {
                this.receiptPaginationGenerated.currentPage++;
                this.fetchGeneratedReceipts();
            }
        },

        // --- HISTÓRICO DE AGENDAMENTOS ---
        appointment_history_prevPage() {
            if (this.appointmentHistoryPagination.currentPage > 1) {
                this.appointmentHistoryPagination.currentPage--;
                this.fetchGlobalAppointments();
            }
        },
        appointment_history_nextPage() {
            if (this.appointmentHistoryPagination.currentPage < this.appointmentHistoryTotalPages) {
                this.appointmentHistoryPagination.currentPage++;
                this.fetchGlobalAppointments();
            }
        },

        async fetchGlobalAppointments() {
            const params = {
                search: this.appointmentHistoryFilters.search,
                status: this.appointmentHistoryFilters.status,
                page: this.appointmentHistoryPagination.currentPage,
                limit: this.appointmentHistoryPagination.itemsPerPage,
                sort: this.appointmentHistoryFilters.sortBy,
                order: this.appointmentHistoryFilters.sortOrder
            };
            
            this.isLoading = true;
            const res = await this.apiRequest('getAllAppointments', params, false, 'GET');
            this.isLoading = false;
            
            if (res.success) {
                this.globalAppointments = res.appointments;
                this.appointmentHistoryTotal = res.total;
                this.appointmentHistoryTotalPages = res.totalPages;
            } else {
                this.globalAppointments = [];
                this.appointmentHistoryTotal = 0;
                this.appointmentHistoryTotalPages = 1;
            }
        },

        sortGlobalAppointments(column) {
            if (this.appointmentHistoryFilters.sortBy === column) {
                this.appointmentHistoryFilters.sortOrder = this.appointmentHistoryFilters.sortOrder === 'asc' ? 'desc' : 'asc';
            } else {
                this.appointmentHistoryFilters.sortBy = column;
                this.appointmentHistoryFilters.sortOrder = 'desc';
            }
            this.appointmentHistoryPagination.currentPage = 1;
            this.fetchGlobalAppointments();
        },

        debouncedSearchWaitingList() {
            if (this.waitingListSearchTimeout) clearTimeout(this.waitingListSearchTimeout);
            this.waitingListSearchTimeout = setTimeout(() => {
                // Busca reativa já tratada no computed sortedWaitingList
            }, 400);
        },

        sortWaitingList(column) {
            if (this.waitingListFilters.sortBy === column) {
                this.waitingListFilters.sortOrder = this.waitingListFilters.sortOrder === 'asc' ? 'desc' : 'asc';
            } else {
                this.waitingListFilters.sortBy = column;
                this.waitingListFilters.sortOrder = (column === 'added_at') ? 'asc' : 'asc';
            }
        },

        sortFutureSchedule(column) {
            if (this.futureScheduleFilters.sortBy === column) {
                this.futureScheduleFilters.sortOrder = this.futureScheduleFilters.sortOrder === 'asc' ? 'desc' : 'asc';
            } else {
                this.futureScheduleFilters.sortBy = column;
                this.futureScheduleFilters.sortOrder = (column === 'return_date') ? 'asc' : 'asc';
            }
        },

        getSortIcon(listName, column) {
            let filters;
            if (listName === 'waitingList') {
                filters = this.waitingListFilters;
            } else if (listName === 'futureSchedule') {
                filters = this.futureScheduleFilters;
            } else if (listName === 'globalAppointments') {
                filters = this.appointmentHistoryFilters;
            } else {
                return 'fa-solid fa-sort';
            }

            if (filters.sortBy !== column) {
                return 'fa-solid fa-sort';
            }
            return filters.sortOrder === 'asc' ? 'fa-solid fa-sort-up' : 'fa-solid fa-sort-down';
        },

        // --- AGENDA FUTURA ---
        async fetchFutureSchedule() {
            if (!this.currentUser) return;
            const params = {
                search: this.futureScheduleFilters.search || '',
                page: this.futureSchedulePagination.currentPage,
                limit: this.futureSchedulePagination.itemsPerPage
            };
            const res = await this.apiRequest('getFutureSchedule', params, false, 'GET');
            if (res.success) {
                this.futureScheduleList = res.schedule;
                this.futureScheduleTotalPages = res.totalPages || 1;
                this.futureScheduleTotal = res.total || 0;
            } else {
                this.futureScheduleList = [];
                this.futureScheduleTotalPages = 1;
                this.futureScheduleTotal = 0;
            }
        },
        debouncedFetchFutureSchedule() {
            if (this.futureScheduleSearchTimeout) clearTimeout(this.futureScheduleSearchTimeout);
            this.futureScheduleSearchTimeout = setTimeout(() => {
                this.futureSchedulePagination.currentPage = 1;
                this.fetchFutureSchedule();
            }, 400);
        },
        async openFutureScheduleModal(item = null) { 
            let patientName = '';
            
            if (item) {
                this.agendaDate = new Date(item.return_date + 'T00:00:00');
            } else {
                this.agendaDate = new Date();
            }
            await this.fetchAppointments(); 

            if (item) {
                patientName = this.getPatientName(item.patient_id) || 'Paciente';
                this.futureScheduleForm = {
                    id: item.id,
                    patient_id: item.patient_id,
                    patient_name: patientName,
                    service_id: item.service_id,
                    return_date: item.return_date,
                    reason: item.reason || '',
                    origin: null,
                    waiting_list_id: null
                };
            } else {
                if (!this.reschedulingPatient || !this.reschedulingPatient.id) {
                    this.showToast('Erro', 'Não há paciente selecionado para agendar o futuro.', 'error');
                    return;
                }
                patientName = this.reschedulingPatient.name || 'Paciente';
                this.futureScheduleForm = {
                    id: null,
                    patient_id: this.reschedulingPatient.id,
                    patient_name: patientName,
                    service_id: this.reschedulingPatient.serviceId,
                    return_date: new Date().toLocaleDateString('en-CA'),
                    reason: '',
                    origin: this.reschedulingPatient.origin || 'service',
                    waiting_list_id: null
                };
            }
            this.hideModal('reschedule-modal');
            this.showModal('future-schedule-modal');
        },
        async openFutureScheduleModalFromWaitingList(item) { 
             if (!item || !item.id) {
                this.showToast('Erro', 'Não há paciente selecionado para agendar o futuro.', 'error');
                return;
            }
            
            this.agendaDate = new Date(); 
            await this.fetchAppointments(); 
            
            this.futureScheduleForm = {
                id: null,
                patient_id: item.id,
                patient_name: item.name || 'Paciente',
                service_id: item.service_id || null,
                return_date: new Date().toLocaleDateString('en-CA'),
                reason: item.reason || '', 
                origin: 'waitingList',
                waiting_list_id: item.waiting_list_id
            };
            this.showModal('future-schedule-modal');
        },
        async handleSaveFutureSchedule() {
            if (!this.futureScheduleForm.return_date) {
                this.showToast('Erro', 'A data de retorno é obrigatória.', 'error');
                return;
            }
            const payload = { ...this.futureScheduleForm };
            const res = await this.apiRequest('saveFutureScheduleEntry', payload);
            
            if (res.success) {
                this.showToast('Sucesso', 'Agenda futura salva.', 'success');
                this.hideModal('future-schedule-modal');
                
                const origin = this.futureScheduleForm.origin;

                this.reschedulingPatient = { serviceId: null, origin: null };
                this.futureScheduleForm = { id: null, patient_id: null, patient_name: '', service_id: null, return_date: '', reason: '', origin: null, waiting_list_id: null };

                if (this.activeView === 'future_schedule') {
                    this.fetchFutureSchedule();
                }
                
                if (origin === 'waitingList') {
                    this.fetchWaitingList(); 
                }
            }
        },
        async deleteFutureScheduleEntry(id) {
            this.showConfirmModal('Tem certeza que deseja excluir esta programação da agenda futura?', async () => {
                this.hideConfirmModal();
                const res = await this.apiRequest('deleteFutureScheduleEntry', { id });
                if (res.success) {
                    this.showToast('Sucesso', 'Programação excluída.', 'success');
                    this.fetchFutureSchedule();
                }
            });
        },
        setFutureDate(monthsToAdd) {
            const today = new Date();
            today.setDate(1); 
            today.setMonth(today.getMonth() + monthsToAdd);
            this.futureScheduleForm.return_date = today.toLocaleDateString('en-CA');
        },
        
        future_prevPage() {
            if (this.futureSchedulePagination.currentPage > 1) {
                this.futureSchedulePagination.currentPage--;
                this.fetchFutureSchedule();
            }
        },
        future_nextPage() {
            if (this.futureSchedulePagination.currentPage < this.futureScheduleTotalPages) {
                this.futureSchedulePagination.currentPage++;
                this.fetchFutureSchedule();
            }
        },

        // --- EXPORTAÇÕES ---
        
        exportAgendaWeekToXLS() {
            if (this.agendaView !== 'week' || !this.appointments || this.appointments.length === 0) {
                this.showToast('Erro', 'Não há agendamentos para exportar na semana atual.', 'error');
                return;
            }

            const dataToExport = [];
            
            const appointmentsToExport = [...this.appointments].sort((a, b) => a.start - b.start);

            appointmentsToExport.forEach(appt => {
                const statusInfo = this.getAppointmentStatusLabel(appt);
                dataToExport.push({
                    'Data': appt.start.toLocaleDateString('pt-BR'),
                    'Início': appt.start.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }),
                    'Fim': appt.end.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }),
                    'Paciente': appt.patient_name || 'Sem paciente',
                    'Título/Descrição': appt.title,
                    'Status': statusInfo.label,
                    'Notas': appt.notes || ''
                });
            });

            if (dataToExport.length === 0) {
                this.showToast('Aviso', 'Nenhum agendamento encontrado na semana visível para exportar.', 'error');
                return;
            }

            const ws = XLSX.utils.json_to_sheet(dataToExport);
            ws['!cols'] = [ { wch: 12 }, { wch: 10 }, { wch: 10 }, { wch: 30 }, { wch: 40 }, { wch: 15 }, { wch: 40 } ];

            const wb = XLSX.utils.book_new();
            const sheetName = `Agenda_${this.agendaTitle.replace(/[^a-zA-Z0-9]/g, '_')}`;
            XLSX.utils.book_append_sheet(wb, ws, sheetName);
            XLSX.writeFile(wb, `Agenda_Semanal_${this.agendaDate.toLocaleDateString('en-CA')}.xlsx`);
        },

        async exportAgendaMonthToXLS() {
            if (this.agendaView !== 'day') return;
            
            const month = this.agendaDate.getMonth() + 1;
            const year = this.agendaDate.getFullYear();

            this.isLoading = true;
            const res = await this.apiRequest('getMonthlyAppointments', { month, year }, false, 'GET');
            this.isLoading = false;

            if (!res.success || !res.appointments || res.appointments.length === 0) {
                this.showToast('Erro', res.error || 'Nenhum agendamento encontrado para exportar no mês atual.', 'error');
                return;
            }

            const dataToExport = res.appointments.map(appt => {
                const apptStart = new Date(appt.start_time.replace(' ', 'T'));
                const apptEnd = new Date(appt.end_time.replace(' ', 'T'));
                
                let statusLabel = 'Agendado';
                if (appt.status === 'Cancelado') {
                    statusLabel = 'Cancelado';
                } else if (appt.service_status) {
                    statusLabel = appt.service_status;
                }

                return {
                    'Data': apptStart.toLocaleDateString('pt-BR'),
                    'Início': apptStart.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }),
                    'Fim': apptEnd.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }),
                    'Paciente': appt.patient_name || 'Sem paciente',
                    'Título/Descrição': appt.title,
                    'Status': statusLabel,
                    'Notas': appt.notes || ''
                };
            });
            
            const ws = XLSX.utils.json_to_sheet(dataToExport);
            ws['!cols'] = [ { wch: 12 }, { wch: 10 }, { wch: 10 }, { wch: 30 }, { wch: 40 }, { wch: 15 }, { wch: 40 } ];
            const wb = XLSX.utils.book_new();
            const sheetName = `Agenda_${String(month).padStart(2, '0')}_${year}`;
            XLSX.utils.book_append_sheet(wb, ws, sheetName);
            XLSX.writeFile(wb, `Agenda_Mensal_${year}_${String(month).padStart(2, '0')}.xlsx`);
        },

        exportWaitingListToXLS() {
            if (this.waitingList.length === 0) {
                return this.showToast('Aviso', 'Não há pacientes na Agenda Espera para exportar.', 'error');
            }
            
            const dataToExport = this.waitingList.map(item => {
                return {
                    'Paciente': item.name,
                    'Adicionado Em': new Date(item.added_at.replace(' ', 'T')).toLocaleDateString('pt-BR'),
                    'Telefone': item.phone || '---',
                    'CPF': item.cpf || '---',
                    'Motivo': item.reason || 'N/D'
                };
            });

            const ws = XLSX.utils.json_to_sheet(dataToExport);
            ws['!cols'] = [ {wch:30}, {wch:15}, {wch:20}, {wch:20}, {wch:40} ];
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, "Agenda_Espera");
            XLSX.writeFile(wb, "Agenda_Espera.xlsx");
        },
        exportFutureScheduleToXLS() {
            if (this.futureScheduleList.length === 0) {
                return this.showToast('Aviso', 'Não há pacientes na Agenda Futura (nos filtros atuais) para exportar.', 'error');
            }
            
            const dataToExport = this.futureScheduleList.map(item => {
                return {
                    'Paciente': item.patient_name,
                    'Data Programada': this.formatDateForDisabledList(item.return_date),
                    'Observações': item.reason || '---'
                };
            });

            const ws = XLSX.utils.json_to_sheet(dataToExport);
            ws['!cols'] = [ {wch:30}, {wch:20}, {wch:40} ];
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, "Agenda_Futura");
            XLSX.writeFile(wb, "Agenda_Futura_Export.xlsx");
        },

        exportHistoricalServicesToXLS() {
            const dataToExport = this.allServices.map(service => {
                return {
                    'Paciente': service.patient_name,
                    'Início': this.formatEntryDate(service.start_date),
                    'Conclusão': service.end_date ? this.formatEntryDate(service.end_date) : '---',
                    'Status Atendimento': service.service_status,
                    'Descrição': service.description,
                    'ID Orçam.': service.budget_id || '',
                    'ID Agend.': service.appointment_id || ''
                };
            });
            
            if (dataToExport.length === 0) {
                return this.showToast('Aviso', 'Nenhum atendimento no histórico para exportar.', 'error');
            }

            const ws = XLSX.utils.json_to_sheet(dataToExport);
            ws['!cols'] = [ {wch:30}, {wch:20}, {wch:20}, {wch:25}, {wch:40}, {wch:10}, {wch:10} ];
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, "Histórico Atendimentos");
            XLSX.writeFile(wb, "Historico_Atendimentos.xlsx");
        },

        exportBudgetsToXLS() {
            const dataToExport = this.budgets.map(budget => {
                return {
                    'Nº': budget.id,
                    'Data': new Date(budget.createdAt).toLocaleDateString('pt-BR'),
                    'Paciente': budget.patient_name,
                    'Valor Total': budget.final_total,
                    'Status': budget.status,
                    'Notas': budget.notes || ''
                };
            });

            if (dataToExport.length === 0) {
                return this.showToast('Aviso', 'Nenhum orçamento para exportar.', 'error');
            }
            
            const ws = XLSX.utils.json_to_sheet(dataToExport);
            const numFmt = '#,##0.00';
            const colIndex = 3;
            for (let R = 1; R < dataToExport.length + 1; ++R) {
                const cell_address = XLSX.utils.encode_cell({c:colIndex, r:R});
                if(ws[cell_address] && typeof ws[cell_address].v === 'number'){
                    ws[cell_address].t = 'n';
                    ws[cell_address].z = numFmt;
                }
            }

            ws['!cols'] = [ {wch:8}, {wch:12}, {wch:30}, {wch:15}, {wch:20}, {wch:40} ];
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, "Orçamentos");
            XLSX.writeFile(wb, "Lista_Orçamentos.xlsx");
        },

        executeDataCleanup() {
            const rules = {
                history: this.editingProfile.data_retention_history,
                agenda: this.editingProfile.data_retention_agenda,
                budgets: this.editingProfile.data_retention_budgets
            };
            
            this.showConfirmModal(
                `Confirma a execução da limpeza de dados? Dados antigos (Histórico: ${rules.history} meses, Agenda: ${rules.agenda} meses, Orçamentos não aprovados: ${rules.budgets} meses) serão REMOVIDOS PERMANENTEMENTE.`,
                async () => {
                    this.hideConfirmModal();
                    this.showToast('Aviso', 'A funcionalidade de limpeza de dados (API) ainda não foi implementada.', 'error', 5000);
                }
            );
        },
        
        printBudget() {
            if (!this.newBudget || !this.newBudget.patient_id) {
                this.showToast('Erro', 'Não há dados de orçamento para imprimir.', 'error');
                return;
            }
            sessionStorage.setItem('budgetToPrint', JSON.stringify({
                budget: this.newBudget,
                user: this.currentUser,
                labels: this.labels
            }));
            
            const printWindow = window.open('budget_print.html', '_blank');
            if (!printWindow) {
                this.showToast('Erro', 'Não foi possível abrir a janela de impressão. Verifique se o seu navegador está bloqueando pop-ups.', 'error');
            }
        }
    },
    mounted() {
        // Remove loader inicial do HTML estático se existir
        const loader = document.getElementById('initial-loader');
        if (loader) {
            loader.remove();
        }
        console.log("Aura System: Aplicação montada com sucesso.");
        
        // Listener global para fechar modais com a tecla ESC
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                if (document.getElementById('memed-prescription-modal')?.classList.contains('flex')) {
                    this.closeMemedModal();
                } else if (document.getElementById('odontogram-modal')?.classList.contains('flex')) {
                    this.hideModal('odontogram-modal');
                    // Reabre o clínico se estava editando
                    if (this.editingClinicalData && this.editingClinicalData.id) {
                        this.openClinicalModal(this.editingClinicalData);
                    }
                } else if (document.getElementById('confirm-modal')?.classList.contains('flex')) {
                    this.hideConfirmModal();
                } else {
                    // Tenta fechar qualquer outro modal aberto
                    const openModals = document.querySelectorAll('.modal-overlay.flex');
                    if (openModals.length > 0) {
                        const lastModal = openModals[openModals.length - 1];
                        if (lastModal.id) this.hideModal(lastModal.id);
                    }
                }
            }
        });
    }
});

// Montagem da aplicação no DOM
window.vueApp = app.mount('#app');