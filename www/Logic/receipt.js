// --- MODELOS DE RECIBO (ADMIN/GLOBAL) ---

export async function fetchReceiptTemplates() {
    const r = await this.apiRequest('getReceiptTemplates', {}, false, 'GET');
    if (r.success) this.receiptTemplates = r.templates;
}

export function openReceiptModal(t) {
    if (t) {
        this.editingReceipt = { ...t, make_global: !!t.is_global, assign_to_user_id: t.is_global ? null : t.user_id };
    } else {
        this.editingReceipt = { id: null, title: '', content: '', make_global: true, assign_to_user_id: null, is_default: false };
    }
    this.showModal('receipt-template-modal');
}

export async function saveReceiptTemplate() {
    const payload = { ...this.editingReceipt };
    if (payload.make_global) {
        payload.assign_to_user_id = null;
    }
    const r = await this.apiRequest('saveReceiptTemplate', payload);
    if (r.success) {
        this.fetchReceiptTemplates();
        this.hideModal('receipt-template-modal');
        this.showToast('Sucesso', 'Modelo de recibo salvo.', 'success');
    }
}

export async function deleteReceiptTemplate(id) {
    this.showConfirmModal('Tem certeza? O modelo será removido permanentemente.', async () => {
        const r = await this.apiRequest('deleteReceiptTemplate', { id });
        if (r.success) {
            this.fetchReceiptTemplates();
            this.hideConfirmModal();
            this.showToast('Sucesso', 'Modelo excluído.', 'success');
        } else {
            this.hideConfirmModal();
        }
    });
}

// --- MEUS MODELOS DE RECIBO (USUÁRIO) ---

export async function fetchUserReceiptTemplates() {
    const res = await this.apiRequest('getUserReceiptTemplates', {}, false, 'GET');
    if (res.success) {
        this.userReceiptTemplates = res.templates;
    } else {
        this.userReceiptTemplates = [];
    }
}

export function openUserReceiptModal(template) {
    if (template) {
        this.editingUserReceipt = { 
            ...template, 
            originalIsGlobal: !!template.is_global,
            is_default: !!template.is_default
        };
    } else {
        this.editingUserReceipt = { id: null, title: '', content: '', originalIsGlobal: false, is_default: false };
    }
    this.showModal('user-receipt-modal');
}

export async function saveUserReceiptTemplate() {
    const payload = { ...this.editingUserReceipt };
    delete payload.originalIsGlobal; // Limpeza

    const r = await this.apiRequest('saveUserReceiptTemplate', payload);
    if (r.success) {
        this.fetchUserReceiptTemplates();
        
        // Atualiza a preferência local se este se tornar o padrão
        if (payload.is_default && this.currentUser.default_receipt_template_id != r.template.id) {
            this.currentUser.default_receipt_template_id = r.template.id;
            sessionStorage.setItem('currentUser', JSON.stringify(this.currentUser));
        } else if (!payload.is_default && this.currentUser.default_receipt_template_id == payload.id) {
             this.currentUser.default_receipt_template_id = null;
             sessionStorage.setItem('currentUser', JSON.stringify(this.currentUser));
        }
        
        this.hideModal('user-receipt-modal');
        this.showToast('Sucesso', 'Modelo de recibo salvo.', 'success');
    }
}

export async function deleteUserReceiptTemplate(id) {
    if (!id) return;
    this.showConfirmModal('Tem certeza que deseja excluir este modelo de recibo?', async () => {
        const r = await this.apiRequest('deleteUserReceiptTemplate', { id });
        if (r.success) {
            this.fetchUserReceiptTemplates();
            
            if (this.currentUser.default_receipt_template_id == id) {
                this.currentUser.default_receipt_template_id = null;
                sessionStorage.setItem('currentUser', JSON.stringify(this.currentUser));
            }
            this.showToast('Sucesso', 'Modelo excluído.', 'success');
        }
        this.hideConfirmModal();
    });
}

// --- GESTÃO DE RECIBOS (LISTAGEM E FILTROS) ---

export async function fetchLedgerEntriesForReceipts() {
    const params = {
        search: this.receiptSearchPending,
        page: this.receiptPaginationPending.currentPage,
        limit: this.receiptPaginationPending.itemsPerPage
    };
    const res = await this.apiRequest('getLedgerEntriesForReceipts', params, false, 'GET');
    if (res.success) {
        this.pendingReceipts.entries = res.entries;
        this.pendingReceipts.total = res.total;
        this.pendingReceipts.totalPages = res.totalPages;
    } else {
        this.pendingReceipts.entries = [];
        this.pendingReceipts.total = 0;
        this.pendingReceipts.totalPages = 1;
    }
}

export async function fetchGeneratedReceipts() {
     const params = {
        search: this.receiptSearchGenerated,
        page: this.receiptPaginationGenerated.currentPage,
        limit: this.receiptPaginationGenerated.itemsPerPage
    };
    const res = await this.apiRequest('getReceipts', params, false, 'GET');
    if (res.success) {
        this.generatedReceipts.entries = res.entries;
        this.generatedReceipts.total = res.total;
        this.generatedReceipts.totalPages = res.totalPages;
    } else {
        this.generatedReceipts.entries = [];
        this.generatedReceipts.total = 0;
        this.generatedReceipts.totalPages = 1;
    }
}

// --- GERAÇÃO DE RECIBOS (INDIVIDUAL E MODAL) ---

export function openReceiptGeneratorModal(entry) {
    const defaultTemplate = this.userReceiptTemplates.find(t => t.is_default) || this.userReceiptTemplates[0] || null;
    
    this.receiptGenerator = {
        isAvulso: false,
        ledger_entry_id: entry.id,
        patient_id: entry.patient_id,
        patient_name: entry.responsible_name || entry.patient_name,
        patient_cpf: entry.responsible_cpf || entry.patient_cpf || '',
        description: entry.description,
        amount: entry.amount,
        date: entry.entry_date,
        template_id: this.currentUser.default_receipt_template_id || (defaultTemplate ? defaultTemplate.id : null)
    };
    this.receiptPatientSearch = '';
    this.receiptPatientResults = [];
    this.showModal('receipt-generator-modal');
}

export async function generateAndPrintReceipt(isReprint = false, reprintData = null) {
    // Validação de segurança para tipo
    if (typeof isReprint !== 'boolean') {
        isReprint = false;
    }
    
    let payload;
    let receiptData;
    let isSuccess = false;
    let receiptNumberForPrint = null;

    if (isReprint) {
        if (!reprintData || !reprintData.id) {
            this.showToast('Erro', 'Dados inválidos para reimpressão.', 'error');
            return;
        }
        payload = {
            ledger_entry_id: reprintData.id,
            template_id: this.currentUser.default_receipt_template_id || this.userReceiptTemplates[0]?.id,
            isReprint: true,
            description: reprintData.description,
            patient_cpf: reprintData.patient_cpf
        };
        receiptNumberForPrint = reprintData.receipt_nfe;
    } else {
        payload = { ...this.receiptGenerator };
    }

    if (!payload.template_id) {
        this.showToast('Erro', 'Nenhum modelo de recibo selecionado ou disponível.', 'error');
        return;
    }

    const res = await this.apiRequest('generateReceipt', payload);
    
    if (res.success) {
        isSuccess = true;
        receiptData = res.populated_content;
        receiptNumberForPrint = res.receipt_number;
    } else {
        if (res.conflict) {
            this.showToast('Conflito', res.error, 'error');
        } else {
            this.showToast('Erro', res.error || 'Falha ao gerar recibo.', 'error');
        }
    }

    if (isSuccess) {
        sessionStorage.setItem('receiptToPrint', JSON.stringify({
            content: receiptData,
            user: this.currentUser,
            receipt_number: receiptNumberForPrint
        }));
        
        const printWindow = window.open('receipt_print.html', '_blank');
        if (!printWindow) {
            this.showToast('Erro', 'Não foi possível abrir a janela de impressão. Verifique se o seu navegador está bloqueando pop-ups.', 'error');
        }
        
        if (!isReprint) {
            this.hideModal('receipt-generator-modal');
            this.fetchLedgerEntriesForReceipts();
            this.fetchGeneratedReceipts();
            // Remove da seleção se estava selecionado
            this.selectedPendingReceipts = this.selectedPendingReceipts.filter(id => id !== payload.ledger_entry_id);
        }
    }
}

export function reprintReceipt(receipt) {
    this.generateAndPrintReceipt(true, receipt);
}

// Visualização rápida (sem gerar novo número se já existe)
export function viewReceipt(receipt) {
    if (!receipt) return;
    
    const printData = {
        user: this.currentUser,
        receipt_number: receipt.receipt_number,
        content: receipt.content_html || receipt.content
    };
    
    sessionStorage.setItem('receiptToPrint', JSON.stringify(printData));
    
    const win = window.open('receipt_print.html', '_blank');
    if (!win) {
        this.showToast('Erro', 'Pop-up bloqueado. Permita pop-ups para visualizar o recibo.', 'error');
    }
}

// --- AÇÕES EM LOTE (BULK) ---

// Função chamada pelo botão "Gerar Recibos (Lote)"
export async function generateReceipt() {
    if (this.selectedPendingReceipts.length === 0) {
        this.showToast('Aviso', 'Selecione pelo menos um lançamento.', 'warning');
        return;
    }
    
    // Verifica template padrão
    if (!this.currentUser.default_receipt_template_id && (!this.userReceiptTemplates || this.userReceiptTemplates.length === 0)) {
         this.showToast('Erro', 'Você precisa ter pelo menos um modelo de recibo cadastrado ou selecionado como padrão.', 'error', 6000);
         return;
    }

    this.showConfirmModal(`Gerar recibos para ${this.selectedPendingReceipts.length} lançamento(s) selecionado(s)?`, async () => {
        const payload = {
            ledgerEntryIds: this.selectedPendingReceipts,
            templateId: this.currentUser.default_receipt_template_id 
        };
        
        const r = await this.apiRequest('generateReceipt', payload); // Backend suporta array
        
        if (r.success) {
            this.showToast('Sucesso', `${r.generatedCount} recibo(s) gerado(s).`, 'success');
            this.selectedPendingReceipts = [];
            this.fetchLedgerEntriesForReceipts();
            this.fetchGeneratedReceipts();
        } else {
            this.showToast('Erro', r.error || 'Falha ao gerar recibos.', 'error');
        }
        this.hideConfirmModal();
    });
}

export function promptDiscardPendingReceipts() {
    const count = this.selectedPendingReceipts.length;
    if (count === 0) {
        this.showToast('Aviso', 'Selecione lançamentos para excluir.', 'warning');
        return;
    }
    
    const message = `Tem certeza que deseja EXCLUIR ${count} Lançamentos pendentes? Esta ação é Irreversível e remove do Livro Caixa.`;
    
    this.showConfirmModal(message, async () => {
        const res = await this.apiRequest('discardPendingReceipts', { 
            ledgerEntryIds: this.selectedPendingReceipts 
        });
        
        if (res.success) {
            this.showToast('Sucesso', `${res.affected_rows || count} lançamento(s) descartado(s).`, 'success');
            this.fetchLedgerEntriesForReceipts(); 
            this.selectedPendingReceipts = [];
        }
        this.hideConfirmModal();
    }, 'bg-yellow-500 hover:bg-yellow-600'); 
}

export async function promptCancelGeneratedReceipts() {
    const count = this.selectedGeneratedReceipts.length;
    if (count === 0) {
        this.showToast('Aviso', 'Selecione recibos para cancelar.', 'warning');
        return;
    }
    
    const message = `Tem certeza que deseja CANCELAR ${count} recibo(s) gerado(s)? Esta ação é reversível e os lançamentos voltarão para a lista de "Pendentes".`;
    
    this.showConfirmModal(message, async () => {
        const res = await this.apiRequest('cancelGeneratedReceipts', { 
            receiptIds: this.selectedGeneratedReceipts 
        });
        
        if (res.success) {
            this.showToast('Sucesso', `${res.message || 'Recibo(s) cancelado(s).'}`, 'success');
            this.fetchLedgerEntriesForReceipts(); 
            this.fetchGeneratedReceipts(); 
            this.selectedGeneratedReceipts = [];
        }
        this.hideConfirmModal();
    });
}

export function emailSelectedReceipts(singleReceiptId = null) {
    let idsToSend = [];
    let count = 0;

    if (singleReceiptId) {
        idsToSend = [singleReceiptId];
        count = 1;
    } else {
        idsToSend = [...this.selectedGeneratedReceipts];
        count = idsToSend.length;
    }
    
    if (count === 0) {
        this.showToast('Aviso', 'Nenhum recibo selecionado para envio.', 'warning');
        return;
    }

    const message = `Tem certeza que deseja enviar ${count} recibo(s) por e-mail para o(s) paciente(s)?`;

    this.showConfirmModal(message, async () => {
        const res = await this.apiRequest('sendReceiptsEmail', { receiptIds: idsToSend });
        
        if (res.success) {
            this.showToast('Sucesso', res.message || 'E-mails enviados.', 'success');
        } else if (res.error && res.message) {
            this.showToast('Envio Parcial', res.message, 'error', 10000);
        }
        
        if (!singleReceiptId) {
            this.selectedGeneratedReceipts = [];
        }
        this.hideConfirmModal();
    }, 'bg-blue-600 hover:bg-blue-700');
}

export function downloadReceiptAsPDF() {
    this.showToast(
        'Como Salvar em PDF', 
        'Para salvar em PDF, clique no ícone de Impressora (reprint) e, na janela de impressão do seu navegador, escolha "Salvar como PDF" como destino.', 
        'success', 
        10000
    );
}

// --- RECIBOS DO PACIENTE (CONTEXTO CLÍNICO) ---

export async function fetchPatientReceipts(patientId) {
    if (!patientId) {
        this.patientReceipts = { pending: [], generated: [] };
        return;
    }
    this.patientReceipts = { pending: [], generated: [] };
    const res = await this.apiRequest('getPatientReceipts', { patientId }, false, 'GET');
    
    if (res.success) {
        this.patientReceipts = {
            pending: res.pending || [],
            generated: res.generated || []
        };
        // Se estiver com o modal clínico aberto, atualiza a view local
        if (this.editingClinicalData && this.editingClinicalData.id == patientId) {
            this.editingClinicalData.patientReceipts = this.patientReceipts;
        }
    } else {
        this.showToast('Erro', 'Falha ao buscar histórico de recibos do paciente.', 'error');
    }
}