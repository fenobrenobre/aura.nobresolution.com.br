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
    <title>Aura Software - Administração</title>
    <meta name="referrer" content="origin-when-cross-origin">
    <meta http-equiv="Permissions-Policy" content="accelerometer=(self 'https://integrations.memed.com.br'), camera=(self 'https://integrations.memed.com.br'), geolocation=(self 'https://integrations.memed.com.br'), gyroscope=(self 'https://integrations.memed.com.br'), magnetometer=(self 'https://integrations.memed.com.br'), microphone=(self 'https://integrations.memed.com.br'), payment=(self 'https://integrations.memed.com.br'), usb=(self)">
    
    <script src="./css/tailwindcss.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://unpkg.com/vue@3"></script>
    <link rel="stylesheet" href="./css/style.css">

</head>
<body class="bg-gray-50 text-gray-800">

    <div id="app" v-cloak>
        <div v-if="isLoading" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-[100]">
            <div class="animate-spin rounded-full h-16 w-16 border-t-4 border-b-4 border-white"></div>
        </div>

        <div v-if="toast.visible" class="fixed top-5 right-5 z-[101] max-w-sm w-full toast-enter-active">
            <div :class="toast.type === 'success' ? 'bg-green-500' : 'bg-red-500'" class="rounded-lg shadow-lg text-white p-4 flex items-start">
                <i :class="toast.type === 'success' ? 'fa-solid fa-check-circle' : 'fa-solid fa-exclamation-circle'" class="text-xl mr-3 mt-1"></i>
                <div class="flex-1">
                    <p class="font-bold">{{ toast.title }}</p>
                    <p class="text-sm">{{ toast.message }}</p>
                </div>
                <button @click="toast.visible = false" class="ml-2 text-xl">&times;</button>
            </div>
        </div>

        <template v-if="currentUser && currentUser.isAdmin == 1">
            <div id="admin-view" class="relative min-h-screen md:flex">
                <div @click="isSidebarOpen = false" v-if="isSidebarOpen" class="fixed inset-0 bg-black opacity-50 z-20 md:hidden"></div>
                
                <aside :class="{'translate-x-0': isSidebarOpen, '-translate-x-full': !isSidebarOpen}" class="w-64 bg-white shadow-md flex flex-col fixed inset-y-0 left-0 z-30 transform transition-transform duration-300 ease-in-out md:relative md:translate-x-0">
                    <div class="p-4 border-b flex justify-center items-center h-20">
                        <img v-if="currentUser.logo" :src="currentUser.logo" class="max-h-12 w-auto" alt="Logo">
                        <span v-else class="text-2xl font-bold text-gray-800">Aura Sistema</span>
                    </div>
                    <nav class="flex-grow p-4"><ul>
                        <li><a href="#" @click.prevent="activeAdminView = 'users'; isSidebarOpen = false" :class="{'active': activeAdminView === 'users'}" class="flex items-center p-3 rounded-lg hover:bg-gray-100 sidebar-link"><i class="fa-solid fa-users-cog w-6"></i> Usuários</a></li>
                        <li><a href="#" @click.prevent="activeAdminView = 'anamnesis'; isSidebarOpen = false" :class="{'active': activeAdminView === 'anamnesis'}" class="flex items-center p-3 rounded-lg hover:bg-gray-100 sidebar-link"><i class="fa-solid fa-file-medical w-6"></i> Modelos Anamnese</a></li>
                        <li><a href="#" @click.prevent="activeAdminView = 'receipts'; isSidebarOpen = false" :class="{'active': activeAdminView === 'receipts'}" class="flex items-center p-3 rounded-lg hover:bg-gray-100 sidebar-link"><i class="fa-solid fa-receipt w-6"></i> Modelos de Recibo</a></li>
                        
                        <li><a href="#" @click.prevent="activeAdminView = 'recommendations'; isSidebarOpen = false; fetchRecommendationTemplates('admin')" :class="{'active': activeAdminView === 'recommendations'}" class="flex items-center p-3 rounded-lg hover:bg-gray-100 sidebar-link"><i class="fa-solid fa-clipboard-list w-6"></i> Recomendações</a></li>

                        <li><a href="#" @click.prevent="activeAdminView = 'price_lists'; isSidebarOpen = false" :class="{'active': activeAdminView === 'price_lists'}" class="flex items-center p-3 rounded-lg hover:bg-gray-100 sidebar-link"><i class="fa-solid fa-tags w-6"></i> Tabelas de Preços</a></li>
                        <li><a href="#" @click.prevent="activeAdminView = 'budget_forms'; isSidebarOpen = false" :class="{'active': activeAdminView === 'budget_forms'}" class="flex items-center p-3 rounded-lg hover:bg-gray-100 sidebar-link"><i class="fa-solid fa-file-invoice w-6"></i> Formulários Orçam.</a></li>
                        
                        <li><a href="#" @click.prevent="activeAdminView = 'medicines'; isSidebarOpen = false; fetchMedicines('admin')" :class="{'active': activeAdminView === 'medicines'}" class="flex items-center p-3 rounded-lg hover:bg-gray-100 sidebar-link"><i class="fa-solid fa-pills w-6"></i> Medicamentos</a></li>
                        <li><a href="#" @click.prevent="activeAdminView = 'exams'; isSidebarOpen = false; fetchExams('admin')" :class="{'active': activeAdminView === 'exams'}" class="flex items-center p-3 rounded-lg hover:bg-gray-100 sidebar-link"><i class="fa-solid fa-microscope w-6"></i> Exames</a></li>
                        <li><a href="#" @click.prevent="activeAdminView = 'prescription_templates'; isSidebarOpen = false; fetchPrescriptionTemplates('admin')" :class="{'active': activeAdminView === 'prescription_templates'}" class="flex items-center p-3 rounded-lg hover:bg-gray-100 sidebar-link"><i class="fa-solid fa-file-prescription w-6"></i> Modelos Prescrição</a></li>
                        
                        <li><a href="#" @click.prevent="activeAdminView = 'custom_fields'; isSidebarOpen = false" :class="{'active': activeAdminView === 'custom_fields'}" class="flex items-center p-3 rounded-lg hover:bg-gray-100 sidebar-link"><i class="fa-solid fa-tasks w-6"></i> Cadastro de Campos</a></li>
                        <li><a href="#" @click.prevent="activeAdminView = 'settings'; isSidebarOpen = false" :class="{'active': activeAdminView === 'settings'}" class="flex items-center p-3 rounded-lg hover:bg-gray-100 sidebar-link"><i class="fa-solid fa-cogs w-6"></i> Configurações Gerais</a></li>
                    </ul></nav>
                    <div class="p-4 border-t">
                        <p class="font-semibold">{{ currentUser.professionalName || currentUser.name }}</p>
                        <p class="text-sm text-gray-500">Administrador</p>
                        <p v-if="trialCountdown" class="text-xs text-red-500 font-semibold mt-1 animate-pulse">{{ trialCountdown }}</p>
                         <div class="mt-2 text-xs text-gray-500">
                            <p><i class="fa-solid fa-clock mr-1"></i> <a href="https://ntp.br" target="_blank" class="hover:underline">{{ currentTimeString }}</a></p>
                        </div>
                        <button @click="logout" class="w-full mt-4 text-left p-3 rounded-lg hover:bg-red-100 text-red-700"><i class="fa-solid fa-right-from-bracket w-6"></i> Sair</button>
                    </div>
                </aside>

                <div class="flex-1 flex flex-col h-screen overflow-hidden">
                    <header class="bg-white shadow-sm p-4 flex justify-between items-center sticky top-0 z-10 md:hidden flex-shrink-0">
                        <button @click.stop="isSidebarOpen = !isSidebarOpen" class="text-gray-500 focus:outline-none"><i class="fa-solid fa-bars fa-lg"></i></button>
                        <h1 class="text-lg font-semibold">Aura Sistema</h1>
                        <div></div>
                    </header>
                    <main class="flex-1 bg-gray-100 p-4 sm:p-6 lg:p-8 overflow-y-auto">
                        
                        <div v-if="activeAdminView === 'users'">
                            <div class="bg-white p-4 sm:p-6 rounded-lg shadow">
                                <div class="flex flex-col sm:flex-row justify-between sm:items-center mb-4 gap-4">
                                    <h2 class="text-lg font-semibold">Gerenciamento de Contratantes</h2>
                                    <div class="flex gap-2">
                                        <button @click="exportUsersToExcel" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 text-sm" title="Exportar para Excel"><i class="fa-solid fa-file-excel"></i><span class="hidden sm:inline ml-2">Exportar</span></button>
                                        <button @click="openUserModal(null)" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm"><i class="fa-solid fa-plus"></i><span class="hidden sm:inline ml-2">Adicionar</span></button>
                                    </div>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full bg-white">
                                        <thead class="bg-gray-50"><tr>
                                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase">Nome</th>
                                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase hidden sm:table-cell">Profissão</th>
                                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase hidden md:table-cell">Desativação</th>
                                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase">Ações</th>
                                        </tr></thead>
                                        <tbody class="divide-y divide-gray-200">
                                            <tr v-for="user in users" :key="user.id">
                                                <td class="py-4 px-4 whitespace-nowrap"><a href="#" @click.prevent="openUserModal(user)" class="text-blue-600 hover:underline flex items-center"><img :src="user.photo || 'https://placehold.co/40x40/E2E8F0/A0AEC0?text=Foto'" class="w-10 h-10 rounded-full object-cover mr-3">{{ user.name }}</a></td>
                                                <td class="py-4 px-4 whitespace-nowrap hidden sm:table-cell">{{ user.profession }}</td>
                                                <td class="py-4 px-4 whitespace-nowrap"><span :class="user.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">{{ user.status === 'active' ? 'Ativo' : 'Inativo' }}</span></td>
                                                <td class="py-4 px-4 whitespace-nowrap hidden md:table-cell">{{ user.deactivationDate ? new Date(user.deactivationDate.replace(' ','T')).toLocaleString('pt-BR') : 'N/A' }}</td>
                                                <td class="py-4 px-4 whitespace-nowrap text-sm font-medium">
                                                    <button @click="openUserModal(user)" class="text-indigo-600 hover:text-indigo-900 mr-3"><i class="fa-solid fa-pen-to-square"></i></button>
                                                    <button @click="deleteUser(user.id)" class="text-red-600 hover:text-red-900"><i class="fa-solid fa-trash-can"></i></button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div v-if="activeAdminView === 'settings'">
                            <div class="bg-white p-4 sm:p-6 rounded-lg shadow">
                                <h2 class="text-xl font-bold mb-6 border-b pb-4">Configurações Gerais</h2>
                                <form @submit.prevent="saveAdminSettings">
                                    <div class="space-y-8">
                                        <div>
                                            <h3 class="text-lg font-semibold">Período de Teste</h3>
                                            <p class="text-gray-600 text-sm mb-2">Defina por quantos dias uma nova conta permanecerá ativa antes da desativação automática.</p>
                                            <div class="flex items-center gap-2 max-w-xs">
                                                <input type="number" v-model.number="adminSettings.trialDays" min="1" class="form-input">
                                                <span class="text-gray-700">dias</span>
                                            </div>
                                        </div>
                                        
                                        <div class="pt-4 border-t">
                                            <h3 class="text-lg font-semibold">Notificação de Novos Cadastros</h3>
                                            <p class="text-gray-600 text-sm mb-2">Informe o e-mail do administrador para receber um aviso sempre que um novo usuário se cadastrar.</p>
                                            <input type="email" v-model="adminSettings.adminNotificationEmail" class="form-input w-full max-w-md" placeholder="admin@exemplo.com">
                                        </div>

                                        <div class="pt-4 border-t">
                                            <h3 class="text-lg font-semibold">Padrões de Documentos</h3>
                                            <p class="text-gray-600 text-sm mb-2">Defina os modelos padrão para os botões de ação rápida (Atestado/Declaração) para todos os usuários.</p>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700">Modelo de Atestado</label>
                                                    <select v-model="adminSettings.default_atestado_template_id" class="form-input mt-1">
                                                        <option value="">Selecione...</option>
                                                        <option v-for="t in prescriptionTemplates.filter(pt => pt.type === 'atestado')" :key="t.id" :value="t.id">
                                                            {{ t.title }} {{ t.is_global ? '(Global)' : '' }}
                                                        </option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700">Modelo de Declaração</label>
                                                    <select v-model="adminSettings.default_declaracao_template_id" class="form-input mt-1">
                                                        <option value="">Selecione...</option>
                                                        <option v-for="t in prescriptionTemplates.filter(pt => pt.type === 'atestado')" :key="t.id" :value="t.id">
                                                            {{ t.title }} {{ t.is_global ? '(Global)' : '' }}
                                                        </option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="pt-8 border-t">
                                            <h3 class="text-lg font-semibold">LEIA-ME - Regras do Cadastro</h3>
                                            <p class="text-gray-600 text-sm mb-2">Este texto será exibido na primeira aba da tela de cadastro para novos usuários.</p>
                                            <textarea v-model="adminSettings.registrationNotes" rows="15" class="w-full rounded-md border-gray-300 shadow-sm"></textarea>
                                        </div>

                                        <div class="pt-8 border-t">
                                            <h3 class="text-lg font-semibold">Template do Email de Boas-Vindas</h3>
                                            <p class="text-gray-600 text-sm mb-2">Este e-mail será enviado para novos usuários após o cadastro. Use as variáveis abaixo:</p>
                                            <div class="p-2 bg-gray-50 border rounded-md mb-2 text-xs text-gray-600">
                                                <strong>Variáveis disponíveis:</strong>
                                                [NOME_USUARIO], [EMAIL_USUARIO], [PERIODO_TESTE], [SENHA_ADMIN]
                                            </div>
                                            <textarea v-model="adminSettings.welcomeEmailTemplate" rows="15" class="w-full rounded-md border-gray-300 shadow-sm"></textarea>
                                        </div>
                                    </div>

                                    <div class="flex justify-end mt-8 pt-6 border-t">
                                        <button type="submit" class="px-6 py-2 bg-blue-600 text-white font-semibold rounded-md hover:bg-blue-700">Salvar Configurações</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div v-if="activeAdminView === 'anamnesis'">
                            <div class="bg-white p-4 sm:p-6 rounded-lg shadow">
                                <div class="flex flex-col sm:flex-row justify-between sm:items-center mb-4 gap-4">
                                    <h2 class="text-xl font-bold">Gerenciador Global de Modelos de Anamnese</h2>
                                    <button @click="openAnamnesisModal(null)" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm"><i class="fa-solid fa-plus"></i><span class="hidden sm:inline ml-2">Novo Modelo</span></button>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full bg-white">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase">Título</th>
                                                <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase hidden sm:table-cell">Proprietário</th>
                                                <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200">
                                            <tr v-for="template in anamnesisTemplates" :key="template.id">
                                                <td class="py-4 px-4 whitespace-nowrap font-medium">{{ template.title }}</td>
                                                <td class="py-4 px-4 whitespace-nowrap hidden sm:table-cell">
                                                    <span v-if="template.is_global" class="text-blue-600 font-semibold">Global</span>
                                                    <div v-else>
                                                        <div>{{ template.user_name }}</div>
                                                        <div class="text-xs text-gray-500">{{ template.user_email }}</div>
                                                    </div>
                                                </td>
                                                <td class="py-4 px-4 whitespace-nowrap text-sm font-medium">
                                                    <button @click="openAnamnesisModal(template)" class="text-indigo-600 hover:text-indigo-900 mr-3" title="Editar"><i class="fa-solid fa-pen-to-square"></i></button>
                                                    <button @click="deleteAnamnesisTemplate(template.id)" class="text-red-600 hover:text-red-900" title="Excluir"><i class="fa-solid fa-trash-can"></i></button>
                                                </td>
                                            </tr>
                                            <tr v-if="anamnesisTemplates.length === 0">
                                                <td colspan="3" class="text-center py-8 text-gray-500">Nenhum modelo de anamnese encontrado no sistema.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <div v-if="activeAdminView === 'receipts'">
                            <div class="bg-white p-4 sm:p-6 rounded-lg shadow">
                                <div class="flex flex-col sm:flex-row justify-between sm:items-center mb-4 gap-4">
                                    <h2 class="text-xl font-bold">Gerenciador Global de Modelos de Recibo</h2>
                                    <button @click="openReceiptModal(null)" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm"><i class="fa-solid fa-plus"></i><span class="hidden sm:inline ml-2">Novo Modelo</span></button>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full bg-white">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase">Título</th>
                                                <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase hidden sm:table-cell">Proprietário</th>
                                                <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase">Padrão</th>
                                                <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200">
                                            <tr v-for="template in receiptTemplates" :key="template.id">
                                                <td class="py-4 px-4 whitespace-nowrap font-medium">{{ template.title }}</td>
                                                <td class="py-4 px-4 whitespace-nowrap hidden sm:table-cell">
                                                    <span v-if="template.is_global" class="text-blue-600 font-semibold">Global</span>
                                                    <div v-else>
                                                        <div>{{ template.user_name }}</div>
                                                        <div class="text-xs text-gray-500">{{ template.user_email }}</div>
                                                    </div>
                                                </td>
                                                <td class="py-4 px-4 whitespace-nowrap">
                                                    <span v-if="template.is_default" class="text-xs bg-yellow-100 text-yellow-800 px-1.5 py-0.5 rounded-full">Padrão</span>
                                                </td>
                                                <td class="py-4 px-4 whitespace-nowrap text-sm font-medium">
                                                    <button @click="openReceiptModal(template)" class="text-indigo-600 hover:text-indigo-900 mr-3" title="Editar"><i class="fa-solid fa-pen-to-square"></i></button>
                                                    <button @click="deleteReceiptTemplate(template.id)" class="text-red-600 hover:text-red-900" title="Excluir"><i class="fa-solid fa-trash-can"></i></button>
                                                </td>
                                            </tr>
                                            <tr v-if="receiptTemplates.length === 0">
                                                <td colspan="4" class="text-center py-8 text-gray-500">Nenhum modelo de recibo encontrado no sistema.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div v-if="activeAdminView === 'recommendations'">
                            <div class="bg-white p-4 sm:p-6 rounded-lg shadow">
                                <div class="flex flex-col sm:flex-row justify-between sm:items-center mb-4 gap-4">
                                    <h2 class="text-xl font-bold">Gerenciador Global de Recomendações</h2>
                                    <button @click="openRecommendationModal(null)" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm"><i class="fa-solid fa-plus"></i><span class="hidden sm:inline ml-2">Nova Recomendação</span></button>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full bg-white">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase">Título</th>
                                                <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase hidden sm:table-cell">Proprietário</th>
                                                <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200">
                                            <tr v-for="template in recommendationTemplates" :key="template.id">
                                                <td class="py-4 px-4 whitespace-nowrap font-medium">{{ template.title }}</td>
                                                <td class="py-4 px-4 whitespace-nowrap hidden sm:table-cell">
                                                    <span v-if="template.is_global" class="text-blue-600 font-semibold">Global</span>
                                                    <div v-else>
                                                        <div>{{ template.user_name }}</div>
                                                        <div class="text-xs text-gray-500">{{ template.user_email }}</div>
                                                    </div>
                                                </td>
                                                <td class="py-4 px-4 whitespace-nowrap text-sm font-medium">
                                                    <button @click="openRecommendationModal(template)" class="text-indigo-600 hover:text-indigo-900 mr-3" title="Editar"><i class="fa-solid fa-pen"></i></button>
                                                    <button @click="deleteRecommendationTemplate(template.id)" class="text-red-600 hover:text-red-900" title="Excluir"><i class="fa-solid fa-trash-can"></i></button>
                                                </td>
                                            </tr>
                                            <tr v-if="recommendationTemplates.length === 0">
                                                <td colspan="3" class="text-center py-8 text-gray-500">Nenhum modelo de recomendação encontrado.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div v-if="activeAdminView === 'price_lists'">
                            <div class="bg-white p-4 sm:p-6 rounded-lg shadow">
                                <div class="flex flex-col sm:flex-row justify-between sm:items-center mb-4 gap-4">
                                    <h2 class="text-xl font-bold">Gerenciador Global de Tabelas de Preços</h2>
                                    <div class="flex gap-2">
                                        <button @click="downloadPriceListTemplate" class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 text-sm"><i class="fa-solid fa-download"></i><span class="hidden sm:inline ml-2">Baixar Modelo</span></button>
                                        <input type="file" ref="admin_xls_import" @change="importPriceList" class="hidden" accept=".xlsx, .xls">
                                        <button @click="$refs.admin_xls_import.click()" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 text-sm"><i class="fa-solid fa-file-import"></i><span class="hidden sm:inline ml-2">Importar Tabela</span></button>
                                        <button @click="openPriceListModal(null)" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm"><i class="fa-solid fa-plus"></i><span class="hidden sm:inline ml-2">Nova Tabela</span></button>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700">Buscar Tabela ou Usuário</label>
                                    <input type="text" v-model="adminPriceListSearch" placeholder="Digite nome da tabela, do usuário ou email..." class="form-input max-w-lg">
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full bg-white">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase">Nome da Tabela</th>
                                                <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase hidden sm:table-cell">Proprietário</th>
                                                <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200">
                                            <tr v-for="list in filteredAllPriceLists" :key="list.id">
                                                <td class="py-4 px-4 whitespace-nowrap font-medium">{{ list.name }}</td>
                                                <td class="py-4 px-4 whitespace-nowrap hidden sm:table-cell">
                                                    <span v-if="list.is_global" class="text-blue-600 font-semibold">Global</span>
                                                    <div v-else>
                                                        <div>{{ list.user_name }}</div>
                                                        <div class="text-xs text-gray-500">{{ list.user_email }}</div>
                                                    </div>
                                                </td>
                                                <td class="py-4 px-4 whitespace-nowrap text-sm font-medium">
                                                    <button @click="managePriceListItems(list)" class="text-blue-600 hover:text-blue-900 mr-3" title="Gerenciar Itens"><i class="fa-solid fa-list-check"></i></button>
                                                    <button @click="openPriceListModal(list)" class="text-indigo-600 hover:text-indigo-900 mr-3" title="Editar"><i class="fa-solid fa-pen-to-square"></i></button>
                                                    <button @click="deletePriceList(list.id)" class="text-red-600 hover:text-red-900" title="Excluir"><i class="fa-solid fa-trash-can"></i></button>
                                                </td>
                                            </tr>
                                            <tr v-if="allPriceLists.length === 0">
                                                <td colspan="3" class="text-center py-8 text-gray-500">Nenhuma tabela de preços encontrada no sistema.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div v-if="activeAdminView === 'budget_forms'">
                             <div class="bg-white p-4 sm:p-6 rounded-lg shadow">
                                <div class="flex flex-col sm:flex-row justify-between sm:items-center mb-4 gap-4">
                                    <h2 class="text-lg font-semibold">Gerenciamento de Formulários de Orçamento</h2>
                                    <button @click="openBudgetFormModal(null)" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm"><i class="fa-solid fa-plus"></i><span class="hidden sm:inline ml-2">Novo Formulário</span></button>
                                </div>
                                <ul class="space-y-3">
                                    <li v-for="form in budgetForms" :key="form.id" class="flex justify-between items-center bg-gray-50 p-3 rounded-md">
                                        <div>
                                            <span class="font-medium">{{ form.name }}</span>
                                            <span class="ml-4 text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full">{{ form.identifier }}</span>
                                            <span v-if="form.id <= 2" class="ml-2 text-xs bg-yellow-100 text-yellow-800 px-2 py-0.5 rounded-full">Padrão</span>
                                        </div>
                                        <div>
                                            <button @click="openBudgetFormModal(form)" class="text-indigo-600 hover:text-indigo-900 mr-3"><i class="fa-solid fa-pen-to-square"></i></button>
                                            <button @click="deleteBudgetForm(form.id)" class="text-red-600 hover:text-red-900" :class="{'opacity-50 cursor-not-allowed': form.id <= 2}" :disabled="form.id <= 2" title="Não é possível excluir formulários padrão"><i class="fa-solid fa-trash-can"></i></button>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        
                        <div v-if="activeAdminView === 'medicines'">
                            <div class="bg-white p-4 sm:p-6 rounded-lg shadow">
                                <div class="flex flex-col sm:flex-row justify-between sm:items-center mb-4 gap-4">
                                    <h2 class="text-xl font-bold">Catálogo Global de Medicamentos</h2>
                                    <button @click="openMedicineModal(null)" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm"><i class="fa-solid fa-plus mr-2"></i>Novo Medicamento</button>
                                </div>
                                <div class="mb-4">
                                    <input type="text" placeholder="Buscar medicamento..." @input="fetchMedicines('admin', $event.target.value)" class="form-input max-w-md">
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full bg-white text-sm">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="py-3 px-4 text-left font-medium text-gray-500 uppercase">Nome</th>
                                                <th class="py-3 px-4 text-left font-medium text-gray-500 uppercase">Proprietário</th>
                                                <th class="py-3 px-4 text-left font-medium text-gray-500 uppercase">Apresentação</th>
                                                <th class="py-3 px-4 text-left font-medium text-gray-500 uppercase">Posologia</th>
                                                <th class="py-3 px-4 text-center font-medium text-gray-500 w-24">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200">
                                            <tr v-if="medicines.length === 0"><td colspan="5" class="p-4 text-center text-gray-500">Nenhum medicamento encontrado.</td></tr>
                                            <tr v-for="med in medicines" :key="med.id" class="hover:bg-gray-50">
                                                <td class="py-3 px-4 font-medium">{{ med.name }}</td>
                                                <td class="py-3 px-4 whitespace-nowrap">
                                                    <span v-if="med.is_global" class="text-blue-600 font-semibold">Global</span>
                                                    <div v-else>{{ med.user_name }}</div>
                                                </td>
                                                <td class="py-3 px-4 text-gray-600 truncate max-w-xs">{{ med.presentation }}</td>
                                                <td class="py-3 px-4 text-gray-600 truncate max-w-xs" :title="med.instructions">{{ med.instructions }}</td>
                                                <td class="py-3 px-4 text-center">
                                                    <button @click="openMedicineModal(med)" class="text-indigo-600 hover:text-indigo-900 mr-3"><i class="fa-solid fa-pen"></i></button>
                                                    <button @click="deleteMedicine(med.id)" :disabled="!med.is_global" :class="{'opacity-30 cursor-not-allowed': !med.is_global}" title="Excluir"><i class="fa-solid fa-trash"></i></button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div v-if="activeAdminView === 'exams'">
                            <div class="bg-white p-4 sm:p-6 rounded-lg shadow">
                                <div class="flex flex-col sm:flex-row justify-between sm:items-center mb-4 gap-4">
                                    <h2 class="text-xl font-bold">Catálogo Global de Exames</h2>
                                    <button @click="openExamModal(null)" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm"><i class="fa-solid fa-plus mr-2"></i>Novo Exame</button>
                                </div>
                                <div class="mb-4">
                                    <input type="text" placeholder="Buscar exame..." @input="fetchExams('admin', $event.target.value)" class="form-input max-w-md">
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full bg-white text-sm">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="py-3 px-4 text-left font-medium text-gray-500">Nome</th>
                                                <th class="py-3 px-4 text-left font-medium text-gray-500">Proprietário</th>
                                                <th class="py-3 px-4 text-left font-medium text-gray-500">Descrição/Justificativa</th>
                                                <th class="py-3 px-4 text-center font-medium text-gray-500 w-24">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200">
                                            <tr v-if="exams.length === 0"><td colspan="4" class="p-4 text-center text-gray-500">Nenhum exame encontrado.</td></tr>
                                            <tr v-for="exam in exams" :key="exam.id" class="hover:bg-gray-50">
                                                <td class="py-3 px-4 font-medium">{{ exam.name }}</td>
                                                <td class="py-3 px-4 whitespace-nowrap">
                                                    <span v-if="exam.is_global" class="text-blue-600 font-semibold">Global</span>
                                                    <div v-else>{{ exam.user_name }}</div>
                                                </td>
                                                <td class="py-3 px-4 text-gray-600 truncate max-w-lg" :title="exam.description">{{ exam.description }}</td>
                                                <td class="py-3 px-4 text-center">
                                                    <button @click="openExamModal(exam)" class="text-indigo-600 hover:text-indigo-900 mr-3"><i class="fa-solid fa-pen"></i></button>
                                                    <button @click="deleteExam(exam.id)" :disabled="!exam.is_global" :class="{'opacity-30 cursor-not-allowed': !exam.is_global}" title="Excluir"><i class="fa-solid fa-trash"></i></button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div v-if="activeAdminView === 'prescription_templates'">
                            <div class="bg-white p-4 sm:p-6 rounded-lg shadow">
                                <div class="flex flex-col sm:flex-row justify-between sm:items-center mb-4 gap-4">
                                    <h2 class="text-xl font-bold">Modelos de Prescrição Globais</h2>
                                    <button @click="openPrescriptionTemplateModal(null)" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm"><i class="fa-solid fa-plus mr-2"></i>Novo Modelo</button>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full bg-white text-sm">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="py-3 px-4 text-left font-medium text-gray-500">Título</th>
                                                <th class="py-3 px-4 text-left font-medium text-gray-500">Proprietário</th>
                                                <th class="py-3 px-4 text-left font-medium text-gray-500">Tipo</th>
                                                <th class="py-3 px-4 text-center font-medium text-gray-500 w-24">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200">
                                            <tr v-if="prescriptionTemplates.length === 0"><td colspan="4" class="p-4 text-center text-gray-500">Nenhum modelo cadastrado.</td></tr>
                                            <tr v-for="tpl in prescriptionTemplates" :key="tpl.id" class="hover:bg-gray-50">
                                                <td class="py-3 px-4 font-medium">{{ tpl.title }}</td>
                                                <td class="py-3 px-4 whitespace-nowrap">
                                                    <span v-if="tpl.is_global" class="text-blue-600 font-semibold">Global</span>
                                                    <div v-else>{{ tpl.user_name }}</div>
                                                </td>
                                                <td class="py-3 px-4 capitalize text-gray-600">{{ tpl.type }}</td>
                                                <td class="py-3 px-4 text-center">
                                                    <button @click="openPrescriptionTemplateModal(tpl)" class="text-indigo-600 hover:text-indigo-900 mr-3"><i class="fa-solid fa-pen"></i></button>
                                                    <button @click="deletePrescriptionTemplate(tpl.id)" :disabled="!tpl.is_global" :class="{'opacity-30 cursor-not-allowed': !tpl.is_global}" title="Excluir"><i class="fa-solid fa-trash"></i></button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="mt-4 p-3 bg-blue-50 border border-blue-100 rounded text-xs text-blue-800">
                                    <strong>Variáveis Disponíveis nos Modelos:</strong>
                                    [PACIENTE_NOME], [CPF], [DATA_NASC], [IDADE], [PESO], [ALTURA], [ENDERECO], [DR_NOME], [DR_REGISTRO], [DATA_HOJE]
                                </div>
                            </div>
                        </div>

                        <div v-if="activeAdminView === 'custom_fields'">
                            <div class="bg-white p-4 sm:p-6 rounded-lg shadow">
                                <h2 class="text-xl font-bold mb-6 border-b pb-4">Cadastro de Campos e Opções</h2>

                                <div class="mb-6 border-b">
                                    <nav class="-mb-px flex space-x-6 overflow-x-auto">
                                        <button @click="activeCustomFieldTab = 'professions'" :class="{'active': activeCustomFieldTab === 'professions'}" class="py-2 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 tab-button whitespace-nowrap">Profissões</button>
                                        <button @click="activeCustomFieldTab = 'periodicity'" :class="{'active': activeCustomFieldTab === 'periodicity'}" class="py-2 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 tab-button whitespace-nowrap">Periodicidade</button>
                                        <button @click="activeCustomFieldTab = 'measurement_unit'" :class="{'active': activeCustomFieldTab === 'measurement_unit'}" class="py-2 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 tab-button whitespace-nowrap">Tipos de Medida</button>
                                        <button @click="activeCustomFieldTab = 'gender'" :class="{'active': activeCustomFieldTab === 'gender'}" class="py-2 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 tab-button whitespace-nowrap">Sexo</button>
                                        <button @click="activeCustomFieldTab = 'marital_status'" :class="{'active': activeCustomFieldTab === 'marital_status'}" class="py-2 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 tab-button whitespace-nowrap">Estado Civil</button>
                                        <button @click="activeCustomFieldTab = 'budget_status'" :class="{'active': activeCustomFieldTab === 'budget_status'}" class="py-2 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 tab-button whitespace-nowrap">Status Orçamento</button>
                                        <button @click="activeCustomFieldTab = 'service_status'" :class="{'active': activeCustomFieldTab === 'service_status'}" class="py-2 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 tab-button whitespace-nowrap">Status Atendimento</button>
                                        <button @click="activeCustomFieldTab = 'payment_status'" :class="{'active': activeCustomFieldTab === 'payment_status'}" class="py-2 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 tab-button whitespace-nowrap">Status Pagamento</button>
                                        <button @click="activeCustomFieldTab = 'payment_method'" :class="{'active': activeCustomFieldTab === 'payment_method'}" class="py-2 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 tab-button whitespace-nowrap">Forma Pagamento</button>
                                        <button @click="activeCustomFieldTab = 'administration_route'" :class="{'active': activeCustomFieldTab === 'administration_route'}" class="py-2 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 tab-button whitespace-nowrap">Vias de Administração</button>
                                    </nav>
                                </div>

                                <div>
                                    <div v-show="activeCustomFieldTab === 'professions'">
                                        <div class="flex justify-between items-center mb-4">
                                            <h3 class="text-lg font-semibold">Profissões</h3>
                                            <button @click="openProfessionModal(null)" class="px-3 py-1 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700"><i class="fa-solid fa-plus mr-1"></i> Nova</button>
                                        </div>
                                        <ul class="space-y-2 max-h-96 overflow-y-auto pr-2">
                                            <li v-for="profession in professions" :key="profession.id" class="flex justify-between items-center bg-gray-50 p-2 rounded-md">
                                                <span class="font-medium">{{ profession.name }}</span>
                                                <div class="flex items-center">
                                                    <button @click="manageSpecialties(profession)" class="text-sm bg-teal-100 text-teal-800 px-2 py-1 rounded hover:bg-teal-200 mr-3" title="Gerenciar Especialidades">
                                                        <i class="fa-solid fa-stethoscope mr-1"></i> Especialidades
                                                    </button>
                                                    <button @click="openProfessionModal(profession)" class="text-indigo-600 hover:text-indigo-900 mr-3"><i class="fa-solid fa-pen"></i></button>
                                                    <button @click="deleteProfession(profession.id)" class="text-red-600 hover:text-red-900"><i class="fa-solid fa-trash"></i></button>
                                                </div>
                                            </li>
                                            <li v-if="!professions.length" class="text-center text-gray-500 py-4">Nenhuma profissão cadastrada.</li>
                                        </ul>
                                    </div>

                                    <div v-for="fieldType in customFieldTypes" :key="fieldType.type" v-show="activeCustomFieldTab === fieldType.type">
                                        <div class="flex justify-between items-center mb-4">
                                            <h3 class="text-lg font-semibold">{{ fieldType.label }}</h3>
                                            <button @click="openCustomFieldOptionModal(null, fieldType.type)" class="px-3 py-1 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700"><i class="fa-solid fa-plus mr-1"></i> Nova Opção</button>
                                        </div>
                                        <ul class="space-y-2 max-h-96 overflow-y-auto pr-2">
                                            <li v-for="option in getOptionsByType(fieldType.type)" :key="option.id" class="flex justify-between items-center bg-gray-50 p-2 rounded-md">
                                                <span>
                                                    {{ option.option_value }}
                                                    <span v-if="option.field_type === 'payment_method'" class="ml-2 text-xs" :class="option.is_global ? 'text-blue-600' : 'text-gray-500'">
                                                        ({{ option.is_global ? 'Global' : option.user_name }})
                                                    </span>
                                                    <span v-if="option.is_default" class="ml-2 text-xs bg-yellow-100 text-yellow-800 px-1.5 py-0.5 rounded-full" title="Opção padrão do sistema">Padrão</span>
                                                </span>
                                                <div>
                                                    <button @click="openCustomFieldOptionModal(option, fieldType.type)" class="text-indigo-600 hover:text-indigo-900 mr-3"><i class="fa-solid fa-pen"></i></button>
                                                    <button @click="deleteCustomFieldOption(option.id)" class="text-red-600 hover:text-red-900" :class="{'opacity-30 cursor-not-allowed': option.is_default || !option.is_deletable}" :disabled="option.is_default || !option.is_deletable" title="Opções padrão ou marcadas como não deletáveis não podem ser excluídas"><i class="fa-solid fa-trash"></i></button>
                                                </div>
                                            </li>
                                             <li v-if="!getOptionsByType(fieldType.type).length" class="text-center text-gray-500 py-4">Nenhuma opção cadastrada para {{ fieldType.label.toLowerCase() }}.</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </main>
                </div>
            </div>
        </template>
        
        <div v-else-if="currentUser && currentUser.isAdmin != 1">
            <div class="min-h-screen flex items-center justify-center p-4 bg-gray-100">
                <p class="text-red-600">Acesso negado. Redirecionando...</p>
             </div>
        </div>
        <div v-else>
             <div class="min-h-screen flex items-center justify-center p-4 bg-gray-100">
                <p class="text-gray-500">Verificando acesso...</p>
             </div>
        </div>

        <div id="confirm-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden items-center justify-center p-4 modal-overlay z-[70]">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
                 <h2 class="text-xl font-bold mb-4">Confirmar Ação</h2>
                 <p class="text-gray-700 mb-6">{{ confirmationModal.message }}</p>
                 <div class="flex justify-end gap-4">
                     <button @click="hideConfirmModal" type="button" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancelar</button>
                     <button @click="confirmationModal.onConfirm" type="button" :class="confirmationModal.confirmButtonClass" class="px-4 py-2 text-white rounded-md">Sim, Confirmar</button>
                 </div>
             </div>
        </div>
        
        <div id="user-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden items-center justify-center p-4 modal-overlay overflow-y-auto z-40">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md sm:max-w-2xl lg:max-w-5xl p-6 my-8">
                <button @click="hideModal('user-modal')" type="button" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark fa-lg"></i></button>
                <h2 class="text-2xl font-bold mb-6">{{ editingUser.id ? `Editando Contratante: ${editingUser.name}` : 'Novo Contratante' }}</h2>
                <form @submit.prevent="saveUser">
                    <div class="border-b border-gray-200 mb-6">
                        <nav class="-mb-px flex space-x-6 overflow-x-auto">
                            <button type="button" @click="activeUserTab = 'main'" :class="{'active': activeUserTab === 'main'}" class="py-2 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 tab-button whitespace-nowrap">Dados Principais</button>
                            <button type="button" @click="activeUserTab = 'docs'" :class="{'active': activeUserTab === 'docs'}" class="py-2 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 tab-button whitespace-nowrap">Documentação</button>
                            <button type="button" @click="activeUserTab = 'contact'" :class="{'active': activeUserTab === 'contact'}" class="py-2 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 tab-button whitespace-nowrap">Endereço/Contato</button>
                            <button type="button" @click="activeUserTab = 'custom'" :class="{'active': activeUserTab === 'custom'}" class="py-2 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 tab-button whitespace-nowrap">Personalizações</button>
                        </nav>
                    </div>
                    
                    <div v-show="activeUserTab === 'main'">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-4">
                            <div class="md:col-span-1 flex flex-col items-center pt-4 space-y-6">
                                <div>
                                    <p class="text-center font-medium mb-2">Foto de Perfil</p>
                                    <img :src="userPhotoPreview || editingUser.photo || 'https://placehold.co/150x150/E2E8F0/A0AEC0?text=Foto'" class="w-36 h-36 rounded-full object-cover bg-gray-200 mb-4">
                                    <input type="file" id="admin-user-photo" @change="handlePhotoUpload($event, 'user')" class="hidden" accept="image/*">
                                    <div class="flex gap-2 w-full max-w-xs">
                                        <button type="button" @click="triggerFileUpload('admin-user-photo')" class="flex-1 text-sm py-2 bg-gray-200 rounded-md"><i class="fa-solid fa-upload mr-2"></i>Carregar</button>
                                        <button type="button" @click="openWebcamModal('user')" class="flex-1 text-sm py-2 bg-gray-200 rounded-md"><i class="fa-solid fa-camera mr-2"></i>Webcam</button>
                                    </div>
                                </div>
                                <div>
                                    <p class="text-center font-medium mb-2">Logo</p>
                                    <img :src="logoPreview || editingUser.logo || 'https://placehold.co/200x100/E2E8F0/A0AEC0?text=Logo'" class="w-48 h-24 object-contain bg-gray-200 mb-4 border">
                                    <input type="file" id="admin-user-logo" @change="handleLogoUpload" class="hidden" accept="image/*">
                                    <button type="button" @click="triggerFileUpload('admin-user-logo')" class="text-sm w-full max-w-xs py-2 bg-gray-200 rounded-md">Carregar Logo</button>
                                </div>
                            </div>

                            <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium">Nome Completo *</label>
                                    <input type="text" v-model="editingUser.name" required class="form-input">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium">Nome Profissional/Nome Comercial (Ex: Dr. Nome) *</label>
                                    <input type="text" v-model="editingUser.professionalName" required class="form-input" placeholder="Como aparecerá em recibos/orçamentos">
                                </div>
                                <div class="md:col-span-1">
                                    <label class="block text-sm font-medium">Email *</label>
                                    <input type="email" v-model="editingUser.email" required class="form-input">
                                </div>
                                <div class="md:col-span-1">
                                    <label class="block text-sm font-medium">Senha *</label>
                                    <input type="password" v-model="editingUser.password" @input="checkPasswordStrength(editingUser.password)" class="form-input" placeholder="Mínimo 8 caracteres">
                                    <div v-if="passwordStrength > 0" class="flex items-center mt-2">
                                        <div class="password-strength-bar-container"><div class="password-strength-bar" :class="['strength-' + passwordStrength]"></div></div>
                                        <span class="ml-2 text-sm font-medium" :class="['strength-text-' + passwordStrength]">{{ passwordFeedback }}</span>
                                    </div>
                                </div>
                                <div class="md:col-span-1">
                                    <label class="block text-sm font-medium">Celular (WhatsApp) *</label>
                                    <input type="tel" v-model="editingUser.phone" 
                                           @input="editingUser.phone = formatPhone($event.target.value)" 
                                           required class="form-input" placeholder="00-00000-0000">
                                </div>
                                <div class="md:col-span-1">
                                    <label class="block text-sm font-medium">Data de Nascimento</label>
                                    <input type="date" v-model="editingUser.birthdate" class="form-input">
                                </div>
                                <div class="md:col-span-1">
                                    <label class="block text-sm font-medium">Sexo</label>
                                    <select v-model="editingUser.gender" class="form-input">
                                        <option :value="null">Selecione...</option>
                                        <option v-for="opt in getOptionsByType('gender')" :key="opt.id" :value="opt.option_value">
                                            {{ opt.option_value }}
                                        </option>
                                    </select>
                                </div>
                                <div class="md:col-span-1">
                                    <label class="block text-sm font-medium">Estado Civil</label>
                                    <select v-model="editingUser.marital_status" class="form-input">
                                        <option :value="null">Selecione...</option>
                                        <option v-for="opt in getOptionsByType('marital_status')" :key="opt.id" :value="opt.option_value">
                                            {{ opt.option_value }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div v-show="activeUserTab === 'docs'">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                            <div class="md:col-span-1">
                                <label class="block text-sm font-medium">CPF / CNPJ *</label>
                                <input type="text" v-model="editingUser.cpf" 
                                       @input="editingUser.cpf = formatCPF_CNPJ($event.target.value); validateDocument(editingUser.cpf, 'editingUser')" 
                                       required class="form-input" 
                                       :class="{'is-invalid': editingUser.isDocumentInvalid}"
                                       placeholder="000.000.000-00 ou 00.000.000/0000-00">
                                <p v-if="editingUser.isDocumentInvalid" class="text-red-600 text-xs mt-1">CPF/CNPJ inválido.</p>
                            </div>
                            <div class="md:col-span-1">
                                <label class="block text-sm font-medium">Profissão *</label>
                                <select v-model="editingUser.profession" @change="updateSpecialtiesForUser" required class="form-input">
                                    <option disabled value="">Selecione...</option>
                                    <option v-for="p in professions" :key="p.id" :value="p.name">{{ p.name }}</option>
                                </select>
                            </div>

                            <div class="md:col-span-1">
                                <label class="block text-sm font-medium">Especialidade</label>
                                <select v-model="editingUser.specialty" class="form-input" :disabled="!specialties.length">
                                    <option :value="null">Selecione a Especialidade...</option>
                                    <option v-for="spec in specialties" :key="spec.id" :value="spec.name">{{ spec.name }}</option>
                                </select>
                                <p v-if="!editingUser.profession" class="text-xs text-gray-500 mt-1">Selecione a Profissão primeiro.</p>
                            </div>
                            
                             <div class="md:col-span-2 grid grid-cols-1 sm:grid-cols-3 gap-x-4 gap-y-4">
                                <div>
                                    <label class="block text-sm font-medium">Tipo de Registro</label>
                                    <input type="text" v-model="editingUser.professional_register_type" class="form-input" placeholder="Ex: CRO, CRM, CREFITO">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium">Número</label>
                                    <input type="text" v-model="editingUser.professional_register_number" class="form-input" placeholder="Ex: 123456">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium">UF</label>
                                    <input type="text" v-model="editingUser.professional_register_uf" class="form-input" placeholder="Ex: MG" maxlength="2">
                                </div>
                            </div>

                            <div class="md:col-span-1">
                                <label class="block text-sm font-medium">Indicado Por</label>
                                <input type="text" v-model="editingUser.referred_by" class="form-input" placeholder="Opcional">
                            </div>
                        </div>
                    </div>
                    
                    <div v-show="activeUserTab === 'contact'">
                         <div class="grid grid-cols-1 md:grid-cols-4 gap-x-6 gap-y-4">
                            <div class="md:col-span-1">
                                <label class="block text-sm font-medium">CEP *</label>
                                <input type="text" v-model="editingUser.zip_code" 
                                       @input="editingUser.zip_code = formatCEP($event.target.value)" 
                                       @blur="fetchAddressByZipCode('user')" 
                                       required class="form-input" placeholder="00000-000">
                            </div>
                            <div class="md:col-span-3">
                                <label class="block text-sm font-medium">Rua *</label>
                                <input type="text" v-model="editingUser.street" required class="form-input">
                            </div>
                            <div class="md:col-span-1">
                                <label class="block text-sm font-medium">Nº *</label>
                                <input type="text" v-model="editingUser.street_number" required class="form-input">
                            </div>
                            <div class="md:col-span-1">
                                <label class="block text-sm font-medium">Bairro *</label>
                                <input type="text" v-model="editingUser.neighborhood" required class="form-input">
                            </div>
                            <div class="md:col-span-1">
                                <label class="block text-sm font-medium">Cidade *</label>
                                <input type="text" v-model="editingUser.city" required class="form-input">
                            </div>
                            <div class="md:col-span-1">
                                <label class="block text-sm font-medium">Estado (UF) *</label>
                                <input type="text" v-model="editingUser.state" required class="form-input" maxlength="2" placeholder="UF">
                            </div>
                            <div class="md:col-span-4">
                                <label class="block text-sm font-medium">Complemento</label>
                                <input type="text" v-model="editingUser.address_complement" class="form-input">
                            </div>
                        </div>
                    </div>
                    
                    <div v-show="activeUserTab === 'custom'">
                        
                        <div class="mb-6 bg-gray-50 p-2 rounded-lg border border-gray-200">
                            <div class="flex flex-wrap gap-2">
                                <button type="button" @click="activeAdminUserCustomTab = 'system'" :class="activeAdminUserCustomTab === 'system' ? 'bg-white text-blue-700 shadow font-semibold' : 'text-gray-600 hover:bg-gray-100'" class="px-3 py-1.5 rounded-md text-sm transition-all">Geral</button>
                                <button type="button" @click="activeAdminUserCustomTab = 'funcionalidades'" :class="activeAdminUserCustomTab === 'funcionalidades' ? 'bg-white text-blue-700 shadow font-semibold' : 'text-gray-600 hover:bg-gray-100'" class="px-3 py-1.5 rounded-md text-sm transition-all">Funcionalidades</button>
                                <button type="button" @click="activeAdminUserCustomTab = 'horarios'" :class="activeAdminUserCustomTab === 'horarios' ? 'bg-white text-blue-700 shadow font-semibold' : 'text-gray-600 hover:bg-gray-100'" class="px-3 py-1.5 rounded-md text-sm transition-all">Horários</button>
                                <button type="button" @click="activeAdminUserCustomTab = 'payment_methods'; fetchUserPaymentMethods(editingUser.id);" :class="activeAdminUserCustomTab === 'payment_methods' ? 'bg-white text-blue-700 shadow font-semibold' : 'text-gray-600 hover:bg-gray-100'" class="px-3 py-1.5 rounded-md text-sm transition-all">Pagamentos</button>
                                <button type="button" @click="activeAdminUserCustomTab = 'comunicacoes'" :class="activeAdminUserCustomTab === 'comunicacoes' ? 'bg-white text-blue-700 shadow font-semibold' : 'text-gray-600 hover:bg-gray-100'" class="px-3 py-1.5 rounded-md text-sm transition-all">Comunicações</button>
                                <button type="button" @click="activeAdminUserCustomTab = 'integrations'" :class="activeAdminUserCustomTab === 'integrations' ? 'bg-white text-blue-700 shadow font-semibold' : 'text-gray-600 hover:bg-gray-100'" class="px-3 py-1.5 rounded-md text-sm transition-all">Integrações</button>
                                <button type="button" @click="activeAdminUserCustomTab = 'maintenance'" :class="activeAdminUserCustomTab === 'maintenance' ? 'bg-white text-blue-700 shadow font-semibold' : 'text-gray-600 hover:bg-gray-100'" class="px-3 py-1.5 rounded-md text-sm transition-all">Manutenção</button>
                            </div>
                        </div>

                        <div v-show="activeAdminUserCustomTab === 'system'">
                            <h3 class="text-lg font-semibold border-b pb-2 mb-4">Configurações Gerais do Sistema</h3>
                            <div class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                                    <div>
                                        <label class="block text-sm font-medium">Fuso Horário</label>
                                        <select v-model="editingUser.timezone" required class="form-input">
                                            <option disabled value="">Selecione o fuso...</option>
                                            <option v-for="tz in timezones" :key="tz" :value="tz">{{ tz }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium">Versão do Sistema</label>
                                        <select v-model="editingUser.system_version" required class="form-input">
                                            <option value="Saude">Saúde (Pacientes, Anamnese, etc.)</option>
                                            <option value="Tecnica">Técnico (Clientes, Registros Técnicos, etc.)</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="pt-4 border-t">
                                    <h3 class="text-md font-medium text-gray-800">Intervalo da Agenda</h3>
                                    <div class="mt-2"> <label class="block text-sm font-medium">Duração do Slot (minutos)</label> <select v-model.number="editingUser.appointment_slot_minutes" class="form-input max-w-xs"> <option value="15">15 minutos</option> <option value="30">30 minutos</option> <option value="60">60 minutos</option> </select> </div>
                                </div>
                                
                                <div class="pt-4 border-t">
                                        <h3 class="text-md font-medium text-gray-800">Controle de Faltas</h3>
                                        <div class="mt-2">
                                        <label class="block text-sm font-medium text-gray-700">Tolerância para Falta (minutos)</label>
                                        <input type="number" v-model.number="editingUser.missed_appointment_tolerance" class="form-input max-w-xs" min="15" placeholder="60">
                                        </div>
                                </div>

                                <div class="pt-4 border-t">
                                    <h3 class="text-md font-medium text-gray-800">Padrões de Documentos</h3>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">
                                        <div>
                                            <label class="block text-sm font-medium">Tipo de Anamnese (Padrão)</label>
                                            <select v-model="editingUser.anamnesis_template_id" class="form-input">
                                                <option :value="null">Nenhum (usará o 1º global)</option>
                                                <option v-for="template in userAnamnesisTemplates" :key="template.id" :value="template.id">
                                                    {{ template.title }} {{ template.is_global ? '(Global)' : '(Próprio)' }}
                                                </option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium">Tipo de Orçamento (Padrão)</label>
                                            <select v-model="editingUser.default_budget_form_identifier" class="form-input">
                                                <option :value="null">Padrão (Odontológico/Clínico)</option>
                                                <option v-for="form in budgetForms" :key="form.identifier" :value="form.identifier">{{ form.name }}</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium">Modelo Padrão de Recibo</label>
                                            <select v-model="editingUser.default_receipt_template_id" class="form-input">
                                                <option :value="null">Nenhum (usará o 1º global)</option>
                                                <option v-for="template in userReceiptTemplates" :key="template.id" :value="template.id">
                                                    {{ template.title }} {{ template.is_global ? '(Global)' : '(Próprio)' }}
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>