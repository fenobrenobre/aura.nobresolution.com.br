export async function fetchLedgerEntries() {
    this.ledgerEntries = [];
    this.ledgerPreviousBalance = 0;

    const params = {
        month: this.ledgerFilters.month,
        year: this.ledgerFilters.year
    };

    const res = await this.apiRequest('getLedgerEntries', params, false, 'GET');
    if (res.success) {
        this.ledgerEntries = res.entries;
        this.ledgerPreviousBalance = res.previous_balance || 0;
    } else {
        this.showToast('Erro', res.error || 'Falha ao buscar lançamentos do Livro Caixa.', 'error');
    }
}

export function openLedgerEntryModal(type = 'entrada', entry = null) {
    this.ledgerPatientSearch = '';
    this.ledgerPatientResults = [];

    if (entry) {
        this.editingLedgerEntry = {
            id: entry.id,
            entry_order: entry.entry_order || '',
            entry_date: entry.entry_date,
            receipt_nfe: entry.receipt_nfe || '',
            description: entry.description,
            entry_type: entry.entry_type,
            amount: entry.amount,
            patient_id: entry.patient_id || null
        };
        if (entry.patient_id) {
            this.ledgerPatientSearch = this.getPatientName(entry.patient_id);
        }
    } else {
        this.editingLedgerEntry = {
            id: null,
            entry_order: '',
            entry_date: new Date().toLocaleDateString('en-CA'),
            receipt_nfe: '',
            description: '',
            entry_type: type,
            amount: null,
            patient_id: null
        };
    }
    this.showModal('ledger-entry-modal');
}

export async function saveLedgerEntry() {
    let payload = {};
    const isUpdate = this.editingLedgerEntry.id;

    if (isUpdate) {
        if (!this.editingLedgerEntry.description) {
            this.showToast('Erro', 'Descrição é obrigatória.', 'error');
            return;
        }
        payload = {
            id: this.editingLedgerEntry.id,
            receipt_nfe: this.editingLedgerEntry.receipt_nfe,
            description: this.editingLedgerEntry.description,
            patient_id: this.editingLedgerEntry.patient_id
        };
        
    } else {
        if (!this.editingLedgerEntry.entry_date || !this.editingLedgerEntry.description || this.editingLedgerEntry.amount == null || this.editingLedgerEntry.amount <= 0) {
             this.showToast('Erro', 'Data, Descrição e Valor (maior que zero) são obrigatórios.', 'error');
             return;
        }
        payload = { ...this.editingLedgerEntry };
    }


    const res = await this.apiRequest('saveLedgerEntry', payload);
    if (res.success) {
        this.showToast('Sucesso', 'Lançamento salvo no Livro Caixa.', 'success');
        this.hideModal('ledger-entry-modal');
        this.fetchLedgerEntries();
    }
}

export async function deleteLedgerEntry(entryId) {
    this.showConfirmModal(`Tem certeza que deseja excluir este lançamento? Se for um lançamento automático (de uma previsão), o valor será ESTORNADO da previsão correspondente.`, async () => {
        const res = await this.apiRequest('deleteLedgerEntry', { id: entryId });
        if (res.success) {
            this.showToast('Sucesso', 'Lançamento excluído.', 'success');
            this.fetchLedgerEntries();
            this.fetchForecastEntries();
            this.fetchLedgerEntriesForReceipts();
            this.fetchGeneratedReceipts();
        }
        this.hideConfirmModal();
    });
}


export function exportLedgerToXLS() {
    if (this.ledgerEntries.length === 0 && this.ledgerPreviousBalance == 0) {
        return this.showToast('Aviso', "Não há lançamentos no Livro Caixa para exportar neste período.", 'error');
    }

    const monthStr = String(this.ledgerFilters.month).padStart(2, '0');
    const yearStr = this.ledgerFilters.year;

    const dataToExport = [
        { A: 'LIVRO CAIXA', B: `${monthStr}/${yearStr}` },
        {},
        { A: 'SALDO ANTERIOR:', G: this.ledgerPreviousBalance },
        {},
        { A: 'Nº Ordem', B: 'Data', C: 'Recibo/NFe', D: 'Descrição', E: 'Entrada', F: 'Saída', G: 'Saldo' }
    ];


    this.ledgerEntries.forEach(entry => {
        dataToExport.push({
            A: entry.entry_order || '',
            B: this.formatDateForDisabledList(entry.entry_date),
            C: entry.receipt_nfe || '',
            D: entry.description,
            E: entry.entry_type === 'entrada' ? entry.amount : '',
            F: entry.entry_type === 'saida' ? entry.amount : '',
            G: entry.running_balance
        });
    });

    const finalBalance = this.ledgerEntries.length > 0 ? this.ledgerEntries[this.ledgerEntries.length - 1].running_balance : this.ledgerPreviousBalance;
    dataToExport.push({});
    dataToExport.push({ A: 'SALDO FINAL:', G: finalBalance });


    const ws = XLSX.utils.json_to_sheet(dataToExport, { skipHeader: true });

    ws['!cols'] = [ {wch:10}, {wch:12}, {wch:15}, {wch:40}, {wch:15}, {wch:15}, {wch:15} ];

    ws['!merges'] = [{ s: { r: 0, c: 0 }, e: { r: 0, c: 6 } }];

    const saldoAnteriorRow = 2;
    const headerRow = 4;
    const saldoFinalRow = dataToExport.length -1;
    
    const numFmt = '#,##0.00;[Red]-#,##0.00';
    const colIndexes = [4, 5, 6];

    for (let R = headerRow + 1; R < saldoFinalRow; ++R) {
        for (let C of colIndexes) {
            const cell_address = XLSX.utils.encode_cell({c:C, r:R});
            if(!ws[cell_address]) continue;
            if(ws[cell_address].v !== undefined && typeof ws[cell_address].v === 'number'){
                ws[cell_address].t = 'n';
                ws[cell_address].z = numFmt;
            }
        }
    }
    
    const saldoAnteriorCell = XLSX.utils.encode_cell({c:6, r:saldoAnteriorRow});
    if (ws[saldoAnteriorCell] && typeof ws[saldoAnteriorCell].v === 'number') {
        ws[saldoAnteriorCell].t = 'n';
        ws[saldoAnteriorCell].z = numFmt;
    }
    const saldoFinalCell = XLSX.utils.encode_cell({c:6, r:saldoFinalRow});
     if (ws[saldoFinalCell] && typeof ws[saldoFinalCell].v === 'number') {
        ws[saldoFinalCell].t = 'n';
        ws[saldoFinalCell].z = numFmt;
     }


    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, `LivroCaixa_${monthStr}_${yearStr}`);
    XLSX.writeFile(wb, `Livro_Caixa_${yearStr}_${monthStr}.xlsx`);

    this.showToast('Sucesso', 'Livro Caixa exportado para Excel.', 'success');
}

export async function fetchForecastEntries() {
    this.forecastEntries = [];
    
    const params = {
        status: this.forecastFilters.status,
        month: this.forecastFilters.month,
        year: this.forecastFilters.year
    };

    const res = await this.apiRequest('getForecastEntries', params, false, 'GET');
    if (res.success) {
        this.forecastEntries = res.entries;
        
        if (res.unfiltered_totals) {
            this.forecastHeaderTotals = res.unfiltered_totals;
        } else {
            this.forecastHeaderTotals = this.forecastTotals;
        }
        
    } else {
         this.showToast('Erro', res.error || 'Falha ao buscar lançamentos da previsão.', 'error');
         this.forecastHeaderTotals = { receitasPrevisto: 0, receitasRealizado: 0, despesasPrevisto: 0, despesasRealizado: 0, saldoPrevisto: 0, saldoRealizado: 0 };
    }
}


export function openManualForecastModal(type = 'receita', entry = null) {
     
     if(entry) {
        this.editingForecastEntry = {
            id: entry.id,
            entry_date: entry.entry_date,
            budget_id: entry.budget_id,
            patient_id: entry.patient_id,
            patient_name: entry.patient_name || '',
            description: entry.description,
            forecast_type: entry.forecast_type,
            total_value: entry.total_value || entry.installment_value,
            installment_value: entry.installment_value,
            received_value: entry.received_value
        };
        this.manualForecastPatientSearch = entry.patient_name || '';
     } else {
         this.editingForecastEntry = {
            id: null,
            entry_date: new Date().toLocaleDateString('en-CA'),
            budget_id: null,
            patient_id: null,
            patient_name: '',
            description: '',
            forecast_type: type,
            total_value: 0,
            installment_value: null,
            received_value: 0
         };
         this.manualForecastPatientSearch = '';
     }
     
     this.manualForecastPatientResults = [...this.patients];
     this.showModal('manual-forecast-modal');
 }
 
export async function saveManualForecastEntry() {
     
     if (!this.editingForecastEntry.entry_date || !this.editingForecastEntry.description || this.editingForecastEntry.installment_value == null || this.editingForecastEntry.installment_value <= 0) {
         this.showToast('Erro', 'Data, Descrição e Valor (maior que zero) são obrigatórios.', 'error');
         return;
     }
     
     const payload = { ...this.editingForecastEntry };
     delete payload.payment_status;
     
     const res = await this.apiRequest('saveForecastEntry', payload);
     if (res.success) {
         this.showToast('Sucesso', 'Lançamento salvo na Previsão.', 'success');
         this.hideModal('manual-forecast-modal');
         this.fetchForecastEntries();

         if (payload.patient_id) {
            this.fetchPatients();
            this.fetchBirthdays();
         }
     }
}

export async function deleteForecastEntry(entryId, budgetId) { 
    if (budgetId) {
        this.showToast('Aviso', 'Não é possível excluir lançamentos originados de orçamentos.', 'error');
        return;
    }
    
    this.showConfirmModal(`Tem certeza que deseja excluir este lançamento manual da previsão?`, async () => {
        const res = await this.apiRequest('deleteForecastEntry', { id: entryId });
        if (res.success) {
            this.showToast('Sucesso', 'Lançamento manual excluído.', 'success');
            this.fetchForecastEntries();
        } else {
            this.showToast('Erro', res.error || 'Falha ao excluir. Verifique se o lançamento já foi pago.', 'error');
        }
        this.hideConfirmModal();
    });
}

// ** Lógica de Taxas no Modal de Baixa **
export function openMarkAsPaidModal(entry) {
    const pendingValue = parseFloat(entry.installment_value) - parseFloat(entry.received_value);
    
    if (entry.payment_status === this.defaultPaymentStatusPaid || (pendingValue <= 0 && entry.forecast_type === 'receita')) {
        this.showToast('Aviso', 'Este lançamento já está marcado como "Pago(Total)" ou não possui valor pendente.', 'error');
        return;
    }

    // ** (Usar nova lista entryPaymentMethods) **
    const suggestedMethod = entry.payment_method;
    // Procura na lista da tabela 'entry_payment_methods' (ex: Boleto, Pix)
    const paymentOption = this.entryPaymentMethods.find(opt => opt.name === suggestedMethod);
    // Fallback: Usa a opção encontrada, ou o primeiro item da lista, ou 'Pix/Transferência'
    const defaultMethod = paymentOption ? paymentOption.name : (this.entryPaymentMethods[0]?.name || 'Pix/Transferência');

    const valueToPay = Math.max(0.01, pendingValue);

    this.editingPaymentForecast = {
        id: entry.id,
        original_description: entry.description,
        forecast_type: entry.forecast_type,
        pending_value: valueToPay,
        payment_date: new Date().toLocaleDateString('en-CA'),
        received_value: valueToPay, // Este é o BRUTO
        payment_method: defaultMethod, // Usa o defaultMethod encontrado
        net_received_value_manual: valueToPay // Líquido inicial = Bruto
    };
    
    this.showModal('mark-as-paid-modal');
}

export async function markForecastAsPaid() {
    const payload = this.editingPaymentForecast;

    if (!payload.id || !payload.payment_date || !payload.payment_method) {
        this.showToast('Erro', 'Dados inválidos para registrar pagamento (ID, Data ou Forma de Pag.).', 'error');
        return;
    }
    
    const valor_label = (payload.forecast_type === 'receita') ? 'recebido' : 'pago';
    
    if (payload.received_value <= 0) {
         this.showToast('Erro', `O valor ${valor_label} (Bruto) deve ser maior que zero.`, 'error');
         return;
    }
    
    const apiPayload = {
        id: payload.id,
        received_value: payload.received_value, // Envia o valor BRUTO
        payment_date: payload.payment_date,
        payment_method: payload.payment_method
    };


    if (payload.forecast_type === 'receita') {
        // --- VALIDAÇÃO DE RECEITA ---
        const netValue = parseFloat(payload.net_received_value_manual) || 0;
        if (netValue > payload.received_value) {
            this.showToast('Erro', 'O Valor Líquido não pode ser maior que o Valor Bruto recebido.', 'error');
            return;
        }
        apiPayload.net_received_value = netValue; // Envia o líquido

    } else {
        // --- VALIDAÇÃO DE DESPESA ---
        // Não envia 'net_received_value', backend tratará
    }


    const res = await this.apiRequest('updateForecastStatus', apiPayload);

    if (res.success) {
        this.showToast('Sucesso!', res.message || 'Baixa registrada com sucesso.', 'success');
        this.hideModal('mark-as-paid-modal');
        this.fetchForecastEntries();
        this.fetchLedgerEntries();
        this.fetchPatients();
        this.fetchBirthdays();
    }
}


export function searchPatientsForManualForecast() {
     if (!this.manualForecastPatientSearch) {
        this.manualForecastPatientResults = [...this.patients];
        return;
     }
     const searchTerm = this.manualForecastPatientSearch.toLowerCase();
     this.manualForecastPatientResults = this.patients.filter(p => 
        p.name.toLowerCase().startsWith(searchTerm) ||
        p.name.toLowerCase().includes(' ' + searchTerm)
    );
 }
 
export function selectPatientForManualForecast(patient) {
     this.editingForecastEntry.patient_id = patient.id;
     this.manualForecastPatientSearch = patient.name;
     this.manualForecastPatientResults = [];
 }
 
export function searchPatientsForLedger() {
     if (!this.ledgerPatientSearch) {
        this.ledgerPatientResults = [...this.patients];
        return;
     }
     const searchTerm = this.ledgerPatientSearch.toLowerCase();
     this.ledgerPatientResults = this.patients.filter(p => 
        p.name.toLowerCase().startsWith(searchTerm) ||
        p.name.toLowerCase().includes(' ' + searchTerm)
    );
}
 
export function selectPatientForLedger(patient) {
     this.editingLedgerEntry.patient_id = patient.id;
     this.ledgerPatientSearch = patient.name;
     this.ledgerPatientResults = [];
}
 
export async function exportForecastToXLS() {
    
    const params = {
        status: 'all'
    };
    
    this.isLoading = true;
    const res = await this.apiRequest('getForecastEntries', params, false, 'GET');
    this.isLoading = false;

    if (!res.success || res.entries.length === 0) {
        return this.showToast('Aviso', "Não há lançamentos na previsão para exportar.", 'error');
    }
    
    const entriesToExport = res.entries;

    const dataToExport = [
        { A: 'PREVISÃO DE RECEITAS E DESPESAS' },
        { A: `Período: Todos os Períodos` },
        { A: `Filtro Status: Todos` },
        {},
        { 
            A: 'Data Prev.', 
            B: 'Orçam. #', 
            C: `${this.labels.patient} / Origem`, 
            D: 'Descrição', 
            E: 'Tipo',
            F: 'Valor Prev.', 
            G: 'Valor Pago', 
            H: 'Status Pag.'
        }
    ];

    let totalPrevistoReceita = 0;
    let totalPagoReceita = 0;
    let totalPrevistoDespesa = 0;
    let totalPagoDespesa = 0;

    entriesToExport.forEach(entry => {
        dataToExport.push({
            A: this.formatDateForDisabledList(entry.entry_date),
            B: entry.budget_id || 'Manual',
            C: entry.patient_name || (entry.forecast_type === 'despesa' ? 'Despesa Manual' : '---'),
            D: entry.description,
            E: entry.forecast_type === 'receita' ? 'Receita' : 'Despesa',
            F: entry.installment_value,
            G: entry.received_value,
            H: entry.payment_status
        });
        
        if (entry.forecast_type === 'receita') {
            totalPrevistoReceita += parseFloat(entry.installment_value || 0);
            totalPagoReceita += parseFloat(entry.received_value || 0);
        } else {
            totalPrevistoDespesa += parseFloat(entry.installment_value || 0);
            totalPagoDespesa += parseFloat(entry.received_value || 0);
        }
    });

    dataToExport.push({});
    dataToExport.push({ D: 'TOTAIS', E: 'Previsto', F: 'Realizado' });
    dataToExport.push({ D: 'Receitas:', E: totalPrevistoReceita, F: totalPagoReceita });
    dataToExport.push({ D: 'Despesas:', E: totalPrevistoDespesa, F: totalPagoDespesa });
    dataToExport.push({ D: 'Saldo:', E: (totalPrevistoReceita - totalPrevistoDespesa), F: (totalPagoReceita - totalPagoDespesa) });

    const ws = XLSX.utils.json_to_sheet(dataToExport, { skipHeader: true });

    ws['!cols'] = [ {wch:12}, {wch:10}, {wch:30}, {wch:40}, {wch:10}, {wch:15}, {wch:15}, {wch:15} ];
    ws['!merges'] = [
        { s: { r: 0, c: 0 }, e: { r: 0, c: 7 } },
        { s: { r: 1, c: 0 }, e: { r: 1, c: 7 } },
        { s: { r: 2, c: 0 }, e: { r: 2, c: 7 } }
    ]; 

    const numFmt = '#,##0.00;[Red]-#,##0.00';
    const headerRow = 4;
    const firstDataRow = 5;
    const lastDataRow = 5 + entriesToExport.length -1;
    const firstTotalRow = lastDataRow + 3;
    const lastTotalRow = firstTotalRow + 2; 

    for (let R = firstDataRow; R <= lastDataRow; ++R) {
        for (let C of [5, 6]) {
            const cell_address = XLSX.utils.encode_cell({c:C, r:R});
            if(ws[cell_address] && typeof ws[cell_address].v === 'number'){
                ws[cell_address].t = 'n';
                ws[cell_address].z = numFmt;
            }
        }
    }
    for (let R = firstTotalRow; R <= lastTotalRow; ++R) {
        for (let C of [4, 5]) {
            const cell_address = XLSX.utils.encode_cell({c:C, r:R});
            if(ws[cell_address] && typeof ws[cell_address].v === 'number'){
                ws[cell_address].t = 'n';
                ws[cell_address].z = numFmt;
            }
        }
    }
    
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, `Previsao`);
    XLSX.writeFile(wb, `Previsao_Financeira_Completa.xlsx`);

    this.showToast('Sucesso', 'Previsão (Completa) exportada para Excel.', 'success');
}

// **INÍCIO DA ADIÇÃO (Métodos de Pagamento de Baixa)**
export async function fetchEntryPaymentMethods() {
    this.entryPaymentMethods = [];
    const res = await this.apiRequest('getEntryPaymentMethods', {}, false, 'GET');
    if (res.success) {
        this.entryPaymentMethods = res.methods;
    } else {
        this.showToast('Erro', 'Falha ao carregar formas de pagamento da baixa.', 'error');
    }
}
// **FIM DA ADIÇÃO**