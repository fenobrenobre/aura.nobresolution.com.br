<?php
// --- PROTEÇÃO DE ROTA E SESSÃO ---
if (session_status() == PHP_SESSION_NONE) {
    session_name('SESSION_AURASOLUTION');
    
    // Configurações de segurança e tempo de vida
    ini_set('session.gc_maxlifetime', 86400); // 24 horas
    ini_set('session.cookie_lifetime', 86400);
    ini_set('session.use_strict_mode', 1); // Previne fixação de sessão
    
    // Detecção de HTTPS
    $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    
    // IMPORTANTE: Para integrações externas (Memed/Google), o cookie deve ser:
    // Secure = true (obrigatório se SameSite=None)
    // SameSite = None (permite envio em requisições cross-site/iframes)
    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => $isSecure,     // Deve ser TRUE em produção (HTTPS)
        'httponly' => true,        // Previne acesso via JS (XSS)
        'samesite' => $isSecure ? 'None' : 'Lax' // 'None' é crucial para integrações
    ]);
    
    session_start();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aura Software</title>
    
    <meta name="referrer" content="no-referrer-when-downgrade">
    <meta name="referrer" content="origin-when-cross-origin">
    <meta http-equiv="Permissions-Policy" content="accelerometer=(self 'https://integrations.memed.com.br'), camera=(self 'https://integrations.memed.com.br'), geolocation=(self 'https://integrations.memed.com.br'), gyroscope=(self 'https://integrations.memed.com.br'), magnetometer=(self 'https://integrations.memed.com.br'), microphone=(self 'https://integrations.memed.com.br'), payment=(self 'https://integrations.memed.com.br'), usb=(self)">

    <script src="./css/tailwindcss.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
    <link rel="stylesheet" href="./css/style.css">
    
    <script>
        // Salva o token gerado pelo PHP imediatamente no armazenamento local
        const serverToken = "<?php echo $_SESSION['csrf_token']; ?>";
        if(serverToken) {
            sessionStorage.setItem('csrf_token', serverToken);
            console.log("Sessão PHP iniciada. Token de segurança injetado.");
        }
    </script>
    
    <style>
        @media (min-width: 768px) {
            body { transform: scale(1); transform-origin: center center; }
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800">

    <div id="app" v-cloak>
        <div v-if="isLoading" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-[100]">
            <div class="animate-spin rounded-full h-16 w-16 border-t-4 border-b-4 border-white"></div>
        </div>

        <div v-if="toast.visible" class="fixed top-5 right-5 z-[101] max-w-sm toast-container">
            <div :class="{
                'bg-green-100 border-green-400 text-green-700': toast.type === 'success',
                'bg-red-100 border-red-400 text-red-700': toast.type === 'error',
                'bg-blue-100 border-blue-400 text-blue-700': toast.type === 'info'
            }" class="border-l-4 p-4 rounded-md shadow-lg" role="alert">
                <div class="flex">
                    <div class_A="py-1">
                        <i v-if="toast.type === 'success'" class="fa-solid fa-check-circle mr-3"></i>
                        <i v-if="toast.type === 'error'" class="fa-solid fa-exclamation-triangle mr-3"></i>
                        <i v-if="toast.type === 'info'" class="fa-solid fa-info-circle mr-3"></i>
                    </div>
                    <div>
                        <p class="font-bold">{{ toast.title }}</p>
                        <p class="text-sm">{{ toast.message }}</p>
                    </div>
                    <button @click="toast.visible = false" class="ml-auto pl-3 -mt-1 -mr-1">&times;</button>
                </div>
            </div>
        </div>

        <div class="max-h-screen flex flex-col justify-center items-center py-12 px-4 sm:px-6 lg:px-8">
            <div class="sm:mx-auto sm:w-full sm:max-w-md">
                <div class="bg-white py-6 px-4 shadow sm:rounded-lg sm:px-10">
                    
                    <img class="mx-auto h-64 w-auto mb-1" src="https://aura.nobresolution.com.br/Capa.png">
                    
                    <form @submit.prevent="handleLogin" class="space-y-4">
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                            <div class="mt-1">
                                <input v-model="loginForm.email" id="email" name="email" type="email" autocomplete="email" required class="form-input">
                            </div>
                        </div>

                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700">Senha</label>
                            <div class="mt-1">
                                <input v-model="loginForm.password" id="password" name="password" type="password" autocomplete="current-password" required class="form-input">
                            </div>
                        </div>

                        <div class="flex items-center justify-between">
                            <div class="text-sm">
                                <a href="#" @click.prevent="openPasswordResetModal" class="font-medium text-blue-600 hover:text-blue-500">Esqueceu sua senha?</a>
                            </div>
                        </div>

                        <div>
                            <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">Entrar</button>
                        </div>
                    </form>

                    <div class="mt-6">
                        <div class="relative">
                            <div class="absolute inset-0 flex items-center">
                                <div class="w-full border-t border-gray-300"></div>
                            </div>
                            <div class="relative flex justify-center text-sm mb-4 mt-6"> 
                                <span class="px-2 bg-white text-gray-500 text-center">Google Autenticação - Somente para usuários cadastrados previamente</span>
                            </div>
                        </div>

                        <div class="mt-6 grid grid-cols-1 gap-3">
                            <div>
                                <div id="google-button-container" class="w-full flex justify-center"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-6 text-center text-sm">
                        <button @click.prevent="openRegisterModal" class="w-full mt-2 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            CADASTRA-SE (Experimente 15 dias)
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <div id="register-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden items-center justify-center p-4 modal-overlay overflow-y-auto z-40">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] my-8">
                <div class="flex justify-between items-center border-b p-4">
                    <h2 class="text-2xl font-bold">Criar minha conta (Teste {{ publicTrialDays || 15 }} dias)</h2>
                    <button @click="hideModal('register-modal')" type="button" class="text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark fa-lg"></i></button>
                </div>
                
                <div class="p-6 overflow-y-auto" style="max-height: calc(90vh - 140px);">
                    
                    <div class="border-b border-gray-200 mb-6">
                        <nav class="-mb-px flex space-x-6 overflow-x-auto">
                            <button type="button" @click="activeRegisterTab = 'rules'" :class="{'active': activeRegisterTab === 'rules'}" class="py-2 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 tab-button whitespace-nowrap">Leia-me</button>
                            <button type="button" @click="activeRegisterTab = 'main'" :class="{'active': activeRegisterTab === 'main'}" class="py-2 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 tab-button whitespace-nowrap">Dados Principais</button>
                            <button type="button" @click="activeRegisterTab = 'docs'" :class="{'active': activeRegisterTab === 'docs'}" class="py-2 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 tab-button whitespace-nowrap">Documentação</button>
                            <button type="button" @click="activeRegisterTab = 'contact'" :class="{'active': activeRegisterTab === 'contact'}" class="py-2 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 tab-button whitespace-nowrap">Endereço/Contato</button>
                            <button type="button" @click="activeRegisterTab = 'custom'" :class="{'active': activeRegisterTab === 'custom'}" class="py-2 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 tab-button whitespace-nowrap">Personalizações</button>
                        </nav>
                    </div>

                    <div v-show="activeRegisterTab === 'rules'">
                        <h3 class="font-semibold text-lg mb-4">Bem-vindo(a)!</h3>
                        <div v-if="publicRegistrationNotes" class="prose max-w-none" v-html="publicRegistrationNotes.replace(/\n/g, '<br>')"></div>
                        <div v-else class="text-center text-gray-500">Carregando informações...</div>
                    </div>

                    <div>
                        <div v-if="activeRegisterTab !== 'rules' && isGoogleRegister" class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded-md mb-6">
                            <p class="text-blue-800">Seus dados (Nome e Email) foram preenchidos pelo Google. Por favor, complete o restante do seu cadastro.</p>
                        </div>
                        
                        <div v-show="activeRegisterTab === 'main'">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-4">
                                <div class="md:col-span-1">
                                    <label class="block text-sm font-medium">Foto (Opcional)</label>
                                    <div class="flex items-center space-x-3 mt-1">
                                        <img :src="userPhotoPreview || 'https://placehold.co/100x100/E2E8F0/A0AEC0?text=Foto'" @error="e => e.target.src='https://placehold.co/100x100/E2E8F0/A0AEC0?text=Foto'" class="h-24 w-24 rounded-full object-cover bg-gray-200">
                                        <div class="flex flex-col space-y-2">
                                            <button type="button" @click="triggerFileUpload('register-photo-upload')" class="px-3 py-1.5 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">Carregar</button>
                                            <button type="button" @click="openWebcamModal('register')" class="px-3 py-1.5 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">Webcam</button>
                                            <input type="file" ref="register_photo_upload" id="register-photo-upload" @change="handlePhotoUpload($event, 'register')" class="hidden" accept="image/jpeg,image/png,image/gif">
                                        </div>
                                    </div>
                                </div>
                                <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium">Nome Completo *</label>
                                        <input type="text" v-model="registerForm.name" required class="form-input">
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium">Nome Profissional/Nome Comercial (Ex: Dr. Nome) *</label>
                                        <input type="text" v-model="registerForm.professionalName" required class="form-input" placeholder="Como aparecerá em recibos/orçamentos">
                                    </div>
                                    <div class="md:col-span-1">
                                        <label class="block text-sm font-medium">Email * <span class="text-xs text-gray-500">(Este será seu usuário no "Login")</span></label>
                                        <input type="email" v-model="registerForm.email" required class="form-input" :readonly="isGoogleRegister" :class="{'bg-gray-100': isGoogleRegister}">
                                    </div>
                                    <div class="md:col-span-1">
                                        <label class="block text-sm font-medium">Senha *</label>
                                        <input type="password" v-model="registerForm.password" @input="checkPasswordStrength(registerForm.password)" required class="form-input" placeholder="Mínimo 8 caracteres">
                                        <div v-if="passwordStrength > 0" class="flex items-center mt-2">
                                            <div class="password-strength-bar-container"><div class="password-strength-bar" :class="['strength-' + passwordStrength]"></div></div>
                                            <span class="ml-2 text-sm font-medium" :class="['strength-text-' + passwordStrength]">{{ passwordFeedback }}</span>
                                        </div>
                                    </div>
                                    <div class="md:col-span-1">
                                        <label class="block text-sm font-medium">Celular (WhatsApp) *</label>
                                        <input type="tel" v-model="registerForm.phone" 
                                               @input="registerForm.phone = formatPhone($event.target.value)" 
                                               required class="form-input" placeholder="00-00000-0000">
                                    </div>
                                    <div class="md:col-span-1">
                                        <label class="block text-sm font-medium">Data de Nascimento</label>
                                        <input type="date" v-model="registerForm.birthdate" class="form-input">
                                    </div>
                                    <div class="md:col-span-1">
                                        <label class="block text-sm font-medium">Sexo</label>
                                        <select v-model="registerForm.gender" class="form-input">
                                            <option :value="null">Selecione...</option>
                                            <option v-for="opt in getOptionsByType('gender')" :key="opt.id" :value="opt.option_value">{{ opt.option_value }}</option>
                                        </select>
                                    </div>
                                    <div class="md:col-span-1">
                                        <label class="block text-sm font-medium">Estado Civil</label>
                                        <select v-model="registerForm.marital_status" class="form-input">
                                            <option :value="null">Selecione...</option>
                                            <option v-for="opt in getOptionsByType('marital_status')" :key="opt.id" :value="opt.option_value">{{ opt.option_value }}</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-show="activeRegisterTab === 'docs'">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                                <div class="md:col-span-1">
                                    <label class="block text-sm font-medium">CPF / CNPJ *</label>
                                    <input type="text" v-model="registerForm.cpf" 
                                           @input="registerForm.cpf = formatCPF_CNPJ($event.target.value); validateDocument(registerForm.cpf, 'registerForm')" 
                                           required class="form-input" 
                                           :class="{'is-invalid': registerForm.isDocumentInvalid}"
                                           placeholder="000.000.000-00 ou 00.000.000/0000-00">
                                    <p v-if="registerForm.isDocumentInvalid" class="text-red-600 text-xs mt-1">CPF/CNPJ inválido.</p>
                                </div>
                                <div class="md:col-span-1">
                                    <label class="block text-sm font-medium">Profissão *</label>
                                    <select v-model="registerForm.profession" @change="updateSpecialtiesForRegister" required class="form-input">
                                        <option disabled value="">Selecione...</option>
                                        <option v-for="p in professions" :key="p.id" :value="p.name">{{ p.name }}</option>
                                    </select>
                                </div>

                                <div class="md:col-span-1">
                                    <label class="block text-sm font-medium">Especialidade</label>
                                    <select v-model="registerForm.specialty" class="form-input" :disabled="!specialties.length">
                                        <option :value="null">Selecione a Especialidade...</option>
                                        <option v-for="spec in specialties" :key="spec.id" :value="spec.name">{{ spec.name }}</option>
                                    </select>
                                    <p v-if="!registerForm.profession" class="text-xs text-gray-500 mt-1">Selecione a Profissão primeiro.</p>
                                </div>
                                
                                <div class="md:col-span-2 grid grid-cols-1 sm:grid-cols-3 gap-x-4 gap-y-4">
                                    <div>
                                        <label class="block text-sm font-medium">Tipo de Registro</label>
                                        <input type="text" v-model="registerForm.professional_register_type" class="form-input" placeholder="Ex: CRO, CRM, CREFITO">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium">Número</label>
                                        <input type="text" v-model="registerForm.professional_register_number" class="form-input" placeholder="Ex: 123456">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium">UF</label>
                                        <input type="text" v-model="registerForm.professional_register_uf" class="form-input" placeholder="Ex: MG" maxlength="2">
                                    </div>
                                </div>
                                <div class="md:col-span-1">
                                    <label class="block text-sm font-medium">Indicado Por</label>
                                    <input type="text" v-model="registerForm.referred_by" class="form-input" placeholder="Opcional">
                                </div>
                            </div>
                        </div>
                        
                        <div v-show="activeRegisterTab === 'contact'">
                             <div class="grid grid-cols-1 md:grid-cols-4 gap-x-6 gap-y-4">
                                <div class="md:col-span-1">
                                    <label class="block text-sm font-medium">CEP *</label>
                                    <input type="text" v-model="registerForm.zip_code" 
                                           @input="registerForm.zip_code = formatCEP($event.target.value)" 
                                           @blur="fetchAddressByZipCode('register')" 
                                           required class="form-input" placeholder="00000-000">
                                </div>
                                <div class="md:col-span-3">
                                    <label class="block text-sm font-medium">Rua *</label>
                                    <input type="text" v-model="registerForm.street" required class="form-input">
                                </div>
                                <div class="md:col-span-1">
                                    <label class="block text-sm font-medium">Nº *</label>
                                    <input type="text" v-model="registerForm.street_number" required class="form-input">
                                </div>
                                <div class="md:col-span-1">
                                    <label class="block text-sm font-medium">Bairro *</label>
                                    <input type="text" v-model="registerForm.neighborhood" required class="form-input">
                                </div>
                                <div class="md:col-span-1">
                                    <label class="block text-sm font-medium">Cidade *</label>
                                    <input type="text" v-model="registerForm.city" required class="form-input">
                                </div>
                                <div class="md:col-span-1">
                                    <label class="block text-sm font-medium">Estado (UF) *</label>
                                    <input type="text" v-model="registerForm.state" required class="form-input" maxlength="2" placeholder="UF">
                                </div>
                                <div class="md:col-span-4">
                                    <label class="block text-sm font-medium">Complemento</label>
                                    <input type="text" v-model="registerForm.address_complement" class="form-input">
                                </div>
                            </div>
                        </div>

                        <div v-show="activeRegisterTab === 'custom'">
                            <p class="text-sm text-gray-700 bg-gray-50 p-3 rounded-md border mb-6">Não se preocupe, todas as estas personalizações poderão ser alteradas dentro do sistema.</p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                                <div class="md:col-span-1">
                                    <label class="block text-sm font-medium">Fuso Horário *</label>
                                    <select v-model="registerForm.timezone" required class="form-input">
                                        <option disabled value="">Selecione o fuso...</option>
                                        <option v-for="tz in timezones" :key="tz" :value="tz">{{ tz }}</option>
                                    </select>
                                </div>
                                <div class="md:col-span-1">
                                    <label class="block text-sm font-medium">Versão do Sistema *</label>
                                    <select v-model="registerForm.system_version" required class="form-input">
                                        <option value="Saude">Saúde (Pacientes, Anamnese, etc.)</option>
                                        <option value="Tecnica">Técnico (Clientes, Registros Técnicos, etc.)</option>
                                    </select>
                                </div>
                                </div>
                        </div>
                    </div>
                    </div>
                
                <div class="flex justify-between items-center bg-gray-50 p-4 border-t rounded-b-lg">
                    <button type="button" v-if="activeRegisterTab !== 'rules'"
                            @click="prevRegisterTab"
                            class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Voltar</button>

                    <button type="button" v-if="activeRegisterTab === 'rules'"
                            @click="hideModal('register-modal')"
                            class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancelar</button>

                    <button type="button" v-if="activeRegisterTab === 'rules'"
                            @click="nextRegisterTab"
                            class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">De acordo, Continuar</button>
                    
                    <button type="button" v-if="activeRegisterTab === 'main'"
                            @click="nextRegisterTab"
                            :disabled="!isRegisterTabMainValid"
                            class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">Continuar</button>
                    
                    <button type="button" v-if="activeRegisterTab === 'docs'"
                            @click="nextRegisterTab"
                            :disabled="!isRegisterTabDocsValid"
                            class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">Continuar</button>

                    <button type="button" v-if="activeRegisterTab === 'contact'"
                            @click="nextRegisterTab"
                            :disabled="!isRegisterTabContactValid"
                            class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">Continuar</button>

                    <button type="button" v-if="activeRegisterTab === 'custom'"
                            @click="handleRegister"
                            :disabled="!isRegisterTabCustomValid"
                            class="px-6 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed">Concluir Cadastro</button>
                </div>
            </div>
        </div>

        
        <div id="forgot-password-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden items-center justify-center p-4 z-50">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
                <button @click="hideModal('forgot-password-modal')" type="button" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark fa-lg"></i></button>
                <h2 class="text-2xl font-bold mb-6">Redefinir Senha</h2>
                
                <div v-if="passwordResetStep === 1">
                    <form @submit.prevent="handleRequestPasswordReset">
                        <p class="text-gray-600 mb-4">Digite seu e-mail e enviaremos instruções para redefinir sua senha.</p>
                        <div>
                            <label for="reset-email" class="block text-sm font-medium text-gray-700">Email</label>
                            <div class="mt-1">
                                <input v-model="resetPasswordForm.email" id="reset-email" name="email" type="email" autocomplete="email" required class="form-input">
                            </div>
                        </div>
                        <div class="flex justify-end gap-4 mt-8 pt-4 border-t">
                            <button type="button" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300" @click="hideModal('forgot-password-modal')">Cancelar</button>
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Enviar Email</button>
                        </div>
                    </form>
                </div>
                
                <div v-if="passwordResetStep === 2">
                    <form @submit.prevent="handlePerformPasswordReset">
                        <p class="text-gray-600 mb-4">Email: {{ resetPasswordForm.email }}</p>
                        <input type="hidden" v-model="resetPasswordForm.token">
                        <input type="hidden" v-model="resetPasswordForm.email">
                        
                        <div>
                            <label for="reset-password" class="block text-sm font-medium text-gray-700">Nova Senha</label>
                            <div class="mt-1">
                                <input v-model="resetPasswordForm.password" id="reset-password" name="password" type="password" required class="form-input" @input="checkPasswordStrength(resetPasswordForm.password)">
                            </div>
                            <div v-if="passwordStrength > 0" class="flex items-center mt-2">
                                <div class="password-strength-bar-container"><div class="password-strength-bar" :class="['strength-' + passwordStrength]"></div></div>
                                <span class="ml-2 text-sm font-medium" :class="['strength-text-' + passwordStrength]">{{ passwordFeedback }}</span>
                            </div>
                        </div>
                        <div class="flex justify-end gap-4 mt-8 pt-4 border-t">
                            <button type="button" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300" @click="hideModal('forgot-password-modal')">Cancelar</button>
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Redefinir Senha</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

         <div id="webcam-modal" class="fixed inset-0 bg-gray-900 bg-opacity-75 hidden items-center justify-center p-4 z-50">
             <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-4 relative">
                 <button @click="closeWebcamModal" type="button" class="absolute top-2 right-2 text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark fa-lg"></i></button>
                 <h2 class="text-xl font-bold mb-4">Capturar Foto</h2>
                 <div class="bg-black rounded-md overflow-hidden">
                     <video ref="webcamVideo" class="w-full h-auto" autoplay playsinline></video>
                 </div>
                 <canvas ref="webcamCanvas" class="hidden"></canvas>
                 <div class="flex justify-center items-center gap-4 mt-4">
                     <button @click="capturePhoto" class="w-16 h-16 bg-white rounded-full border-4 border-blue-500 hover:bg-gray-200" title="Capturar Foto"></button>
                 </div>
             </div>
        </div>
        
        <div class="sm:mx-auto sm:w-full sm:max-w-md mt-12 mb-6">
            <p class="mt-2 text-center text-sm text-gray-600" id="datetime-container"></p>
        </div>
        
    </div> <script>
        document.addEventListener('DOMContentLoaded', () => {
            const container = document.getElementById('datetime-container');
            
            // 1. Obter Data e Hora no formato local do usuário
            const now = new Date();
            const dateOptions = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            const timeOptions = { hour: '2-digit', minute: '2-digit', second: '2-digit' }; 
            
            const dateStr = now.toLocaleDateString('pt-BR', dateOptions);
            const timeStr = now.toLocaleTimeString('pt-BR', timeOptions);
            const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone; // Obtém o fuso completo

            // Texto inicial com data/hora na primeira linha
            let initialHtml = `<i class="fa-solid fa-calendar-alt mr-1"></i> ${dateStr}, ${timeStr} <br> <i class="fa-solid fa-hourglass-half mr-1"></i> Buscando localização e Fuso...`;
            
            container.innerHTML = initialHtml;


            // 2. Obter Localização (Cidade/Estado) usando Geolocalização
            if (navigator.geolocation) {
                // Tenta obter a localização com alta precisão
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        // Se for bem-sucedido, vamos mostrar o fuso na linha de baixo.
                        
                        const finalHtml = `<i class="fa-solid fa-calendar-alt mr-1"></i> ${dateStr}, ${timeStr} <br> <i class="fa-solid fa-globe mr-1"></i> Fuso: ${timezone}`;
                        container.innerHTML = finalHtml;

                    },
                    (error) => {
                        // Erro ao obter geolocalização (usuário bloqueou, etc.). Mostra o fuso do SO na linha de baixo.
                        console.warn(`Erro de Geolocalização: ${error.code} - ${error.message}`);
                        const errorHtml = `<i class="fa-solid fa-calendar-alt mr-1"></i> ${dateStr}, ${timeStr} <br> <i class="fa-solid fa-triangle-exclamation text-yellow-500 mr-1"></i> Localização indisponível. Fuso: ${timezone}`;
                        container.innerHTML = errorHtml;
                    },
                    {
                        enableHighAccuracy: true, 
                        timeout: 5000, 
                        maximumAge: 0
                    }
                );
            } else {
                // Navegador não suporta Geolocalização
                const fallbackHtml = `<i class="fa-solid fa-calendar-alt mr-1"></i> ${dateStr}, ${timeStr} <br> <i class="fa-solid fa-globe mr-1"></i> Fuso: ${timezone}`;
                container.innerHTML = fallbackHtml;
            }
            
            // NOTE: O código de updateTime em tempo real está comentado, mas a estrutura de exibição está correta.
        });
    </script>
    
    <script type="module" src="./Logic/app.js"></script>

</body>
</html>