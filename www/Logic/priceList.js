export async function fetchPriceLists() { 
    const res = await this.apiRequest('getPriceLists', {}, false, 'GET'); 
    if (res.success) this.priceLists = res.lists; 
}

export async function fetchAllPriceListsAdmin() { 
    const res = await this.apiRequest('getAllPriceLists', {}, false, 'GET'); 
    if (res.success) this.allPriceLists = res.lists; 
}

export function openPriceListModal(list) { 
    if (this.currentUser.isAdmin) { 
        if (list) { 
            this.editingPriceList = { ...list, make_global: !!list.is_global, user_id: list.is_global ? null : list.user_id }; 
        } else { 
            this.editingPriceList = { id: null, name: '', make_global: true, user_id: null }; 
        } 
    } else { 
        if (list) { 
            this.editingPriceList = { ...list, originalIsGlobal: !!list.is_global }; 
        } else { 
            this.editingPriceList = { id: null, name: '', originalIsGlobal: false }; 
        } 
    } 
    this.showModal('price-list-modal'); 
}

export async function savePriceList() { 
    let payload = { ...this.editingPriceList }; 
    if (this.currentUser.isAdmin) { 
        payload.assign_to_user_id = payload.user_id; 
        if (payload.make_global) { 
            payload.assign_to_user_id = null; 
        } 
        delete payload.user_id; 
    } else { 
        delete payload.make_global; 
        delete payload.user_id; 
        delete payload.originalIsGlobal; 
    } 
    const res = await this.apiRequest('savePriceList', payload); 
    if (res.success) { 
        this.showToast('Sucesso', 'Tabela de preços salva.', 'success'); 
        this.hideModal('price-list-modal'); 
        if (this.currentUser.isAdmin) { 
            this.fetchAllPriceListsAdmin(); 
        } else { 
            this.fetchPriceLists(); 
        } 
        if (this.editingPriceList.itemsToImport) { 
            delete this.editingPriceList.itemsToImport; 
        } 
    } 
}

export async function deletePriceList(id) { 
    this.showConfirmModal('Tem certeza que deseja excluir esta tabela e todos os seus itens? Esta ação é permanente.', async () => { 
        const res = await this.apiRequest('deletePriceList', { id }); 
        if (res.success) { 
            this.showToast('Sucesso', 'Tabela de preços excluída.', 'success'); 
            if (this.currentUser.isAdmin) { 
                this.fetchAllPriceListsAdmin(); 
            } else { 
                this.fetchPriceLists(); 
                if (this.editingProfile.default_price_list_id == id) { 
                    this.editingProfile.default_price_list_id = null; 
                    if (this.currentUser.default_price_list_id == id) { 
                        this.currentUser.default_price_list_id = null; 
                        sessionStorage.setItem('currentUser', JSON.stringify(this.currentUser)); 
                    } 
                } 
            } 
        } 
        this.hideConfirmModal(); 
    }); 
}

export function managePriceListItems(list) { 
    this.activePriceListForItems = list; 
    this.fetchPriceItems(list.id); 
    this.showModal('admin-manage-items-modal'); 
}

export function navigateToSettings(sectionId) { 
    this.activeView = 'settings'; 
    this.$nextTick(() => { 
        const section = document.getElementById(sectionId); 
        if (section) { 
            section.scrollIntoView({ behavior: 'smooth' }); 
        } 
    }); 
}

export async function fetchPriceItems(priceListId) { 
    this.priceItems = []; 
    if (!priceListId) return; 
    const res = await this.apiRequest('getPriceItems', { priceListId }, false, 'GET'); 
    if (res.success) { 
        this.priceItems = res.items; 
    } else { 
        if (res.error?.includes('Acesso negado')) { 
            this.hideModal('admin-manage-items-modal'); 
        } 
    } 
}

export function openPriceItemModal(item) {
    this.editingPriceItem = item ? { ...item } : { name: '', category: '', cost: 0, unit: 'Unidade' };
    if (!this.customFieldOptions.length) this.fetchPublicConfig();
    this.showModal('price-item-modal');
}

export async function savePriceItem() { 
    if (!this.activePriceListForItems || !this.activePriceListForItems.id) { 
        this.showToast('Erro', 'Nenhuma tabela de preços ativa para salvar o item.', 'error'); 
        return; 
    } 
    if (this.activePriceListForItems.is_global && !this.currentUser.isAdmin) { 
        this.showToast('Acesso Negado', 'Você não pode modificar itens de uma tabela global.', 'error'); 
        return; 
    } 
    const payload = { ...this.editingPriceItem, price_list_id: this.activePriceListForItems.id }; 
    const res = await this.apiRequest('savePriceItem', payload); 
    if (res.success) { 
        this.showToast('Sucesso', 'Item salvo.', 'success'); 
        this.hideModal('price-item-modal'); 
        this.fetchPriceItems(this.activePriceListForItems.id); 
    } 
}

export async function deletePriceItem(id) { 
    if (!this.activePriceListForItems || !this.activePriceListForItems.id) return; 
    if (this.activePriceListForItems.is_global && !this.currentUser.isAdmin) { 
        this.showToast('Acesso Negado', 'Você não pode excluir itens de uma tabela global.', 'error'); 
        return; 
    } 
    this.showConfirmModal('Tem certeza que deseja excluir este item?', async () => { 
        const res = await this.apiRequest('deletePriceItem', { id }); 
        if (res.success) { 
            this.showToast('Sucesso', 'Item excluído.', 'success'); 
            this.fetchPriceItems(this.activePriceListForItems.id); 
        } 
        this.hideConfirmModal(); 
    }); 
}