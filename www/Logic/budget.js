export async function openBudgetForm(patient) {
    await this.fetchPriceLists();
    // Garante configurações carregadas
    if (!this.customFieldOptions || !this.customFieldOptions.length) {
        await this.fetchPublicConfig();
    }
    
    const defaultPeriodicity = this.getOptionsByType('periodicity')[0]?.option_value || 'Mensal';
    
    // ** LÓGICA DE RETORNO: Detecta se veio do modal clínico **
    const clinicalModal = document.getElementById('clinical-modal');
    this.budgetReturnToClinical = clinicalModal && clinicalModal.classList.contains('flex');
    
    this.newBudget = {
        id: null, 
        patient_id: patient.id, 
        patient_name: patient.name, 
        patient_cpf: patient.cpf || '', 
        patient_phone: patient.phone || '', 
        price_list_id: this.currentUser.default_price_list_id,
        items: [
            { region: '', description: '', value: 0, increment: 0, discount: 0 },
            { region: '', description: '', value: 0, increment: 0, discount: 0 }
        ],
        recurring_items: [
            { description: '', periodicity: defaultPeriodicity, value: 0, increment: 0, discount: 0 }
        ],
        subtotal: 0, 
        total: 0,
        payment_details: [],
        recurring_payment_details: []
    };
    
    this.priceItems = [];
    
    // Fecha o modal clínico (se estiver aberto) para focar no Orçamento
    this.hideModal('clinical-modal');
    
    this.$nextTick(() => {
        this.activeView = 'budgets'; 
        this.activeBudgetTab = 'create';
        if (this.isSidebarOpen) this.isSidebarOpen = false;
        
        if (this.newBudget.price_list_id) {
            this.fetchPriceItems(this.newBudget.price_list_id);
        }
        this.addDefaultPaymentDetailIfNeeded();
        this.addDefaultRecurringPaymentDetailIfNeeded();
    });
}

export function addBudgetItem() { 
    this.newBudget.items.push({ region: '', description: '', value: 0, increment: 0, discount: 0 }); 
}

export function removeBudgetItem(index) { 
    this.newBudget.items.splice(index, 1); 
    this.addDefaultPaymentDetailIfNeeded();
}

export function addRecurringBudgetItem() { 
    const defaultPeriodicity = this.getOptionsByType('periodicity')[0]?.option_value || 'Mensal';
    this.newBudget.recurring_items.push({ description: '', periodicity: defaultPeriodicity, value: 0, increment: 0, discount: 0 }); 
    this.addDefaultRecurringPaymentDetailIfNeeded();
}

export function removeRecurringBudgetItem(index) { 
    this.newBudget.recurring_items.splice(index, 1); 
    this.addDefaultRecurringPaymentDetailIfNeeded();
}

export function searchProcedures(index, itemsArray, sourceList) { 
    const query = itemsArray[index].description.toLowerCase();
    if (query.length < 1) {
        this.procedureSearch.results = [];
        return;
    }
    this.procedureSearch.index = index;
    const availableItems = sourceList || this.priceItems;
    this.procedureSearch.results = availableItems.filter(item => 
        item.name.toLowerCase().startsWith(query)
    );
    this.procedureSearch.activeIndex = -1; 
}

export function navigateProcedureResults(direction) { 
    if (this.procedureSearch.results.length === 0) return; 
    this.procedureSearch.activeIndex += direction; 
    if (this.procedureSearch.activeIndex < 0) { 
        this.procedureSearch.activeIndex = this.procedureSearch.results.length - 1; 
    } 
    if (this.procedureSearch.activeIndex >= this.procedureSearch.results.length) { 
        this.procedureSearch.activeIndex = 0; 
    } 
}

export function selectProcedure(proc, index, itemsArray) { 
    const items = itemsArray || this.newBudget.items;
    if (!proc) { 
        if (this.procedureSearch.activeIndex !== -1 && this.procedureSearch.results[this.procedureSearch.activeIndex]) { 
            proc = this.procedureSearch.results[this.procedureSearch.activeIndex]; 
        } else { 
            this.procedureSearch.results = []; 
            this.procedureSearch.index = -1; 
            this.procedureSearch.activeIndex = -1; 
            return; 
        } 
    } 
    items[index].description = proc.name; 
    items[index].value = proc.cost; 
    items[index].increment = 0; 
    items[index].discount = 0; 
    this.procedureSearch.results = []; 
    this.procedureSearch.index = -1; 
    this.procedureSearch.activeIndex = -1; 
}

export async function saveBudget() {
    const itemsWithQuantity = this.newBudget.items.map(item => ({ ...item, quantity: item.quantity || 1 }));
    const recurringItemsWithQuantity = this.newBudget.recurring_items.map(item => ({ ...item, quantity: item.quantity || 1 }));

    if (this.budgetTotalMainItems > 0 && Math.abs(this.budgetTotalMainItems - this.budgetPaymentDetailsTotal) > 0.01) {
        this.showToast('Erro', 'A soma das formas de pagamento não corresponde ao valor total dos itens principais.', 'error');
        return;
    }
    if (this.budgetTotalRecurringItems > 0 && Math.abs(this.budgetTotalRecurringItems - this.budgetRecurringPaymentDetailsTotal) > 0.01) {
         this.showToast('Erro', 'A soma das formas de pagamento não corresponde ao valor total dos itens recorrentes.', 'error');
         return;
    }

    const finalPaymentDetails = (this.budgetTotalMainItems > 0 && this.newBudget.payment_details.length > 0) ? this.newBudget.payment_details : null;
    const finalRecurringPaymentDetails = (this.budgetTotalRecurringItems > 0 && this.newBudget.recurring_payment_details.length > 0) ? this.newBudget.recurring_payment_details : null;

    const payload = {
         ...this.newBudget,
         items: itemsWithQuantity,
         recurring_items: recurringItemsWithQuantity,
         subtotal: this.budgetSubtotal,
         total: this.budgetTotal,
         payment_details: finalPaymentDetails,
         recurring_payment_details: finalRecurringPaymentDetails
    };
    
    delete payload.final_discount; 

    const res = await this.apiRequest('saveBudget', payload);
    if (res.success) { 
        this.showToast('Sucesso!', 'Orçamento salvo.', 'success'); 
        this.newBudget.id = res.budgetId;
        
        // ** RETORNO AO MODAL CLÍNICO **
        if (this.budgetReturnToClinical) {
            const patient = this.patients.find(p => p.id == this.newBudget.patient_id);
            if (patient) {
                // Chama a função openClinicalModal na aba 'budgets'
                this.openClinicalModal(patient, 'budgets');
            }
            this.budgetReturnToClinical = false;
        } else {
            this.activeBudgetTab = 'list'; 
            await this.fetchBudgets(); 
        }
        
        await this.fetchPatients(); 
        
        setTimeout(() => {
            this.printBudget(); // Usa a função existente no componente pai/mixin
        }, 500);

    } else if (res.conflict) {
        this.showToast('Bloqueado', res.error || 'Este orçamento possui parcelas pagas e não pode ser modificado.', 'error', 6000);
    }
}

export async function fetchBudgets() { 
    const res = await this.apiRequest('getBudgets', {}, false, 'GET'); 
    if (res.success) this.budgets = res.budgets; 
}

export async function fetchPatientBudgets(patientId) { 
    const res = await this.apiRequest('getBudgets', { patient_id: patientId }, false, 'GET'); 
    if (res.success) this.patientBudgets = res.budgets; 
}

export async function viewBudget(budgetId) {
    await this.fetchPriceLists();
    if (!this.customFieldOptions.length) await this.fetchPublicConfig();
    
    const res = await this.apiRequest('getBudgetDetails', { id: budgetId }, false, 'GET');
    const today = new Date().toLocaleDateString('en-CA');
    
    if (res.success) {
        // ** Captura estado anterior (se veio do clínico) **
        const clinicalModal = document.getElementById('clinical-modal');
        this.budgetReturnToClinical = clinicalModal && clinicalModal.classList.contains('flex');
        this.hideModal('clinical-modal');

        this.newBudget = {
            ...res.budget,
            total: res.budget.final_total,
            items: (res.budget.items || []).map(item => ({ ...item, increment: item.increment || 0 })),
            recurring_items: (res.budget.recurring_items || []).map(item => ({ ...item, increment: item.increment || 0 })),
            payment_details: (Array.isArray(res.budget.payment_details) ? res.budget.payment_details : []).map(d => ({...d, date: d.date || today })),
            recurring_payment_details: (Array.isArray(res.budget.recurring_payment_details) ? res.budget.recurring_payment_details : []).map(d => ({...d, date: d.date || today }))
        };
        
        this.newBudget.patient_cpf = res.budget.patient_cpf || (this.patients.find(p => p.id == res.budget.patient_id)?.cpf || '');
        this.newBudget.patient_phone = res.budget.patient_phone || (this.patients.find(p => p.id == res.budget.patient_id)?.phone || '');
        
        while (this.newBudget.items.length < 2) {
            this.addBudgetItem();
        }
        if (this.newBudget.recurring_items.length === 0) {
            this.addRecurringBudgetItem();
        }
        
        this.priceItems = [];
        if (this.newBudget.price_list_id) { await this.fetchPriceItems(this.newBudget.price_list_id); }
        
        this.$nextTick(() => {
            this.activeView = 'budgets'; 
            this.activeBudgetTab = 'create';
            this.addDefaultPaymentDetailIfNeeded();
            this.addDefaultRecurringPaymentDetailIfNeeded();
            
            setTimeout(() => {
                // this.printBudget(); // Comentado no original para visualização antes
                // Se desejar auto-print ao visualizar, descomente.
            }, 500);
        });
    }
}

export async function printBudgetById(budgetId) {
    if (!budgetId) return;
    
    const res = await this.apiRequest('getBudgetDetails', { id: budgetId }, false, 'GET');
    
    if (res.success && res.budget) {
        sessionStorage.setItem('budgetToPrint', JSON.stringify({
            budget: res.budget,
            user: this.currentUser,
            labels: this.labels
        }));
        
        const printWindow = window.open('budget_print.html', '_blank');
        if (!printWindow) {
            this.showToast('Erro', 'Não foi possível abrir a janela de impressão. Verifique se o seu navegador está bloqueando pop-ups.', 'error');
        }
    } else {
        this.showToast('Erro', res.error || 'Não foi possível carregar os dados do orçamento para impressão.', 'error');
    }
}

export async function deleteBudget(budgetId) { 
    this.showConfirmModal('Tem certeza que deseja excluir este orçamento? (Apenas orçamentos "Reprovados" e sem títulos financeiros podem ser excluídos).', async () => { 
        const res = await this.apiRequest('deleteBudget', { id: budgetId }); 
        
        if (res.success) { 
            this.showToast('Sucesso', 'Orçamento excluído.', 'success'); 
            this.fetchBudgets(); 
            this.fetchPatients();
            if (this.editingClinicalData && this.editingClinicalData.id) { 
                this.fetchPatientBudgets(this.editingClinicalData.id); 
            } 
        } else if (res.conflict) {
            this.showToast('Bloqueado', res.error || 'Este orçamento não pode ser excluído.', 'error', 6000);
        }

        this.hideConfirmModal(); 
    }); 
}

export function toggleStatusMenu(clickedBudget) { 
    this.budgets.forEach(b => { if (b.id !== clickedBudget.id) b.showStatusMenu = false; }); 
    this.patientBudgets.forEach(b => { if (b.id !== clickedBudget.id) b.showStatusMenu = false; }); 
    clickedBudget.showStatusMenu = !clickedBudget.showStatusMenu; 
}

export async function updateBudgetStatus(budget, newStatus) {
    if (!budget || !budget.id) {
        console.error("Objeto orçamento inválido passado para updateBudgetStatus");
        return;
    }
    
    if (!this.customFieldOptions || !this.customFieldOptions.length) {
        await this.fetchPublicConfig();
    }

    const validStatuses = this.getOptionsByType('budget_status').map(opt => opt.option_value);
    
    if (!validStatuses.includes(newStatus)) { 
        budget.showStatusMenu = false; 
        this.showToast('Erro', `Status "${newStatus}" inválido ou não configurado.`, 'error');
        return; 
    }
    
    const payload = { 
        budgetId: budget.id, 
        status: newStatus
    };
    
    const res = await this.apiRequest('updateBudgetStatus', payload);

    if (res.success) {
        this.showToast('Sucesso!', `Orçamento #${budget.id} atualizado para "${newStatus}".`, 'success'); 
        budget.showStatusMenu = false;
        
        const mainListIndex = this.budgets.findIndex(b => b.id === budget.id); 
        if (mainListIndex > -1) { this.budgets[mainListIndex].status = newStatus; }
        
        const patientListIndex = this.patientBudgets.findIndex(b => b.id === budget.id); 
        if (patientListIndex > -1) { this.patientBudgets[patientListIndex].status = newStatus; }
        
        await Promise.all([
            this.fetchForecastEntries(),
            this.fetchLedgerEntries(),
            this.fetchPatients(),
            this.fetchAppointments()
        ]);
        
        this.updateBirthdayChecklist();

    } else if (res.conflict) {
        this.showToast('Bloqueado', res.error || 'Este orçamento possui parcelas pagas e não pode ser alterado.', 'error', 6000);
        budget.showStatusMenu = false; 
    } else { 
        budget.showStatusMenu = false;
        this.showToast('Erro', res.error || 'Falha ao atualizar status.', 'error');
    }
}

export function openNewBudgetPatientSearch() { 
    this.newBudgetPatientSearch = ''; 
    this.newBudgetPatientResults = [...this.patients]; 
    this.showModal('new-budget-patient-modal'); 
}

export function searchPatientsForNewBudget() { 
    if (!this.newBudgetPatientSearch) { 
        this.newBudgetPatientResults = [...this.patients]; 
        return; 
    } 
    const searchTerm = this.newBudgetPatientSearch.toLowerCase(); 
    this.newBudgetPatientResults = this.patients.filter(p => 
        p.name.toLowerCase().startsWith(searchTerm) || 
        p.name.toLowerCase().includes(' ' + searchTerm) ||
        (p.cpf && p.cpf.includes(searchTerm))
    ); 
}

export function selectPatientAndCreateBudget(patient) { 
    this.hideModal('new-budget-patient-modal'); 
    this.openBudgetForm(patient); 
}

export function sortBy(column) { 
    if (this.budgetFilters.sortBy === column) { 
        this.budgetFilters.sortOrder = this.budgetFilters.sortOrder === 'asc' ? 'desc' : 'asc'; 
    } else { 
        this.budgetFilters.sortBy = column; 
        this.budgetFilters.sortOrder = 'desc'; 
    } 
}

export function sortIcon(column) { 
    if (this.budgetFilters.sortBy !== column) { 
        return 'fa-solid fa-sort'; 
    } 
    return this.budgetFilters.sortOrder === 'asc' ? 'fa-solid fa-sort-up' : 'fa-solid fa-sort-down'; 
}

export function recalculatePaymentDetails(detailsArray, totalValue) {
    if (!detailsArray || detailsArray.length === 0 || totalValue <= 0) {
        (detailsArray || []).forEach(detail => detail.value = 0);
        return;
    }

    const count = detailsArray.length;
    const baseValue = Math.floor((totalValue / count) * 100) / 100;
    let remainder = parseFloat((totalValue - (baseValue * count)).toFixed(2));

    detailsArray.forEach((detail, index) => {
        detail.value = baseValue;
    });

    let i = 0;
    while (remainder > 0.009) {
        detailsArray[i % count].value = parseFloat((detailsArray[i % count].value + 0.01).toFixed(2));
        remainder = parseFloat((remainder - 0.01).toFixed(2));
        i++;
    }
}

export function addPaymentDetail() { 
    let newDate = new Date();
    let lastMethod = this.defaultPaymentMethod;
    
    if (this.newBudget.payment_details.length > 0) {
        const lastDetail = this.newBudget.payment_details[this.newBudget.payment_details.length - 1];
        lastMethod = lastDetail.method;
        try {
            const lastDate = new Date(lastDetail.date + 'T00:00:00');
            if (!isNaN(lastDate)) {
                newDate = new Date(lastDate.getTime() + (30 * 24 * 60 * 60 * 1000));
            }
        } catch(e) { }
    }

    this.newBudget.payment_details.push({ 
        date: newDate.toLocaleDateString('en-CA'),
        method: lastMethod,
        value: 0
    }); 
    
    this.recalculatePaymentDetails(this.newBudget.payment_details, this.budgetTotalMainItems);
}

export function removePaymentDetail(index) { 
    this.newBudget.payment_details.splice(index, 1); 
    this.recalculatePaymentDetails(this.newBudget.payment_details, this.budgetTotalMainItems);
    this.addDefaultPaymentDetailIfNeeded();
}

export function updatePaymentDetailValue(index) {
    const details = this.newBudget.payment_details;
    const total = this.budgetTotalMainItems;
    
    const numFollowing = details.length - (index + 1);
    if (numFollowing <= 0) return;

    let sumPreviousAndCurrent = 0;
    for (let i = 0; i <= index; i++) {
        sumPreviousAndCurrent += (parseFloat(details[i].value) || 0);
    }
    
    let remainingTotal = parseFloat((total - sumPreviousAndCurrent).toFixed(2));
    if (remainingTotal < 0) remainingTotal = 0;

    const newValuePerFollowing = Math.floor((remainingTotal / numFollowing) * 100) / 100;
    let remainder = parseFloat((remainingTotal - (newValuePerFollowing * numFollowing)).toFixed(2));

    for (let i = index + 1; i < details.length; i++) {
        details[i].value = newValuePerFollowing;
        if (remainder > 0.009) {
            details[i].value = parseFloat((details[i].value + 0.01).toFixed(2));
            remainder = parseFloat((remainder - 0.01).toFixed(2));
        }
    }
    if (remainder > 0.009 && details[index + 1]) {
        details[index + 1].value = parseFloat((details[index + 1].value + remainder).toFixed(2));
    }
}

export function addDefaultPaymentDetailIfNeeded() { 
    if (this.budgetTotalMainItems > 0 && this.newBudget.payment_details.length === 0) { 
        this.addPaymentDetail();
    }
}

export function addRecurringPaymentDetail() { 
    let newDate = new Date();
    let lastMethod = this.defaultPaymentMethod;
    
    if (this.newBudget.recurring_payment_details.length > 0) {
        const lastDetail = this.newBudget.recurring_payment_details[this.newBudget.recurring_payment_details.length - 1];
        lastMethod = lastDetail.method;
        try {
            const lastDate = new Date(lastDetail.date + 'T00:00:00');
            if (!isNaN(lastDate)) {
                newDate = new Date(lastDate.getTime() + (30 * 24 * 60 * 60 * 1000));
            }
        } catch(e) { }
    }

    this.newBudget.recurring_payment_details.push({ 
        date: newDate.toLocaleDateString('en-CA'), 
        method: lastMethod,
        value: 0 
    }); 
    
    this.recalculatePaymentDetails(this.newBudget.recurring_payment_details, this.budgetTotalRecurringItems);
}

export function removeRecurringPaymentDetail(index) { 
    this.newBudget.recurring_payment_details.splice(index, 1); 
    this.recalculatePaymentDetails(this.newBudget.recurring_payment_details, this.budgetTotalRecurringItems);
    this.addDefaultRecurringPaymentDetailIfNeeded();
}

export function updateRecurringPaymentDetailValue(index) {
    const details = this.newBudget.recurring_payment_details;
    const total = this.budgetTotalRecurringItems;
    
    const numFollowing = details.length - (index + 1);
    if (numFollowing <= 0) return;

    let sumPreviousAndCurrent = 0;
    for (let i = 0; i <= index; i++) {
        sumPreviousAndCurrent += (parseFloat(details[i].value) || 0);
    }
    let remainingTotal = parseFloat((total - sumPreviousAndCurrent).toFixed(2));
    if (remainingTotal < 0) remainingTotal = 0;

    const newValuePerFollowing = Math.floor((remainingTotal / numFollowing) * 100) / 100;
    let remainder = parseFloat((remainingTotal - (newValuePerFollowing * numFollowing)).toFixed(2));

    for (let i = index + 1; i < details.length; i++) {
        details[i].value = newValuePerFollowing;
        if (remainder > 0.009) {
            details[i].value = parseFloat((details[i].value + 0.01).toFixed(2));
            remainder = parseFloat((remainder - 0.01).toFixed(2));
        }
    }
    if (remainder > 0.009 && details[index + 1]) {
        details[index + 1].value = parseFloat((details[index + 1].value + remainder).toFixed(2));
    }
}

export function addDefaultRecurringPaymentDetailIfNeeded() { 
    if (this.budgetTotalRecurringItems > 0 && this.newBudget.recurring_payment_details.length === 0) { 
        this.addRecurringPaymentDetail();
    }
}

export async function fetchBudgetForms() { 
    const res = await this.apiRequest('getBudgetForms', {}, false, 'GET'); 
    if (res.success) { this.budgetForms = res.forms; } 
}

export function openBudgetFormModal(form) { 
    if (form) { 
        this.editingBudgetForm = JSON.parse(JSON.stringify(form)); 
        if (typeof this.editingBudgetForm.fields !== 'object' || this.editingBudgetForm.fields === null) { 
            this.editingBudgetForm.fields = { region: false }; 
        } 
    } else { 
        this.editingBudgetForm = { id: null, name: '', identifier: '', fields: { region: false } }; 
    } 
    this.showModal('budget-form-modal'); 
}

export async function saveBudgetForm() { 
    this.editingBudgetForm.identifier = (this.editingBudgetForm.identifier || '').replace(/\s+/g, '_').toLowerCase(); 
    const res = await this.apiRequest('saveBudgetForm', {...this.editingBudgetForm}); 
    if (res.success) { 
        this.showToast('Sucesso', 'Formulário de orçamento salvo.', 'success'); 
        this.hideModal('budget-form-modal'); 
        await this.fetchBudgetForms(); 
        if (this.currentUser && this.editingBudgetForm.identifier === this.currentUser.default_budget_form_identifier) { 
            this.currentUser.budget_form_fields = this.editingBudgetForm.fields; 
            sessionStorage.setItem('currentUser', JSON.stringify(this.currentUser)); 
        } 
    } 
}

export async function deleteBudgetForm(id) { 
    this.showConfirmModal('Tem certeza que deseja excluir este formulário? Esta ação não pode ser desfeita.', async () => { 
        const res = await this.apiRequest('deleteBudgetForm', { id }); 
        if (res.success) { 
            this.showToast('Sucesso', 'Formulário excluído.', 'success'); 
            this.fetchBudgetForms(); 
        } 
        this.hideConfirmModal(); 
    }); 
}

export async function emailBudget(budgetId) {
    if (!budgetId) return;
    
    this.showConfirmModal('Deseja enviar este orçamento para o e-mail do paciente?', async () => {
        const res = await this.apiRequest('sendBudgetEmail', { budgetId });
        if (res.success) {
            this.showToast('Sucesso', 'E-mail enviado com sucesso!', 'success');
        }
        this.hideConfirmModal();
    });
}

export function getBudgetStatusClass(status) {
    const statusApproved = this.getDefaultOptionValue('budget_status', 'Aprovado');
    const statusRejected = this.getDefaultOptionValue('budget_status', 'Reprovado');
    const statusNegotiation = this.getDefaultOptionValue('budget_status', 'Em Negociação');
    
    if (status === statusApproved) return 'bg-green-100 text-green-800';
    if (status === statusRejected) return 'bg-red-100 text-red-800';
    if (status === statusNegotiation) return 'bg-blue-100 text-blue-800';
    return 'bg-yellow-100 text-yellow-800'; 
}