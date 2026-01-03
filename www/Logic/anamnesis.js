export async function fetchAnamnesisTemplates() { 
    const r = await this.apiRequest('getAnamnesisTemplates', {}, false, 'GET'); 
    if (r.success) this.anamnesisTemplates = r.templates; 
}

export function openAnamnesisModal(t) { 
    if (t) { 
        this.editingAnamnesis = { ...t, make_global: !!t.is_global, assign_to_user_id: t.is_global ? null : t.user_id }; 
    } else { 
        this.editingAnamnesis = { id: null, title: '', content: '', make_global: true, assign_to_user_id: null }; 
    } 
    this.showModal('anamnesis-modal'); 
}

export async function saveAnamnesisTemplate() { 
    const payload = { ...this.editingAnamnesis }; 
    if (payload.make_global) { 
        payload.assign_to_user_id = null; 
    } 
    const r = await this.apiRequest('saveAnamnesisTemplate', payload); 
    if (r.success) { 
        this.fetchAnamnesisTemplates(); 
        this.hideModal('anamnesis-modal'); 
        this.showToast('Sucesso', 'Modelo de anamnese salvo.', 'success'); 
    } 
}

export async function deleteAnamnesisTemplate(id) { 
    this.showConfirmModal('Tem certeza? O modelo será removido permanentemente.', async () => { 
        const r = await this.apiRequest('deleteAnamnesisTemplate', { id }); 
        if (r.success) { 
            this.fetchAnamnesisTemplates(); 
            this.hideConfirmModal(); 
            this.showToast('Sucesso', 'Modelo excluído.', 'success'); 
        } else { 
            this.hideConfirmModal(); 
        } 
    }); 
}

export async function fetchUserAnamnesisTemplates(targetUserId = null) { 
    const userIdToFetch = targetUserId || this.currentUser?.id; 
    const params = { userId: userIdToFetch }; 
    const r = await this.apiRequest('getUserAnamnesisTemplates', params, false, 'GET'); 
    if (r.success) { 
        this.userAnamnesisTemplates = r.templates; 
    } else { 
        this.userAnamnesisTemplates = []; 
    } 
}

export function openUserAnamnesisModal(t) { 
    if (t && t.is_global) { 
        this.editingUserAnamnesis = { id: null, title: t.title + " (Cópia)", content: t.content, originalIsGlobal: true }; 
    } else { 
        this.editingUserAnamnesis = t ? { ...t, originalIsGlobal: false } : { id: null, title: '', content: '', originalIsGlobal: false }; 
    } 
    this.showModal('user-anamnesis-modal'); 
}

export async function saveUserAnamnesisTemplate() { 
    const payload = { ...this.editingUserAnamnesis }; 
    const r = await this.apiRequest('saveUserAnamnesisTemplate', payload); 
    if (r.success) { 
        this.fetchUserAnamnesisTemplates(); 
        this.hideModal('user-anamnesis-modal'); 
        this.showToast('Sucesso', 'Modelo salvo.', 'success'); 
    } 
}

export async function deleteUserAnamnesisTemplate(id) { 
    this.showConfirmModal('Tem certeza que deseja excluir este modelo pessoal?', async () => { 
        const r = await this.apiRequest('deleteUserAnamnesisTemplate', { id }); 
        if (r.success) { 
            this.fetchUserAnamnesisTemplates(); 
            if (this.editingProfile.anamnesis_template_id == id) { 
                this.editingProfile.anamnesis_template_id = null; 
                if (this.currentUser.anamnesis_template_id == id) { 
                    this.currentUser.anamnesis_template_id = null; 
                    sessionStorage.setItem('currentUser', JSON.stringify(this.currentUser)); 
                } 
            } 
            this.showToast('Sucesso', 'Modelo excluído.', 'success'); 
        } 
        this.hideConfirmModal(); 
    }); 
}

export function exportUserAnamnesisTemplate(templateId) { 
    if (this.currentUser) { 
        const timestamp = new Date().getTime(); 
        window.location.href = `${this.API_URL}?action=exportAnamnesisTemplate&templateId=${templateId}&userId=${this.currentUser.id}&t=${timestamp}`; 
    } 
}

export function triggerAnamnesisImport() { 
    this.$refs.anamnesis_import.click(); 
}

export async function handleAnamnesisImport(event) { 
    const file = event.target.files[0]; 
    if (!file) return; 
    if (file.type !== 'application/json') { 
        this.showToast('Erro', 'Arquivo inválido. Apenas arquivos .json são permitidos.', 'error'); 
        event.target.value = ''; 
        return; 
    } 
    const formData = new FormData(); 
    formData.append('anamnesis_import', file); 
    const r = await this.apiRequest('importAnamnesisTemplate', formData, true); 
    if (r.success) { 
        this.showToast('Sucesso', 'Modelo de anamnese importado com sucesso!', 'success'); 
        this.fetchUserAnamnesisTemplates(); 
    } 
    event.target.value = ''; 
}