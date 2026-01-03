// --- CONFIGURAÇÃO INICIAL (DIAGNÓSTICOS) ---

export async function fetchDentalDiagnoses() {
    try {
        const r = await this.apiRequest('getDentalDiagnoses', {}, false, 'GET');
        if (r.success) {
            this.dentalDiagnoses = r.diagnoses;
            // Seleciona o primeiro diagnóstico por padrão se não houver nenhum selecionado
            if (this.dentalDiagnoses.length > 0 && !this.selectedDiagnosis) {
                this.selectedDiagnosis = this.dentalDiagnoses[0];
            }
        }
    } catch (error) {
        console.error("Erro ao buscar diagnósticos:", error);
    }
}

export function openDiagnosisConfigModal() {
    this.editingDiagnosis = { id: null, name: '', color: '#FF0000', type: 'face' };
    this.showModal('diagnosis-config-modal');
}

export async function saveDiagnosis() {
    const payload = { ...this.editingDiagnosis };
    const r = await this.apiRequest('saveDentalDiagnosis', payload);
    if (r.success) {
        this.fetchDentalDiagnoses();
        this.hideModal('diagnosis-config-modal');
        this.showToast('Sucesso', 'Diagnóstico salvo.', 'success');
    }
}

export async function deleteDiagnosis(id) {
    this.showConfirmModal('Excluir este diagnóstico? Registros existentes não serão afetados visualmente, mas perderão a referência.', async () => {
        const r = await this.apiRequest('deleteDentalDiagnosis', { id });
        if (r.success) {
            this.fetchDentalDiagnoses();
            this.hideConfirmModal();
            this.showToast('Sucesso', 'Diagnóstico removido.', 'success');
        } else {
            this.hideConfirmModal();
        }
    });
}

export function selectDiagnosis(diagnosis) {
    this.selectedDiagnosis = diagnosis;
}

// --- ODONTOGRAMA DO PACIENTE ---

export async function openOdontogramModal() {
    // 1. Tenta recuperar ID e NOME das variáveis de estado (prioridade)
    let patientId = this.editingClinicalData?.id || this.currentPatient?.id;
    let patientName = this.editingClinicalData?.name || this.currentPatient?.name;
    
    // 2. Se falhar, tenta recuperar do armazenamento seguro (SessionStorage)
    if (!patientId) {
        patientId = sessionStorage.getItem('currentPatientId');
        if (patientId) {
            patientName = sessionStorage.getItem('currentPatientName'); 
        }
    }

    // 3. Validação Final
    if (!patientId) {
        console.error("ERRO: ID do paciente não encontrado ao tentar abrir o odontograma.");
        this.showToast('Erro', 'Nenhum paciente selecionado. Recarregue a página.', 'error');
        return;
    }

    // 4. GARANTIA DO NOME (API BLOQUEANTE)
    if (!patientName) {
        this.isLoading = true; 
        try {
            const r = await this.apiRequest('getPatientDetails', { patientId }, false, 'GET');
            if (r.success && r.patient) {
                patientName = r.patient.name;
                // Atualiza os objetos globais com os dados frescos
                this.editingClinicalData = r.patient; 
                this.currentPatient = r.patient;
            }
        } catch (e) {
            console.warn("Falha ao recuperar nome do paciente para o Odontograma.", e);
            patientName = 'Paciente'; 
        } finally {
            this.isLoading = false;
        }
    }

    // 5. Persistência
    sessionStorage.setItem('currentPatientId', patientId);
    if (patientName) {
        sessionStorage.setItem('currentPatientName', patientName);
    }
    
    // 6. FORÇA A REATIVIDADE DO VUE
    if (!this.editingClinicalData) {
        this.editingClinicalData = { id: patientId, name: patientName };
    } else {
        this.editingClinicalData = { 
            ...this.editingClinicalData, 
            id: patientId, 
            name: patientName 
        };
    }

    // Sincroniza currentPatient também
    if (!this.currentPatient) {
        this.currentPatient = { id: patientId, name: patientName };
    } else {
        this.currentPatient.name = patientName;
    }
    
    // 7. Abre o modal e carrega dados
    this.hideModal('clinical-modal'); 
    this.showModal('odontogram-modal');
    
    // Limpa versões anteriores da memória antes de buscar novas
    this.odontogramVersions = [];
    this.currentOdontogramVersionId = null;
    
    await this.fetchDentalDiagnoses();
    await this.fetchOdontogramVersions(patientId);
}

export async function fetchOdontogramVersions(patientId) {
    try {
        const r = await this.apiRequest('getOdontogramVersions', { patientId }, false, 'GET');
        if (r.success) {
            this.odontogramVersions = r.versions;
            
            if (this.odontogramVersions.length > 0) {
                // Se já tinha um ID selecionado e ele ainda existe na lista, mantém. Senão, pega o primeiro.
                const exists = this.odontogramVersions.some(v => v.id == this.currentOdontogramVersionId);
                if (!this.currentOdontogramVersionId || !exists) {
                    this.currentOdontogramVersionId = this.odontogramVersions[0].id;
                }
                
                // Carrega os dados da versão selecionada
                await this.fetchPatientOdontogram(this.currentOdontogramVersionId);
            } else {
                this.odontogramEntries = [];
            }
        }
    } catch (e) {
        console.error("Erro ao buscar versões:", e);
    }
}

export async function changeOdontogramVersion() {
    if (this.currentOdontogramVersionId) {
        await this.fetchPatientOdontogram(this.currentOdontogramVersionId);
    }
}

export async function createNewOdontogramVersion() {
    const defaultName = `Evolução ${new Date().toLocaleDateString('pt-BR')}`;
    const name = prompt("Nome da nova versão do Odontograma:", defaultName);
    
    if (!name) return;

    // --- CORREÇÃO AQUI: Recuperação Robusta do ID do Paciente ---
    let patientId = this.editingClinicalData?.id || this.currentPatient?.id;
    if (!patientId) {
        patientId = sessionStorage.getItem('currentPatientId');
    }

    if (!patientId) {
        this.showToast('Erro', 'Paciente não identificado. Tente recarregar a página.', 'error');
        return;
    }
    // ------------------------------------------------------------

    let copyFromCurrent = false;
    if (this.odontogramEntries && this.odontogramEntries.length > 0) {
        copyFromCurrent = confirm("Deseja copiar as marcações do odontograma atual para o novo?");
    }

    const payload = {
        userId: this.currentUser.id,
        patient_id: patientId, // Usa a variável segura
        name: name,
        base_version_id: copyFromCurrent ? this.currentOdontogramVersionId : null
    };

    try {
        this.isLoading = true;
        const r = await this.apiRequest('saveOdontogramVersion', payload);
        if (r.success) {
            this.showToast('Sucesso', 'Nova versão criada.', 'success');
            await this.fetchOdontogramVersions(patientId);
        } else {
            this.showToast('Erro', r.error || 'Erro ao criar versão.', 'error');
        }
    } catch (e) {
        console.error(e);
        this.showToast('Erro', 'Erro de conexão.', 'error');
    } finally {
        this.isLoading = false;
    }
}

export async function deleteCurrentOdontogramVersion() {
    if (!this.currentOdontogramVersionId) return;
    
    if (this.odontogramVersions.length <= 1) {
        this.showToast('Aviso', 'Não é possível excluir a única versão existente.', 'warning');
        return;
    }

    const currentVersion = this.odontogramVersions.find(v => v.id == this.currentOdontogramVersionId);
    const confirmMsg = `Tem certeza que deseja EXCLUIR a versão "${currentVersion?.name}"? Todas as marcações dela serão perdidas.`;

    this.showConfirmModal(confirmMsg, async () => {
        try {
            // Recupera ID do paciente antes de deletar para poder recarregar a lista depois
            let patientId = this.editingClinicalData?.id || sessionStorage.getItem('currentPatientId');

            const r = await this.apiRequest('deleteOdontogramVersion', { id: this.currentOdontogramVersionId });
            if (r.success) {
                this.showToast('Sucesso', 'Versão excluída.', 'success');
                this.currentOdontogramVersionId = null; 
                if (patientId) {
                    await this.fetchOdontogramVersions(patientId);
                }
            }
            this.hideConfirmModal();
        } catch (e) {
            console.error(e);
        }
    });
}

// --- ODONTOGRAMA (ENTRADAS) ---

export async function fetchPatientOdontogram(odontogramId) {
    if (!odontogramId) return;

    this.isLoadingOdontogram = true;
    try {
        const r = await this.apiRequest('getPatientOdontogram', { odontogramId }, false, 'GET');
        if (r.success) {
            this.odontogramEntries = r.entries;
        } else {
            this.odontogramEntries = [];
        }
    } catch (e) {
        console.error("Erro na API do odontograma:", e);
        this.odontogramEntries = [];
    } finally {
        this.isLoadingOdontogram = false;
    }
}

// Ação principal: Clicar no dente ou na face
export async function handleToothClick(toothNumber, face = null) {
    if (!this.selectedDiagnosis) {
        this.showToast('Atenção', 'Selecione um diagnóstico na barra lateral primeiro.', 'warning');
        return;
    }

    if (!this.currentOdontogramVersionId) {
        this.showToast('Erro', 'Nenhuma versão de odontograma selecionada.', 'error');
        return;
    }

    // Validação de Paciente
    let patientId = this.editingClinicalData?.id || this.currentPatient?.id;
    if (!patientId) patientId = sessionStorage.getItem('currentPatientId');

    if (!patientId) {
        this.showToast('Erro', 'Sessão do paciente perdida. Tente reabrir o odontograma.', 'error');
        return;
    }

    let finalFace = face;
    if (this.selectedDiagnosis.type === 'root' || this.selectedDiagnosis.type === 'tooth') {
        finalFace = null; 
    }

    const payload = {
        odontogram_id: this.currentOdontogramVersionId, // Vincula à versão atual
        patient_id: patientId, 
        tooth_number: toothNumber,
        diagnosis_id: this.selectedDiagnosis.id,
        face: finalFace,
        notes: '' 
    };

    try {
        const r = await this.apiRequest('saveOdontogramEntry', payload);
        if (r.success) {
            this.odontogramEntries.push(r.entry);
        }
    } catch (error) {
        console.error("Erro ao salvar dente:", error);
        this.showToast('Erro', 'Falha ao salvar no servidor.', 'error');
    }
}

export async function removeOdontogramEntry(entryId) {
    const r = await this.apiRequest('deleteOdontogramEntry', { id: entryId });
    if (r.success) {
        this.odontogramEntries = this.odontogramEntries.filter(e => e.id !== entryId);
        this.showToast('Sucesso', 'Registro removido.', 'success');
    }
}

// --- LÓGICA VISUAL (PINTURA) ---

export function getFaceColor(toothNumber, face) {
    if (!this.odontogramEntries || this.odontogramEntries.length === 0) return 'transparent';

    const wholeToothEntry = this.odontogramEntries
        .filter(e => e.tooth_number == toothNumber && e.face === null)
        .pop(); 
        
    let baseColor = wholeToothEntry ? wholeToothEntry.diagnosis_color : 'transparent';
    
    const faceEntry = this.odontogramEntries
        .filter(e => e.tooth_number == toothNumber && e.face === face)
        .pop();

    if (faceEntry) {
        return faceEntry.diagnosis_color;
    }
    
    return baseColor;
}

export function getToothLabelColor(toothNumber) {
    if (!this.odontogramEntries) return '#6B7280';

    const entry = this.odontogramEntries
        .filter(e => e.tooth_number == toothNumber && e.face === null)
        .pop();
        
    return entry ? entry.diagnosis_color : '#6B7280';
}