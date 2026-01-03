/**
 * Módulo de Integração com Memed Sinapse (Frontend)
 * Gerencia o ciclo de vida do widget de prescrição.
 */

let memedTokenCache = null; 

// --- INICIALIZAÇÃO E PRELOAD ---

export async function preloadMemed(patient = null) {
    if (!this.currentUser || (this.currentUser.memed_enabled != 1 && this.currentUser.memed_enabled != '1')) {
        return;
    }
    // console.log("Aura-Memed: Preload iniciado.");
    try {
        if (!memedTokenCache) {
            const res = await this.apiRequest('getMemedToken', { userId: this.currentUser.id }, false, 'GET');
            if (!res.success) return;
            memedTokenCache = res.token;
            this.memedScriptUrl = res.script_url;
        }
        await this.loadMemedScript(this.memedScriptUrl, memedTokenCache);
    } catch (error) {
        console.error('Aura-Memed: Erro no preload:', error);
    }
}

export function loadMemedScript(url, token) {
    return new Promise((resolve, reject) => {
        let script = document.querySelector(`script[src*="memed"]`);
        
        // Se o script já existe
        if (script) {
            script.setAttribute('data-token', token);
            // REMOVE data-container se existir de versões anteriores para evitar conflito
            script.removeAttribute('data-container'); 
            setTimeout(resolve, 100); 
            return;
        }

        // Cria novo script
        script = document.createElement('script');
        script.type = 'text/javascript';
        script.src = url;
        script.async = true;
        
        // Configurações
        script.setAttribute('data-token', token);
        script.setAttribute('data-color', '#3b82f6');
        // IMPORTANTE: NÃO definimos data-container aqui. Faremos o show manualmente.
        
        script.onload = () => {
            let attempts = 0;
            const interval = setInterval(() => {
                if (window.MdHub) { 
                    clearInterval(interval); 
                    resolve(); 
                }
                if (++attempts > 100) { // 10 segundos de timeout
                    clearInterval(interval); 
                    reject(new Error('Timeout ao carregar MdHub.')); 
                }
            }, 100);
        };
        
        script.onerror = () => reject(new Error('Falha ao carregar script da Memed.'));
        document.body.appendChild(script);
    });
}

// --- ABERTURA E CONTROLE DO MODAL ---

export async function openMemed(patient) {
    // 1. Validações Básicas
    if (!this.currentUser || (this.currentUser.memed_enabled != 1 && this.currentUser.memed_enabled != '1')) {
        this.showToast('Inativo', 'Integração Memed desativada.', 'error');
        return;
    }

    let targetPatient = patient;
    if (!targetPatient && this.editingClinicalData && this.editingClinicalData.id) {
        targetPatient = this.editingClinicalData;
    }

    if (!targetPatient || !targetPatient.id) {
        this.showToast('Erro', 'Selecione um paciente.', 'error');
        return;
    }

    this.isLoading = true;

    try {
        // 2. Garante que script e token estão prontos
        if (!memedTokenCache || !window.MdHub) {
            await this.preloadMemed(targetPatient);
        }
        
        if (!memedTokenCache) throw new Error('Token Memed indisponível. Tente recarregar a página.');

        // 3. MANIPULAÇÃO DE UI (Esconde Clínico -> Mostra Memed)
        const clinicalModal = document.getElementById('clinical-modal');
        if (clinicalModal) {
            clinicalModal.classList.remove('flex');
            clinicalModal.classList.add('hidden');
        }

        const memedModal = document.getElementById('memed-prescription-modal');
        if (memedModal) {
            memedModal.classList.remove('hidden');
            memedModal.classList.add('flex');
        } else {
            throw new Error('Modal da Memed (HTML) não encontrado na página.');
        }

        // 4. Inicialização do Módulo Memed
        if (window.MdHub) {
            const memedContainer = document.getElementById('memed-container');
            if (!memedContainer) throw new Error('Container #memed-container não encontrado.');

            // Limpa qualquer iframe anterior para garantir uma instância nova
            memedContainer.innerHTML = ''; 

            // Exibe o módulo dentro do container específico
            await window.MdHub.module.show('plataforma.prescricao', {
                element: memedContainer,
                width: '100%',
                height: '100%',
                style: { backgroundColor: '#ffffff', border: 'none' }
            });

            // 5. Envia Dados (Contexto)
            await this.setMemedPatient(targetPatient);
            await this.setMemedWorkplace();
            await this.activateMemedFeatures();

            // 6. Configura Eventos (Impressão, Fechamento)
            this.setupMemedEvents(targetPatient);
            
        } else {
            throw new Error('Biblioteca MdHub não carregada corretamente.');
        }

    } catch (error) {
        console.error('Erro Memed:', error);
        this.showToast('Erro', 'Falha ao abrir Memed: ' + (error.message || error), 'error');
        // Em caso de erro, tenta restaurar o estado anterior
        this.closeMemedModal(); 
    } finally {
        this.isLoading = false;
    }
}

export function closeMemedModal() {
    // 1. Envia comando para a Memed limpar estado interno
    if (window.MdHub && window.MdHub.command) {
        try { window.MdHub.command.send('plataforma.prescricao', 'hide'); } catch(e){}
    }

    // 2. Esconde o modal da Memed visualmente
    const memedModal = document.getElementById('memed-prescription-modal');
    if (memedModal) {
        memedModal.classList.remove('flex');
        memedModal.classList.add('hidden');
    }

    // 3. Limpa o container (destroi o iframe)
    const memedContainer = document.getElementById('memed-container');
    if (memedContainer) {
        memedContainer.innerHTML = ''; 
    }

    // 4. Restaura o modal de Dados Clínicos
    if (this.editingClinicalData && this.editingClinicalData.id) {
        const clinicalModal = document.getElementById('clinical-modal');
        if (clinicalModal) {
            clinicalModal.classList.remove('hidden');
            clinicalModal.classList.add('flex');
        }
        
        // Se estiver na aba de documentos, atualiza a lista para ver a receita nova
        if (this.activeClinicalTab === 'documents') {
            this.fetchPatientPrescriptions(this.editingClinicalData.id);
        }
    } else {
        this.activeView = 'patients';
    }
}

// --- CONFIGURAÇÃO DE DADOS ---

export function setMemedPatient(patient) {
    return new Promise((resolve) => {
        // Tratamento de Telefone
        let telefone = (patient.phone || patient.phone2 || '').replace(/\D/g, '');
        if (telefone.length < 10 || telefone.length > 11) telefone = null;

        // Tratamento de CPF
        const cpf = patient.cpf ? String(patient.cpf).replace(/\D/g, '') : null;
        
        // Tratamento de Data de Nascimento
        let dataNascimentoFormatada = '';
        if (patient.birthdate) {
            let rawDate = String(patient.birthdate).trim().split(' ')[0]; // Remove hora
            if (rawDate.includes('-')) { // YYYY-MM-DD
                const parts = rawDate.split('-');
                if(parts.length === 3) dataNascimentoFormatada = `${parts[2]}/${parts[1]}/${parts[0]}`;
            } else if (rawDate.includes('/')) { // DD/MM/YYYY
                dataNascimentoFormatada = rawDate;
            }
        }
        
        // Tratamento de Medidas
        let peso = patient.measure_weight || patient.weight || null;
        let altura = patient.measure_height || patient.height || null;
        // Converte CM para Metros se necessário
        if (altura && parseFloat(altura) > 3) altura = parseFloat(altura) / 100;

        const patientData = {
            idExterno: String(patient.id),
            nome: patient.name,
            endereco: patient.street || null,
            numero: patient.street_number || 'S/N',
            complemento: patient.address_complement || null,
            bairro: patient.neighborhood || null,
            cidade: patient.city || null,
            uf: patient.state || 'SP',
            cep: patient.zip_code ? patient.zip_code.replace(/\D/g, '') : null,
            telefone: telefone,
            email: patient.email || null,
            nomeMae: patient.parentage_mother || null,
            peso: peso,
            altura: altura,
            // Envia nas duas chaves possíveis para garantir compatibilidade
            dataNascimento: dataNascimentoFormatada,
            data_nascimento: dataNascimentoFormatada
        };

        // Campos condicionais
        if (cpf && cpf.length === 11) patientData.cpf = cpf;
        
        if (patientData.dataNascimento === '' || !patientData.dataNascimento) {
            delete patientData.dataNascimento;
            delete patientData.data_nascimento;
        }
        
        if (patient.gender) {
            const g = patient.gender.toLowerCase();
            if (g.includes('masculino') || g === 'm') patientData.sexo = 'M';
            else if (g.includes('feminino') || g === 'f') patientData.sexo = 'F';
        }

        console.log("Aura-Memed: Configurando paciente:", patientData);

        if (window.MdHub && window.MdHub.command) {
            window.MdHub.command.send('plataforma.prescricao', 'setPaciente', patientData)
                .then(() => resolve())
                .catch((err) => {
                    console.warn('Aura-Memed: Aviso setPaciente:', err);
                    resolve(); // Continua mesmo com erro não-fatal
                });
        } else {
            resolve();
        }
    });
}

export function setMemedWorkplace() {
    return new Promise((resolve) => {
        if (!this.currentUser) { resolve(); return; }
        
        let endereco = `${this.currentUser.street || ''}, ${this.currentUser.street_number || ''}`;
        if (this.currentUser.neighborhood) endereco += ` - ${this.currentUser.neighborhood}`;
        
        let telefone = (this.currentUser.phone || '').replace(/\D/g, '');
        if (telefone.length < 10) telefone = '';

        const workplaceData = {
            nome: this.currentUser.professionalName || this.currentUser.name || 'Consultório',
            endereco: endereco,
            cidade: this.currentUser.city || 'Cidade',
            uf: this.currentUser.state || 'SP',
            telefone: telefone
        };

        if (window.MdHub && window.MdHub.command) {
            window.MdHub.command.send('plataforma.prescricao', 'setDadosConsultorio', workplaceData)
                .then(resolve).catch(resolve);
        } else { resolve(); }
    });
}

export function activateMemedFeatures() {
    return new Promise((resolve) => {
        if (!window.MdHub || !window.MdHub.command) { resolve(); return; }
        
        const features = [
            'exames', 'atestados', 'historico_prescricoes', 'alergias', 
            'medicamentos', 'formulas', 'protocolos', 'opcoes_receituario', 
            'cabecalho_paciente', 'editar_paciente'
        ];
        
        // Dispara comandos em paralelo
        const promises = features.map(fn => 
            window.MdHub.command.send('plataforma.prescricao', 'setFeatureToggle', { nome: fn, valor: true })
                .catch(() => {})
        );
        
        Promise.all(promises).then(resolve);
    });
}

// --- EVENTOS ---

export function setupMemedEvents(patient) {
    if (!window.MdHub || !window.MdHub.event) return;
    
    window.MdHub.event.remove('prescricaoImpressa');
    window.MdHub.event.remove('moduloFechado');

    window.MdHub.event.add('prescricaoImpressa', (pData) => {
        console.log('Aura-Memed: Prescrição:', pData);
        
        let contentHtml = `
            <div style="font-family: sans-serif;">
            <h3>Prescrição Digital (Memed)</h3>
            <p>Data: ${new Date().toLocaleDateString('pt-BR')}</p>
        `;
        
        // Tenta extrair itens de locais variados
        let items = [];
        if (pData.prescricao && pData.prescricao.medicamentos) items = pData.prescricao.medicamentos;
        else if (pData.medicamentos) items = pData.medicamentos;
        else if (pData.itens) items = pData.itens;
        else if (pData.data && pData.data.attributes && Array.isArray(pData.data.attributes.medicamentos)) {
             items = pData.data.attributes.medicamentos;
        }

        if (items.length > 0) {
            contentHtml += `<ul>`;
            items.forEach(i => { 
                contentHtml += `<li>
                    <strong>${i.nome || 'Item'}</strong> 
                    ${i.uso ? ' - ' + i.uso : ''} 
                    ${i.quantidade ? '(Qtd: ' + i.quantidade + ')' : ''}<br>
                    <small>${i.posologia || i.descricao || ''}</small>
                </li>`; 
            });
            contentHtml += `</ul>`;
        }
        
        const link = pData.url || (pData.data?.attributes?.url) || '#';
        contentHtml += `<br><a href="${link}" target="_blank" style="font-weight:bold; color:blue;">Abrir PDF Assinado</a></div>`;

        this.savePrescriptionHistory({
            patient_id: patient.id,
            type: 'receita',
            content: contentHtml,
            items: items
        });
        this.showToast('Sucesso', 'Prescrição salva no histórico.', 'success');
    });

    window.MdHub.event.add('moduloFechado', () => {
        this.closeMemedModal();
    });
}

export function watchForMemedIframe() {
    const c = document.getElementById('memed-container');
    if(!c) return;
    const obs = new MutationObserver((mutations) => {
        mutations.forEach((m) => m.addedNodes.forEach((n) => {
            if(n.tagName === 'IFRAME') {
                n.setAttribute('allow', 'camera; microphone; geolocation; payment; usb; clipboard-read; clipboard-write; autofocus');
                n.style.cssText = 'width:100%; height:100%; border:none;';
            }
        }));
    });
    obs.observe(c, { childList: true, subtree: true });
}

// Mantido para compatibilidade, mas o botão visual foi removido
export function logoutMemed() {
    if (window.MdHub && window.MdHub.command) {
        window.MdHub.command.send('plataforma.prescricao', 'logout').catch(()=>{});
        memedTokenCache = null;
        this.closeMemedModal();
    }
}

export async function registerMemedUser() {
    if (!this.currentUser) return;
    this.showConfirmModal('Sincronizar com Memed? (Requer CPF válido no cadastro)', async () => {
        this.hideConfirmModal();
        const res = await this.apiRequest('registerMemedUser', { userId: this.currentUser.id });
        if (res.success) {
            this.showToast('Sucesso', 'Sincronizado.', 'success');
            this.currentUser.memed_enabled = 1;
            sessionStorage.setItem('currentUser', JSON.stringify(this.currentUser));
        } else {
            this.showToast('Erro', res.error, 'error');
        }
    });
}

// ** NOVA FUNÇÃO: Excluir Vínculo Memed **
export async function deleteMemedUser() {
    if (!this.currentUser) return;
    
    this.showConfirmModal('ATENÇÃO: Deseja excluir seu cadastro de prescritor na Memed? Isso removerá seu vínculo e desativará a integração.', async () => {
        this.hideConfirmModal();
        const res = await this.apiRequest('deleteMemedUser', { userId: this.currentUser.id });
        if (res.success) {
            this.showToast('Sucesso', res.message || 'Vínculo removido.', 'success');
            this.currentUser.memed_enabled = 0;
            sessionStorage.setItem('currentUser', JSON.stringify(this.currentUser));
        } else {
            this.showToast('Erro', res.error, 'error');
        }
    }, 'bg-red-600 hover:bg-red-700', 'Sim, Excluir');
}