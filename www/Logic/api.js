export async function apiRequest(action, data = {}, isFormData = false, method = 'POST') {
    this.isLoading = true;
    try {
        // Recupera o token da sessão
        const csrfToken = sessionStorage.getItem('csrf_token');
        let url = `${this.API_URL}?action=${action}`;
        
        // Anexa token na URL sempre (backup para proxies que limpam headers)
        if (csrfToken) {
            url += `&csrf_token=${encodeURIComponent(csrfToken)}`;
        }

        const options = { 
            method, 
            headers: {},
            credentials: 'include' // Importante: Envia o Cookie PHPSESSID
        };

        // Identificação do Usuário (se não for ação pública)
        const publicActions = ['getPublicConfig', 'login', 'googleLogin', 'registerUser', 'requestPasswordReset', 'performPasswordReset'];

        if (this.currentUser && !publicActions.includes(action)) {
            if (isFormData) {
                if (!data.has('userId') && !data.has('adminId')) {
                   if (this.currentUser.isAdmin) data.append('adminId', this.currentUser.id);
                   else data.append('userId', this.currentUser.id);
                }
            } else {
                if (typeof data.append !== 'function' && typeof data === 'object' && data !== null) {
                    if (!data.userId && !data.adminId) {
                       if (this.currentUser.isAdmin) data.adminId = this.currentUser.id;
                       else data.userId = this.currentUser.id;
                    }
                }
            }
        }

        // Header de Segurança
        if (csrfToken) {
            options.headers['X-CSRF-Token'] = csrfToken;
            if (method === 'POST' && !isFormData && typeof data === 'object' && data !== null) {
                data.csrf_token = csrfToken;
            }
        }

        if (method === 'POST') {
            if (isFormData) {
                options.body = data;
            } else {
                options.headers['Content-Type'] = 'application/json';
                options.body = JSON.stringify(data);
            }
        } else {
            const filteredData = Object.fromEntries(Object.entries(data).filter(([, val]) => val !== null && val !== undefined && val !== ''));
            const queryParams = new URLSearchParams(filteredData).toString();
            if (queryParams) url += `&${queryParams}`;
        }

        const response = await fetch(url, options);
        
        if (!response.ok) {
            // FIX: Tratamento específico para 401 (Não autorizado / Sessão caiu)
            if (response.status === 401) {
                console.warn("Sessão expirada (401). Forçando logout.");
                this.showToast('Sessão Expirada', 'Conexão perdida. Faça login novamente.', 'error');
                sessionStorage.clear();
                setTimeout(() => window.location.href = 'index.php', 1500);
                throw new Error("Session expired");
            }

            // FIX: Tratamento inteligente para 403 (Proibido)
            if (response.status === 403) {
                // Tenta ler o JSON de erro para ver se é problema de Token
                const errJson = await response.clone().json().catch(() => ({}));
                const errString = JSON.stringify(errJson).toLowerCase();
                
                // Só desloga se for explicitamente erro de segurança/token
                if (errString.includes('csrf') || errString.includes('token') || errString.includes('mismatch') || errString.includes('sessão')) {
                    console.warn("Erro de Segurança/Token. Forçando logout.");
                    this.showToast('Sessão Expirada', 'Token de segurança inválido. Faça login novamente.', 'error');
                    sessionStorage.clear();
                    setTimeout(() => window.location.href = 'index.php', 1500);
                    throw new Error("Security Token mismatch");
                }
                
                // Se for outro 403 (ex: Permissão negada no Memed), não faz nada aqui, deixa o erro fluir
            }
            
            let errorText = `Erro ${response.status}`;
            try { 
                const errJson = await response.json(); 
                if (errJson && errJson.error) errorText = errJson.error; 
            } catch (e) { }
            throw new Error(errorText);
        }

        const responseText = await response.text();
        try {
            if (response.headers.get('Content-Disposition')) return { success: true };
            if (!responseText) return { success: true };
            
            const jsonData = JSON.parse(responseText);
            
            // Atualiza token se o servidor mandar um novo
            if (jsonData.csrf_token) {
                sessionStorage.setItem('csrf_token', jsonData.csrf_token);
            }

            if (jsonData.success === false) {
                if (jsonData.conflict) return jsonData;
                throw new Error(jsonData.error || 'Erro desconhecido.');
            }
            return jsonData;
        } catch (e) {
            if (response.ok && e instanceof SyntaxError) return { success: true }; 
            throw new Error(e.message || `Resposta inválida.`);
        }
    } catch (error) {
        const isConflict = action === 'saveAppointment' && error.message.includes('ocupado');
        const isSessionDrop = error.message === "Session expired" || error.message === "Security Token mismatch";

        if (!isConflict && !isSessionDrop) {
            console.error("API Error:", error);
            this.showToast('Erro', error.message, 'error'); 
        }
        return { success: false, error: error.message, conflict: isConflict };
    } finally {
        this.isLoading = false;
    }
}