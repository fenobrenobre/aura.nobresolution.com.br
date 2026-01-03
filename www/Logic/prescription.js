// --- MEDICAMENTOS (CADASTRO / CONFIGURAÇÕES) ---

export async function fetchMedicines(search = '') {
    const r = await this.apiRequest('getMedicines', { search }, false, 'GET');
    if (r.success) {
        this.medicines = r.medicines;
    }
}

export async function saveMedicine() {
    const payload = { ...this.editingMedicine };
    const r = await this.apiRequest('saveMedicine', payload);
    if (r.success) {
        this.showToast('Sucesso', 'Medicamento salvo.', 'success');
        this.hideModal('medicine-modal');
        this.fetchMedicines();
    }
}

export async function deleteMedicine(id) {
    this.showConfirmModal('Excluir este medicamento?', async () => {
        const r = await this.apiRequest('deleteMedicine', { id });
        if (r.success) {
            this.showToast('Sucesso', 'Medicamento excluído.', 'success');
            this.fetchMedicines();
        }
        this.hideConfirmModal();
    });
}

export function openMedicineModal(med) {
    this.editingMedicine = med ? { ...med } : { 
        id: null, 
        name: '', 
        instructions: '', 
        presentation: '', 
        default_route: '', 
        default_duration: '' 
    };
    this.showModal('medicine-modal');
}

// --- BUSCA E SELEÇÃO (COMPARTILHADO) ---

export function searchMedicines(query) {
    if (!query) {
        this.medicines = [];
        return;
    }
    if (this.searchTimeout) clearTimeout(this.searchTimeout);
    this.searchTimeout = setTimeout(() => {
        this.fetchMedicines(query);
    }, 300);
}

// Seleção para o Gerador de Prescrição (User)
export function selectMedicine(med) {
    this.tempPrescriptionItem.name = med.name;
    this.tempPrescriptionItem.presentation = med.presentation || '';
    this.tempPrescriptionItem.route = med.default_route || '';
    this.tempPrescriptionItem.instructions = med.instructions || '';
    this.tempPrescriptionItem.duration = med.default_duration || '';
    
    this.medicineSearchQuery = '';
    this.medicines = [];
}

// Seleção para o Cadastro de Medicamentos (Admin/Config)
export function selectMedicineForAdmin(med) {
    this.editingMedicine.name = med.name;
    this.editingMedicine.presentation = med.presentation || '';
    this.editingMedicine.default_route = med.default_route || '';
    this.editingMedicine.instructions = med.instructions || '';
    this.editingMedicine.default_duration = med.default_duration || '';
    
    this.medicines = [];
}

// --- EXAMES (CADASTRO / CONFIGURAÇÕES) ---

export async function fetchExams(search = '') {
    const r = await this.apiRequest('getExams', { search }, false, 'GET');
    if (r.success) this.exams = r.exams;
}

export async function saveExam() {
    const payload = { ...this.editingExam };
    const r = await this.apiRequest('saveExam', payload);
    if (r.success) {
        this.showToast('Sucesso', 'Exame salvo.', 'success');
        this.hideModal('exam-modal');
        this.fetchExams();
    }
}

export async function deleteExam(id) {
    this.showConfirmModal('Excluir este exame?', async () => {
        const r = await this.apiRequest('deleteExam', { id });
        if (r.success) {
            this.showToast('Sucesso', 'Exame excluído.', 'success');
            this.fetchExams();
        }
        this.hideConfirmModal();
    });
}

export function openExamModal(exam) {
    this.editingExam = exam ? { ...exam } : { id: null, name: '', description: '' };
    this.showModal('exam-modal');
}

// --- MODELOS DE PRESCRIÇÃO (TEMPLATES) ---

export async function fetchPrescriptionTemplates(type = null) {
    const r = await this.apiRequest('getPrescriptionTemplates', { type }, false, 'GET');
    if (r.success) this.prescriptionTemplates = r.templates;
}

export function openPrescriptionTemplateModal(tpl) {
    if (tpl) {
        this.editingPrescriptionTemplate = { ...tpl, make_global: !!tpl.is_global, assign_to_user_id: tpl.is_global ? null : tpl.user_id };
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
        this.showToast('Sucesso', 'Modelo salvo.', 'success');
        this.hideModal('prescription-template-modal');
        this.fetchPrescriptionTemplates();
    }
}

export async function deletePrescriptionTemplate(id) {
    this.showConfirmModal('Excluir este modelo?', async () => {
        const r = await this.apiRequest('deletePrescriptionTemplate', { id });
        if (r.success) {
            this.showToast('Sucesso', 'Modelo excluído.', 'success');
            this.fetchPrescriptionTemplates();
        }
        this.hideConfirmModal();
    });
}

// --- MODELOS DE RECOMENDAÇÃO (RODAPÉ) ---

export async function fetchRecommendationTemplates() {
    const r = await this.apiRequest('getRecommendationTemplates', {}, false, 'GET');
    if (r.success) this.recommendationTemplates = r.templates;
}

export function openRecommendationModal(tpl) {
    if (tpl) {
        this.editingRecommendation = { ...tpl, make_global: !!tpl.is_global };
    } else {
        this.editingRecommendation = { id: null, title: '', content: '', make_global: false };
    }
    this.showModal('recommendation-template-modal');
}

export async function saveRecommendationTemplate() {
    const payload = { ...this.editingRecommendation };
    const r = await this.apiRequest('saveRecommendationTemplate', payload);
    if (r.success) {
        this.showToast('Sucesso', 'Recomendação salva.', 'success');
        this.hideModal('recommendation-template-modal');
        this.fetchRecommendationTemplates();
    }
}

export async function deleteRecommendationTemplate(id) {
    this.showConfirmModal('Excluir esta recomendação?', async () => {
        const r = await this.apiRequest('deleteRecommendationTemplate', { id });
        if (r.success) {
            this.showToast('Sucesso', 'Removido.', 'success');
            this.fetchRecommendationTemplates();
        }
        this.hideConfirmModal();
    });
}

// --- GERADOR DE PRESCRIÇÃO (LÓGICA PRINCIPAL) ---

export async function fetchPatientPrescriptions(patientId) {
    if (!patientId) return;
    const r = await this.apiRequest('getPatientPrescriptions', { patientId }, false, 'GET');
    if (r.success && this.editingClinicalData && this.editingClinicalData.id == patientId) {
        this.editingClinicalData.prescriptions = r.history;
    }
}

// Injeta o HTML do modal no final do body (se ainda não existir)
function injectCertificateModal() {
    if (document.getElementById('certificate_options_modal')) return;

    // Z-INDEX AUMENTADO PARA 10005 para garantir que fique acima do modal de confirmação
    const modalHTML = `
    <div id="certificate_options_modal" class="fixed inset-0 bg-gray-900 bg-opacity-60 hidden items-center justify-center p-4 modal-overlay z-[10005]">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6 relative">
            <h2 class="text-xl font-bold mb-4" id="cert_modal_title">Detalhes do Documento</h2>
            
            <form onsubmit="return false;">
                <div class="mb-4" id="group-certificate-days">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Dias de Afastamento</label>
                    <input type="number" id="cert_opt_days" class="form-input w-full border p-2 rounded" min="0" value="1" placeholder="Qtd. dias" autofocus>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1" id="label_cert_activity">Atividade / Finalidade</label>
                    <input type="text" id="cert_opt_activity" class="form-input w-full border p-2 rounded" placeholder="Ex: atividades laborais">
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button" id="btn_cancel_cert_opts" class="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300">Cancelar</button>
                    <button type="button" id="btn_confirm_cert_opts" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 font-bold">Confirmar</button>
                </div>
            </form>
        </div>
    </div>`;

    document.body.insertAdjacentHTML('beforeend', modalHTML);
}

// ** MODIFICADO: Lógica de Interceptação Blindada **
export function openPrescriptionGenerator(patient, type, extraData = null) {
    const typeLower = (type || '').toLowerCase();

    // 1. Interceptação Atestado/Declaração (se não tiver conteúdo já gerado)
    // Verifica se extraData tem 'content' para saber se já passamos pelo modal
    if ((typeLower === 'atestado' || typeLower === 'declaracao') && (!extraData || !extraData.content)) {
        
        injectCertificateModal();

        // Variáveis de controle temporárias
        this.certificateOptions = { days: '1', activity: '' };
        this.tempCertificatePatient = patient;
        this.tempCertificateType = type;

        // Limpa campos visuais
        const elDays = document.getElementById('cert_opt_days');
        const elActivity = document.getElementById('cert_opt_activity');
        if(elDays) elDays.value = '1';
        if(elActivity) elActivity.value = '';

        // Títulos dinâmicos
        const elTitle = document.getElementById('cert_modal_title');
        const elGroupDays = document.getElementById('group-certificate-days');
        const elLabelAct = document.getElementById('label_cert_activity');

        if(elTitle) {
            if (typeLower === 'atestado') {
                elTitle.innerText = 'Gerar Atestado Médico';
                if(elGroupDays) elGroupDays.style.display = 'block';
                if(elLabelAct) elLabelAct.innerText = 'Afastamento de (Atividade):';
            } else {
                elTitle.innerText = 'Gerar Declaração';
                if(elGroupDays) elGroupDays.style.display = 'none';
                if(elLabelAct) elLabelAct.innerText = 'Compareceu para (Finalidade):';
            }
        }

        // VINCULAÇÃO DIRETA DOS EVENTOS (Garante que 'this' funciona)
        const btnConfirm = document.getElementById('btn_confirm_cert_opts');
        const btnCancel = document.getElementById('btn_cancel_cert_opts');
        const modalEl = document.getElementById('certificate_options_modal');

        if (btnConfirm) {
            // Remove listeners antigos para evitar duplicação (cloneNode reset)
            const newBtn = btnConfirm.cloneNode(true);
            btnConfirm.parentNode.replaceChild(newBtn, btnConfirm);
            
            // Adiciona novo listener com bind(this)
            newBtn.onclick = this.confirmCertificateOptions.bind(this);
        }

        if (btnCancel) {
            btnCancel.onclick = () => {
                modalEl.classList.remove('flex');
                modalEl.classList.add('hidden');
            };
        }

        // Pequeno delay para garantir que o modal de "Confirmação Retroativa" tenha fechado visualmente
        setTimeout(() => {
            modalEl.classList.remove('hidden');
            modalEl.classList.add('flex');
            // Tenta focar no input
            if(elDays && typeLower === 'atestado') elDays.focus();
            if(elActivity && typeLower !== 'atestado') elActivity.focus();
        }, 200);

        return; // PAUSA O FLUXO AQUI
    }

    // --- FLUXO NORMAL (Receita, Exame ou Pós-Modal) ---

    // Esconde o modal clínico para focar na prescrição
    this.hideModal('clinical-modal');

    this.prescriptionForm = {
        patient_id: patient.id,
        patient_name: patient.name,
        type: type, // 'receita', 'exame', 'atestado'
        items: [],
        recommendations: '',
        content: '' 
    };
    
    // Se vier do modal, injeta o texto gerado
    if (extraData && extraData.content) {
        this.prescriptionForm.recommendations = extraData.content;
        // Opcional: duplicar para content se o editor usar esse campo
        this.prescriptionForm.content = extraData.content;
    }

    this.tempPrescriptionItem = { name: '', presentation: '', route: '', instructions: '', duration: '' };
    this.medicineSearchQuery = '';
    this.selectedRecommendationTemplate = null;
    this.medicines = [];

    // Carrega templates relevantes para o tipo
    this.fetchPrescriptionTemplates(type);
    
    // Garante que templates de recomendação estejam carregados
    if (!this.recommendationTemplates || !this.recommendationTemplates.length) {
        this.fetchRecommendationTemplates();
    }
    
    this.showModal('prescription-generator-modal');
}

// ** FUNÇÃO DE CONFIRMAÇÃO DO MODAL **
export function confirmCertificateOptions() {
    // Busca valores diretamente do DOM (mais seguro que v-model em HTML injetado)
    const elDays = document.getElementById('cert_opt_days');
    const elActivity = document.getElementById('cert_opt_activity');
    
    const days = elDays ? elDays.value : '0';
    let activity = elActivity ? elActivity.value : '';
    
    const type = this.tempCertificateType;
    const typeLower = (type || '').toLowerCase();
    const patientName = this.tempCertificatePatient.name || 'PACIENTE';
    const today = new Date().toLocaleDateString('pt-BR');

    // Helper para número por extenso
    const extenso = (n) => {
        const nums = {1:'um', 2:'dois', 3:'três', 4:'quatro', 5:'cinco', 7:'sete', 10:'dez', 15:'quinze', 30:'trinta'};
        return nums[n] ? `(${nums[n]})` : '';
    };

    let generatedText = '';

    if (typeLower === 'atestado') {
        if (!activity) activity = 'suas atividades laborais';
        const diasExt = extenso(days);
        const plural = days == 1 ? 'dia' : 'dias';

        generatedText = `Atesto para os devidos fins que o(a) Sr(a). ${patientName} foi atendido(a) nesta data e necessita de ${days} ${diasExt} ${plural} de afastamento de ${activity}, por motivo de doença (CID: ____), a partir desta data.\n\nLocal e Data: __________________, ${today}`;
    } 
    else if (typeLower === 'declaracao') {
        if (!activity) activity = 'consulta médica';
        const periodo = 'período da manhã/tarde'; 

        generatedText = `Declaro para os devidos fins que o(a) Sr(a). ${patientName} compareceu a este serviço para ${activity} nesta data, no ${periodo}.\n\nLocal e Data: __________________, ${today}`;
    }

    // Fecha o modal manualmente
    const modalEl = document.getElementById('certificate_options_modal');
    if (modalEl) {
        modalEl.classList.remove('flex');
        modalEl.classList.add('hidden');
    }

    // Re-chama o gerador passando o texto pronto e flag 'extraData'
    this.openPrescriptionGenerator(this.tempCertificatePatient, type, { content: generatedText });
}

export function closePrescriptionGenerator() {
    this.hideModal('prescription-generator-modal');
    
    // ** LÓGICA DE RETORNO: Verifica se estava no prontuário **
    if (this.editingClinicalData && this.editingClinicalData.id) {
        // Reabre o modal clínico na aba de prescrições
        this.openClinicalModal(this.editingClinicalData, 'prescriptions');
    }
}

export function addPrescriptionItem() {
    const item = { ...this.tempPrescriptionItem };
    
    // Validação de Nome (Sempre obrigatório)
    if (!item.name) {
        this.showToast('Atenção', 'O nome do item é obrigatório.', 'warning');
        return;
    }

    // Validação de Instruções Apenas para Receitas
    if (this.prescriptionForm.type === 'receita' && !item.instructions) {
        this.showToast('Atenção', 'A posologia/instruções é obrigatória para receitas.', 'warning');
        return;
    }
    
    if (this.prescriptionForm.type === 'receita' && !item.route) {
        item.route = 'USO INTERNO'; // Valor padrão
    }

    this.prescriptionForm.items.push(item);
    
    // Limpa campos temporários
    this.tempPrescriptionItem = { name: '', presentation: '', route: '', instructions: '', duration: '' };
    this.medicineSearchQuery = '';
}

export function removePrescriptionItem(index) {
    this.prescriptionForm.items.splice(index, 1);
}

export function clearPrescription() {
    this.prescriptionForm.items = [];
    this.prescriptionForm.recommendations = '';
    this.showToast('Info', 'Prescrição limpa.', 'info');
}

export function applyPrescriptionTemplate(templateId) {
    if (!templateId) return;
    
    const template = this.prescriptionTemplates.find(t => t.id == templateId);
    if (!template) return;

    // --- SUBSTITUIÇÃO DE VARIÁVEIS NO MOMENTO DA APLICAÇÃO ---
    // Prepara dados do paciente para substituição
    const p = this.editingClinicalData || {};
    const u = this.currentUser || {};
    
    // Cálculos auxiliares
    let idade = '';
    if (p.birthdate) {
        const birth = new Date(p.birthdate);
        const today = new Date();
        let age = today.getFullYear() - birth.getFullYear();
        const m = today.getMonth() - birth.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
        idade = age + ' anos';
    }
    
    let imc = '';
    if (p.measure_weight && p.measure_height) {
        const h = p.measure_height / 100;
        const val = p.measure_weight / (h * h);
        imc = val.toFixed(2);
    }
    
    const endereco = `${p.street || ''}, ${p.street_number || ''} ${p.neighborhood ? '- ' + p.neighborhood : ''} ${p.city ? '- ' + p.city : ''}/${p.state || ''}`;

    // Função auxiliar de substituição
    const replaceVariables = (text) => {
        if (!text) return '';
        return text
            .replace(/\[PACIENTE_NOME\]/g, p.name || '')
            .replace(/\[CPF\]/g, p.cpf || '')
            .replace(/\[RG\]/g, p.rg || '')
            .replace(/\[DATA_NASC\]/g, p.birthdate ? new Date(p.birthdate).toLocaleDateString('pt-BR') : '')
            .replace(/\[IDADE\]/g, idade)
            .replace(/\[SEXO\]/g, p.gender || '')
            .replace(/\[PESO\]/g, p.measure_weight ? p.measure_weight + ' kg' : '')
            .replace(/\[ALTURA\]/g, p.measure_height ? p.measure_height + ' cm' : '')
            .replace(/\[IMC\]/g, imc)
            .replace(/\[PRONTUARIO\]/g, p.id || '')
            .replace(/\[ENDERECO\]/g, endereco)
            .replace(/\[DR_NOME\]/g, u.professionalName || u.name)
            .replace(/\[DR_REGISTRO\]/g, u.professional_register || '')
            .replace(/\[DATA_HOJE\]/g, new Date().toLocaleDateString('pt-BR'));
    };

    try {
        // Tenta parsear como JSON (novo formato estruturado)
        const data = JSON.parse(template.items_json || template.content); // Tenta content como fallback se items_json for null
        
        if (data && (Array.isArray(data) || (data.items && Array.isArray(data.items)))) {
            const itemsToAdd = Array.isArray(data) ? data : data.items;
            
            // Adiciona itens
            this.prescriptionForm.items = [...this.prescriptionForm.items, ...itemsToAdd];
            
            // Processa recomendações com variáveis (se houver no JSON)
            if (data.recommendations) {
                const processedRecs = replaceVariables(data.recommendations);
                if (this.prescriptionForm.recommendations) {
                    this.prescriptionForm.recommendations += "\n\n" + processedRecs;
                } else {
                    this.prescriptionForm.recommendations = processedRecs;
                }
            } else if (template.recommendations) {
                 // Se recomendações estiverem no objeto template fora do JSON
                 const processedRecs = replaceVariables(template.recommendations);
                 if (this.prescriptionForm.recommendations) {
                    this.prescriptionForm.recommendations += "\n\n" + processedRecs;
                } else {
                    this.prescriptionForm.recommendations = processedRecs;
                }
            }
            this.showToast('Sucesso', 'Padrão carregado.', 'success');
        } else {
            // Se JSON válido mas formato desconhecido, joga erro para cair no catch
            throw new Error("Formato não reconhecido");
        }
    } catch (e) {
        // Se falhar o parse JSON (Formato Antigo/Texto Puro)
        let textContent = template.content.replace(/<[^>]*>/g, '\n').replace(/&nbsp;/g, ' ').trim();
        textContent = replaceVariables(textContent);
        
        // Se for receita, tentamos adicionar como item genérico
        if (this.prescriptionForm.type === 'receita') {
             this.prescriptionForm.items.push({
                 name: template.title || 'Prescrição Padrão',
                 instructions: textContent,
                 presentation: '', route: '', duration: ''
             });
        } else {
            // Se for atestado/outro, joga nas recomendações/corpo
            if (this.prescriptionForm.recommendations) {
                this.prescriptionForm.recommendations += "\n\n" + textContent;
            } else {
                this.prescriptionForm.recommendations = textContent;
            }
        }
        this.showToast('Aviso', 'Modelo carregado (formato texto).', 'info');
    }
}

export function applyRecommendationTemplate() {
    if (this.selectedRecommendationTemplate) {
        const textToAdd = this.selectedRecommendationTemplate.content;
        if (this.prescriptionForm.recommendations) {
            this.prescriptionForm.recommendations += "\n\n" + textToAdd;
        } else {
            this.prescriptionForm.recommendations = textToAdd;
        }
        this.selectedRecommendationTemplate = null;
    }
}

export async function savePrescriptionAsModel() {
    if (this.prescriptionForm.items.length === 0 && !this.prescriptionForm.recommendations) {
        this.showToast('Erro', 'Adicione itens ou texto para salvar como modelo.', 'error');
        return;
    }

    const title = prompt("Digite um título para este modelo:");
    if (!title) return;

    const modelData = {
        items: this.prescriptionForm.items,
        recommendations: this.prescriptionForm.recommendations
    };

    const payload = {
        title: title,
        type: this.prescriptionForm.type,
        items_json: JSON.stringify(modelData.items),
        recommendations: modelData.recommendations,
        content: '', // Mantém compatibilidade
        make_global: false
    };

    const r = await this.apiRequest('savePrescriptionTemplate', payload);
    if (r.success) {
        this.showToast('Sucesso', 'Modelo salvo com sucesso!', 'success');
        this.fetchPrescriptionTemplates(this.prescriptionForm.type);
    }
}

// ** FUNÇÃO ATUALIZADA: GARANTE O TEXTO DE INTRODUÇÃO NA PRIMEIRA IMPRESSÃO (EMISSÃO) **
export async function saveAndPrintPrescription() {
    if (this.prescriptionForm.items.length === 0 && !this.prescriptionForm.recommendations) {
        this.showToast('Erro', 'A prescrição está vazia.', 'error');
        return;
    }

    
    
    // Verifica de forma robusta se é exame
    const typeLower = (this.prescriptionForm.type || '').toLowerCase();
    const isExame = (typeLower === 'exame' || typeLower === 'exames');

    // Monta o HTML 
    let summaryHtml = "";
    // REMOVIDO: Texto introdutório fixo

    summaryHtml += "<ul>";
    this.prescriptionForm.items.forEach(i => {
        summaryHtml += `<li><strong>${i.name}</strong> (${i.presentation || ''}) - ${i.instructions}`;
        if(i.duration) summaryHtml += ` [${i.duration}]`;
        summaryHtml += `</li>`;
    });
    summaryHtml += "</ul>";
    
    if (this.prescriptionForm.recommendations) {
        summaryHtml += `<br><strong>Recomendações:</strong><br><pre>${this.prescriptionForm.recommendations}</pre>`;
    }

    const payload = {
        patient_id: this.prescriptionForm.patient_id,
        type: this.prescriptionForm.type,
        final_content: summaryHtml, // Para exibição no histórico
        items_json: JSON.stringify(this.prescriptionForm.items),
        recommendations: this.prescriptionForm.recommendations
    };

    const r = await this.apiRequest('savePrescription', payload);
    
    if (r.success) {
        this.showToast('Sucesso', 'Documento emitido e salvo no histórico.', 'success');
        
        // Atualiza a lista do prontuário
        if (this.editingClinicalData && this.editingClinicalData.id == this.prescriptionForm.patient_id) {
            this.fetchPatientPrescriptions(this.prescriptionForm.patient_id);
        }

        // --- PREPARAÇÃO DADOS DE IMPRESSÃO ---
        let p = this.editingClinicalData || { 
            name: this.prescriptionForm.patient_name, 
            id: this.prescriptionForm.patient_id 
        };
        
        let idadePrint = '';
        if (p.birthdate) {
            const birth = new Date(p.birthdate);
            const today = new Date();
            let age = today.getFullYear() - birth.getFullYear();
            const m = today.getMonth() - birth.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
            idadePrint = age + ' anos';
        }
        
        const patientForPrint = {
            ...p,
            age_formatted: idadePrint,
            full_address: `${p.street || ''}, ${p.street_number || ''} ${p.neighborhood ? '- ' + p.neighborhood : ''} ${p.city ? '- ' + p.city : ''}/${p.state || ''}`
        };

        const printData = {
            user: this.currentUser,
            patient: patientForPrint,
            document: {
                type: this.prescriptionForm.type,
                id: r.document ? r.document.id : r.id, 
                items: this.prescriptionForm.items,
                recommendations: this.prescriptionForm.recommendations,
                date: new Date().toLocaleDateString('pt-BR'),
                // ** FORÇA A PRESENÇA DO TEXTO NA IMPRESSÃO **
                intro_text: "",
                content: summaryHtml, 
                final_content: summaryHtml 
            }
        };
        
        sessionStorage.setItem('prescriptionToPrint', JSON.stringify(printData));
        
        // Define o arquivo alvo
        let targetFile = 'prescription_print.html';
        if (isExame) {
            targetFile = 'exam_print.html';
        }
        
        // Delay de segurança
        setTimeout(() => {
            const win = window.open(targetFile, '_blank');
            if (!win) {
                 this.showToast('Erro', 'Pop-up bloqueado. Permita pop-ups para imprimir.', 'error');
            }
        }, 250);

        this.closePrescriptionGenerator();
    }
}

// --- CARTAS / RISCO CIRÚRGICO / DOCUMENTOS DIVERSOS ---

export function openLetterModal() {
    // Busca templates genéricos/outros se ainda não foram carregados ou para atualizar
    this.fetchPrescriptionTemplates(null); // Null traz tudo, depois filtramos
    this.showModal('letters-selection-modal');
}

export function getLettersTemplates() {
    if (!this.prescriptionTemplates) return [];
    // Retorna APENAS os templates do tipo 'outro' (Cartas, Risco, etc)
    return this.prescriptionTemplates.filter(t => t.type === 'outro'); 
}

export function selectLetterTemplate(template) {
    if (!template) return;

    // Fecha a seleção e prepara a edição
    this.hideModal('letters-selection-modal');
    
    // Pega o conteúdo (seja JSON ou texto puro)
    let rawContent = template.content || '';
    // Se for JSON (legado de tentativa anterior), tenta extrair texto
    if (template.items_json) {
        try {
            const parsed = JSON.parse(template.items_json);
            // Se for array, tenta juntar instructions, senão usa recommendations
            if (Array.isArray(parsed)) {
                rawContent = parsed.map(i => i.instructions).join('\n');
            } else if (parsed.content) {
                rawContent = parsed.content;
            }
        } catch(e) {}
    }

    // --- SUBSTITUIÇÃO DE VARIÁVEIS ---
    // Prepara dados
    const p = this.editingClinicalData || {};
    const u = this.currentUser || {};
    const today = new Date().toLocaleDateString('pt-BR');
    
    let idade = '';
    if (p.birthdate) {
        const birth = new Date(p.birthdate);
        const now = new Date();
        let age = now.getFullYear() - birth.getFullYear();
        if (now.getMonth() < birth.getMonth() || (now.getMonth() === birth.getMonth() && now.getDate() < birth.getDate())) {
            age--;
        }
        idade = age + ' anos';
    }

    let address = `${p.street || ''}, ${p.street_number || ''}`;
    if (p.neighborhood) address += ` - ${p.neighborhood}`;
    if (p.city) address += ` - ${p.city}/${p.state || ''}`;
    if (p.zip_code) address += ` CEP: ${p.zip_code}`;

    // Executa substituições
    let finalContent = rawContent
        .replace(/\[PACIENTE\]|\[PACIENTE_NOME\]/g, p.name || '')
        .replace(/\[CPF\]/g, p.cpf || '')
        .replace(/\[RG\]/g, p.rg || '')
        .replace(/\[DATA_NASC\]/g, p.birthdate ? new Date(p.birthdate).toLocaleDateString('pt-BR') : '')
        .replace(/\[IDADE\]/g, idade)
        .replace(/\[ENDERECO\]/g, address)
        .replace(/\[DATA_HOJE\]/g, today)
        .replace(/\[DATA\]/g, today)
        .replace(/\[PROFISSIONAL\]|\[DR_NOME\]/g, u.professionalName || u.name)
        .replace(/\[REGISTRO\]|\[DR_REGISTRO\]/g, u.professional_register || '');

    // Define o objeto de edição para o novo modal
    this.editingLetter = {
        title: template.title,
        content: finalContent, // Texto já processado
        patient_id: p.id,
        type: 'outro'
    };

    // Abre o NOVO modal de edição direta
    this.showModal('letter-editor-modal');
}

export async function printLetter() {
    if (!this.editingLetter || !this.editingLetter.content) {
        this.showToast('Erro', 'O documento está vazio.', 'error');
        return;
    }

    this.isLoading = true;
    try {
        // 1. Salvar no Histórico
        const payload = {
            userId: this.currentUser.id,
            patientId: this.editingLetter.patient_id,
            type: 'outro', // Importante para diferenciar de receitas
            items: [], 
            recommendations: '', 
            final_content: this.editingLetter.content // O conteúdo editado vai aqui
        };

        const res = await this.apiRequest('savePrescription', payload);

        if (res.success) {
            this.showToast('Sucesso', 'Documento salvo no histórico.', 'success');
            
            // 2. Preparar objeto para impressão
            // Usa 'print_document_others.html' que espera 'documentToPrint'
            const printData = {
                user: this.currentUser,
                patient: this.editingClinicalData,
                document: {
                    id: res.id,
                    type: 'outro',
                    final_content: this.editingLetter.content,
                    created_at: new Date().toISOString()
                }
            };
            
            sessionStorage.setItem('documentToPrint', JSON.stringify(printData));
            
            const win = window.open('print_document_others.html', '_blank');
            if (!win) this.showToast('Aviso', 'Pop-up bloqueado. Permita pop-ups para imprimir.', 'warning');

            // Finaliza
            this.hideModal('letter-editor-modal');
            this.editingLetter = null;
            
            // Atualiza lista do prontuário
            if (this.fetchPatientPrescriptions && this.editingClinicalData) {
                this.fetchPatientPrescriptions(this.editingClinicalData.id);
            }

        } else {
            this.showToast('Erro', res.error || 'Falha ao salvar.', 'error');
        }

    } catch (e) {
        console.error("Erro ao imprimir carta:", e);
        this.showToast('Erro', 'Erro interno ao processar documento.', 'error');
    } finally {
        this.isLoading = false;
    }
}

// --- HISTÓRICO GLOBAL E VISUALIZAÇÃO ---

export async function fetchGlobalPrescriptions() {
    const search = this.prescriptionHistoryFilters.search;
    const page = this.prescriptionHistoryPagination.currentPage;
    const limit = this.prescriptionHistoryPagination.itemsPerPage;

    const params = { search, page, limit };
    const r = await this.apiRequest('getPrescriptionsHistory', params, false, 'GET');

    if (r.success) {
        this.globalPrescriptions = r.history;
        this.prescriptionHistoryTotal = r.total;
        this.prescriptionHistoryTotalPages = r.totalPages;
    } else {
        this.globalPrescriptions = [];
        this.prescriptionHistoryTotal = 0;
        this.prescriptionHistoryTotalPages = 1;
    }
}

export function searchGlobalPrescriptions() {
   if (this.searchTimeout) clearTimeout(this.searchTimeout);
   this.searchTimeout = setTimeout(() => {
       this.prescriptionHistoryPagination.currentPage = 1;
       this.fetchGlobalPrescriptions();
   }, 300);
}

// ** FUNÇÃO ATUALIZADA: GARANTE O ROTEAMENTO E DADOS CORRETOS NA REIMPRESSÃO **
export function viewDocument(doc) {
    if (!doc) return;

    let patientData = {};
    
    if (this.editingClinicalData && this.editingClinicalData.id == doc.patient_id) {
        patientData = this.editingClinicalData;
    } else {
        patientData = {
            name: doc.patient_name || 'Paciente',
            cpf: doc.patient_cpf || '',
            id: doc.patient_id,
            birthdate: '', 
            street: '',
            city: ''
        };
    }

    // Lógica de roteamento
    let printPage = 'prescription_print.html';
    let storageKey = 'prescriptionToPrint';
    let docContent = doc.final_content || '';
    let docItems = doc.items_json ? JSON.parse(doc.items_json) : [];

    // Verifica tipo exame
    const typeLower = (doc.type || '').toLowerCase();
    const isExame = (typeLower === 'exame' || typeLower === 'exames');

    // 1. Exame (prioridade para o novo layout)
    if (isExame) {
        printPage = 'exam_print.html';
        storageKey = 'prescriptionToPrint'; 
    }
    // 2. Atestado ou Declaração/Comprovante
    else if (doc.type === 'atestado' || doc.type === 'declaracao') {
        printPage = 'certificate_print.html';
        storageKey = 'certificateToPrint';
    } 
    // 3. Outros/Cartas
    else if (doc.type === 'outro') {
        printPage = 'print_document_others.html';
        storageKey = 'documentToPrint';
    }
    // 4. Receita (EXPLICITAMENTE)
    else if (doc.type === 'receita') {
        printPage = 'prescription_print.html';
        storageKey = 'prescriptionToPrint';
    }
    // 5. Fallback (Compatibilidade com antigos)
    else if (docContent && docItems.length === 0) {
        printPage = 'print_document_others.html';
        storageKey = 'documentToPrint';
    }

    // REMOVIDO TEXTO FIXO introText

    const printData = {
        user: this.currentUser,
        patient: patientData,
        document: {
            type: doc.type,
            id: doc.id,
            items: docItems,
            content: docContent, 
            final_content: docContent, 
            date: new Date(doc.created_at).toLocaleDateString('pt-BR'),
            recommendations: doc.recommendations,
            intro_text: ""
        }
    };

    sessionStorage.setItem(storageKey, JSON.stringify(printData));
    const win = window.open(printPage, '_blank');
    
    if (!win) {
         this.showToast('Erro', 'Pop-up bloqueado. Permita pop-ups para imprimir.', 'error');
    }
}

export async function emailDocument(documentId) {
    if (!documentId) return;
    
    this.showConfirmModal('Deseja enviar este documento por e-mail para o paciente?', async () => {
        this.hideConfirmModal();
        
        const res = await this.apiRequest('sendDocumentEmail', { documentId });
        
        if (res.success) {
            this.showToast('Sucesso', 'Documento enviado por e-mail.', 'success');
        } else {
            this.showToast('Erro', res.error || 'Falha ao enviar e-mail.', 'error');
        }
    });
}