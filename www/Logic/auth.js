export function handleGoogleCredentialResponse(response) {
    const profile = jwt_decode(response.credential);
    if (profile && window.vueApp) {
        window.vueApp.handleGoogleLogin({ name: profile.name, email: profile.email });
    }
}

export function jwt_decode(token) {
    try {
        return JSON.parse(atob(token.split('.')[1]));
    } catch (e) {
        return null;
    }
}

export async function handleLogin() {
    const r = await this.apiRequest('login', this.loginForm);
    // ** ALTERAÇÃO CSRF: Passando o token recebido para a sessão **
    if (r.success) this.startSession(r.user, r.csrf_token);
}

export async function handleGoogleLogin(d) {
    const r = await this.apiRequest('googleLogin', d);
    // ** ALTERAÇÃO CSRF: Passando o token recebido para a sessão **
    if (r.success) {
        this.startSession(r.user, r.csrf_token);
    }
}

// ** NOVA FUNÇÃO: Atualizar especialidades no registro **
export async function updateSpecialtiesForRegister() {
    const professionName = this.registerForm.profession;
    this.specialties = []; // Limpa lista anterior
    this.registerForm.specialty = null; // Limpa seleção anterior
    
    if (!professionName) return;

    // Garante que a lista de profissões esteja carregada
    if (!this.professions || this.professions.length === 0) {
        await this.fetchPublicConfig();
    }

    // Encontra o ID da profissão baseada no nome
    const profession = this.professions.find(p => p.name === professionName);
    
    if (profession) {
        const r = await this.apiRequest('getSpecialties', { professionId: profession.id }, false, 'GET');
        if (r.success) {
            this.specialties = r.specialties;
        }
    }
}

export async function handleRegister() {
    if (this.registerForm.isDocumentInvalid) {
        this.showToast('Erro', 'CPF/CNPJ inválido. Verifique o documento digitado.', 'error');
        return;
    }

    this.checkPasswordStrength(this.registerForm.password); 
    if (this.passwordStrength < 3) { 
        this.showToast('Senha Fraca', 'A senha deve ter no mínimo 8 caracteres, incluir letras maiúsculas, minúsculas e um símbolo.', 'error', 6000);
        return;
    }

    const fd = new FormData();
    
    let combinedProfessionalRegister = '';
    const regType = this.registerForm.professional_register_type?.trim();
    const regNum = this.registerForm.professional_register_number?.trim();
    const regUF = this.registerForm.professional_register_uf?.trim().toUpperCase();

    if (regType && regNum) {
        combinedProfessionalRegister = `${regType}: ${regNum}`;
        if (regUF) {
            combinedProfessionalRegister += `/${regUF}`;
        }
    } else if (regNum) {
        combinedProfessionalRegister = regNum;
    }

    for (const k in this.registerForm) {
        const value = this.registerForm[k];
        
        if (k === 'professional_register_type' || k === 'professional_register_number' || k === 'professional_register_uf') {
            continue;
        }

        if (value !== null && value !== undefined) {
            fd.append(k, value);
        }
    }
    
    if (combinedProfessionalRegister) {
        fd.append('professional_register', combinedProfessionalRegister);
    }

    if (this.userPhotoFile) fd.append('photo', this.userPhotoFile);
    
    const r = await this.apiRequest('registerUser', fd, true);
    
    if (r.success) {
        this.showToast('Sucesso!', `Cadastro realizado. Faça o login para continuar.`, 'success');
        this.hideModal('register-modal');
    } else if (r.error) {
        this.showToast('Erro no Cadastro', r.error, 'error');
    }
}

export function openPasswordResetModal() {
    if(this.passwordResetStep !== 2) {
        this.resetPasswordForm = { email: '', token: '', password: '' };
        this.passwordResetStep = 1;
    }
    this.passwordStrength = 0;
    this.passwordFeedback = '';
    this.showModal('forgot-password-modal');
}

export async function handleRequestPasswordReset() {
    const r = await this.apiRequest('requestPasswordReset', { email: this.resetPasswordForm.email });
    if (r.success) {
        this.showToast('Verifique seu E-mail', `Instruções de redefinição foram enviadas.`, 'success', 10000);
        this.hideModal('forgot-password-modal');
    }
}

export async function handlePerformPasswordReset() {
    const r = await this.apiRequest('performPasswordReset', this.resetPasswordForm);
    if (r.success) {
        this.showToast('Sucesso!', 'Sua senha foi redefinida. Você já pode fazer login.', 'success');
        this.hideModal('forgot-password-modal');
        window.location.href = './index.php';
    }
}

export function startSession(user, token = null) {
    user.weekly_schedule = this.ensureValidSchedule(user.weekly_schedule);
    user.disabled_dates = Array.isArray(user.disabled_dates) ? user.disabled_dates : [];
    
    sessionStorage.setItem('currentUser', JSON.stringify(user));

    // ** ALTERAÇÃO CSRF: Salva o token se ele foi fornecido (Login) **
    // Se não for fornecido (ex: atualização de perfil), mantém o token antigo
    if (token) {
        sessionStorage.setItem('csrf_token', token);
    }

    // Usamos replace() para substituir a entrada atual no histórico
    if (user.isAdmin == 1) {
        window.location.replace('./admin.php');
    } else {
        window.location.replace('./user.php');
    }
}

export function logout() {
    // Limpa intervalos ativos
    if (this.countdownInterval) clearInterval(this.countdownInterval);
    if (this.clockInterval) clearInterval(this.clockInterval);
    
    // Limpa dados da sessão
    this.currentUser = null;
    sessionStorage.clear(); 
    localStorage.removeItem('currentUser'); 

    // Força redirecionamento para index.php
    window.location.replace('./index.php');
}

export function openRegisterModal() {
    this.registerForm = this.getNewRegisterTemplate();
    this.activeRegisterTab = 'rules';
    this.passwordStrength = 0;
    this.passwordFeedback = '';
    
    // Inicializa array de especialidades vazio
    this.specialties = [];
    
    if (!this.professions.length || !this.customFieldOptions.length) { this.fetchPublicConfig(); }
    this.showModal('register-modal');
}
