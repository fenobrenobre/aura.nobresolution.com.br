<?php
// --- INÍCIO DA SESSÃO NO SERVIDOR ---
// Isso garante que o Cookie SESSION_SAUDE seja enviado no primeiro acesso
if (session_status() == PHP_SESSION_NONE) {
    session_name('SESSION_AURASOLUTION');
    ini_set('session.gc_maxlifetime', 86400);
    ini_set('session.cookie_lifetime', 86400);
    
    $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    
    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    session_start();
}

// Garante que temos um Token de Segurança
if (empty($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        $_SESSION['csrf_token'] = uniqid('', true);
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redefinir Senha - Aura Software</title>

    <script src="./css/tailwindcss.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://unpkg.com/vue@3"></script>
    <link rel="stylesheet" href="./css/style.css">
    
    <style>
        body { font-family: 'Inter', sans-serif; }
        [v-cloak] { display: none; }
    </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen p-4">

    <div id="reset-app" v-cloak class="w-full max-w-md">
        
        <div v-if="isLoading" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-[100]">
            <div class="animate-spin rounded-full h-16 w-16 border-t-4 border-b-4 border-white"></div>
        </div>

        <div v-if="toast.visible" class="fixed top-5 right-5 z-[101] max-w-sm w-full">
            <div :class="toast.type === 'success' ? 'bg-green-500' : 'bg-red-500'" class="rounded-lg shadow-lg text-white p-4 flex items-start">
                <i :class="toast.type === 'success' ? 'fa-solid fa-check-circle' : 'fa-solid fa-exclamation-circle'" class="text-xl mr-3 mt-1"></i>
                <div class="flex-1">
                    <p class="font-bold">{{ toast.title }}</p>
                    <p class="text-sm">{{ toast.message }}</p>
                </div>
                <button @click="toast.visible = false" class="ml-2 text-xl">&times;</button>
            </div>
        </div>

        <div class="bg-white p-6 sm:p-8 rounded-xl shadow-lg">
            <div class="text-center mb-6">
                <h1 class="text-2xl font-bold text-gray-800">Redefinir sua Senha</h1>
                <p class="text-gray-500 mt-2">Crie uma nova senha para sua conta.</p>
            </div>

            <form @submit.prevent="handlePerformPasswordReset">
                <div class="space-y-4">
                    <input type="hidden" v-model="resetForm.email">
                    <p class="text-sm text-center text-gray-600">Redefinindo senha para: <strong>{{ resetForm.email }}</strong></p>
                    
                    <input type="hidden" v-model="resetForm.token">

                    <div>
                        <label for="new-password" class="block text-sm font-medium text-gray-700">Nova Senha</label>
                        <input type="password" id="new-password" v-model="resetForm.password" required class="form-input mt-1">
                        <div v-if="resetForm.password" class="mt-2 text-xs flex items-center">
                            <div class="password-strength-bar-container">
                                <div class="password-strength-bar"
                                     :class="{
                                         'strength-1': passwordStrength === 1,
                                         'strength-2': passwordStrength === 2,
                                         'strength-3': passwordStrength === 3,
                                         'strength-4': passwordStrength === 4
                                     }"></div>
                            </div>
                            <span class="feedback-text" :class="{
                                'feedback-1': passwordStrength === 1,
                                'feedback-2': passwordStrength === 2,
                                'feedback-3': passwordStrength === 3,
                                'feedback-4': passwordStrength === 4,
                                'feedback-0': passwordStrength === 0
                            }">{{ passwordFeedback || '&nbsp;' }}</span>
                        </div>
                    </div>
                </div>

                <div class="mt-6">
                    <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Redefinir Senha
                    </button>
                </div>
            </form>
             <div class="text-center mt-6">
                <a href="index.php" class="text-sm font-medium text-blue-600 hover:text-blue-500">Voltar para o Login</a>
            </div>
        </div>
    </div>

    <script>
        const { createApp } = Vue;

        createApp({
            data() {
                return {
                    API_URL: '../BackEnd/api.php',
                    isLoading: false,
                    toast: { visible: false, message: '', title: '', type: 'success' },
                    resetForm: {
                        email: '',
                        token: '',
                        password: ''
                    },
                    passwordStrength: 0,
                    passwordFeedback: '',
                };
            },
            watch: {
                'resetForm.password'(newPassword) {
                    this.checkPasswordStrength(newPassword);
                }
            },
            created() {
                const urlParams = new URLSearchParams(window.location.search);
                const token = urlParams.get('reset_token');
                const email = urlParams.get('email');

                if (token && email) {
                    this.resetForm.token = token;
                    this.resetForm.email = email;
                } else {
                    this.showToast('Erro', 'Link de redefinição inválido ou expirado.', 'error');
                }
            },
            methods: {
                showToast(title, message, type = 'success', duration = 5000) {
                    this.toast = { title, message, type, visible: true };
                    setTimeout(() => { this.toast.visible = false; }, duration);
                },

                checkPasswordStrength(password) {
                    let score = 0;
                    const feedback = [];

                    if (!password || password.length === 0) {
                        this.passwordStrength = 0;
                        this.passwordFeedback = '';
                        return;
                    }
                    if (password.length < 8) feedback.push('Curta (mínimo 8)');
                    else if (password.length >= 8 && password.length <= 11) score += 1;
                    else score += 2;
                    if (/[a-z]/.test(password)) score += 1; else feedback.push('Falta minúscula');
                    if (/[A-Z]/.test(password)) score += 1; else feedback.push('Falta maiúscula');
                    if (/\d/.test(password)) score += 1; else feedback.push('Falta número');
                    if (/[^a-zA-Z\d]/.test(password)) score += 1; else feedback.push('Falta símbolo');

                    if (score <= 2) { this.passwordStrength = 1; this.passwordFeedback = 'Fraca'; }
                    else if (score <= 4) { this.passwordStrength = 2; this.passwordFeedback = 'Média'; }
                    else if (score <= 5) { this.passwordStrength = 3; this.passwordFeedback = 'Forte'; }
                    else { this.passwordStrength = 4; this.passwordFeedback = 'Muito Forte'; }

                     if (this.passwordStrength < 3 && feedback.length > 0) {
                         this.passwordFeedback += ` (${feedback.slice(0, 2).join(', ')})`;
                     }
                },

                async handlePerformPasswordReset() {
                    if (!this.resetForm.email || !this.resetForm.token || !this.resetForm.password) {
                        this.showToast('Erro', 'Formulário incompleto.', 'error');
                        return;
                    }
                    if (this.passwordStrength < 2) {
                         this.showToast('Senha Fraca', 'Por favor, escolha uma senha mais forte.', 'error');
                         return;
                    }

                    this.isLoading = true;
                    try {
                        const response = await fetch(`${this.API_URL}?action=performPasswordReset`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(this.resetForm)
                        });
                        const result = await response.json();

                        if (result.success) {
                            this.showToast('Sucesso!', 'Senha redefinida. Você será redirecionado para o login.', 'success');
                            setTimeout(() => {
                                window.location.href = 'login.html';
                            }, 3000);
                        } else {
                            this.showToast('Erro', result.error || 'Não foi possível redefinir a senha.', 'error');
                        }
                    } catch (error) {
                        this.showToast('Erro de Rede', 'Não foi possível conectar ao servidor.', 'error');
                    } finally {
                        this.isLoading = false;
                    }
                }
            }
        }).mount('#reset-app');
    </script>

</body>
</html>