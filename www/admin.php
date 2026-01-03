&
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

                        <div v-show="activeAdminUserCustomTab === 'funcionalidades'">
                            <h3 class="text-lg font-semibold border-b pb-2 mb-4">Ativação de Funcionalidades</h3>
                            <div class="space-y-4">
                                
                                <div>
                                    <label class="block text-sm font-medium">Agenda (Geral)</label>
                                    <div class="flex items-center mt-2">
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" v-model="editingUser.agenda_enabled" :true-value="1" :false-value="0" class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                                        </label>
                                        <span class="ml-3 font-medium">{{ editingUser.agenda_enabled == 1 ? 'Ativada' : 'Desativada' }}</span>
                                    </div>
                                </div>

                                <div class="pt-4 border-t" :class="{'opacity-50': editingUser.agenda_enabled != 1}">
                                    <label class="block text-sm font-medium">Menu Aniversariantes</label>
                                    <div class="flex items-center mt-2">
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" v-model="editingUser.birthday_list_enabled" :true-value="1" :false-value="0" class="sr-only peer" :disabled="editingUser.agenda_enabled != 1">
                                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                                        </label>
                                        <span class="ml-3 font-medium">{{ editingUser.birthday_list_enabled == 1 && editingUser.agenda_enabled == 1 ? 'Ativado' : 'Desativado' }}</span>
                                    </div>
                                </div>

                                <div class="pt-4 border-t" :class="{'opacity-50': editingUser.agenda_enabled != 1}">
                                    <label class="block text-sm font-medium">Agenda Espera/Não Resolvidos</label>
                                    <div class="flex items-center mt-2">
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" v-model="editingUser.waiting_list_enabled" @change="handleWaitingListChange" :true-value="1" :false-value="0" class="sr-only peer" :disabled="editingUser.agenda_enabled != 1">
                                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                                        </label>
                                        <span class="ml-3 font-medium">{{ editingUser.waiting_list_enabled == 1 && editingUser.agenda_enabled == 1 ? 'Ativada' : 'Desativada' }}</span>
                                    </div>
                                </div>
                            
                                <div class="pt-4 border-t" :class="{'opacity-50': editingUser.agenda_enabled != 1 || editingUser.waiting_list_enabled != 1}">
                                    <label class="block text-sm font-medium">Agenda Futura</label>
                                    <div class="flex items-center mt-2">
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" v-model="editingUser.future_schedule_enabled" @change="handleFutureScheduleChange" :true-value="1" :false-value="0" class="sr-only peer" :disabled="editingUser.agenda_enabled != 1 || editingUser.waiting_list_enabled != 1">
                                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                                        </label>
                                        <span class="ml-3 font-medium">{{ editingUser.future_schedule_enabled == 1 && editingUser.agenda_enabled == 1 && editingUser.waiting_list_enabled == 1 ? 'Ativada' : 'Desativada' }}</span>
                                    </div>
                                </div>
                                
                                <div class="pt-4 border-t">
                                    <label class="block text-sm font-medium">Integração MEMED</label>
                                    <div class="flex items-center mt-2">
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" v-model="editingUser.memed_enabled" :true-value="1" :false-value="0" class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                                        </label>
                                        <span class="ml-3 font-medium">{{ editingUser.memed_enabled == 1 ? 'Ativado' : 'Desativado' }}</span>
                                    </div>
                                </div>

                                <div class="pt-4 border-t" v-if="editingUser.system_version === 'Saude'">
                                    <label class="block text-sm font-medium">Odontograma Interativo</label>
                                    <div class="flex items-center mt-2">
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" v-model="editingUser.odontogram_enabled" :true-value="1" :false-value="0" class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                                        </label>
                                        <span class="ml-3 font-medium">{{ editingUser.odontogram_enabled == 1 ? 'Ativado' : 'Desativado' }}</span>
                                    </div>
                                </div>

                                <div class="pt-4 border-t">
                                    <label class="block text-sm font-medium">Módulo Financeiro</label>
                                    <div class="flex items-center mt-2">
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" v-model="editingUser.finance_enabled" :true-value="1" :false-value="0" class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                                        </label>
                                        <span class="ml-3 font-medium">{{ editingUser.finance_enabled == 1 ? 'Ativado' : 'Desativado' }}</span>
                                    </div>
                                </div>

                                <div class="ml-4" v-if="editingUser.finance_enabled == 1">
                                    <label class="block text-sm font-medium">Livro Caixa</label>
                                    <div class="flex items-center mt-2">
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" v-model="editingUser.finance_ledger_enabled" :true-value="1" :false-value="0" class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                                        </label>
                                        <span class="ml-3 font-medium">{{ editingUser.finance_ledger_enabled == 1 ? 'Ativado' : 'Desativado' }}</span>
                                    </div>
                                </div>

                                <div class="ml-4" v-if="editingUser.finance_enabled == 1">
                                    <label class="block text-sm font-medium">Previsão Receitas/Desp.</label>
                                    <div class="flex items-center mt-2">
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" v-model="editingUser.finance_forecast_enabled" :true-value="1" :false-value="0" class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                                        </label>
                                        <span class="ml-3 font-medium">{{ editingUser.finance_forecast_enabled == 1 ? 'Ativado' : 'Desativado' }}</span>
                                    </div>
                                </div>
                            </div> 
                        </div>

                        <div v-show="activeAdminUserCustomTab === 'horarios'">
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                <div>
                                    <h3 class="text-lg font-semibold border-b pb-2 mb-4">Horário Semanal</h3>
                                    <div v-if="editingUser.weekly_schedule" class="space-y-4">
                                        <div v-for="(day, index) in weekDaysNames" :key="index" class="border-b pb-3 last:border-b-0 last:pb-0">
                                            <h3 class="font-semibold text-sm mb-2">{{ day }}</h3>
                                            <div class="flex items-center space-x-2 mb-2">
                                                <input type="checkbox" :id="'day-1-'+index" v-model="editingUser.weekly_schedule[index].enabled" class="h-5 w-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                                <label :for="'day-1-'+index" class="w-12 text-sm text-gray-600">Turno 1</label>
                                                <input type="time" v-model="editingUser.weekly_schedule[index].start" class="form-input p-1 text-sm w-24" :disabled="!editingUser.weekly_schedule[index].enabled">
                                                <span class="text-gray-500">às</span>
                                                <input type="time" v-model="editingUser.weekly_schedule[index].end" class="form-input p-1 text-sm w-24" :disabled="!editingUser.weekly_schedule[index].enabled">
                                            </div>
                                            <div class="flex items-center space-x-2">
                                                <input type="checkbox" :id="'day-2-'+index" v-model="editingUser.weekly_schedule[index].enabled2" class="h-5 w-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                                <label :for="'day-2-'+index" class="w-12 text-sm text-gray-600">Turno 2</label>
                                                <input type="time" v-model="editingUser.weekly_schedule[index].start2" class="form-input p-1 text-sm w-24" :disabled="!editingUser.weekly_schedule[index].enabled2">
                                                <span class="text-gray-500">às</span>
                                                <input type="time" v-model="editingUser.weekly_schedule[index].end2" class="form-input p-1 text-sm w-24" :disabled="!editingUser.weekly_schedule[index].enabled2">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <h3 class="text-lg font-semibold border-b pb-2 mb-4">Datas Desativadas</h3>
                                    <div class="flex items-center gap-2 mb-4"> <input type="date" v-model="newDisabledDate" class="form-input flex-grow"> <button type="button" @click="addDisabledDate" class="px-3 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"><i class="fa-solid fa-plus"></i></button> </div>
                                    <div class="max-h-48 overflow-y-auto space-y-2"> <p v-if="!editingUser.disabled_dates || editingUser.disabled_dates.length === 0" class="text-center text-sm text-gray-500 py-4">Nenhuma data desativada adicionada.</p> <div v-else v-for="date in editingUser.disabled_dates" :key="date" class="flex justify-between items-center bg-gray-50 p-2 rounded"> <span class="text-sm font-medium">{{ formatDateForDisabledList(date) }}</span> <button type="button" @click="removeDisabledDate(date)" class="text-red-500 hover:text-red-700 text-xs"><i class="fa-solid fa-times"></i></button> </div> </div>
                                </div>
                            </div>
                        </div>

                        <div v-show="activeAdminUserCustomTab === 'payment_methods'">
                            <h3 class="text-lg font-semibold border-b pb-2 mb-4">Formas de Pagamento</h3>
                            <div class="flex justify-end mb-4">
                                <button @click="openUserPaymentMethodModal(null)" type="button" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">
                                    <i class="fa-solid fa-plus mr-2"></i>Adicionar Método Pessoal para este Usuário
                                </button>
                            </div>

                            <div class="border rounded-lg overflow-hidden">
                                <table class="min-w-full bg-white">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase w-10">Usar</th>
                                            <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase">Nome</th>
                                            <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase">Proprietário</th>
                                            <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <tr v-if="!userPaymentMethods || userPaymentMethods.length === 0">
                                            <td colspan="4" class="p-4 text-center text-gray-500">Nenhuma forma de pagamento disponível.</td>
                                        </tr>
                                        <tr v-for="method in userPaymentMethods" :key="method.id" class="hover:bg-gray-50">
                                            <td class="py-3 px-3 text-center">
                                                <input type="checkbox" :value="method.id" v-model="editingUser.enabled_payment_methods" 
                                                       class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                       :disabled="method.is_default && method.is_global">
                                            </td>
                                            <td class="py-3 px-3 font-medium text-gray-900">{{ method.option_value }}</td>
                                            <td class="py-3 px-3">
                                                <span class="text-xs px-2 py-0.5 rounded-full" 
                                                      :class="method.is_global ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'">
                                                      {{ method.is_global ? 'Global' : 'Pessoal' }}
                                                </span>
                                            </td>
                                            <td class="py-3 px-3 text-sm">
                                                <button @click.prevent="openUserPaymentMethodModal(method)" 
                                                        :disabled="method.is_global"
                                                        :class="{'opacity-30 cursor-not-allowed': method.is_global}"
                                                        class="text-indigo-600 hover:text-indigo-900 mr-3">
                                                    <i class="fa-solid fa-pen"></i>
                                                </button>
                                                <button @click.prevent="deleteUserPaymentMethod(method.id)" 
                                                        :disabled="method.is_global || method.is_default"
                                                        :class="{'opacity-30 cursor-not-allowed': method.is_global || method.is_default}"
                                                        class="text-red-600 hover:text-red-900">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div v-show="activeAdminUserCustomTab === 'comunicacoes'">
                            <h3 class="text-lg font-semibold border-b pb-2 mb-4">Comunicações por E-mail</h3>
                            <div class="space-y-6">
                                <div>
                                    <div class="flex items-center justify-between">
                                        <label class="block text-sm font-medium">Confirmação de Agendamento (Imediato)</label>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" v-model="editingUser.schedule_email_enabled" :true-value="1" :false-value="0" class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                                        </label>
                                    </div>
                                    <textarea v-model="editingUser.schedule_email_template" rows="4" class="form-input mt-2" placeholder="Ex: Olá [PACIENTE], seu agendamento..."></textarea>
                                </div>

                                <div class="pt-4 border-t">
                                    <div class="flex items-center justify-between">
                                        <label class="block text-sm font-medium">Lembrete de Agendamento</label>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" v-model="editingUser.reminder_email_enabled" :true-value="1" :false-value="0" class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                                        </label>
                                    </div>
                                    <div class="flex gap-4 mt-2">
                                        <label class="flex items-center text-sm">
                                            <input type="checkbox" value="24" v-model="editingUser.reminder_email_hours" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                            <span class="ml-2 text-gray-700">24h antes</span>
                                        </label>
                                        <label class="flex items-center text-sm">
                                            <input type="checkbox" value="48" v-model="editingUser.reminder_email_hours" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                            <span class="ml-2 text-gray-700">48h antes</span>
                                        </label>
                                    </div>
                                    <textarea v-model="editingUser.reminder_email_template" rows="4" class="form-input mt-2" placeholder="Ex: Lembrete do agendamento..."></textarea>
                                </div>
                                
                                <div class="pt-4 border-t" v-if="editingUser.future_schedule_enabled == 1">
                                    <div class="flex items-center justify-between">
                                        <label class="block text-sm font-medium">Notificação de Agenda Futura</label>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" v-model="editingUser.future_schedule_email_enabled" :true-value="1" :false-value="0" class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                                        </label>
                                    </div>
                                    <textarea v-model="editingUser.future_schedule_email_template" rows="4" class="form-input mt-2" placeholder="Ex: Olá [PACIENTE], seu retorno..."></textarea>
                                </div>

                                <div class="pt-4 border-t">
                                    <div class="flex items-center justify-between">
                                        <label class="block text-sm font-medium">Mensagem de Aniversário</label>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" v-model="editingUser.birthday_email_enabled" :true-value="1" :false-value="0" class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                                        </label>
                                    </div>
                                    <div class="mt-2">
                                        <label class="block text-sm font-medium">Horário de Envio</label>
                                        <input type="time" v-model="editingUser.birthday_email_time" class="form-input p-1 text-sm w-32">
                                    </div>
                                    <textarea v-model="editingUser.birthday_email_template" rows="4" class="form-input mt-2" placeholder="Ex: Feliz aniversário!"></textarea>
                                </div>
                            </div>
                        </div>

                        <div v-show="activeAdminUserCustomTab === 'integrations'">
                            <h3 class="text-lg font-semibold border-b pb-2 mb-4">Integrações Externas</h3>
                            <div class="space-y-6">
                                <div>
                                    <div class="flex justify-between items-center mb-4">
                                        <span class="font-medium flex items-center gap-2"><i class="fa-brands fa-google text-red-500"></i> Google Calendar</span>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" v-model="editingUser.google_calendar_enabled" :true-value="1" :false-value="0" class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 peer-checked:bg-green-600 transition-all"></div>
                                        </label>
                                    </div>
                                    <div class="grid grid-cols-1 gap-3" v-if="editingUser.google_calendar_enabled == 1">
                                        <div><label class="block text-xs font-medium">Client ID</label><input type="text" v-model="editingUser.google_client_id" class="form-input text-sm"></div>
                                        <div><label class="block text-xs font-medium">Client Secret</label><input type="password" v-model="editingUser.google_client_secret" class="form-input text-sm"></div>
                                    </div>
                                </div>
                                <div class="pt-4 border-t">
                                    <div class="flex justify-between items-center mb-4">
                                        <span class="font-medium flex items-center gap-2"><i class="fa-solid fa-file-prescription text-green-600"></i> Memed (Prescrição)</span>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" v-model="editingUser.memed_enabled" :true-value="1" :false-value="0" class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 peer-checked:bg-green-600 transition-all"></div>
                                        </label>
                                    </div>
                                </div>
                                <div class="pt-4 border-t">
                                    <div class="flex justify-between items-center mb-4">
                                        <span class="font-medium flex items-center gap-2"><i class="fa-solid fa-tooth text-blue-500"></i> Odontograma</span>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" v-model="editingUser.odontogram_enabled" :true-value="1" :false-value="0" class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 peer-checked:bg-green-600 transition-all"></div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-show="activeAdminUserCustomTab === 'maintenance'">
                            <h3 class="text-lg font-semibold border-b pb-2 mb-4">Manutenção e Acesso</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Status do Contratante</label>
                                    <div class="mt-2 flex items-center">
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" v-model="isUserActive" class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                                        </label>
                                        <span class="ml-3 font-medium" :class="isUserActive ? 'text-green-700' : 'text-red-700'">{{ isUserActive ? 'Ativo' : 'Inativo' }}</span>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Data de Desativação (Trial)</label>
                                    <input type="datetime-local" v-model="editingUser.deactivationDate" class="form-input">
                                </div>
                                <div class="md:col-span-2 pt-4 border-t">
                                    <label class="block text-sm font-medium text-gray-700">Senha Administrativa (Limpeza de Dados)</label>
                                    <p class="text-xs text-gray-500 mb-2">Defina uma senha para que o usuário possa realizar limpezas de banco de dados.</p>
                                    <input type="text" v-model="editingUser.admin_password" class="form-input" placeholder="Gerada automaticamente se vazio">
                                </div>
                                <div class="md:col-span-2 pt-4 border-t">
                                    <label class="block text-sm font-medium text-gray-700">Permissão de Administrador</label>
                                    <div class="mt-2 flex items-center">
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" v-model="editingUser.isAdmin" :true-value="1" :false-value="0" class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                        </label>
                                        <span class="ml-3 font-medium">{{ editingUser.isAdmin == 1 ? 'Sim, é Admin' : 'Não, é Usuário' }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div> <div class="flex justify-end items-center gap-4 mt-8 pt-4 border-t">
                        <button type="button" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300" @click="hideModal('user-modal')">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Salvar</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="profession-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden items-center justify-center p-4 z-50">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
                <h2 class="text-xl font-bold mb-4">{{ editingProfession.id ? 'Editar Profissão' : 'Nova Profissão' }}</h2>
                <form @submit.prevent="saveProfession">
                    <div><label class="block text-sm font-medium">Nome da Profissão</label><input type="text" v-model="editingProfession.name" required class="form-input"></div>
                    <div class="flex justify-end gap-4 mt-6">
                        <button type="button" @click="hideModal('profession-modal')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Salvar</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="anamnesis-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden items-center justify-center p-4 z-50 overflow-y-auto">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl p-6 my-8">
                <button @click="hideModal('anamnesis-modal')" type="button" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark fa-lg"></i></button>
                <h2 class="text-xl font-bold mb-4">{{ editingAnamnesis.id ? 'Editar Modelo Anamnese' : 'Novo Modelo de Anamnese' }}</h2>
                <form @submit.prevent="saveAnamnesisTemplate">
                    <div class="mb-4"><label class="block text-sm font-medium">Título do Modelo *</label><input type="text" v-model="editingAnamnesis.title" required class="form-input"></div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2">Proprietário</label>
                        <div class="flex items-center space-x-4">
                            <label class="flex items-center">
                                <input type="checkbox" v-model="editingAnamnesis.make_global" @change="editingAnamnesis.assign_to_user_id = null" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-gray-700">Tornar Global (visível para todos)</span>
                            </label>
                            <select v-model="editingAnamnesis.assign_to_user_id" class="form-input flex-1" :disabled="editingAnamnesis.make_global">
                                <option :value="null">-- Atribuir a Usuário --</option>
                                <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                            </select>
                        </div>
                    </div>
                    <div><label class="block text-sm font-medium">Conteúdo da Anamnese</label><textarea v-model="editingAnamnesis.content" rows="15" class="form-input"></textarea></div>
                    <div class="flex justify-end gap-4 mt-6">
                        <button type="button" @click="hideModal('anamnesis-modal')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Salvar Modelo</button>
                    </div>
                </form>
            </div>
        </div>
        
        <div id="receipt-template-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden items-center justify-center p-4 z-50 overflow-y-auto">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl p-6 my-8">
                <button @click="hideModal('receipt-template-modal')" type="button" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark fa-lg"></i></button>
                <h2 class="text-xl font-bold mb-4">{{ editingReceipt.id ? 'Editar Modelo Recibo' : 'Novo Modelo de Recibo' }}</h2>
                <form @submit.prevent="saveReceiptTemplate">
                    <div class="mb-4"><label class="block text-sm font-medium">Título do Modelo *</label><input type="text" v-model="editingReceipt.title" required class="form-input"></div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2">Proprietário</label>
                        <div class="flex items-center space-x-4">
                            <label class="flex items-center">
                                <input type="checkbox" v-model="editingReceipt.make_global" @change="editingReceipt.assign_to_user_id = null" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-gray-700">Tornar Global (visível para todos)</span>
                            </label>
                            <select v-model="editingReceipt.assign_to_user_id" class="form-input flex-1" :disabled="editingReceipt.make_global">
                                <option :value="null">-- Atribuir a Usuário --</option>
                                <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="flex items-center">
                            <input type="checkbox" v-model="editingReceipt.is_default" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="ml-2 text-gray-700">Tornar Padrão (para o dono selecionado, ou global)</span>
                        </label>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium">Conteúdo do Recibo</label>
                        <div class="p-2 bg-gray-50 border rounded-md mb-2 text-xs text-gray-600">
                            <strong>Variáveis disponíveis:</strong>
                            [PACIENTE], [CPF], [VALOR], [VALOR_EXTENSO], [DATA], [RECIBO_NUMERO], [DESCRICAO], 
                            [USUARIO_NOME], [USUARIO_PROFISSAO], [USUARIO_CPF], [CIDADE], [DATA_GERACAO]
                        </div>
                        <textarea v-model="editingReceipt.content" rows="15" class="form-input"></textarea>
                    </div>
                    <div class="flex justify-end gap-4 mt-6">
                        <button type="button" @click="hideModal('receipt-template-modal')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Salvar Modelo</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="recommendation-template-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden items-center justify-center p-4 z-50 overflow-y-auto">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl p-6 my-8">
                <button @click="hideModal('recommendation-template-modal')" type="button" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark fa-lg"></i></button>
                <h2 class="text-xl font-bold mb-4">{{ editingRecommendation.id ? 'Editar Recomendação' : 'Nova Recomendação' }}</h2>
                <form @submit.prevent="saveRecommendationTemplate">
                    <div class="mb-4"><label class="block text-sm font-medium">Título *</label><input type="text" v-model="editingRecommendation.title" required class="form-input"></div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2">Proprietário</label>
                        <div class="flex items-center space-x-4">
                            <label class="flex items-center">
                                <input type="checkbox" v-model="editingRecommendation.make_global" @change="editingRecommendation.assign_to_user_id = null" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-gray-700">Tornar Global (visível para todos)</span>
                            </label>
                            <select v-model="editingRecommendation.assign_to_user_id" class="form-input flex-1" :disabled="editingRecommendation.make_global">
                                <option :value="null">-- Atribuir a Usuário --</option>
                                <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                            </select>
                        </div>
                    </div>
                    <div><label class="block text-sm font-medium">Conteúdo (Texto do Rodapé)</label><textarea v-model="editingRecommendation.content" rows="5" class="form-input"></textarea></div>
                    <div class="flex justify-end gap-4 mt-6">
                        <button type="button" @click="hideModal('recommendation-template-modal')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Salvar</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="budget-form-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden items-center justify-center p-4 z-50">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6">
                <h2 class="text-xl font-bold mb-4">{{ editingBudgetForm.id ? 'Editar Formulário' : 'Novo Formulário de Orçamento' }}</h2>
                <form @submit.prevent="saveBudgetForm">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium">Nome do Formulário</label>
                            <input type="text" v-model="editingBudgetForm.name" required class="form-input">
                        </div>
                        <div>
                            <label class="block text-sm font-medium">Identificador Único</label>
                            <input type="text" v-model="editingBudgetForm.identifier" required class="form-input" :disabled="editingBudgetForm.id <= 2">
                            <p class="text-xs text-gray-500 mt-1">Usado pelo sistema. Apenas letras, números e underscore (_). Não pode ser alterado para os formulários padrão.</p>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium mb-2">Campos Visíveis</h3>
                            <label class="flex items-center">
                                <input type="checkbox" v-model="editingBudgetForm.fields.region" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-gray-700">Exibir campo "Região"</span>
                            </label>
                        </div>
                    </div>
                    <div class="flex justify-end gap-4 mt-6 pt-4 border-t">
                        <button type="button" @click="hideModal('budget-form-modal')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Salvar</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="price-item-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden items-center justify-center p-4 z-50">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
                <h2 class="text-xl font-bold mb-4">{{ editingPriceItem.id ? 'Editar Item' : 'Novo Item na Tabela' }}</h2>
                <form @submit.prevent="savePriceItem">
                    <div class="space-y-4">
                        <div><label class="block text-sm font-medium">Nome/Descrição *</label><input type="text" v-model="editingPriceItem.name" required class="form-input"></div>
                        <div><label class="block text-sm font-medium">Categoria</label><input type="text" v-model="editingPriceItem.category" class="form-input"></div>
                        <div><label class="block text-sm font-medium">Custo (R$) *</label><input type="number" step="0.01" v-model.number="editingPriceItem.cost" required class="form-input"></div>
                        <div>
                            <label class="block text-sm font-medium">Tipo de Medida</label>
                            <select v-model="editingPriceItem.unit" class="form-input">
                                <option v-for="opt in getOptionsByType('measurement_unit')" :key="opt.id" :value="opt.option_value"> {{ opt.option_value }} </option>
                                <option v-if="!getOptionsByType('measurement_unit').length" disabled>Carregando...</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-end gap-4 mt-6">
                        <button type="button" @click="hideModal('price-item-modal')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Salvar</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="price-list-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden items-center justify-center p-4 z-50 overflow-y-auto">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6 my-8">
                <button @click="hideModal('price-list-modal')" type="button" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark fa-lg"></i></button>
                <h2 class="text-xl font-bold mb-4">{{ editingPriceList.id ? 'Editar Tabela' : 'Nova Tabela de Preços' }}</h2>
                <form @submit.prevent="savePriceList">
                    <div class="mb-4 space-y-4">
                         <div>
                             <label class="block text-sm font-medium">Nome da Tabela *</label>
                             <input type="text" v-model="editingPriceList.name" required class="form-input">
                         </div>
                         <label class="flex items-center">
                             <input type="checkbox" v-model="editingPriceList.make_global" @change="editingPriceList.user_id = null" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                             <span class="ml-2 text-gray-700">Tornar Global (visível para todos)</span>
                         </label>
                         <div>
                             <label class="block text-sm font-medium">Atribuir a:</label>
                             <select v-model="editingPriceList.user_id" class="form-input" :disabled="editingPriceList.make_global">
                                 <option :value="null">-- Nenhum (se global) --</option>
                                 <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                             </select>
                         </div>
                    </div>
                    <div class="flex justify-end gap-4 mt-6">
                        <button type="button" @click="hideModal('price-list-modal')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Salvar</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="admin-manage-items-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden items-center justify-center p-4 z-40 modal-overlay overflow-y-auto">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-3xl p-6 my-8">
                 <button @click="hideModal('admin-manage-items-modal'); activePriceListForItems = null" type="button" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark fa-lg"></i></button>
                 <div v-if="activePriceListForItems">
                     <h2 class="text-2xl font-bold mb-2">Gerenciando Itens da Tabela: {{ activePriceListForItems.name }}</h2>
                     <p class="text-sm text-gray-600 mb-6" v-if="currentUser.isAdmin && !activePriceListForItems.is_global">Proprietário: {{ activePriceListForItems.user_name }}</p>
                     <p class="text-sm text-blue-600 mb-6" v-if="activePriceListForItems.is_global">Esta é uma Tabela Global.</p>
                 </div>
                 <div class="flex justify-end mb-4">
                     <button @click="openPriceItemModal(null)" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm"><i class="fa-solid fa-plus"></i><span class="hidden sm:inline ml-2">Adicionar Item</span></button>
                 </div>
                 <div class="overflow-x-auto max-h-[60vh]">
                     <table class="min-w-full bg-white">
                         <thead class="bg-gray-50 sticky top-0">
                             <tr>
                                 <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase">Nome/Descrição</th>
                                 <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase hidden sm:table-cell">Categoria</th>
                                 <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase">Custo (R$)</th>
                                 <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase hidden sm:table-cell">Unidade</th>
                                 <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase">Ações</th>
                             </tr>
                         </thead>
                         <tbody class="divide-y divide-gray-200">
                             <tr v-for="item in priceItems" :key="item.id">
                                 <td class="py-4 px-4 whitespace-nowrap font-medium">{{ item.name }}</td>
                                 <td class="py-4 px-4 whitespace-nowrap hidden sm:table-cell">{{ item.category }}</td>
                                 <td class="py-4 px-4 whitespace-nowrap">{{ formatCurrency(item.cost) }}</td>
                                 <td class="py-4 px-4 whitespace-nowrap hidden sm:table-cell">{{ item.unit }}</td>
                                 <td class="py-4 px-4 whitespace-nowrap text-sm font-medium">
                                     <button @click="openPriceItemModal(item)" class="text-indigo-600 hover:text-indigo-900 mr-3"><i class="fa-solid fa-pen-to-square"></i></button>
                                     <button @click="deletePriceItem(item.id)" class="text-red-600 hover:text-red-900"><i class="fa-solid fa-trash-can"></i></button>
                                 </td>
                             </tr>
                             <tr v-if="priceItems.length === 0">
                                 <td colspan="5" class="text-center py-8 text-gray-500">Nenhum item cadastrado nesta tabela de preços.</td>
                             </tr>
                         </tbody>
                     </table>
                 </div>
                 <div class="flex justify-end mt-6">
                     <button type="button" @click="hideModal('admin-manage-items-modal'); activePriceListForItems = null" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Fechar</button>
                 </div>
             </div>
        </div>

        <div id="custom-field-option-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden items-center justify-center p-4 z-50">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
                <button @click="hideModal('custom-field-option-modal')" type="button" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark fa-lg"></i></button>
                <h2 class="text-xl font-bold mb-4">{{ editingCustomFieldOption.id ? 'Editar Opção' : 'Nova Opção' }}</h2>
                <p class="text-sm text-gray-600 mb-4">Para o campo: <strong class="capitalize">{{ editingCustomFieldOption.field_type?.replace('_', ' ') }}</strong></p>
                <form @submit.prevent="saveCustomFieldOption">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium">Valor da Opção *</label>
                            <input type="text" v-model="editingCustomFieldOption.option_value" required class="form-input">
                        </div>
                        
                        <div v-if="editingCustomFieldOption.field_type === 'payment_method'" class="space-y-4 pt-4 border-t">
                            <div>
                                <label class="block text-sm font-medium mb-2">Proprietário</label>
                                <div class="flex items-center space-x-4">
                                    <label class="flex items-center">
                                        <input type="checkbox" v-model="editingCustomFieldOption.make_global" @change="editingCustomFieldOption.assign_to_user_id = null" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-gray-700">Tornar Global</span>
                                    </label>
                                    <select v-model="editingCustomFieldOption.assign_to_user_id" class="form-input flex-1" :disabled="editingCustomFieldOption.make_global">
                                        <option :value="null">-- Atribuir a Usuário --</option>
                                        <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        </div>
                    <div class="flex justify-end gap-4 mt-6">
                        <button type="button" @click="hideModal('custom-field-option-modal')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Salvar Opção</button>
                    </div>
                </form>
            </div>
        </div>
        
        <div id="medicine-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden justify-center p-4 z-50 modal-overlay overflow-y-auto">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6 my-8">
                <button @click="hideModal('medicine-modal')" type="button" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark fa-lg"></i></button>
                <h2 class="text-xl font-bold mb-4">{{ editingMedicine.id ? 'Editar Medicamento' : 'Novo Medicamento' }}</h2>
                <form @submit.prevent="saveMedicine">
                    <div class="space-y-4">
                        <div class="relative">
                            <label class="block text-sm font-medium text-gray-700">Nome do Medicamento *</label>
                            <div class="flex gap-2">
                                <input type="text" v-model="editingMedicine.name" @input="searchMedicines(editingMedicine.name)" required class="form-input flex-grow" placeholder="Digite para buscar ou cadastrar...">
                                <button v-if="editingMedicine.name" type="button" @click="editingMedicine.name = ''; medicines = []" class="text-gray-400 hover:text-gray-600"><i class="fa-solid fa-times"></i></button>
                            </div>
                            
                            <div v-if="medicines.length > 0 && editingMedicine.name && medicines[0].name !== editingMedicine.name" class="absolute z-10 w-full bg-white border rounded-md mt-1 max-h-48 overflow-y-auto shadow-lg">
                                <a v-for="med in medicines" :key="med.id" @click.prevent="selectMedicineForAdmin(med)" class="block px-4 py-2 text-sm hover:bg-blue-50 cursor-pointer border-b last:border-0">
                                    <div class="font-semibold text-gray-800">
                                        {{ med.name }}
                                        <span v-if="med.source === 'external'" class="text-xs text-orange-500 ml-1 font-normal">(Banco Nacional)</span>
                                    </div>
                                    <div class="text-xs text-gray-500 truncate">{{ med.presentation || med.instructions }}</div>
                                </a>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Apresentação</label>
                                <input type="text" v-model="editingMedicine.presentation" class="form-input" placeholder="Ex: Caixa com 21 comp.">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Via de Administração</label>
                                <select v-model="editingMedicine.default_route" class="form-input">
                                    <option value="">Selecione...</option>
                                    <option v-for="opt in getOptionsByType('administration_route')" :key="opt.id" :value="opt.option_value">
                                        {{ opt.option_value }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Posologia Padrão</label>
                            <textarea v-model="editingMedicine.instructions" rows="3" class="form-input" placeholder="Ex: Tomar 1 comprimido de 8 em 8 horas..."></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Duração Padrão</label>
                            <input type="text" v-model="editingMedicine.default_duration" class="form-input" placeholder="Ex: 7 dias">
                        </div>

                        <div class="mb-4 pt-4 border-t">
                            <label class="block text-sm font-medium mb-2">Proprietário</label>
                            <div class="flex items-center space-x-4">
                                <label class="flex items-center">
                                    <input type="checkbox" v-model="editingMedicine.make_global" @change="editingMedicine.assign_to_user_id = null" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <span class="ml-2 text-gray-700">Tornar Global (visível para todos)</span>
                                </label>
                                <select v-model="editingMedicine.assign_to_user_id" class="form-input flex-1" :disabled="editingMedicine.make_global">
                                    <option :value="null">-- Atribuir a Usuário --</option>
                                    <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-4 mt-6 pt-4 border-t">
                        <button type="button" @click="hideModal('medicine-modal')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Salvar</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="exam-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden justify-center p-4 z-50 modal-overlay overflow-y-auto">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6 my-8">
                <button @click="hideModal('exam-modal')" type="button" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark fa-lg"></i></button>
                <h2 class="text-xl font-bold mb-4">{{ editingExam.id ? 'Editar Exame' : 'Novo Exame' }}</h2>
                <form @submit.prevent="saveExam">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium">Nome do Exame *</label>
                            <input type="text" v-model="editingExam.name" required class="form-input">
                        </div>
                        <div>
                            <label class="block text-sm font-medium">Descrição/Justificativa</label>
                            <textarea v-model="editingExam.description" rows="3" class="form-input"></textarea>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-2">Proprietário</label>
                            <div class="flex items-center space-x-4">
                                <label class="flex items-center">
                                    <input type="checkbox" v-model="editingExam.make_global" @change="editingExam.assign_to_user_id = null" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <span class="ml-2 text-gray-700">Tornar Global (visível para todos)</span>
                                </label>
                                <select v-model="editingExam.assign_to_user_id" class="form-input flex-1" :disabled="editingExam.make_global">
                                    <option :value="null">-- Atribuir a Usuário --</option>
                                    <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-4 mt-6">
                        <button type="button" @click="hideModal('exam-modal')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Salvar</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="prescription-template-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden justify-center p-4 z-50 modal-overlay overflow-y-auto">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl p-6 my-8">
                <button @click="hideModal('prescription-template-modal')" type="button" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark fa-lg"></i></button>
                <h2 class="text-xl font-bold mb-4">{{ editingPrescriptionTemplate.id ? 'Editar Modelo' : 'Novo Modelo de Prescrição' }}</h2>
                <form @submit.prevent="savePrescriptionTemplate">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium">Título do Modelo *</label>
                            <input type="text" v-model="editingPrescriptionTemplate.title" required class="form-input">
                        </div>
                        <div>
                            <label class="block text-sm font-medium">Tipo</label>
                            <select v-model="editingPrescriptionTemplate.type" class="form-input">
                                <option value="receita">Receita</option>
                                <option value="exame">Pedido de Exame</option>
                                <option value="atestado">Atestado</option>
                                <option value="outro">Outro</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium">Conteúdo (HTML permitido)</label>
                        <div class="p-2 bg-gray-50 border rounded-md mb-2 text-xs text-gray-600">
                            <strong>Variáveis:</strong> [PACIENTE_NOME], [CPF], [DATA_NASC], [IDADE], [PESO], [ALTURA], [ENDERECO], [DR_NOME], [DR_REGISTRO], [DATA_HOJE]
                        </div>
                        <textarea v-model="editingPrescriptionTemplate.content" rows="12" class="form-input font-mono text-sm"></textarea>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2">Proprietário</label>
                        <div class="flex items-center space-x-4">
                            <label class="flex items-center">
                                <input type="checkbox" v-model="editingPrescriptionTemplate.make_global" @change="editingPrescriptionTemplate.assign_to_user_id = null" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-gray-700">Tornar Global (visível para todos)</span>
                            </label>
                            <select v-model="editingPrescriptionTemplate.assign_to_user_id" class="form-input flex-1" :disabled="editingPrescriptionTemplate.make_global">
                                <option :value="null">-- Atribuir a Usuário --</option>
                                <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-end gap-4 mt-6">
                        <button type="button" @click="hideModal('prescription-template-modal')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Salvar Modelo</button>
                    </div>
                </form>
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

        <div id="admin-manage-specialties-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden justify-center p-4 z-50 modal-overlay overflow-y-auto">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6 my-8">
                <button @click="hideModal('admin-manage-specialties-modal'); activeProfessionForSpecialties = null" type="button" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark fa-lg"></i></button>
                
                <div v-if="activeProfessionForSpecialties">
                    <h2 class="text-xl font-bold mb-2">Especialidades: {{ activeProfessionForSpecialties.name }}</h2>
                    <p class="text-sm text-gray-500 mb-4">Gerencie as especialidades vinculadas a esta profissão.</p>
                    
                    <div class="flex justify-end mb-4">
                         <button @click="openSpecialtyModal(null)" class="px-3 py-1 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700"><i class="fa-solid fa-plus mr-1"></i> Nova Especialidade</button>
                    </div>

                    <div class="border rounded-md max-h-64 overflow-y-auto">
                        <ul class="divide-y divide-gray-200">
                            <li v-for="spec in specialties" :key="spec.id" class="flex justify-between items-center p-3 hover:bg-gray-50">
                                <span class="text-sm text-gray-800">{{ spec.name }}</span>
                                <div>
                                    <button @click="openSpecialtyModal(spec)" class="text-indigo-600 hover:text-indigo-900 mr-3"><i class="fa-solid fa-pen"></i></button>
                                    <button @click="deleteSpecialty(spec.id)" class="text-red-600 hover:text-red-900"><i class="fa-solid fa-trash"></i></button>
                                </div>
                            </li>
                            <li v-if="!specialties.length" class="p-4 text-center text-gray-500 text-sm">Nenhuma especialidade cadastrada.</li>
                        </ul>
                    </div>
                </div>
                
                <div class="flex justify-end mt-6">
                    <button type="button" @click="hideModal('admin-manage-specialties-modal')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Fechar</button>
                </div>
            </div>
        </div>
        
        <div id="specialty-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden items-center justify-center p-4 z-[60]">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-sm p-6">
                <h2 class="text-lg font-bold mb-4">{{ editingSpecialty.id ? 'Editar Especialidade' : 'Nova Especialidade' }}</h2>
                <form @submit.prevent="saveSpecialty">
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">Nome</label>
                        <input type="text" v-model="editingSpecialty.name" required class="form-input w-full">
                    </div>
                    <div class="flex justify-end gap-3">
                        <button type="button" @click="hideModal('specialty-modal')" class="px-3 py-1.5 bg-gray-200 text-gray-800 rounded hover:bg-gray-300 text-sm">Cancelar</button>
                        <button type="submit" class="px-3 py-1.5 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
        
        <div id="user-payment-method-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden items-center justify-center p-4 z-[70]">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
                <button @click="hideModal('user-payment-method-modal')" type="button" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark fa-lg"></i></button>
                <h2 class="text-xl font-bold mb-4">{{ editingUserPaymentMethod.id ? 'Editar Método de Pagamento' : 'Novo Método de Pagamento' }}</h2>
                <p v-if="editingUserPaymentMethod.originalIsGlobal" class="text-xs text-blue-600 mb-4 bg-blue-50 p-2 rounded border border-blue-200">Nota: Você está editando uma cópia de um método global. Salvar criará um novo método pessoal para este usuário.</p>
                <form @submit.prevent="saveUserPaymentMethod">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium">Nome do Método *</label>
                            <input type="text" v-model="editingUserPaymentMethod.option_value" required class="form-input" placeholder="Ex: Cartão de Crédito 3x">
                        </div>
                    </div>
                    <div class="flex justify-end gap-4 mt-6">
                        <button type="button" @click="hideModal('user-payment-method-modal')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Salvar Método</button>
                    </div>
                </form>
            </div>
        </div>
    
    </div>

    <script type="module" src="./Logic/app.js"></script>
</body>
</html>
&