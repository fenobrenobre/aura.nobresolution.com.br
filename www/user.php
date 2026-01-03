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

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
    <link rel="stylesheet" href="./css/style.css">
    
    <style>
        @media (min-width: 600px) {
            body {
                transform: scale(0.98);
                transform-origin: center center; 
            }
        }
        .form-input, .form-select { width: 100%; }
        @media (min-width: 768px) {
             .form-input, .form-select { width: 75%; }
        }
    </style>
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

        <template v-if="currentUser && currentUser.isAdmin != 1">
            
            <div id="user-dashboard-view" class="relative min-h-screen">
                <div @click="isSidebarOpen = false" v-if="isSidebarOpen" class="fixed inset-0 bg-black opacity-50 z-20 md:hidden"></div>
                
                <aside :class="{'translate-x-0': isSidebarOpen, '-translate-x-full': !isSidebarOpen}" class="w-64 bg-white shadow-md flex flex-col z-30 transform transition-transform duration-300 ease-in-out fixed inset-y-0 left-0 md:translate-x-0">
                
                <nav class="flex-grow p-4 overflow-y-auto">
                        <ul class="mt-4">
                        
                        <li>
                                <a href="#" @click.prevent="activeView = 'patients'; isSidebarOpen = false" 
                                   :class="{'active': activeView === 'patients' || activeView === 'active_services' || activeView === 'budgets'}" 
                                   class="flex items-center p-3 rounded-lg sidebar-link">
                                    <i class="fa-solid fa-users w-6 icon-pacientes"></i> {{ labels.patients }}
                                </a>
                                <ul class="ml-4 mt-1 space-y-1">
                                    <li>
                                        <a href="#" @click.prevent="activeView = 'active_services'; isSidebarOpen = false" 
                                           :class="{'active': activeView === 'active_services'}" 
                                           class="flex items-center p-2 rounded-lg sidebar-sublink">
                                            <i class="fa-solid fa-clipboard-check w-5 mr-1 text-gray-500"></i> Atendimentos Ativos
                                        </a>
                                    </li>
                                    
                                    <li>
                                        <a href="#" @click.prevent="showBudgetList" 
                                           :class="{'active': activeView === 'budgets'}" 
                                           class="flex items-center p-2 rounded-lg sidebar-sublink">
                                            <i class="fa-solid fa-file-invoice-dollar w-5 mr-1 text-gray-500"></i> Orçamentos
                                        </a>
                                    </li>
                                </ul>
                            </li>
                        
                        
                        <li v-if="currentUser.agenda_enabled == 1">
                                <a href="#" @click.prevent="activeView = 'agenda'; isSidebarOpen = false" 
                                   :class="{'active': activeView === 'agenda' || activeView === 'waiting_list' || activeView === 'future_schedule' || activeView === 'birthdays'}" 
                                   class="flex items-center p-3 rounded-lg sidebar-link">
                                    <i class="fa-solid fa-calendar-alt w-6 icon-agenda"></i> Agenda
                                </a>
                                <ul class="ml-4 mt-1 space-y-1">
                                    
                                    <li v-if="currentUser.waiting_list_enabled == 1">
                                        <a href="#" @click.prevent="activeView = 'waiting_list'; isSidebarOpen = false" 
                                           :class="{'active': activeView === 'waiting_list'}" 
                                           class="flex items-center p-2 rounded-lg sidebar-sublink">
                                            <i class="fa-solid fa-clock w-5 mr-1 text-gray-500"></i> Agenda Espera
                                        </a>
                                        </li>
                                    <li v-if="currentUser.future_schedule_enabled == 1">
                                        <a href="#" @click.prevent="activeView = 'future_schedule'; isSidebarOpen = false" 
                                           :class="{'active': activeView === 'future_schedule'}" 
                                           class="flex items-center p-2 rounded-lg sidebar-sublink">
                                            <i class="fa-solid fa-history w-5 mr-1 text-gray-500"></i> Agenda Futura
                                        </a>
                                    </li>
                                    <li v-if="currentUser.birthday_list_enabled == 1">
                                        <a href="#" @click.prevent="activeView = 'birthdays'; isSidebarOpen = false" 
                                           :class="{'active': activeView === 'birthdays'}" 
                                           class="flex items-center p-2 rounded-lg sidebar-sublink">
                                            <i class="fa-solid fa-cake-candles w-5 mr-1 text-pink-500"></i> Aniversariantes
                                        </a>
                                    </li>
                                </ul>
                            </li>
                            
                            <li class="mt-4 pt-4 border-t border-gray-200" v-if="currentUser.finance_enabled == 1">
                                <a href="#" @click.prevent="activeView = 'financeiro_livrocaixa'; isSidebarOpen = false"
                                     class="flex items-center p-3 rounded-lg sidebar-link"
                                     :class="{'active': activeView.startsWith('financeiro_')}"
                                >
                                <i class="fa-solid fa-dollar-sign w-6 icon-financeiro"></i> Financeiro
                                </a>
                                <ul class="mt-1 ml-4 space-y-1">
                                    <li v-if="currentUser.finance_forecast_enabled == 1"><a href="#" @click.prevent="activeView = 'financeiro_previsao'; isSidebarOpen = false" :class="{'active': activeView === 'financeiro_previsao'}" class="flex items-center p-2 rounded-lg sidebar-sublink"><i class="fa-solid fa-chart-line w-5 mr-1"></i> Previsão Receitas/Desp.</a></li>
                                    <li v-if="currentUser.finance_ledger_enabled == 1"><a href="#" @click.prevent="activeView = 'financeiro_recibos'; isSidebarOpen = false" :class="{'active': activeView === 'financeiro_recibos'}" class="flex items-center p-2 rounded-lg sidebar-sublink"><i class="fa-solid fa-receipt w-5 mr-1"></i> Recibos</a></li>
                                    <li v-if="currentUser.finance_ledger_enabled == 1"><a href="#" @click.prevent="activeView = 'financeiro_livrocaixa'; isSidebarOpen = false" :class="{'active': activeView === 'financeiro_livrocaixa'}" class="flex items-center p-2 rounded-lg sidebar-sublink"><i class="fa-solid fa-book w-5 mr-1"></i> Livro Caixa</a></li>
                                    </ul>
                            </li>
                            

                            <li>
                                <a href="#" @click.prevent="isSidebarOpen = false" 
                                   :class="{'active': activeView === 'history_appointments' || activeView === 'hist_atendimentos' || activeView === 'history_documents' || activeView === 'history_receipts' || activeView === 'history_budgets'}" 
                                   class="flex items-center p-3 rounded-lg sidebar-link">
                                    <i class="fa-solid fa-briefcase w-6 text-gray-600"></i> Gestão
                                </a>
                                <ul class="ml-4 mt-1 space-y-1">
                                    
                                    </li>
                                    <li>
                                        <a href="#" @click.prevent="activeView = 'history_appointments'; isSidebarOpen = false" 
                                           :class="{'active': activeView === 'history_appointments'}" 
                                           class="flex items-center p-2 rounded-lg sidebar-sublink">
                                            <i class="fa-solid fa-calendar-check w-5 mr-1 text-gray-500"></i> Hist. Agendamentos
                                        </a>
                                    </li>
                                    
                                    <li>
                                        <a href="#" @click.prevent="activeView = 'hist_atendimentos'; isSidebarOpen = false" 
                                           :class="{'active': activeView === 'hist_atendimentos'}" 
                                           class="flex items-center p-2 rounded-lg sidebar-sublink">
                                            <i class="fa-solid fa-history w-5 mr-1 text-gray-500"></i> Hist. Atendimentos
                                        </a>
                                    </li>
                                    <li>
                                        <a href="#" @click.prevent="activeView = 'history_documents'; isSidebarOpen = false" 
                                           :class="{'active': activeView === 'history_documents'}" 
                                           class="flex items-center p-2 rounded-lg sidebar-sublink">
                                            <i class="fa-solid fa-file-medical w-5 mr-1 text-gray-500"></i> Hist. Documentos
                                        </a>
                                    </li>
                                    <li>
                                        <a href="#" @click.prevent="activeView = 'history_receipts'; isSidebarOpen = false" 
                                           :class="{'active': activeView === 'history_receipts'}" 
                                           class="flex items-center p-2 rounded-lg sidebar-sublink">
                                            <i class="fa-solid fa-receipt w-5 mr-1 text-gray-500"></i> Hist. Recibos
                                        </a>
                                    </li>
                                    <li>
                                        <a href="#" @click.prevent="activeView = 'history_budgets'; isSidebarOpen = false" 
                                           :class="{'active': activeView === 'history_budgets'}" 
                                           class="flex items-center p-2 rounded-lg sidebar-sublink">
                                            <i class="fa-solid fa-file-invoice-dollar w-5 mr-1 text-gray-500"></i> Hist. Orçamentos
                                        </a>
                                    </li>
                                </ul>
                            </li>
                            
                            

                            <li class="mt-4 pt-4 border-t border-gray-200"><a href="#" @click.prevent="activeView = 'settings'; isSidebarOpen = false" :class="{'active': activeView === 'settings'}" class="flex items-center p-3 rounded-lg sidebar-link"><i class="fa-solid fa-cog w-6 icon-config"></i> Configurações</a></li>
                        </ul>
                    </nav>
                     
                     <div class="p-4 border-t flex-shrink-0">
                        <p class="font-semibold">{{ currentUser.professionalName || currentUser.name }}</p>
                        <p class="text-sm text-gray-500">{{ currentUser.profession }}</p>
                        <p v-if="trialCountdown" class="text-xs text-red-500 font-semibold mt-1 animate-pulse">{{ trialCountdown }}</p>
                         <div class="mt-2 text-xs text-gray-500"><p><i class="fa-solid fa-clock mr-1"></i> <a href="https://ntp.br" target="_blank" class="hover:underline">{{ currentTimeString }}</a></p></div>
                        <button @click="logout" class="w-full mt-4 text-left p-3 rounded-lg hover:bg-red-100 text-red-700"><i class="fa-solid fa-right-from-bracket w-6"></i> Sair</button>
                    </div>
                </aside>
                 
                 <div class="flex-1 flex flex-col h-screen overflow-hidden md:pl-64">
                    
                    <header class="bg-white shadow-sm p-4 flex justify-between items-center sticky top-0 z-10 flex-shrink-0">
                        <div class="flex items-center gap-3">
                            <button @click.stop="isSidebarOpen = !isSidebarOpen" class="text-gray-500 focus:outline-none md:hidden">
                                <i class="fa-solid fa-bars fa-lg"></i>
                            </button>
                            <img src="./Capa.png" class="h-11 w-auto">
                            <h3>Aura Software</h3>
                        </div>
                            <div v-if="currentUser.waiting_list_enabled == 1 && waitingList.length > 0" class="flex-1 text-center">
                                <a href="#" @click.prevent="activeView = 'waiting_list'" 
                                   class="px-3 py-1.5 bg-red-100 text-red-700 rounded-full text-sm font-semibold hover:bg-red-200 animate-pulse flex items-center justify-center max-w-xs mx-auto">
                                    <i class="fa-solid fa-bell mr-2"></i>
                                    <span class="text-left leading-tight" style="font-size: 0.8rem;">
                                        {{ waitingList.length }} AGENDA ESPERA!!!
                                    </span>
                                </a>
                            </div>
                            <div class="flex-1 flex justify-center items-center gap-4 px-4">
                            <div v-if="birthdaysToday.length > 0" class="flex-1 text-center">
                                <a href="#" @click.prevent="activeView = 'birthdays'" 
                                   class="px-3 py-1.5 bg-orange-100 text-orange-700 rounded-full text-sm font-semibold hover:bg-orange-200 animate-pulse flex items-center justify-center max-w-xs mx-auto">
                                    <i class="fa-solid fa-cake-candles mr-2"></i>
                                    <span class="text-left leading-tight" style="font-size: 0.8rem;">
                                        {{ birthdaysToday.length }} Niver(s) de Hoje!
                                    </span>
                                </a>
                            </div>
                        </div>
                        </header>

                    <main class="flex-1 bg-gray-100 p-4 sm:p-6 lg:p-8 overflow-y-auto">
                    
                    <div v-if="activeView === 'agenda'">
                           <div class="bg-white p-4 sm:p-6 rounded-lg shadow">
                                <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4">
                                    <div class="flex items-center gap-2 sm:gap-4">
                                        <button @click="navigateAgenda(-1)" class="p-2 rounded-full hover:bg-gray-200"><i class="fa-solid fa-chevron-left"></i></button>
                                        <h2 class="text-lg sm:text-xl font-bold text-center w-48 sm:w-64">{{ agendaTitle }}</h2>
                                        <button @click="navigateAgenda(1)" class="p-2 rounded-full hover:bg-gray-200"><i class="fa-solid fa-chevron-right"></i></button>
                                    </div>
                                    
                                    <div v-if="agendaView === 'day' && birthdaysToday.length > 0" class="flex-1 text-center">
                                        </div>
                                    <div class="flex items-center gap-2">
                                        <div class="flex items-center gap-2 rounded-lg bg-gray-100 p-1">
                                            <button @click="agendaView = 'day'" :class="{'bg-white shadow': agendaView === 'day'}" class="px-3 py-1 rounded-md text-sm font-semibold">Dia</button>
                                            <button @click="agendaView = 'week'" :class="{'bg-white shadow': agendaView === 'week'}" class="px-3 py-1 rounded-md text-sm font-semibold">Semana</button>
                                        </div>
                                        <button @click="exportAgendaWeekToXLS" v-if="agendaView === 'week'" class="p-2 rounded-full hover:bg-gray-200 text-green-600" title="Exportar Semana (Excel)">
                                            <i class="fa-solid fa-file-excel fa-lg"></i>
                                        </button>
                                        <button @click="exportAgendaMonthToXLS" v-if="agendaView === 'day'" class="p-2 rounded-full hover:bg-gray-200 text-green-600" title="Exportar Mês (Excel)">
                                            <i class="fa-solid fa-file-invoice-dollar fa-lg"></i>
                                        </button>
                                        <button @click="openAppointmentModal(null, agendaDate, timeSlots[0] ? timeSlots[0].time : '08:00', null)" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm ml-2"><i class="fa-solid fa-plus mr-2"></i><span class="hidden sm:inline">Agendamento</span></button>
                                        </div>
                                </div>

                                <div v-if="agendaView === 'day'" class="agenda-grid border-t border-l border-gray-200">
                                    <div class="col-start-1 border-r border-gray-200 relative">
                                        <div v-for="label in hourLabels" :key="label.hour" class="absolute text-right pr-2 text-xs text-gray-500 -mt-2.5 w-full h-16 flex items-center justify-end" :style="{ top: label.top }">
                                            {{ label.label }}
                                        </div>
                                    </div>
                                    <div class="relative border-r border-b border-gray-200 col-start-2">
                                        <div v-for="(slot, index) in timeSlots" :key="index"
                                             @click="isTimeSlotEnabled(agendaDate, slot.time) ? openAppointmentModal(null, agendaDate, slot.time) : null"
                                             class="border-b border-gray-200"
                                             :class="{'cursor-pointer hover:bg-blue-50': isTimeSlotEnabled(agendaDate, slot.time), 'bg-gray-100 cursor-not-allowed opacity-70': !isTimeSlotEnabled(agendaDate, slot.time)}"
                                             :style="{height: (currentUser.appointment_slot_minutes / 60 * 4) + 'rem'}">
                                        </div>
                                        <div v-for="appt in processedDayAppointments" :key="appt.id" @click="openAppointmentModal(appt)" :class="getAppointmentClass(appt, 'day')" :style="getAppointmentStyle(appt)">
                                            <p class="font-semibold text-gray-800 text-xs truncate">
                                                <i v-if="isAppointmentFinalized(appt)" class="fa-solid fa-check-double text-green-700 mr-1 font-bold" title="Atendimento Finalizado"></i>
                                                
                                                <i v-if="getPatientFinanceStatus(appt.patient_id)" class="fa-solid fa-dollar-sign text-yellow-500 mr-1" title="Pendência Financeira"></i>
                                                <i v-if="isPatientBirthday(appt.patient_id)" class="fa-solid fa-cake-candles text-pink-500 mr-1" title="Aniversariante!"></i>
                                                {{ appt.title }}
                                            </p>
                                            <p class="text-gray-600 text-xs truncate">
                                                <a v-if="appt.patient_id" href="#" @click.stop.prevent="openPatientQuickView(appt.patient_id)" class="clickable-patient-name" :title="`Ver dados de ${appt.patient_name}`">
                                                    {{ appt.patient_name }}
                                                </a>
                                                <span v-else>{{ `Sem ${labels.patient.toLowerCase()}` }}</span>
                                            </p>
                                            <div class="flex items-center justify-between">
                                                <p v-if="appt.notes" class="text-gray-500 text-xs italic truncate flex-1" :title="appt.notes">{{ appt.notes }}</p>
                                                <div class="flex gap-2">
                                                    <span v-if="isAppointmentFinalized(appt)" class="text-green-700 font-bold text-xs flex items-center">
                                                        <i class="fa-solid fa-check mr-1"></i> OK
                                                    </span>

                                                    <a v-if="appt.patient_id && appt.status !== 'Cancelado' && appt.status !== 'Não Compareceu' && !isAppointmentActive(appt.id) && !isAppointmentFinalized(appt) && !isAppointmentMissed(appt)" 
                                                       href="#" @click.stop.prevent="startServiceFromAppointment(appt.id)" 
                                                       class="text-green-600 hover:text-green-800 text-xs font-bold">INICIAR ATENDIMENTO</a>
                                                    
                                                    <a v-if="appt.patient_id && appt.status !== 'Cancelado' && isAppointmentActive(appt.id)" 
                                                       href="#" @click.stop.prevent="findAndFinishService(appt)" 
                                                       class="text-red-600 hover:text-red-800 text-xs font-bold">FINALIZAR ATENDIMENTO</a>
                                                    
                                                    <a v-if="(isAppointmentMissed(appt) || appt.status === 'Não Compareceu') && !isRescheduled(appt)" 
                                                       href="#" @click.stop.prevent="rescheduleMissedToWaitingList(appt)" 
                                                       class="text-orange-600 hover:text-orange-800 text-xs font-bold" 
                                                       title="Marcar como Não Compareceu e enviar para Espera">REAGENDAR (FALTOU)</a>
                                                </div>
                                                </div>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="agendaView === 'week'" class="overflow-x-auto">
                                    <div class="agenda-grid-week border-t border-l border-gray-200">
                                         <div></div> <div v-for="day in weekDays" :key="day.date.toISOString()" class="text-center font-semibold p-2 border-b border-r border-gray-200" :class="{'bg-gray-100 opacity-70': !day.enabled}">
                                            <p class="text-sm">{{ day.name }}</p>
                                            <p class="text-2xl" :class="{'text-blue-600': isToday(day.date)}">{{ day.date.getDate() }}</p>
                                        </div>
                                        <div class="col-start-1 row-start-2 border-r border-gray-200 relative">
                                            <div v-for="label in hourLabels" :key="label.hour" class="absolute text-right pr-2 text-xs text-gray-500 -mt-2.5 w-full h-16 flex items-center justify-end" :style="{ top: label.top }">
                                                {{ label.label }}
                                            </div>
                                        </div>
                                        <div v-for="(day, index) in weekDays" :key="'week-day-' + index"
                                             class="relative border-r border-b border-gray-200"
                                             :class="{'bg-gray-100 opacity-70': !day.enabled}"
                                             :style="{ 'grid-column-start': index + 2, 'grid-row-start': 2 }">
                                            <div v-for="(slot, slotIndex) in timeSlots" :key="'week-slot-' + slotIndex"
                                                 @click="isTimeSlotEnabled(day.date, slot.time) ? openAppointmentModal(null, day.date, slot.time) : null"
                                                 class="border-b border-gray-200"
                                                 :class="{'cursor-pointer hover:bg-blue-50': isTimeSlotEnabled(day.date, slot.time), 'cursor-not-allowed': !isTimeSlotEnabled(day.date, slot.time)}"
                                                 :style="{height: (currentUser.appointment_slot_minutes / 60 * 4) + 'rem'}">
                                            </div>
                                            <div v-for="appt in getAppointmentsForDay(day.date)" :key="appt.id" @click="openAppointmentModal(appt)" :class="getAppointmentClass(appt, 'week')" :style="getAppointmentStyle(appt)">
                                                <p class="font-semibold text-gray-800 text-xs truncate">
                                                    <i v-if="isAppointmentFinalized(appt)" class="fa-solid fa-check-double text-green-700 mr-1 font-bold" title="Atendimento Finalizado"></i>
                                                    
                                                    <i v-if="getPatientFinanceStatus(appt.patient_id)" class="fa-solid fa-dollar-sign text-yellow-500" title="Pendência Financeira"></i>
                                                    <i v-if="isPatientBirthday(appt.patient_id)" class="fa-solid fa-cake-candles text-pink-500" title="Aniversariante!"></i>
                                                    <a v-if="appt.patient_id" href="#" @click.stop.prevent="openPatientQuickView(appt.patient_id)" class="clickable-patient-name" :title="`Ver dados de ${appt.patient_name}`">
                                                        {{ appt.patient_name || appt.title }}
                                                    </a>
                                                    <span v-else>{{ appt.title }}</span>
                                                    </p>
                                                <div class="flex items-center justify-between">
                                                    <p v-if="appt.notes" class="text-gray-500 text-xs italic truncate flex-1" :title="appt.notes">{{ appt.notes }}</p>
                                                    <div class="flex gap-2">
                                                        <span v-if="isAppointmentFinalized(appt)" class="text-green-700 font-bold text-xs flex items-center">
                                                            <i class="fa-solid fa-check mr-1"></i> OK
                                                        </span>

                                                        <a v-if="appt.patient_id && appt.status !== 'Cancelado' && appt.status !== 'Não Compareceu' && !isAppointmentActive(appt.id) && !isAppointmentFinalized(appt) && !isAppointmentMissed(appt)" 
                                                           href="#" @click.stop.prevent="startServiceFromAppointment(appt.id)" 
                                                           class="text-green-600 hover:text-green-800 text-xs font-bold">INICIAR ATENDIMENTO</a>
                                                        
                                                        <a v-if="appt.patient_id && appt.status !== 'Cancelado' && isAppointmentActive(appt.id)" 
                                                           href="#" @click.stop.prevent="findAndFinishService(appt)" 
                                                           class="text-red-600 hover:text-red-800 text-xs font-bold">FINALIZAR ATENDIMENTO</a>
                                                        
                                                        <a v-if="(isAppointmentMissed(appt) || appt.status === 'Não Compareceu') && !isRescheduled(appt)" 
                                                           href="#" @click.stop.prevent="rescheduleMissedToWaitingList(appt)" 
                                                           class="text-orange-600 hover:text-orange-800 text-xs font-bold" 
                                                           title="Marcar como Não Compareceu e enviar para Espera">REAGENDAR (FALTOU)</a>
                                                    </div>
                                                    </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div v-if="activeView === 'patients'">
                            <div class="bg-white p-2 rounded-lg shadow">
                            <div class="flex flex-col md:flex-row justify-between md:items-center mb-2 gap-2">
                                <h1 class="text-2xl sm:text-1xl font-bold">Gestão de {{ labels.patients }}</h1>
                                <div class="flex justify-end mb-4">
                                    <span v-if="selectedPatients.length > 0" class="flex items-center gap-2">
                                        <span class="text-sm text-gray-600">{{ selectedPatients.length }} selecionado(s)</span>
                                        <button @click="deleteSelectedPatients" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700"><i class="fa-solid fa-trash-can"></i><span class="ml-2 hidden sm:inline">Excluir</span></button>
                                    </span>
                                    <button v-else @click="openPatientModal(null)" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"><i class="fa-solid fa-plus"></i><span class="ml-2 hidden sm:inline">Novo {{ labels.patient }}</span></button>
                                    <button @click="exportPatientsToExcel" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700" title="Exportar Lista para Excel"><i class="fa-solid fa-file-excel"></i><span class="ml-2 hidden sm:inline">Exportar (Filtros Atuais)</span></button>
                                </div>
                            </div>
                            
                            <input type="text" v-model="patientSearchTerm" @keyup="searchPatients" :placeholder="'Pesquisar por nome, apelido, cpf...'" class="form-input max-w-lg">
                                        
                            
                                 <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 min-h-[48rem]"> <div v-for="patient in paginatedPatients" :key="patient.id" class="border rounded-lg p-4 flex flex-col justify-between relative">
                                 <input type="checkbox" :value="patient.id" v-model="selectedPatients" class="absolute top-3 left-3 h-5 w-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        
                                        <span v-if="patient.has_pending_finance" class="financial-alert-dot" title="Pendência Financeira"></span>
                                        <div class="flex items-center space-x-4 mb-4" :class="{'ml-8': !patient.has_pending_finance, 'ml-12': patient.has_pending_finance}">
                                            <img :src="patient.photo || 'https://placehold.co/64x64/E2E8F0/A0AEC0?text=Foto'" @error="e => e.target.src='https://placehold.co/64x64/E2E8F0/A0AEC0?text=Foto'" class="w-16 h-16 rounded-full object-cover bg-gray-200">
                                            <div class="overflow-hidden">
                                                <h3 class="font-semibold text-lg truncate" :title="patient.name">
                                                    <a href="#" @click.prevent="openPatientQuickView(patient.id)" class="clickable-patient-name">
                                                        {{ patient.name }}
                                                    </a>
                                                    </h3>
                                                <p v-if="patient.birthdate" class="text-sm text-gray-600">{{ calculateAge(patient.birthdate) }} anos</p>
                                                <p class="text-sm text-gray-600 truncate" :title="patient.cpf || 'CPF não cadastrado'">CPF: {{ patient.cpf || '---' }}</p>
                                                <p class="text-sm text-gray-600 truncate" :title="patient.phone || 'Celular não cadastrado'">Cel: {{ patient.phone || '---' }}</p>
                                            </div>
                                        </div>
                                        
                                        <div class="flex items-center space-x-2 border-t pt-3 mt-auto">
                                            <button @click="openPatientModal(patient)" class="flex-1 text-sm px-3 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 text-center">Ver Detalhes</button>
                                            <button @click="openClinicalModalByPatientId(patient.id)" class="flex-1 text-sm px-3 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 text-center">{{ labels.clinicalData }}</button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div v-if="patients.length === 0" class="text-center text-gray-500 py-8 min-h-[48rem]">Nenhum {{ labels.patient }} encontrado.</div>
                                
                                <div v-if="totalPages > 1" class="flex justify-between items-center mt-6 pt-4 border-t">
                                    <button @click="prevPage" :disabled="pagination.currentPage === 1" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 disabled:opacity-50 disabled:cursor-not-allowed">
                                        <i class="fa-solid fa-chevron-left mr-2"></i> Anterior
                                    </button>
                                    <span class="text-sm font-medium text-gray-700">
                                        Página {{ pagination.currentPage }} de {{ totalPages }}
                                    </span>
                                    <button @click="nextPage" :disabled="pagination.currentPage === totalPages" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 disabled:opacity-50 disabled:cursor-not-allowed">
                                        Próxima <i class="fa-solid fa-chevron-right ml-2"></i>
                                    </button>
                                </div>
                                </div>
                        </div>
                        
                        <div v-if="activeView === 'budgets'">
                            <div class="bg-white p-4 sm:p-6 rounded-lg shadow">
                            <div class="flex flex-col md:flex-row justify-between md:items-center mb-2 gap-2">
                                
                                <h1 class="text-2xl sm:text-1xl font-bold">Orçamentos</h1>
                                <button @click="openNewBudgetPatientSearch" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm"><i class="fa-solid fa-plus mr-2"></i>Novo Orçamento</button>
                                
                            </div>
                            <div v-show="activeBudgetTab === 'list'">
                                 
                                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6 pb-4 border-b">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Filtrar por Nº</label>
                                            <input type="text" v-model="budgetFilters.id" class="form-input" placeholder="Digite o Nº do orçamento...">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Filtrar por {{ labels.patient }}</label>
                                            <input type="text" v-model="budgetFilters.patientName" class="form-input" :placeholder="'Digite o nome do ' + labels.patient.toLowerCase() + '...'">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Filtrar por Status</label>
                                            <select v-model="budgetFilters.status" class="form-input">
                                                <option value="">Todos</option>
                                                <option v-for="opt in getOptionsByType('budget_status')" :key="opt.id" :value="opt.option_value">{{ opt.option_value }}</option>
                                            </select>
                                        </div>
                                        <div class="flex items-end">
                                            <button @click="exportBudgetsToXLS" class="w-full px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 text-sm"><i class="fa-solid fa-file-excel mr-2"></i>Exportar (Filtros Atuais)</button>
                                        </div>
                                    </div>
                                    
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full bg-white">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th @click="sortBy('id')" class="cursor-pointer py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase hover:bg-gray-100 w-20">Nº <i :class="sortIcon('id')" class="ml-1"></i></th>
                                                    <th @click="sortBy('createdAt')" class="cursor-pointer py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase hover:bg-gray-100">Data <i :class="sortIcon('createdAt')" class="ml-1"></i></th>
                                                    <th @click="sortBy('patient_name')" class="cursor-pointer py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase hover:bg-gray-100">{{ labels.patient }} <i :class="sortIcon('patient_name')" class="ml-1"></i></th>
                                                    <th @click="sortBy('final_total')" class="cursor-pointer py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase hover:bg-gray-100">Valor Total <i :class="sortIcon('final_total')" class="ml-1"></i></th>
                                                    <th @click="sortBy('status')" class="cursor-pointer py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase hover:bg-gray-100">Status <i :class="sortIcon('status')" class="ml-1"></i></th>
                                                    <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase">Ações</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200">
                                                <tr v-for="budget in filteredAndSortedBudgets" :key="budget.id">
                                                    <td class="py-4 px-4 whitespace-nowrap font-semibold">{{ budget.id }}</td>
                                                    <td class="py-4 px-4 whitespace-nowrap">{{ new Date(budget.createdAt).toLocaleDateString('pt-BR') }}</td>
                                                    <td class="py-4 px-4 whitespace-nowrap font-medium">
                                                        <span v-if="getPatientFinanceStatus(budget.patient_id)" class="financial-alert-dot-inline" title="Pendência Financeira"></span>
                                                        <a href="#" @click.prevent="openPatientQuickView(budget.patient_id)" class="clickable-patient-name" :title="`Ver dados de ${budget.patient_name}`">
                                                            {{ budget.patient_name }}
                                                        </a>
                                                        </td>
                                                    <td class="py-4 px-4 whitespace-nowrap">{{ formatCurrency(budget.final_total) }}</td>
                                                    <td class="py-4 px-4 whitespace-nowrap"><span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full status-uppercase" :class="getBudgetStatusClass(budget.status)">{{ budget.status }}</span></td>
                                                    <td class="py-4 px-4 whitespace-nowrap text-sm font-medium">
                                                        <div class="flex items-center gap-3">
                                                            <button @click="viewBudget(budget.id)" class="text-blue-600 hover:text-blue-900" title="Ver Detalhes / Editar"><i class="fa-solid fa-eye"></i></button>
                                                            
                                                            <button @click="printBudgetById(budget.id)" class="text-gray-600 hover:text-blue-900" title="Imprimir Orçamento"><i class="fa-solid fa-print"></i></button>
                                                            
                                                            <button @click="emailBudget(budget.id)" class="text-purple-600 hover:text-purple-800" title="Enviar por E-mail"><i class="fa-solid fa-envelope"></i></button>
                                                            <button v-if="budget.status !== defaultBudgetStatusApproved" @click="updateBudgetStatus(budget, defaultBudgetStatusApproved)" class="text-green-500 hover:text-green-700" title="Aprovar"><i class="fa-solid fa-check"></i></button>
                                                            <button v-if="budget.status !== defaultBudgetStatusNegotiation" @click="updateBudgetStatus(budget, defaultBudgetStatusNegotiation)" class="text-blue-500 hover:text-blue-700" title="Marcar como 'Em Negociação'"><i class="fa-solid fa-user-clock"></i></button>
                                                            <button v-if="budget.status !== defaultBudgetStatusRejected" @click="updateBudgetStatus(budget, defaultBudgetStatusRejected)" class="text-red-500 hover:text-red-700" title="Reprovar"><i class="fa-solid fa-times"></i></button>

                                                            <div class="relative">
                                                                <button @click="toggleStatusMenu(budget)" class="p-1 rounded-full hover:bg-gray-200 focus:outline-none" title="Alterar Status">
                                                                    <i class="fa-solid fa-ellipsis-v text-gray-600"></i>
                                                                </button>
                                                                <div v-if="budget.showStatusMenu" class="origin-top-right absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-10">
                                                                    <div class="py-1" role="menu" aria-orientation="vertical">
                                                                        <span class="block px-4 pt-2 pb-1 text-xs text-gray-500">Alterar status para:</span>
                                                                        <a v-for="opt in getOptionsByType('budget_status')" :key="opt.id" href="#" @click.prevent="updateBudgetStatus(budget, opt.option_value)" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 status-uppercase">
                                                                            <i :class="{
                                                                                'fa-solid fa-check w-5 text-green-500': opt.option_value === defaultBudgetStatusApproved,
                                                                                'fa-solid fa-user-clock w-5 text-blue-500': opt.option_value === defaultBudgetStatusNegotiation,
                                                                                'fa-solid fa-times w-5 text-red-500': opt.option_value === defaultBudgetStatusRejected,
                                                                                'fa-solid fa-ban w-5 text-gray-500': opt.option_value === defaultBudgetStatusCanceled,
                                                                                'fa-solid fa-hourglass-half w-5 text-yellow-500': opt.option_value === defaultBudgetStatusPending || opt.option_value === defaultBudgetStatusWaitingApproval,
                                                                                'fa-solid fa-question-circle w-5 text-gray-400': ![defaultBudgetStatusApproved, defaultBudgetStatusNegotiation, defaultBudgetStatusRejected, defaultBudgetStatusCanceled, defaultBudgetStatusPending, defaultBudgetStatusWaitingApproval].includes(opt.option_value)
                                                                                
                                                                            }"></i>
                                                                            {{ opt.option_value }}
                                                                        </a>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <button @click="deleteBudget(budget.id)" class="text-gray-400 hover:text-red-600" title="Excluir"><i class="fa-solid fa-trash-can"></i></button>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <tr v-if="filteredAndSortedBudgets.length === 0">
                                                    <td colspan="6" class="text-center py-8 text-gray-500">Nenhum orçamento encontrado.</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <div v-if="activeBudgetTab === 'create'">
                                 <div class="bg-white p-6 rounded-lg shadow">
                                    <div class="flex justify-between items-center mb-6 pb-4 border-b">
                                        <h2 class="text-xl font-bold">{{ newBudget.id ? `Orçamento Nº #${newBudget.id}` : 'Novo Orçamento' }}</h2>
                                        <button @click="activeBudgetTab = 'list'" class="text-gray-500 hover:text-gray-800"><i class="fa-solid fa-times fa-lg"></i></button>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">{{ labels.patient }}</label>
                                            <a href="#" @click.prevent="openPatientQuickView(newBudget.patient_id)" class="clickable-patient-name text-lg" :title="`Ver dados de ${newBudget.patient_name}`">
                                                {{ newBudget.patient_name }}
                                            </a>
                                            </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Tabela de Preços</label>
                                            <select v-model="newBudget.price_list_id" @change="fetchPriceItems(newBudget.price_list_id)" class="form-input">
                                                <option :value="null">Nenhuma (manual)</option>
                                                <option v-for="list in priceLists" :key="list.id" :value="list.id">
                                                    {{ list.name }} {{ list.is_global ? '(Global)' : '' }}
                                                </option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="overflow-x-auto">
                                        <table class="min-w-full bg-white mb-4">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th v-if="currentUser.budget_form_fields && currentUser.budget_form_fields.region" class="py-2 px-2 text-left text-xs font-medium text-gray-500 uppercase w-24">Região</th>
                                                    <th class="py-2 px-2 text-left text-xs font-medium text-gray-500 uppercase">{{ currentUser.system_version === 'Tecnica' ? 'Itens do Orçamento' : 'Procedimento' }}</th>
                                                    <th class="py-2 px-2 text-left text-xs font-medium text-gray-500 uppercase w-32">Valor Unit.</th>
                                                    <th class="py-2 px-2 text-left text-xs font-medium text-gray-500 uppercase w-32">Acréscimo (R$)</th>
                                                    <th class="py-2 px-2 text-left text-xs font-medium text-gray-500 uppercase w-32">Desconto (R$)</th>
                                                    <th class="py-2 px-2 text-left text-xs font-medium text-gray-500 uppercase w-32">Valor Final</th>
                                                    <th class="py-2 px-2 text-left text-xs font-medium text-gray-500 uppercase w-12"></th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200">
                                                <tr v-for="(item, index) in newBudget.items" :key="'item-'+index">
                                                    <td v-if="currentUser.budget_form_fields && currentUser.budget_form_fields.region" class="p-1"><input type="text" v-model="item.region" maxlength="10" class="form-input p-2 text-sm text-center"></td>
                                                    <td class="p-1 relative">
                                                        <input type="text" v-model="item.description" @input="searchProcedures(index, newBudget.items, priceItems)" @keydown.down.prevent="navigateProcedureResults(1)" @keydown.up.prevent="navigateProcedureResults(-1)" @keydown.enter.prevent="selectProcedure(procedureSearch.results[procedureSearch.activeIndex], index, newBudget.items)" class="form-input p-2 text-sm w-full">
                                                        <div v-if="procedureSearch.index === index && procedureSearch.results.length > 0" class="absolute z-10 w-full bg-white border rounded-md shadow-lg max-h-48 overflow-y-auto bottom-full mb-1">
                                                            <a v-for="(proc, procIndex) in procedureSearch.results" :key="proc.id" @click.prevent="selectProcedure(proc, index, newBudget.items)" class="block px-4 py-2 text-sm hover:bg-gray-100 cursor-pointer" :class="{'bg-blue-100': procedureSearch.activeIndex === procIndex}">
                                                                {{ proc.name }} - {{ formatCurrency(proc.cost) }}
                                                            </a>
                                                        </div>
                                                    </td>
                                                    <td class="p-1"><input type="number" step="0.01" v-model.number="item.value" class="form-input p-2 text-sm text-right"></td>
                                                    <td class="p-1"><input type="number" step="0.01" v-model.number="item.increment" class="form-input p-2 text-sm text-right"></td>
                                                    <td class="p-1"><input type="number" step="0.01" v-model.number="item.discount" class="form-input p-2 text-sm text-right"></td>
                                                    <td class="p-1 text-sm text-right font-semibold">{{ formatCurrency(((item.value || 0) + (item.increment || 0)) * 1 - (item.discount || 0)) }}</td>
                                                    <td class="p-1 text-center">
                                                        <button @click="removeBudgetItem(index)" type="button" class="text-red-500 hover:text-red-700"><i class="fa-solid fa-trash-can"></i></button>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <button @click="addBudgetItem" type="button" class="mt-2 mb-6 px-3 py-1 bg-gray-200 text-sm rounded-md hover:bg-gray-300"><i class="fa-solid fa-plus mr-2"></i>{{ currentUser.system_version === 'Tecnica' ? 'Adicionar Item' : 'Adicionar Procedimento' }}</button>

                                    <div class="mt-8 pt-6 border-t">
                                        <h3 class="text-lg font-semibold mb-4">Itens Recorrentes (Mensalidades/Sessões)</h3>
                                        <div v-if="newBudget.recurring_items.length > 0" class="overflow-x-auto">
                                            <table class="min-w-full bg-white mb-4">
                                                <thead class="bg-gray-50">
                                                    <tr>
                                                        <th class="py-2 px-2 text-left text-xs font-medium text-gray-500 uppercase">Descrição</th>
                                                        <th class="py-2 px-2 text-left text-xs font-medium text-gray-500 uppercase w-32">Periodicidade</th>
                                                        <th class="py-2 px-2 text-left text-xs font-medium text-gray-500 uppercase w-32">Valor Unit.</th>
                                                        <th class="py-2 px-2 text-left text-xs font-medium text-gray-500 uppercase w-32">Acréscimo (R$)</th> <th class="py-2 px-2 text-left text-xs font-medium text-gray-500 uppercase w-32">Desconto (R$)</th>
                                                        <th class="py-2 px-2 text-left text-xs font-medium text-gray-500 uppercase w-32">Valor Final</th>
                                                        <th class="py-2 px-2 text-left text-xs font-medium text-gray-500 uppercase w-12"></th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-200">
                                                    <tr v-for="(item, index) in newBudget.recurring_items" :key="'rec-'+index">
                                                        <td class="p-1"><input type="text" v-model="item.description" class="form-input p-2 text-sm w-full"></td>
                                                        <td class="p-1">
                                                            <select v-model="item.periodicity" class="form-input p-2 text-sm w-full">
                                                                <option v-for="opt in getOptionsByType('periodicity')" :key="opt.id" :value="opt.option_value"> {{ opt.option_value }} </option>
                                                                <option v-if="!getOptionsByType('periodicity').length" disabled>Carregando...</option>
                                                            </select>
                                                        </td>
                                                        <td class="p-1"><input type="number" step="0.01" v-model.number="item.value" class="form-input p-2 text-sm text-right"></td>
                                                        <td class="p-1"><input type="number" step="0.01" v-model.number="item.increment" class="form-input p-2 text-sm text-right"></td> <td class="p-1"><input type="number" step="0.01" v-model.number="item.discount" class="form-input p-2 text-sm text-right"></td>
                                                        <td class="p-1 text-sm text-right font-semibold">{{ formatCurrency(((item.value || 0) + (item.increment || 0)) * 1 - (item.discount || 0)) }}</td> <td class="p-1 text-center"> <button @click="removeRecurringBudgetItem(index)" type="button" class="text-red-500 hover:text-red-700"><i class="fa-solid fa-trash-can"></i></button> </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                         <p v-else class="text-sm text-gray-500 mb-4">Nenhum item recorrente adicionado.</p>
                                        <button @click="addRecurringBudgetItem" type="button" class="mt-2 mb-6 px-3 py-1 bg-gray-200 text-sm rounded-md hover:bg-gray-300"><i class="fa-solid fa-plus mr-2"></i>Adicionar Item Recorrente</button>
                                    </div>
                                    
                                    <div class="pt-6 border-t">
                                        <h3 class="text-lg font-semibold mb-4">Forma de Pagamento</h3>
                                        <div v-if="budgetTotalMainItems > 0">
                                            <h4 class="text-md font-medium mb-2">Itens Principais (Total: {{ formatCurrency(budgetTotalMainItems) }})</h4>
                                            
                                            <div v-for="(detail, index) in newBudget.payment_details" :key="'pay-'+index" class="flex items-center gap-2 mb-2">
                                                <span class="font-semibold text-gray-600 w-8 text-right">{{ index + 1 }}:</span>
                                                <input type="date" v-model="detail.date" class="form-input p-2 text-sm w-36">
                                                <select v-model="detail.method" class="form-input p-2 text-sm flex-grow">
                                                    <option v-for="opt in getOptionsByType('payment_method')" :key="opt.id" :value="opt.option_value">{{ opt.option_value }}</option>
                                                    <option v-if="!getOptionsByType('payment_method').length" disabled>Carregando...</option>
                                                </select>
                                                <input type="number" step="0.01" v-model.number="detail.value" @input="updatePaymentDetailValue(index)" class="form-input p-2 text-sm w-32 text-right">
                                                <button @click="removePaymentDetail(index)" type="button" class="text-red-500 hover:text-red-700 px-2" title="Remover Forma de Pagamento"><i class="fa-solid fa-times"></i></button>
                                            </div>
                                            <div class="flex justify-between items-center mt-2 text-sm">
                                                <button @click="addPaymentDetail" type="button" class="px-3 py-1 bg-gray-200 text-xs rounded-md hover:bg-gray-300"><i class="fa-solid fa-plus mr-1"></i>Dividir Pagamento</button>
                                                <div :class="budgetPaymentDetailsRemaining < 0.01 ? 'text-green-600' : 'text-red-600'">
                                                    Restante: {{ formatCurrency(budgetPaymentDetailsRemaining) }}
                                                </div>
                                            </div>
                                        </div>
                                        <p v-else class="text-sm text-gray-500 mb-4">Nenhum item principal para definir pagamento.</p>

                                        <div v-if="budgetTotalRecurringItems > 0" class="mt-6 pt-4 border-t">
                                            <h4 class="text-md font-medium mb-2">Itens Recorrentes (Total: {{ formatCurrency(budgetTotalRecurringItems) }})</h4>
                                            
                                            <div v-for="(detail, index) in newBudget.recurring_payment_details" :key="'rec-pay-'+index" class="flex items-center gap-2 mb-2">
                                                <span class="font-semibold text-gray-600 w-8 text-right">{{ index + 1 }}:</span>
                                                <input type="date" v-model="detail.date" class="form-input p-2 text-sm w-36">
                                                <select v-model="detail.method" class="form-input p-2 text-sm flex-grow">
                                                    <option v-for="opt in getOptionsByType('payment_method')" :key="opt.id" :value="opt.option_value"> {{ opt.option_value }} </option>
                                                    <option v-if="!getOptionsByType('payment_method').length" disabled>Carregando...</option>
                                                </select>
                                                <input type="number" step="0.01" v-model.number="detail.value" @input="updateRecurringPaymentDetailValue(index)" class="form-input p-2 text-sm w-32 text-right">
                                                <button @click="removeRecurringPaymentDetail(index)" type="button" class="text-red-500 hover:text-red-700 px-2" title="Remover Forma de Pagamento"><i class="fa-solid fa-times"></i></button>
                                            </div>
                                            <div class="flex justify-between items-center mt-2 text-sm">
                                                <button @click="addRecurringPaymentDetail" type="button" class="px-3 py-1 bg-gray-200 text-xs rounded-md hover:bg-gray-300"><i class="fa-solid fa-plus mr-1"></i>Dividir Pagamento</button>
                                                <div :class="budgetRecurringPaymentDetailsRemaining < 0.01 ? 'text-green-600' : 'text-red-600'">
                                                    Restante: {{ formatCurrency(budgetRecurringPaymentDetailsRemaining) }}
                                                </div>
                                            </div>
                                            </div>
                                        <p v-else class="text-sm text-gray-500 mt-6 pt-4 border-t">Nenhum item recorrente para definir pagamento.</p>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mt-8 pt-4 border-t">
                                        <div class="md:col-span-2 space-y-6">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Notas Adicionais</label>
                                                <textarea v-model="newBudget.notes" rows="4" class="form-input"></textarea>
                                            </div>
                                        </div>

                                        <div class="md:col-span-1 flex flex-col items-end gap-4">
                                            <div class="w-full max-w-sm space-y-2 text-right">
                                                <div class="flex justify-between items-center">
                                                    <span class="font-semibold">Subtotal (Itens):</span>
                                                    <span>{{ formatCurrency(budgetSubtotal) }}</span>
                                                </div>
                                                <div class="flex justify-between items-center">
                                                    <span class="font-semibold">Total Recorrente:</span>
                                                    <span>{{ formatCurrency(budgetRecurringTotal) }}</span>
                                                </div>
                                                <div class="flex justify-between items-center text-xl font-bold border-t pt-2 mt-2">
                                                    <span>Total Geral:</span>
                                                    <span class="text-blue-600">{{ formatCurrency(budgetTotal) }}</span>
                                                </div>
                                            </div>
                                            <div class="flex gap-4 mt-4 w-full justify-end">
                                                <button @click="activeBudgetTab = 'list'" type="button" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Sair</button>
                                                <button @click="printBudget" type="button" class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700" title="Imprimir Orçamento"><i class="fa-solid fa-print"></i></button>
                                                <button v-if="newBudget.id" @click="emailBudget(newBudget.id)" type="button" class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700" title="Enviar por E-mail"><i class="fa-solid fa-envelope"></i></button>
                                                <button @click="saveBudget" type="button" class="px-6 py-2 bg-blue-600 text-white font-semibold rounded-md hover:bg-blue-700">Salvar Orçamento</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        
                        
                        <div v-if="activeView === 'active_services'">
                            <div class="bg-white p-6 rounded-lg shadow">
                                <div class="flex flex-col md:flex-row justify-between md:items-center mb-2 gap-2">
                                    <h1 class="text-2xl sm:text-1xl font-bold">Atendimentos Ativos</h1>
                                    <button @click="openStandaloneServiceModal" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm w-full md:w-auto"><i class="fa-solid fa-plus mr-2"></i>Atendimento Avulso</button>
                                    </div>
                                
                                    <div class="overflow-x-auto min-h-[24rem]">
                                        <table class="min-w-full bg-white">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase">{{ labels.patient }}</th>
                                                    <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase">Início</th>
                                                    <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                                    <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase">Ações</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200">
                                                <tr v-if="paginatedActiveServices.length === 0">
                                                    <td colspan="4" class="text-center py-8 text-gray-500">Nenhum atendimento ativo no momento.</td>
                                                </tr>
                                                <tr v-else v-for="service in paginatedActiveServices" :key="service.id">
                                                <td class="py-4 px-4 whitespace-nowrap font-medium">
                                                        <span v-if="getPatientFinanceStatus(service.patient_id)" class="financial-alert-dot-inline" title="Pendência Financeira"></span>
                                                        <a href="#" @click.prevent="openPatientQuickView(service.patient_id)" class="clickable-patient-name" :title="`Ver dados de ${service.patient_name}`">
                                                            {{ service.patient_name }}
                                                        </a>
                                                        </td>
                                                    <td class="py-4 px-4 whitespace-nowrap text-sm text-gray-600">{{ formatEntryDate(service.start_date) }}</td>
                                                    <td class="py-4 px-4 whitespace-nowrap">
                                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full status-uppercase" :class="getServiceStatusClass(service.service_status)">{{ service.service_status }}</span>
                                                    </td>
                                                    <td class="py-4 px-4 whitespace-nowrap text-sm font-medium">
                                                        <button @click="openClinicalModalByPatientId(service.patient_id)" class="px-3 py-1 bg-blue-500 text-white rounded-md hover:bg-blue-600 text-xs">{{ labels.clinicalData }}</button>
                                                        <button @click="finishService(service, null)" class="px-3 py-1 bg-green-500 text-white rounded-md hover:bg-green-600 text-xs ml-2">Finalizar</button>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <div v-if="activeServicesTotalPages > 1" class="flex justify-between items-center mt-6 pt-4 border-t">
                                        <button @click="activeServices_prevPage" :disabled="activeServicesPagination.currentPage === 1" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 disabled:opacity-50 disabled:cursor-not-allowed">
                                            <i class="fa-solid fa-chevron-left mr-2"></i> Anterior
                                        </button>
                                        <span class="text-sm font-medium text-gray-700">
                                            Página {{ activeServicesPagination.currentPage }} de {{ activeServicesTotalPages }}
                                        </span>
                                        <button @click="activeServices_nextPage" :disabled="activeServicesPagination.currentPage === activeServicesTotalPages" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 disabled:opacity-50 disabled:cursor-not-allowed">
                                            Próxima <i class="fa-solid fa-chevron-right ml-2"></i>
                                        </button>
                                    </div>
                                    
                            </div>

                        </div>
                        
                        
    

<div v-if="activeView === 'history_receipts'">
    <div class="bg-white p-6 rounded-lg shadow">
        <div class="flex flex-col md:flex-row justify-between md:items-center mb-4 gap-2">
            <h1 class="text-2xl font-bold">Histórico de Recibos</h1>
            <div class="flex gap-2">
                <button v-if="selectedGeneratedReceipts.length > 0" @click="sendReceiptsEmail" class="px-3 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700"><i class="fa-solid fa-envelope mr-1"></i> Email em Massa</button>
                <button v-if="selectedGeneratedReceipts.length > 0" @click="promptCancelGeneratedReceipts" class="px-3 py-2 bg-red-600 text-white text-sm rounded hover:bg-red-700"><i class="fa-solid fa-ban mr-1"></i> Cancelar Selecionados</button>
            </div>
        </div>
        
        <div class="mb-4">
            <input type="text" v-model="receiptSearchGenerated" @input="debouncedFetchGeneratedReceipts" placeholder="Buscar por recibo, paciente ou CPF..." class="form-input max-w-md">
        </div>

        <div class="overflow-x-auto min-h-[20rem]">
            <table class="min-w-full bg-white text-sm">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="p-3 w-8"><input type="checkbox" @change="toggleSelectAllGeneratedReceipts" :checked="selectedGeneratedReceipts.length > 0 && selectedGeneratedReceipts.length === generatedReceipts.entries.length"></th>
                        <th class="py-3 px-4 text-left font-medium text-gray-500 uppercase">Data</th>
                        <th class="py-3 px-4 text-left font-medium text-gray-500 uppercase">Nº Recibo</th>
                        <th class="py-3 px-4 text-left font-medium text-gray-500 uppercase">Paciente</th>
                        <th class="py-3 px-4 text-left font-medium text-gray-500 uppercase">Valor</th>
                        <th class="py-3 px-4 text-center font-medium text-gray-500 uppercase">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-if="generatedReceipts.entries.length === 0">
                        <td colspan="6" class="text-center py-8 text-gray-500">Nenhum recibo gerado encontrado.</td>
                    </tr>
                    <tr v-for="receipt in generatedReceipts.entries" :key="receipt.id" class="hover:bg-gray-50">
                        <td class="p-3 text-center"><input type="checkbox" :value="receipt.id" v-model="selectedGeneratedReceipts"></td>
                        <td class="py-3 px-4 whitespace-nowrap">{{ new Date(receipt.created_at).toLocaleDateString('pt-BR') }}</td>
                        <td class="py-3 px-4 font-bold text-gray-700">{{ receipt.receipt_number }}</td>
                        <td class="py-3 px-4 font-medium">{{ receipt.patient_name || 'Avulso' }}</td>
                        <td class="py-3 px-4 text-green-700 font-semibold">{{ formatCurrency(receipt.amount) }}</td>
                        <td class="py-3 px-4 text-center flex justify-center gap-3">
                            <button @click="viewReceipt(receipt)" class="text-gray-600 hover:text-blue-600" title="Visualizar/Imprimir"><i class="fa-solid fa-print"></i></button>
                            <button @click="emailSelectedReceipts(receipt.id)" class="text-purple-600 hover:text-purple-800" title="Enviar por Email"><i class="fa-solid fa-envelope"></i></button>
                            <button @click="reprintReceipt(receipt)" class="text-blue-600 hover:text-blue-800" title="Reimprimir (2ª Via)"><i class="fa-solid fa-copy"></i></button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-if="generatedReceipts.totalPages > 1" class="flex justify-between items-center mt-4 pt-4 border-t">
            <button @click="receiptPaginationGenerated.currentPage--; fetchGeneratedReceipts()" :disabled="receiptPaginationGenerated.currentPage === 1" class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50">Ant.</button>
            <span>Pág. {{ receiptPaginationGenerated.currentPage }}</span>
            <button @click="receiptPaginationGenerated.currentPage++; fetchGeneratedReceipts()" :disabled="receiptPaginationGenerated.currentPage === generatedReceipts.totalPages" class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50">Próx.</button>
        </div>
    </div>
</div>



                            
                            
                            <div v-if="activeView === 'waiting_list'">
				            <div class="bg-white p-6 rounded-lg shadow">
                            <div class="flex flex-col md:flex-row justify-between md:items-center mb-2 gap-2">
                                <h1 class="text-2xl sm:text-1xl font-bold">Agenda Espera/Não Resolvidos</h1>
                                <div class="flex gap-2">
                                    <button @click="exportWaitingListToXLS" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 text-sm"><i class="fa-solid fa-file-excel mr-2"></i>Exportar (Filtros Atuais)</button>
                                    <button @click="openAddToWaitingListModal" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm"><i class="fa-solid fa-plus mr-2"></i>Adicionar Manual</button>
                                </div>
                            </div>
                            
                                <div class="mb-4">
                                    <input type="text" v-model="waitingListFilters.search" @input="debouncedSearchWaitingList" placeholder="Buscar por nome ou motivo..." class="form-input max-w-lg">
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full bg-white">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th @click="sortWaitingList('name')" class="cursor-pointer py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase hover:bg-gray-100">
                                                    {{ labels.patient }} <i :class="getSortIcon('waitingList', 'name')" class="ml-1"></i>
                                                </th>
                                                <th @click="sortWaitingList('added_at')" class="cursor-pointer py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase hover:bg-gray-100">
                                                    Adicionado em <i :class="getSortIcon('waitingList', 'added_at')" class="ml-1"></i>
                                                </th>
                                                <th @click="sortWaitingList('reason')" class="cursor-pointer py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase hover:bg-gray-100">
                                                    Motivo <i :class="getSortIcon('waitingList', 'reason')" class="ml-1"></i>
                                                </th>
                                                <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200">
                                            <tr v-if="sortedWaitingList.length === 0">
                                                <td colspan="4" class="text-center py-8 text-gray-500">
                                                    {{ waitingListFilters.search ? 'Nenhum resultado encontrado.' : 'A agenda de espera está vazia.' }}
                                                </td>
                                            </tr>
                                            <tr v-else v-for="item in sortedWaitingList" :key="item.id">
                                                <td class="py-4 px-4 whitespace-nowrap font-medium">
                                                    <div class="flex items-center space-x-3">
                                                        <img :src="item.photo || 'https://placehold.co/40x40/E2E8F0/A0AEC0?text=Foto'" @error="e => e.target.src='https://placehold.co/40x40/E2E8F0/A0AEC0?text=Foto'" class="w-10 h-10 rounded-full object-cover bg-gray-200">
                                                        <div>
                                                            <a href="#" @click.prevent="openPatientQuickView(item.id)" class="clickable-patient-name" :title="`Ver dados de ${item.name}`">
                                                                {{ item.name }}
                                                            </a>
                                                            <div class="text-xs text-gray-500">{{ item.phone }}</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="py-4 px-4 whitespace-nowrap text-sm text-gray-600">{{ formatEntryDate(item.added_at) }}</td>
                                                <td class="py-4 px-4 text-sm text-gray-600 truncate" :title="item.reason">{{ item.reason || '---' }}</td>
                                                <td class="py-4 px-4 whitespace-nowrap text-sm font-medium">
                                                    <button @click="scheduleFromWaitingList(item)" class="px-3 py-1 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-xs" title="Agendar na Agenda Principal"><i class="fa-solid fa-calendar-check"></i> Agendar</button>
                                                    <button v-if="currentUser.future_schedule_enabled == 1" @click="openFutureScheduleModalFromWaitingList(item)" class="px-3 py-1 bg-gray-600 text-white rounded-md hover:bg-gray-700 text-xs ml-2" title="Mover para Agenda Futura"><i class="fa-solid fa-history"></i> Ag. Futura</button>
                                                    
                                                    <button @click.prevent="finishTreatmentFromWaitingList(item)" 
                                                            class="px-3 py-1 bg-red-600 text-white rounded-md hover:bg-red-700 text-xs ml-2" 
                                                            title="Finalizar Tratamento (Remove da Lista)">Finalizar Tratamento</button>

                                                    <button @click="openClinicalModalByPatientId(item.id)" class="text-blue-600 hover:text-blue-900 ml-3" :title="labels.clinicalData"><i class="fa-solid fa-notes-medical"></i></button>
                                                    
                                                    </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <div v-if="activeView === 'future_schedule'">
                            <div class="bg-white p-6 rounded-lg shadow">
                            <div class="flex flex-col md:flex-row justify-between md:items-center mb-2 gap-2">
                                <h1 class="text-2xl sm:text-1xl font-bold">Agenda Futura</h1>
                                <button @click="exportFutureScheduleToXLS" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 text-sm"><i class="fa-solid fa-file-excel mr-2"></i>Exportar (Filtros Atuais)</button>
                                
                                </div>
                                <div class="mb-4">
                                <input type="text" v-model="futureScheduleFilters.search" @keyup="debouncedFetchFutureSchedule" :placeholder="'Buscar ' + labels.patient.toLowerCase() + '...'" class="form-input max-w-lg">
                                        <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                                </div>    
                                
                                <div class="overflow-x-auto min-h-[19rem]"> <table class="min-w-full bg-white">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th @click="sortFutureSchedule('patient_name')" class="cursor-pointer py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase hover:bg-gray-100">
                                                    {{ labels.patient }} <i :class="getSortIcon('futureSchedule', 'patient_name')" class="ml-1"></i>
                                                </th>
                                                <th @click="sortFutureSchedule('return_date')" class="cursor-pointer py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase hover:bg-gray-100">
                                                    Data Programada <i :class="getSortIcon('futureSchedule', 'return_date')" class="ml-1"></i>
                                                </th>
                                                <th @click="sortFutureSchedule('reason')" class="cursor-pointer py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase hover:bg-gray-100">
                                                    Motivo <i :class="getSortIcon('futureSchedule', 'reason')" class="ml-1"></i>
                                                </th>
                                                <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200">
                                            <tr v-if="sortedFutureScheduleList.length === 0">
                                                <td colspan="4" class="text-center py-8 text-gray-500">
                                                    {{ futureScheduleFilters.search ? 'Nenhum resultado encontrado.' : 'Nenhum registro encontrado.' }}
                                                </td>
                                            </tr>
                                            <tr v-else v-for="item in sortedFutureScheduleList" :key="item.id" :class="getFutureScheduleItemClass(item.return_date)">
                                                <td class="py-4 px-4 whitespace-nowrap font-medium">
                                                    <div class="flex items-center space-x-3">
                                                        <img :src="item.photo || 'https://placehold.co/40x40/E2E8F0/A0AEC0?text=Foto'" @error="e => e.target.src='https://placehold.co/40x40/E2E8F0/A0AEC0?text=Foto'" class="w-10 h-10 rounded-full object-cover bg-gray-200">
                                                        <a href="#" @click.prevent="openPatientQuickView(item.patient_id)" class="clickable-patient-name" :title="`Ver dados de ${item.patient_name}`">
                                                            {{ item.patient_name }}
                                                        </a>
                                                    </div>
                                                </td>
                                                <td class="py-4 px-4 whitespace-nowrap text-sm text-gray-600 font-medium">{{ formatDateForDisabledList(item.return_date) }}</td>
                                                <td class="py-4 px-4 whitespace-nowrap text-sm text-gray-600 truncate" :title="item.reason">{{ item.reason || '---' }}</td>
                                                <td class="py-4 px-4 whitespace-nowrap text-sm font-medium">
                                                    <button @click="scheduleFromFutureSchedule(item)" class="px-3 py-1 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-xs mr-2" title="Agendar na Agenda Principal"><i class="fa-solid fa-calendar-check"></i> Agendar</button>
                                                    <button @click.prevent="openFutureScheduleModal(item)" class="text-indigo-600 hover:text-indigo-900 mr-3" title="Editar Data/Notas"><i class="fa-solid fa-pen"></i></button>
                                                    </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div v-if="futureScheduleTotalPages > 1" class="flex justify-between items-center mt-6 pt-4 border-t">
                                    <button @click="future_prevPage" :disabled="futureSchedulePagination.currentPage === 1" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 disabled:opacity-50 disabled:cursor-not-allowed">
                                        <i class="fa-solid fa-chevron-left mr-2"></i> Anterior
                                    </button>
                                    <span class="text-sm font-medium text-gray-700">
                                        Página {{ futureSchedulePagination.currentPage }} de {{ futureScheduleTotalPages }} (Total: {{ futureScheduleTotal }} registros)
                                    </span>
                                    <button @click="future_nextPage" :disabled="futureSchedulePagination.currentPage === futureScheduleTotalPages" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 disabled:opacity-50 disabled:cursor-not-allowed">
                                        Próxima <i class="fa-solid fa-chevron-right ml-2"></i>
                                    </button>
                                </div>
                                </div>
                        </div>

                        <div v-if="activeView === 'birthdays'">
                            <div class="bg-white p-6 rounded-lg shadow">
                                <div class="flex flex-col md:flex-row justify-between md:items-center mb-2 gap-2">
                                    <h1 class="text-2xl sm:text-1xl font-bold">Aniversariantes</h1>
                                    <button @click="sendBirthdayEmails" :disabled="selectedBirthdays.length === 0" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed">
                                        <i class="fa-solid fa-paper-plane mr-2"></i>Enviar E-mail ({{ selectedBirthdays.length }})
                                    </button>
                                </div>
                            </div>
    
                                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                                    <div class="lg:col-span-1">
                                        <div class="bg-white p-6 rounded-lg shadow">
                                        <h2 class="text-xl font-bold text-pink-600 mb-4 border-b pb-2"><i class="fa-solid fa-cake-candles mr-2"></i>Aniversariantes de Hoje</h2>
                                        <div v-if="birthdaysToday.length === 0" class="text-center text-gray-500 py-8">
                                            Nenhum aniversariante hoje.
                                        </div>
                                    <div v-else class="space-y-4">
                                    <div v-for="patient in birthdaysToday" :key="'bday-today-'+patient.id" class="border rounded-lg p-4 flex flex-col justify-between relative">
                                        <input type="checkbox" :value="patient.id" v-model="selectedBirthdays" class="absolute top-3 left-3 h-5 w-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500 z-10">
                                        <span v-if="patient.has_pending_finance" class="financial-alert-dot" title="Pendência Financeira"></span>
                                        <div class="flex items-center space-x-4 mb-4" :class="{'ml-8': !patient.has_pending_finance, 'ml-12': patient.has_pending_finance}">
                                            <img :src="patient.photo || 'https://placehold.co/64x64/E2E8F0/A0AEC0?text=Foto'" @error="e => e.target.src='https://placehold.co/64x64/E2E8F0/A0AEC0?text=Foto'" class="w-16 h-16 rounded-full object-cover bg-gray-200">
                                            <div class="overflow-hidden">
                                                <h3 class="font-semibold text-lg truncate" :title="patient.name">
                                                    <a href="#" @click.prevent="openPatientQuickView(patient.id)" class="clickable-patient-name">
                                                    {{ patient.name }}
                                                    </a>
                                                </h3>
                                                <p v-if="patient.birthdate" class="text-sm text-gray-600">{{ calculateAge(patient.birthdate) }} anos</p>
                                                <p class="text-sm text-gray-600 truncate" :title="patient.cpf || 'CPF não cadastrado'">CPF: {{ patient.cpf || '---' }}</p>
                                                <p class="text-sm text-gray-600 truncate" :title="patient.phone || 'Celular não cadastrado'">Cel: {{ patient.phone || '---' }}</p>
                                            </div>
                                        </div>
                                        <div class="flex items-center space-x-2 border-t pt-3 mt-auto">
                                            <button @click="openPatientModal(patient)" class="flex-1 text-sm px-3 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 text-center">Detalhes</button>
                                            <button @click="openClinicalModalByPatientId(patient.id)" class="flex-1 text-sm px-3 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 text-center">{{ labels.clinicalData }}</button>
                                        </div>
                                    </div>
                                    </div>
                    </div>
                </div>
        
                                            <div class="lg:col-span-2">
                                                <div class="bg-white p-6 rounded-lg shadow">
                                                    <h2 class="text-xl font-bold mb-4 border-b pb-2">Próximos 15 Dias</h2>
                                                    <div v-if="birthdaysNext15Days.length === 0" class="text-center text-gray-500 py-8">
                                                        Nenhum aniversariante nos próximos 15 dias.
                                                    </div>
                                                    <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                        <div v-for="patient in birthdaysNext15Days" :key="'bday-next-'+patient.id" class="border rounded-lg p-4 flex flex-col justify-between relative">
                                                            <input type="checkbox" :value="patient.id" v-model="selectedBirthdays" class="absolute top-3 left-3 h-5 w-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500 z-10">
                                                            <span v-if="patient.has_pending_finance" class="financial-alert-dot" title="Pendência Financeira"></span>
                                                            <div class="flex items-center space-x-4 mb-4" :class="{'ml-8': !patient.has_pending_finance, 'ml-12': patient.has_pending_finance}">
                                                                <img :src="patient.photo || 'https://placehold.co/64x64/E2E8F0/A0AEC0?text=Foto'" @error="e => e.target.src='https://placehold.co/64x64/E2E8F0/A0AEC0?text=Foto'" class="w-16 h-16 rounded-full object-cover bg-gray-200">
                                                                <div class="overflow-hidden">
                                                                    <h3 class="font-semibold text-lg truncate" :title="patient.name">
                                                                        <a href="#" @click.prevent="openPatientQuickView(patient.id)" class="clickable-patient-name">
                                                                            {{ patient.name }}
                                                                        </a>
                                                                        </h3>
                                                                    <p class="font-bold text-gray-700">{{ formatDateForDisabledList(patient.birthdate) }} ({{ calculateAge(patient.birthdate) + 1 }} anos)</p>
                                                                    <p class="text-sm text-gray-600 truncate" :title="patient.cpf || 'CPF não cadastrado'">CPF: {{ patient.cpf || '---' }}</p>
                                                                    <p class="text-sm text-gray-600 truncate" :title="patient.phone || 'Celular não cadastrado'">Cel: {{ patient.phone || '---' }}</p>
                                                                </div>
                                                            </div>
                                                            <div class="flex items-center space-x-2 border-t pt-3 mt-auto">
                                                                <button @click="openPatientModal(patient)" class="flex-1 text-sm px-3 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 text-center">Detalhes</button>
                                                                <button @click="openClinicalModalByPatientId(patient.id)" class="flex-1 text-sm px-3 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 text-center">{{ labels.clinicalData }}</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                </div>
                        </div>




















<div v-if="activeView === 'settings'">
    <h1 class="text-2xl sm:text-1xl font-bold mb-6">Configurações</h1>
    
    <div class="bg-white p-6 rounded-lg shadow w-full">
        
        <div class="border-b border-gray-200 mb-6">
            <nav class="-mb-px flex space-x-6 overflow-x-auto">
                <button type="button" @click="activeProfileTab = 'main'" 
                    :class="{'border-blue-500 text-blue-600': activeProfileTab === 'main', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeProfileTab !== 'main'}" 
                    class="py-4 px-1 border-b-2 font-medium text-sm whitespace-nowrap">
                    <i class="fa-solid fa-user mr-2"></i>Perfil
                </button>

                <button type="button" @click="activeProfileTab = 'docs'" 
                    :class="{'border-blue-500 text-blue-600': activeProfileTab === 'docs', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeProfileTab !== 'docs'}" 
                    class="py-4 px-1 border-b-2 font-medium text-sm whitespace-nowrap">
                    <i class="fa-solid fa-id-card mr-2"></i>Documentações
                </button>

                <button type="button" @click="activeProfileTab = 'contact'" 
                    :class="{'border-blue-500 text-blue-600': activeProfileTab === 'contact', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeProfileTab !== 'contact'}" 
                    class="py-4 px-1 border-b-2 font-medium text-sm whitespace-nowrap">
                    <i class="fa-solid fa-map-marker-alt mr-2"></i>Contato/Endereço
                </button>

                <button type="button" @click="activeProfileTab = 'system'; fetchPrescriptionTemplates()" 
                    :class="{'border-blue-500 text-blue-600': ['system', 'funcionalidades', 'horarios', 'payment_methods', 'comunicacoes', 'price_lists', 'anamnesis_templates', 'receipt_templates', 'prescription_templates', 'medicines', 'exams_list', 'integrations', 'maintenance'].includes(activeProfileTab), 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': !['system', 'funcionalidades', 'horarios', 'payment_methods', 'comunicacoes', 'price_lists', 'anamnesis_templates', 'receipt_templates', 'prescription_templates', 'medicines', 'exams_list', 'integrations', 'maintenance'].includes(activeProfileTab)}" 
                    class="py-4 px-1 border-b-2 font-medium text-sm whitespace-nowrap">
                    <i class="fa-solid fa-cogs mr-2"></i>Sistema
                </button>
            </nav>
        </div>

        <form @submit.prevent="saveProfile">

            <div v-show="['system', 'funcionalidades', 'horarios', 'payment_methods', 'comunicacoes', 'price_lists', 'anamnesis_templates', 'receipt_templates', 'prescription_templates', 'medicines', 'exams_list', 'integrations', 'maintenance'].includes(activeProfileTab)" class="mb-6 bg-gray-50 p-2 rounded-lg border border-gray-200">
                <div class="flex flex-wrap gap-2">
                    <button type="button" @click="activeProfileTab = 'system'" :class="activeProfileTab === 'system' ? 'bg-white text-blue-700 shadow font-semibold' : 'text-gray-600 hover:bg-gray-100'" class="px-3 py-1.5 rounded-md text-sm transition-all">Geral</button>
                    <button type="button" @click="activeProfileTab = 'funcionalidades'" :class="activeProfileTab === 'funcionalidades' ? 'bg-white text-blue-700 shadow font-semibold' : 'text-gray-600 hover:bg-gray-100'" class="px-3 py-1.5 rounded-md text-sm transition-all">Funcionalidades</button>
                    <button type="button" @click="activeProfileTab = 'horarios'" :class="activeProfileTab === 'horarios' ? 'bg-white text-blue-700 shadow font-semibold' : 'text-gray-600 hover:bg-gray-100'" class="px-3 py-1.5 rounded-md text-sm transition-all">Horários</button>
                    <button type="button" @click="activeProfileTab = 'payment_methods'; fetchUserPaymentMethods();" :class="activeProfileTab === 'payment_methods' ? 'bg-white text-blue-700 shadow font-semibold' : 'text-gray-600 hover:bg-gray-100'" class="px-3 py-1.5 rounded-md text-sm transition-all">Pagamentos</button>
                    <button type="button" @click="activeProfileTab = 'comunicacoes'" :class="activeProfileTab === 'comunicacoes' ? 'bg-white text-blue-700 shadow font-semibold' : 'text-gray-600 hover:bg-gray-100'" class="px-3 py-1.5 rounded-md text-sm transition-all">Comunicações (E-mail)</button>
                    
                    <div class="w-px h-6 bg-gray-300 mx-1 self-center hidden sm:block"></div>
                    
                    <button type="button" @click="activeProfileTab = 'price_lists'; fetchPriceLists()" :class="activeProfileTab === 'price_lists' ? 'bg-white text-blue-700 shadow font-semibold' : 'text-gray-600 hover:bg-gray-100'" class="px-3 py-1.5 rounded-md text-sm transition-all">Tabelas de Preço</button>
                    <button type="button" @click="activeProfileTab = 'anamnesis_templates'; fetchAnamnesisTemplates()" :class="activeProfileTab === 'anamnesis_templates' ? 'bg-white text-blue-700 shadow font-semibold' : 'text-gray-600 hover:bg-gray-100'" class="px-3 py-1.5 rounded-md text-sm transition-all">Anamneses</button>
                    <button type="button" @click="activeProfileTab = 'receipt_templates'; fetchUserReceiptTemplates()" :class="activeProfileTab === 'receipt_templates' ? 'bg-white text-blue-700 shadow font-semibold' : 'text-gray-600 hover:bg-gray-100'" class="px-3 py-1.5 rounded-md text-sm transition-all">Recibos</button>
                    
                    <button type="button" @click="activeProfileTab = 'prescription_templates'; fetchPrescriptionTemplates()" :class="activeProfileTab === 'prescription_templates' ? 'bg-white text-blue-700 shadow font-semibold' : 'text-gray-600 hover:bg-gray-100'" class="px-3 py-1.5 rounded-md text-sm transition-all">Modelos Doc.</button>
                    <button type="button" @click="activeProfileTab = 'medicines'; fetchMedicines()" :class="activeProfileTab === 'medicines' ? 'bg-white text-blue-700 shadow font-semibold' : 'text-gray-600 hover:bg-gray-100'" class="px-3 py-1.5 rounded-md text-sm transition-all">Medicamentos</button>
                    <button type="button" @click="activeProfileTab = 'exams_list'; fetchExams()" :class="activeProfileTab === 'exams_list' ? 'bg-white text-blue-700 shadow font-semibold' : 'text-gray-600 hover:bg-gray-100'" class="px-3 py-1.5 rounded-md text-sm transition-all">Exames</button>
                    <div class="w-px h-6 bg-gray-300 mx-1 self-center hidden sm:block"></div>

                    <button type="button" @click="activeProfileTab = 'integrations'" :class="activeProfileTab === 'integrations' ? 'bg-white text-blue-700 shadow font-semibold' : 'text-gray-600 hover:bg-gray-100'" class="px-3 py-1.5 rounded-md text-sm transition-all">Integrações</button>
                    <button type="button" @click="activeProfileTab = 'maintenance'" :class="activeProfileTab === 'maintenance' ? 'bg-red-50 text-red-700 shadow font-semibold border border-red-100' : 'text-gray-600 hover:bg-gray-100'" class="px-3 py-1.5 rounded-md text-sm transition-all">Manutenção</button>
                </div>
            </div>

            <div v-show="activeProfileTab === 'main'">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <div class="md:col-span-2 space-y-4">
                        <h2 class="text-xl font-semibold border-b pb-2">Meu Perfil</h2>
                        <div><label>Nome Completo</label><input type="text" v-model="editingProfile.name" required class="form-input bg-gray-100" readonly></div>
                        <div><label>Nome Profissional</label><input type="text" v-model="editingProfile.professionalName" class="form-input"></div>
                        <div><label>Email</label><input type="email" v-model="editingProfile.email" required class="form-input bg-gray-100" readonly></div>
                        <div>
                            <label>Nova Senha (deixe em branco para não alterar)</label>
                            <input type="password" v-model="editingProfile.password" class="form-input">
                            <div v-if="editingProfile.password" class="mt-2 text-xs flex items-center">
                                <div class="password-strength-bar-container">
                                    <div class="password-strength-bar" :class="{ 'strength-1': passwordStrength === 1, 'strength-2': passwordStrength === 2, 'strength-3': passwordStrength === 3, 'strength-4': passwordStrength === 4 }"></div>
                                </div>
                                <span class="feedback-text" :class="{ 'feedback-1': passwordStrength === 1, 'feedback-2': passwordStrength === 2, 'feedback-3': passwordStrength === 3, 'feedback-4': passwordStrength === 4, 'feedback-0': passwordStrength === 0 }">{{ passwordFeedback || '&nbsp;' }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="md:col-span-1 space-y-4">
                        <h2 class="text-xl font-semibold border-b pb-2">Imagens</h2>
                        <div class="flex flex-col items-center"> <p class="font-medium mb-2">Foto de Perfil</p> <img :src="userPhotoPreview || editingProfile.photo || 'https://placehold.co/150x150/E2E8F0/A0AEC0?text=Foto'" class="w-36 h-36 rounded-full object-cover bg-gray-200 mb-4"> <input type="file" id="profile-photo" @change="handlePhotoUpload($event, 'user')" class="hidden" accept="image/*"> <div class="flex gap-2 w-full max-w-xs"> <button type="button" @click="triggerFileUpload('profile-photo')" class="flex-1 text-sm py-2 bg-gray-200 rounded-md"><i class="fa-solid fa-upload mr-2"></i>Carregar</button> <button type="button" @click="openWebcamModal('user')" class="flex-1 text-sm py-2 bg-gray-200 rounded-md"><i class="fa-solid fa-camera mr-2"></i>Webcam</button> </div> </div>
                        <div class="flex flex-col items-center mt-6"> <p class="font-medium mb-2">Logo da Empresa</p> <img :src="logoPreview || editingProfile.logo || 'https://placehold.co/200x100/E2E8F0/A0AEC0?text=Logo'" class="w-48 h-24 object-contain bg-gray-200 mb-4 border"> <input type="file" id="profile-logo" @change="handleLogoUpload" class="hidden" accept="image/*"> <button type="button" @click="triggerFileUpload('profile-logo')" class="text-sm w-full max-w-xs py-2 bg-gray-200 rounded-md">Carregar Logo</button> </div>
                    </div>
                </div>
            </div>

            <div v-show="activeProfileTab === 'docs'">
                <h2 class="text-xl font-semibold border-b pb-2 mb-4">Documentação e Pessoais</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                    <div>
                        <label>CPF / CNPJ</label>
                        <input type="text" v-model="editingProfile.cpf" placeholder="000.000.000-00" required class="form-input bg-gray-100" readonly>
                    </div>
                    <div><label>Data de Nascimento</label><input type="date" v-model="editingProfile.birthdate" required class="form-input bg-gray-100" readonly></div>
                    <div>
                        <label>Sexo</label>
                        <select v-model="editingProfile.gender" class="form-input">
                            <option :value="null">Selecione...</option>
                            <option v-for="opt in getOptionsByType('gender')" :key="opt.id" :value="opt.option_value">{{ opt.option_value }}</option>
                        </select>
                    </div>
                    <div>
                        <label>Estado Civil</label>
                        <select v-model="editingProfile.marital_status" class="form-input">
                            <option :value="null">Selecione...</option>
                            <option v-for="opt in getOptionsByType('marital_status')" :key="opt.id" :value="opt.option_value">{{ opt.option_value }}</option>
                        </select>
                    </div>
                    <div>
                        <label>Profissão</label>
                        <select v-model="editingProfile.profession" @change="updateSpecialtiesForProfile" required class="form-input">
                            <option disabled value="">Selecione</option>
                            <option v-for="p in professions" :key="p.id" :value="p.name">{{ p.name }}</option>
                        </select>
                    </div>

                    <div>
                        <label>Especialidade</label>
                        <select v-model="editingProfile.specialty" class="form-input" :disabled="!specialties.length">
                            <option :value="null">Selecione a Especialidade...</option>
                            <option v-for="spec in specialties" :key="spec.id" :value="spec.name">{{ spec.name }}</option>
                        </select>
                        <p v-if="!editingProfile.profession" class="text-xs text-gray-500 mt-1">Selecione a Profissão primeiro.</p>
                    </div>
                    
                    <div class="sm:col-span-2 bg-gray-50 p-3 rounded border border-gray-200">
                        <label class="block text-sm font-medium mb-2">Registro Profissional (Obrigatório para Prescrição)</label>
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="text-xs text-gray-500">Tipo (Ex: CRM)</label>
                                <input type="text" v-model="editingProfile.professional_register_type" class="form-input uppercase" placeholder="CRM/CRO" maxlength="10">
                            </div>
                            <div>
                                <label class="text-xs text-gray-500">Número</label>
                                <input type="text" v-model="editingProfile.professional_register_number" class="form-input" placeholder="123456">
                            </div>
                            <div>
                                <label class="text-xs text-gray-500">UF</label>
                                <input type="text" v-model="editingProfile.professional_register_uf" class="form-input uppercase" placeholder="UF" maxlength="2">
                            </div>
                        </div>
                    </div>
                    <div class="sm:col-span-2">
                        <label>Indicado Por</label>
                        <input type="text" v-model="editingProfile.referred_by" class="form-input" placeholder="Opcional">
                    </div>
                </div>
            </div>

            <div v-show="activeProfileTab === 'contact'">
                <h2 class="text-xl font-semibold border-b pb-2 mb-4">Endereço e Contato</h2>
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-x-6 gap-y-4">
                    <div class="sm:col-span-2">
                        <label>Celular (WhatsApp)</label>
                        <input type="tel" v-model="editingProfile.phone" @input="editingProfile.phone = formatPhone($event.target.value)" required class="form-input" placeholder="00-00000-0000">
                    </div>
                    <div class="sm:col-span-2">
                        <label>Fuso Horário</label>
                        <select v-model="editingProfile.timezone" required class="form-input">
                            <option disabled value="">Selecione</option><option v-for="tz in timezones" :key="tz" :value="tz">{{ tz }}</option>
                        </select>
                    </div>
                    
                    <div class="col-span-1 sm:col-span-4"><hr class="my-2"></div>
                    
                    <div class="sm:col-span-1">
                        <label>CEP</label>
                        <input type="text" v-model="editingProfile.zip_code" @input="editingProfile.zip_code = formatCEP($event.target.value)" @blur="fetchAddressByZipCode('profile')" placeholder="00000-000" required class="form-input">
                    </div>
                    <div class="sm:col-span-3">
                        <label>Rua / Avenida</label>
                        <input type="text" v-model="editingProfile.street" class="form-input">
                    </div>
                    <div class="sm:col-span-1">
                        <label>Número</label>
                        <input type="text" v-model="editingProfile.street_number" required class="form-input">
                    </div>
                    <div class="sm:col-span-3">
                        <label>Bairro</label>
                        <input type="text" v-model="editingProfile.neighborhood" class="form-input">
                    </div>
                    <div class="sm:col-span-2">
                        <label>Cidade</label>
                        <input type="text" v-model="editingProfile.city" class="form-input">
                    </div>
                    <div class="sm:col-span-1">
                        <label>Estado</label>
                        <input type="text" v-model="editingProfile.state" class="form-input bg-gray-100" readonly>
                    </div>
                    <div class="sm:col-span-4">
                        <label>Complemento (Apto, Loja, etc)</label>
                        <input type="text" v-model="editingProfile.address_complement" class="form-input">
                    </div>
                </div>
            </div>

            <div v-show="activeProfileTab === 'system'">
                <h2 class="text-xl font-semibold border-b pb-2 mb-4">Configurações Gerais do Sistema</h2>
                <div class="space-y-4">
                    <div>
                        <label>Versão do Sistema</label>
                        <select v-model="editingProfile.system_version" class="form-input max-w-xs"> <option value="Saude">Área da Saúde</option> <option value="Tecnica">Área Técnica</option> </select>
                    </div>
                    <div class="pt-4 border-t">
                        <h3 class="text-lg font-medium text-gray-800">Intervalo da Agenda</h3>
                        <div class="mt-2"> <label class="block text-sm font-medium">Duração do Slot (minutos)</label> <select v-model.number="editingProfile.appointment_slot_minutes" class="form-input max-w-xs"> <option value="15">15 minutos</option> <option value="30">30 minutos</option> <option value="60">60 minutos</option> </select> </div>
                    </div>
                    
                    <div class="pt-4 border-t">
                            <h3 class="text-lg font-medium text-gray-800">Controle de Faltas</h3>
                            <div class="mt-2">
                            <label class="block text-sm font-medium text-gray-700">Tolerância para Falta (minutos)</label>
                            <p class="text-xs text-gray-500 mb-1">Tempo após o início do agendamento para considerar que o paciente "Não Compareceu".</p>
                            <input type="number" v-model.number="editingProfile.missed_appointment_tolerance" class="form-input max-w-xs" min="15" placeholder="60">
                            </div>
                    </div>

                    <div class="pt-4 border-t">
                        <h3 class="text-lg font-medium text-gray-800">Orçamento</h3>
                        <div class="mt-2">
                            <label class="block text-sm font-medium">Modelo Padrão</label>
                            <select v-model="editingProfile.default_budget_form_identifier" class="form-input max-w-xs">
                                <option v-for="form in budgetForms" :key="form.id" :value="form.identifier">{{ form.name }}</option>
                                <option v-if="!budgetForms.length" disabled>Carregando...</option>
                            </select>
                            </div>
                        <div class="mt-4"><label class="block text-sm font-medium">Tabela Padrão</label> <select v-model="editingProfile.default_price_list_id" class="form-input max-w-xs"> <option :value="null">Nenhuma</option> <option v-for="list in priceLists" :key="list.id" :value="list.id"> {{ list.name }} {{ list.is_global ? '(Global)' : '' }} </option> </select> </div>
                    </div>
                    <div class="pt-4 border-t">
                        <h3 class="text-lg font-medium text-gray-800">{{ labels.anamnesis }}</h3>
                        <div class="mt-2"> <label class="block text-sm font-medium">Modelo Padrão (para novos {{ labels.patients.toLowerCase() }})</label> <select v-model="editingProfile.anamnesis_template_id" class="form-input max-w-xs"> <option :value="null">Nenhum (usará o 1º global)</option> <option v-for="template in userAnamnesisTemplates" :key="template.id" :value="template.id"> {{ template.title }} {{ template.is_global ? '(Global)' : '' }} </option> </select> </div>
                    </div>
                    <div class="pt-4 border-t">
                        <h3 class="text-lg font-medium text-gray-800">Recibos</h3>
                        <div class="mt-2">
                            <label class="block text-sm font-medium">Modelo Padrão (para gerar recibos)</label>
                            <select v-model="editingProfile.default_receipt_template_id" class="form-input max-w-xs">
                                <option :value="null">Nenhum (usará o 1º disponível)</option>
                                <option v-for="template in userReceiptTemplates" :key="template.id" :value="template.id">
                                    {{ template.title }} {{ template.is_global ? '(Global)' : '' }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="pt-4 border-t">
                        <h3 class="text-lg font-medium text-gray-800">Padrões de Documentos (Atestados)</h3>
                        <div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium">Modelo Padrão de Atestado</label>
                                <select v-model="editingProfile.default_atestado_template_id" class="form-input max-w-xs">
                                    <option :value="null">Usar Padrão do Sistema</option>
                                    <option v-for="t in prescriptionTemplates.filter(pt => pt.type === 'atestado')" :key="t.id" :value="t.id">
                                        {{ t.title }} {{ t.is_global ? '(Global)' : '(Próprio)' }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium">Modelo Padrão de Declaração</label>
                                <select v-model="editingProfile.default_declaracao_template_id" class="form-input max-w-xs">
                                    <option :value="null">Usar Padrão do Sistema</option>
                                    <option v-for="t in prescriptionTemplates.filter(pt => pt.type === 'atestado')" :key="t.id" :value="t.id">
                                        {{ t.title }} {{ t.is_global ? '(Global)' : '(Próprio)' }}
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-show="activeProfileTab === 'funcionalidades'">
                <h2 class="text-xl font-semibold border-b pb-2 mb-4">Ativação de Funcionalidades</h2>
                <div class="space-y-4">
                    
                    <div>
                        <label class="block text-sm font-medium">Agenda (Geral)</label>
                        <div class="flex items-center mt-2">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" v-model="editingProfile.agenda_enabled" :true-value="1" :false-value="0" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                            </label>
                            <span class="ml-3 font-medium">{{ editingProfile.agenda_enabled == 1 ? 'Ativada' : 'Desativada' }}</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Controla a exibição do menu "Agenda" e o funcionamento dos horários.</p>
                    </div>

                    <div class="pt-4 border-t" :class="{'opacity-50': editingProfile.agenda_enabled != 1}">
                        <label class="block text-sm font-medium">Menu Aniversariantes</label>
                        <div class="flex items-center mt-2">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" v-model="editingProfile.birthday_list_enabled" :true-value="1" :false-value="0" class="sr-only peer" :disabled="editingProfile.agenda_enabled != 1">
                                <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                            </label>
                            <span class="ml-3 font-medium">{{ editingProfile.birthday_list_enabled == 1 && editingProfile.agenda_enabled == 1 ? 'Ativado' : 'Desativado' }}</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">
                            Exibe a lista de aniversariantes no menu da Agenda.
                            <strong v-if="editingProfile.agenda_enabled != 1" class="text-red-600">Requer a "Agenda (Geral)" ativa.</strong>
                        </p>
                    </div>

                    <div class="pt-4 border-t" :class="{'opacity-50': editingProfile.agenda_enabled != 1}">
                        <label class="block text-sm font-medium">Agenda Espera/Não Resolvidos</label>
                        <div class="flex items-center mt-2">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" v-model="editingProfile.waiting_list_enabled" @change="handleWaitingListChange" :true-value="1" :false-value="0" class="sr-only peer" :disabled="editingProfile.agenda_enabled != 1">
                                <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                            </label>
                            <span class="ml-3 font-medium">{{ editingProfile.waiting_list_enabled == 1 && editingProfile.agenda_enabled == 1 ? 'Ativada' : 'Desativada' }}</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">
                            Controla a "Agenda de Espera" e o reagendamento ao finalizar atendimentos.
                            <strong v-if="editingProfile.agenda_enabled != 1" class="text-red-600">Requer a "Agenda (Geral)" ativa.</strong>
                        </p>
                    </div>
                
                    <div class="pt-4 border-t" :class="{'opacity-50': editingProfile.agenda_enabled != 1 || editingProfile.waiting_list_enabled != 1}">
                        <label class="block text-sm font-medium">Agenda Futura</label>
                        <div class="flex items-center mt-2">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" v-model="editingProfile.future_schedule_enabled" @change="handleFutureScheduleChange" :true-value="1" :false-value="0" class="sr-only peer" :disabled="editingProfile.agenda_enabled != 1 || editingProfile.waiting_list_enabled != 1">
                                <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                            </label>
                            <span class="ml-3 font-medium">{{ editingProfile.future_schedule_enabled == 1 && editingProfile.agenda_enabled == 1 && editingProfile.waiting_list_enabled == 1 ? 'Ativada' : 'Desativada' }}</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">
                            Permite agendar retornos futuros.
                            <strong v-if="editingProfile.agenda_enabled != 1 || editingProfile.waiting_list_enabled != 1" class="text-red-600">Requer a "Agenda (Geral)" e a "Agenda Espera" ativas.</strong>
                        </p>
                    </div>
                    
                    <div class="pt-4 border-t">
                        <label class="block text-sm font-medium">Integração MEMED (Prescrição Digital)</label>
                        <div class="flex items-center mt-2">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" v-model="editingProfile.memed_enabled" :true-value="1" :false-value="0" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                            </label>
                            <span class="ml-3 font-medium">{{ editingProfile.memed_enabled == 1 ? 'Ativado' : 'Desativado' }}</span>
                            <button type="button" @click="registerMemedUser" class="px-3 py-1 bg-green-600 text-white text-xs rounded-md hover:bg-green-700 ml-2">
                                <i class="fa-solid fa-user-plus mr-1"></i> Cadastrar Usuário Prescritor
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Habilita o botão de prescrição digital via MEMED nos atendimentos. Requer CPF e Registro Profissional.</p>
                    </div>

                    <div class="pt-4 border-t" v-if="editingProfile.system_version === 'Saude'">
                        <label class="block text-sm font-medium">Odontograma Interativo</label>
                        <div class="flex items-center mt-2">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" v-model="editingProfile.odontogram_enabled" :true-value="1" :false-value="0" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                            </label>
                            <span class="ml-3 font-medium">{{ editingProfile.odontogram_enabled == 1 ? 'Ativado' : 'Desativado' }}</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Habilita a aba "Odontograma" nos dados clínicos (apenas para área da Saúde).</p>
                    </div>

                    <div class="pt-4 border-t">
                        <label class="block text-sm font-medium">Módulo Financeiro</label>
                        <div class="flex items-center mt-2">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" v-model="editingProfile.finance_enabled" :true-value="1" :false-value="0" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                            </label>
                            <span class="ml-3 font-medium">{{ editingProfile.finance_enabled == 1 ? 'Ativado' : 'Desativado' }}</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Controla a exibição do menu "Financeiro".</p>
                    </div>

                    <div class="ml-4" v-if="editingProfile.finance_enabled == 1">
                        <label class="block text-sm font-medium">Livro Caixa</label>
                        <div class="flex items-center mt-2">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" v-model="editingProfile.finance_ledger_enabled" :true-value="1" :false-value="0" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                            </label>
                            <span class="ml-3 font-medium">{{ editingProfile.finance_ledger_enabled == 1 ? 'Ativado' : 'Desativado' }}</span>
                        </div>
                    </div>

                    <div class="ml-4" v-if="editingProfile.finance_enabled == 1">
                        <label class="block text-sm font-medium">Previsão Receitas/Desp.</label>
                        <div class="flex items-center mt-2">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" v-model="editingProfile.finance_forecast_enabled" :true-value="1" :false-value="0" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                            </label>
                            <span class="ml-3 font-medium">{{ editingProfile.finance_forecast_enabled == 1 ? 'Ativado' : 'Desativado' }}</span>
                        </div>
                    </div>
                </div> 
            </div>
            
            <div v-show="activeProfileTab === 'comunicacoes'">
                <h2 class="text-xl font-semibold border-b pb-2 mb-4">Comunicações por E-mail</h2>
                <div class="space-y-6">
                    <div>
                        <div class="flex items-center justify-between">
                            <label class="block text-sm font-medium">Confirmação de Agendamento (Imediato)</label>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" v-model="editingProfile.schedule_email_enabled" :true-value="1" :false-value="0" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                            </label>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Enviado automaticamente ao criar um novo agendamento.</p>
                        <textarea v-model="editingProfile.schedule_email_template" rows="4" class="form-input mt-2" placeholder="Ex: Olá [PACIENTE], seu agendamento para [DATA_HORA] foi confirmado. Atenciosamente, [PROFISSIONAL]"></textarea>
                        <p class="text-xs text-gray-400 mt-1">Variáveis: [PACIENTE], [DATA_HORA], [PROFISSIONAL], [TITULO]</p>
                    </div>

                    <div class="pt-4 border-t">
                        <div class="flex items-center justify-between">
                            <label class="block text-sm font-medium">Lembrete de Agendamento (Automático)</label>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" v-model="editingProfile.reminder_email_enabled" :true-value="1" :false-value="0" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                            </label>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Enviado automaticamente antes da consulta.</p>
                        <div class="flex gap-4 mt-2">
                            <label class="flex items-center text-sm">
                                <input type="checkbox" value="24" v-model="editingProfile.reminder_email_hours" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-gray-700">24h antes</span>
                            </label>
                            <label class="flex items-center text-sm">
                                <input type="checkbox" value="48" v-model="editingProfile.reminder_email_hours" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-gray-700">48h antes</span>
                            </label>
                        </div>
                        <textarea v-model="editingProfile.reminder_email_template" rows="4" class="form-input mt-2" placeholder="Ex: Olá [PACIENTE], lembrete do seu agendamento para [DATA_HORA]."></textarea>
                        <p class="text-xs text-gray-400 mt-1">Variáveis: [PACIENTE], [DATA_HORA]</p>
                    </div>
                    
                    <div class="pt-4 border-t" v-if="editingProfile.future_schedule_enabled == 1">
                        <div class="flex items-center justify-between">
                            <label class="block text-sm font-medium">Notificação de Agenda Futura</label>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" v-model="editingProfile.future_schedule_email_enabled" :true-value="1" :false-value="0" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                            </label>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Enviado ao mover o paciente para a Agenda Futura.</p>
                        <textarea v-model="editingProfile.future_schedule_email_template" rows="4" class="form-input mt-2" placeholder="Ex: Olá [PACIENTE], seu retorno está programado para [DATA_RETORNO]. Entraremos em contato em breve."></textarea>
                        <p class="text-xs text-gray-400 mt-1">Variáveis: [PACIENTE], [DATA_RETORNO], [PROFISSIONAL]</p>
                    </div>

                    <div class="pt-4 border-t">
                        <div class="flex items-center justify-between">
                            <label class="block text-sm font-medium">Mensagem de Aniversário</label>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" v-model="editingProfile.birthday_email_enabled" :true-value="1" :false-value="0" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                            </label>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Ativa o envio automático no dia do aniversário do paciente.</p>
                        <div class="mt-2">
                            <label class="block text-sm font-medium">Horário de Envio</label>
                            <input type="time" v-model="editingProfile.birthday_email_time" class="form-input p-1 text-sm w-32">
                        </div>
                        <textarea v-model="editingProfile.birthday_email_template" rows="4" class="form-input mt-2" placeholder="Ex: Olá [PACIENTE], feliz aniversário!"></textarea>
                        <p class="text-xs text-gray-400 mt-1">Variáveis: [PACIENTE]</p>
                    </div>
                </div>
            </div>

            <div v-show="activeProfileTab === 'horarios'">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div>
                        <h2 class="text-xl font-semibold border-b pb-2 mb-4">Horário Semanal</h2>
                        <div v-if="editingProfile.weekly_schedule" class="space-y-4">
                            <div v-for="(day, index) in weekDaysNames" :key="index" class="border-b pb-3 last:border-b-0 last:pb-0">
                                <h3 class="font-semibold text-sm mb-2">{{ day }}</h3>
                                <div class="flex items-center space-x-2 mb-2">
                                    <input type="checkbox" :id="'day-1-'+index" v-model="editingProfile.weekly_schedule[index].enabled" class="h-5 w-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <label :for="'day-1-'+index" class="w-12 text-sm text-gray-600">Turno 1</label>
                                    <input type="time" v-model="editingProfile.weekly_schedule[index].start" class="form-input p-1 text-sm w-24" :disabled="!editingProfile.weekly_schedule[index].enabled">
                                    <span class="text-gray-500">às</span>
                                    <input type="time" v-model="editingProfile.weekly_schedule[index].end" class="form-input p-1 text-sm w-24" :disabled="!editingProfile.weekly_schedule[index].enabled">
                                </div>
                                <div class="flex items-center space-x-2">
                                    <input type="checkbox" :id="'day-2-'+index" v-model="editingProfile.weekly_schedule[index].enabled2" class="h-5 w-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <label :for="'day-2-'+index" class="w-12 text-sm text-gray-600">Turno 2</label>
                                    <input type="time" v-model="editingProfile.weekly_schedule[index].start2" class="form-input p-1 text-sm w-24" :disabled="!editingProfile.weekly_schedule[index].enabled2">
                                    <span class="text-gray-500">às</span>
                                    <input type="time" v-model="editingProfile.weekly_schedule[index].end2" class="form-input p-1 text-sm w-24" :disabled="!editingProfile.weekly_schedule[index].enabled2">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div>
                        <h2 class="text-xl font-semibold border-b pb-2 mb-4">Datas Desativadas</h2>
                        <p class="text-xs text-gray-500 mb-2">(Feriados, Férias)</p>
                        <div class="flex items-center gap-2 mb-4"> <input type="date" v-model="newDisabledDate" class="form-input flex-grow"> <button type="button" @click="addDisabledDate" class="px-3 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"><i class="fa-solid fa-plus"></i></button> </div>
                        <div class="max-h-48 overflow-y-auto space-y-2"> <p v-if="!editingProfile.disabled_dates || editingProfile.disabled_dates.length === 0" class="text-center text-sm text-gray-500 py-4">Nenhuma data desativada adicionada.</p> <div v-else v-for="date in editingProfile.disabled_dates" :key="date" class="flex justify-between items-center bg-gray-50 p-2 rounded"> <span class="text-sm font-medium">{{ formatDateForDisabledList(date) }}</span> <button type="button" @click="removeDisabledDate(date)" class="text-red-500 hover:text-red-700 text-xs"><i class="fa-solid fa-times"></i></button> </div> </div>
                    </div>
                </div>
            </div>
            
            <div v-show="activeProfileTab === 'payment_methods'">
                <h2 class="text-xl font-semibold border-b pb-2 mb-4">Formas de Pagamento</h2>
                <p class="text-sm text-gray-600 mb-4">Gerencie suas formas de pagamento pessoais e selecione quais métodos (globais ou pessoais) devem aparecer nos seus orçamentos e baixas financeiras.</p>
                
                <div class="flex justify-end mb-4">
                    <button @click="openUserPaymentMethodModal(null)" type="button" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">
                        <i class="fa-solid fa-plus mr-2"></i>Adicionar Método Pessoal
                    </button>
                </div>

                <div class="border rounded-lg overflow-hidden">
                    <table class="min-w-full bg-white">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase w-10">Usar</th>
                                <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase">Nome da Forma de Pagamento</th>
                                <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase">Proprietário</th>
                                <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-if="!userPaymentMethods || userPaymentMethods.length === 0">
                                <td colspan="5" class="p-4 text-center text-gray-500">Nenhuma forma de pagamento encontrada.</td>
                            </tr>
                            <tr v-for="method in userPaymentMethods" :key="method.id" class="hover:bg-gray-50">
                                <td class="py-3 px-3 text-center">
                                    <input type="checkbox" :value="method.id" v-model="editingProfile.enabled_payment_methods" 
                                           class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                           :disabled="method.is_default && method.is_global"
                                           :title="method.is_default && method.is_global ? 'Métodos padrão globais não podem ser desabilitados' : 'Habilitar/Desabilitar método'">
                                </td>
                                <td class="py-3 px-3 font-medium text-gray-900">{{ method.option_value }}</td>
                                <td class="py-3 px-3">
                                    <span class="text-xs px-2 py-0.5 rounded-full" 
                                          :class="method.is_global ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'">
                                          {{ method.is_global ? 'Global' : 'Pessoal' }}
                                    </span>
                                </td>
                                <td class="py-3 px-3 text-sm">
                                    <button type="button" @click.prevent="openUserPaymentMethodModal(method)" 
                                            :disabled="method.is_global"
                                            :class="{'opacity-30 cursor-not-allowed': method.is_global}"
                                            class="text-indigo-600 hover:text-indigo-900 mr-3" 
                                            :title="method.is_global ? 'Métodos globais não podem ser editados (crie uma cópia)' : 'Editar Método'">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <button type="button" @click.prevent="deleteUserPaymentMethod(method.id)" 
                                            :disabled="method.is_global || method.is_default"
                                            :class="{'opacity-30 cursor-not-allowed': method.is_global || method.is_default}"
                                            class="text-red-600 hover:text-red-900" 
                                            :title="method.is_global || method.is_default ? 'Métodos globais ou padrão não podem ser excluídos' : 'Excluir Método'">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="text-xs text-gray-500 mt-2">
                    <i class="fa-solid fa-info-circle mr-1"></i>
                    Se nenhuma forma de pagamento for marcada, **todas** as disponíveis (Globais e Pessoais) serão exibidas.
                </p>
            </div>

            <div v-show="activeProfileTab === 'maintenance'">
                <h2 class="text-xl font-semibold border-b pb-2 mb-4">Manutenção de Dados</h2>
                <p class="text-sm text-gray-600 mb-6 bg-yellow-50 border border-yellow-200 p-3 rounded">
                    <i class="fa-solid fa-triangle-exclamation text-yellow-600 mr-2"></i>
                    <strong>Atenção:</strong> As ações nesta aba são destrutivas e irreversíveis. Certifique-se de ter um backup antes de prosseguir. A senha administrativa do seu usuário será solicitada.
                </p>

                <div class="mb-8 pb-6 border-b">
                    <h3 class="text-lg font-medium text-gray-800 mb-3">Limpeza de Histórico de Atendimentos</h3>
                    <p class="text-sm text-gray-500 mb-4">Remove agendamentos, atendimentos e registros clínicos antigos.</p>
                    <div class="flex flex-wrap items-end gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Manter dados de:</label>
                            <select v-model="maintenance.clinicalPeriod" class="form-input w-48">
                                <option value="18">Últimos 18 meses</option>
                                <option value="12">Últimos 12 meses</option>
                                <option value="6">Últimos 6 meses</option>
                                <option value="0">Zerar Histórico (Apagar Tudo)</option>
                            </select>
                        </div>
                        <button type="button" @click.prevent="promptCleanup('clinical')" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 text-sm">
                            Executar Limpeza
                        </button>
                    </div>
                </div>

                <div class="mb-8 pb-6 border-b">
                    <h3 class="text-lg font-medium text-gray-800 mb-3">Limpeza de Recibos</h3>
                    <p class="text-sm text-gray-500 mb-4">Remove lançamentos do Livro Caixa (Entradas) com base no status do recibo.</p>
                    <div class="flex flex-wrap items-end gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Alvo da Limpeza:</label>
                            <div class="flex gap-4 mt-2">
                                <label class="inline-flex items-center">
                                    <input type="radio" v-model="maintenance.receiptTarget" value="pending" class="form-radio text-blue-600">
                                    <span class="ml-2 text-sm">Recibos não Gerados</span>
                                </label>
                                <label class="inline-flex items-center">
                                    <input type="radio" v-model="maintenance.receiptTarget" value="generated" class="form-radio text-blue-600">
                                    <span class="ml-2 text-sm">Recibos Emitidos</span>
                                </label>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Manter dados de:</label>
                            <select v-model="maintenance.receiptPeriod" class="form-input w-48">
                                <option value="18">Últimos 18 meses</option>
                                <option value="12">Últimos 12 meses</option>
                                <option value="6">Últimos 6 meses</option>
                                <option value="0">Zerar (Apagar Tudo)</option>
                            </select>
                        </div>
                        <button type="button" @click.prevent="promptCleanup('receipts')" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 text-sm">
                            Executar Limpeza
                        </button>
                    </div>
                </div>

                <div>
                    <h3 class="text-lg font-medium text-red-700 mb-3"><i class="fa-solid fa-bomb mr-2"></i>Limpeza Financeira Total</h3>
                    <p class="text-sm text-gray-600 mb-4 bg-red-50 p-2 rounded border border-red-100">
                        <strong>Zona de Perigo:</strong> Estas opções removem TODOS os registros financeiros do módulo selecionado, sem respeitar data. 
                        Exige validação dupla (Senha Admin + Senha de Login).
                    </p>
                    <div class="flex gap-4">
                        <button type="button" @click.prevent="promptFinancialCleanup('forecast')" class="px-4 py-2 bg-red-700 text-white rounded-md hover:bg-red-800 text-sm w-full sm:w-auto">
                            Zerar Previsão de Receitas/Despesas
                        </button>
                        <button type="button" @click.prevent="promptFinancialCleanup('ledger')" class="px-4 py-2 bg-red-700 text-white rounded-md hover:bg-red-800 text-sm w-full sm:w-auto">
                            Zerar Livro Caixa
                        </button>
                    </div>
                </div>
                
                <div class="bg-gray-100 p-4 rounded mt-8">
                    <h3 class="text-lg font-medium mb-3">Backup Manual</h3>
                    <p class="mb-4 text-gray-700 text-sm">Clique para gerar e baixar um arquivo `.sql` com todos os seus dados.</p> <button type="button" @click="generateBackup" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"><i class="fa-solid fa-download"></i> Gerar e Baixar Backup</button>
                </div>
            </div>

            <div v-show="activeProfileTab === 'price_lists'">
                <h2 class="text-xl font-semibold border-b pb-2 mb-4">Gerenciar Tabelas de Preços</h2>
                <div class="space-y-3 mb-4"> <div v-for="list in priceLists" :key="list.id" class="bg-gray-50 p-3 rounded-md"> <div class="flex justify-between items-center"> <span class="font-semibold">{{ list.name }} <span v-if="list.is_global" class="text-xs text-blue-600">(Global)</span></span> <div> <button type="button" @click.prevent="openPriceListModal(list)" :title="list.is_global ? 'Editar (Cria Cópia)' : 'Renomear'" class="text-gray-500 hover:text-indigo-600 mr-2"><i class="fa-solid fa-pen"></i></button> <button type="button" v-if="!list.is_global" @click.prevent="deletePriceList(list.id)" title="Excluir Tabela" class="text-gray-500 hover:text-red-600"><i class="fa-solid fa-trash"></i></button> <span v-else class="text-gray-400 cursor-not-allowed inline-block mr-1" title="Modelos globais não podem ser excluídos"><i class="fa-solid fa-trash"></i></span> </div> </div> <div class="flex flex-wrap gap-2 mt-3 pt-3 border-t"> <button type="button" @click.prevent="managePriceListItems(list)" class="text-xs px-3 py-1 bg-blue-100 text-blue-800 rounded-full hover:bg-blue-200">Gerenciar Itens</button> <button type="button" @click.prevent="exportPriceList(list.id)" class="text-xs px-3 py-1 bg-green-100 text-green-800 rounded-full hover:bg-green-200">Baixar (XLS)</button> </div> </div> <p v-if="priceLists.length === 0" class="text-sm text-center text-gray-500 py-4">Nenhuma tabela criada.</p> </div>
                <div class="flex flex-col sm:flex-row gap-2 mt-4 pt-4 border-t"> <button @click="openPriceListModal(null)" type="button" class="w-full sm:w-auto flex-1 px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">Adicionar Tabela</button> <input type="file" ref="xls_import" @change="importPriceList" class="hidden" accept=".xlsx, .xls"> <button @click="$refs.xls_import.click()" type="button" class="w-full sm:w-auto flex-1 px-4 py-2 bg-gray-600 text-white text-sm rounded-md hover:bg-gray-700">Importar (XLS)</button> </div>
            </div>

            <div v-show="activeProfileTab === 'anamnesis_templates'">
                <h2 class="text-xl font-semibold border-b pb-2 mb-4">Gerenciar Modelos de {{ labels.anamnesis }}</h2>
                <div class="space-y-3 mb-4"> <div v-for="template in userAnamnesisTemplates" :key="template.id" class="bg-gray-50 p-3 rounded-md"> <div class="flex justify-between items-center"> <span class="font-semibold">{{ template.title }} <span v-if="template.is_global" class="text-xs text-blue-600">(Global)</span></span> <div> <button type="button" @click.prevent="openUserAnamnesisModal(template)" :title="template.is_global ? 'Editar (Cria Cópia)' : 'Editar'" class="text-gray-500 hover:text-indigo-600 mr-2"><i class="fa-solid fa-pen"></i></button> <button type="button" v-if="!template.is_global" @click.prevent="deleteUserAnamnesisTemplate(template.id)" title="Excluir Modelo" class="text-gray-500 hover:text-red-600"><i class="fa-solid fa-trash"></i></button> <span v-else class="text-gray-400 cursor-not-allowed inline-block mr-1" title="Modelos globais não podem ser excluídos"><i class="fa-solid fa-trash"></i></span> </div> </div> <div class="flex flex-wrap gap-2 mt-3 pt-3 border-t"> <button type="button" @click.prevent="exportUserAnamnesisTemplate(template.id)" class="text-xs px-3 py-1 bg-green-100 text-green-800 rounded-full hover:bg-green-200">Baixar (JSON)</button> </div> </div> <p v-if="userAnamnesisTemplates.length === 0" class="text-sm text-center text-gray-500 py-4">Nenhum modelo encontrado.</p> </div>
                <div class="flex flex-col sm:flex-row gap-2 mt-4 pt-4 border-t"> <button @click="openUserAnamnesisModal(null)" type="button" class="w-full sm:w-auto flex-1 px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">Adicionar Modelo</button> <input type="file" ref="anamnesis_import" @change="handleAnamnesisImport" class="hidden" accept=".json"> <button @click="triggerAnamnesisImport" type="button" class="w-full sm:w-auto flex-1 px-4 py-2 bg-gray-600 text-white text-sm rounded-md hover:bg-gray-700">Importar (JSON)</button> </div>
            </div>

            <div v-show="activeProfileTab === 'receipt_templates'">
                <h2 class="text-xl font-semibold border-b pb-2 mb-4">Gerenciar Modelos de Recibo</h2>
                <div class="space-y-3 mb-4">
                    <div v-for="template in userReceiptTemplates" :key="template.id" class="bg-gray-50 p-3 rounded-md">
                        <div class="flex justify-between items-center">
                            <span class="font-semibold">
                                {{ template.title }}
                                <span v-if="template.is_global" class="text-xs text-blue-600">(Global)</span>
                                <span v-if="template.is_default" class="ml-2 text-xs bg-yellow-100 text-yellow-800 px-1.5 py-0.5 rounded-full">Padrão</span>
                            </span>
                            <div>
                                <button type="button" @click.prevent="openUserReceiptModal(template)" :title="template.is_global ? 'Editar (Cria Cópia)' : 'Editar'" class="text-gray-500 hover:text-indigo-600 mr-2"><i class="fa-solid fa-pen"></i></button>
                                <button type="button" v-if="!template.is_global" @click.prevent="deleteUserReceiptTemplate(template.id)" title="Excluir Modelo" class="text-gray-500 hover:text-red-600"><i class="fa-solid fa-trash"></i></button>
                                <span v-else class="text-gray-400 cursor-not-allowed inline-block mr-1" title="Modelos globais não podem ser excluídos"><i class="fa-solid fa-trash"></i></span>
                            </div>
                        </div>
                    </div>
                    <p v-if="userReceiptTemplates.length === 0" class="text-sm text-center text-gray-500 py-4">Nenhum modelo encontrado.</p>
                </div>
                <div class="flex flex-col sm:flex-row gap-2 mt-4 pt-4 border-t">
                    <button @click="openUserReceiptModal(null)" type="button" class="w-full sm:w-auto flex-1 px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">Adicionar Modelo</button>
                </div>
            </div>

            <div v-show="activeProfileTab === 'integrations'">
                <h2 class="text-xl font-semibold mb-4">Integração Google</h2>
                <div class="space-y-4"> 
                    <div><label class="block text-sm font-medium">Google Client ID</label><input type="text" v-model="editingProfile.google_client_id" class="form-input"></div> 
                    <div><label class="block text-sm font-medium">Google Client Secret</label><input type="password" v-model="editingProfile.google_client_secret" class="form-input"></div> 
                    <div class="flex items-center">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" v-model="editingProfile.google_calendar_enabled" :true-value="1" :false-value="0" class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                        </label>
                        <span class="ml-3 font-medium">{{ editingProfile.google_calendar_enabled == 1 ? 'Sincronização Ativa' : 'Sincronização Inativa' }}</span>
                    </div> 
                    <p class="text-xs text-gray-500">Credenciais obtidas no Google Cloud Console.</p> 
                </div>
            </div>

            <div v-show="activeProfileTab === 'prescription_templates'">
                <h2 class="text-xl font-semibold border-b pb-2 mb-4">Modelos de Documentos (Receitas/Atestados)</h2>
                <div class="flex justify-end mb-4">
                    <button type="button" @click="openPrescriptionTemplateModal(null)" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700"><i class="fa-solid fa-plus mr-2"></i>Novo Modelo</button>
                </div>
                <div class="border rounded-lg overflow-hidden max-h-96 overflow-y-auto">
                    <table class="min-w-full bg-white text-sm">
                        <thead class="bg-gray-50 sticky top-0">
                            <tr>
                                <th class="py-2 px-4 text-left font-medium text-gray-500">Título do Modelo</th>
                                <th class="py-2 px-4 text-left font-medium text-gray-500">Tipo</th>
                                <th class="py-2 px-4 text-center font-medium text-gray-500 w-20">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-if="prescriptionTemplates.length === 0"><td colspan="3" class="p-4 text-center text-gray-500">Nenhum modelo cadastrado.</td></tr>
                            <tr v-for="tpl in prescriptionTemplates" :key="tpl.id" class="hover:bg-gray-50">
                                <td class="py-2 px-4 font-medium">
                                    {{ tpl.title }}
                                    <span v-if="tpl.is_global" class="text-xs text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded ml-1">Global</span>
                                </td>
                                <td class="py-2 px-4 text-gray-600 capitalize">{{ tpl.type }}</td>
                                <td class="py-2 px-4 text-center">
                                    <button type="button" @click.prevent="openPrescriptionTemplateModal(tpl)" class="text-indigo-600 hover:text-indigo-900 mr-2" :title="tpl.is_global ? 'Ver (Não Editável)' : 'Editar'"><i class="fa-solid fa-pen"></i></button>
                                    <button type="button" @click.prevent="deletePrescriptionTemplate(tpl.id)" :disabled="tpl.is_global" :class="{'opacity-30 cursor-not-allowed': tpl.is_global}" class="text-red-600 hover:text-red-900" title="Excluir"><i class="fa-solid fa-trash"></i></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="mt-4 p-3 bg-blue-50 border border-blue-100 rounded text-xs text-blue-800">
                    <strong>Variáveis Disponíveis:</strong>
                    [PACIENTE_NOME], [CPF], [DATA_NASC], [IDADE], [PESO], [ALTURA], [ENDERECO], [DR_NOME], [DR_REGISTRO], [DATA_HOJE]
                </div>
            </div>

            <div v-show="activeProfileTab === 'medicines'">
                <h2 class="text-xl font-semibold border-b pb-2 mb-4">Catálogo de Medicamentos</h2>
                <div class="flex justify-between mb-4">
                    <input type="text" placeholder="Buscar medicamento..." @input="fetchMedicines($event.target.value)" class="form-input max-w-xs text-sm">
                    <button type="button" @click="openMedicineModal(null)" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700"><i class="fa-solid fa-plus mr-2"></i>Adicionar</button>
                </div>
                <div class="border rounded-lg overflow-hidden max-h-96 overflow-y-auto">
                    <table class="min-w-full bg-white text-sm">
                        <thead class="bg-gray-50 sticky top-0">
                            <tr>
                                <th class="py-2 px-4 text-left font-medium text-gray-500">Nome</th>
                                <th class="py-2 px-4 text-left font-medium text-gray-500">Posologia Padrão</th>
                                <th class="py-2 px-4 text-center font-medium text-gray-500 w-20">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-if="medicines.length === 0"><td colspan="3" class="p-4 text-center text-gray-500">Nenhum medicamento cadastrado.</td></tr>
                            <tr v-for="med in medicines" :key="med.id" class="hover:bg-gray-50">
                                <td class="py-2 px-4 font-medium">
                                    {{ med.name }}
                                    <span v-if="med.is_global" class="text-xs text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded ml-1">Global</span>
                                </td>
                                <td class="py-2 px-4 text-gray-600 truncate max-w-xs" :title="med.instructions">{{ med.instructions }}</td>
                                <td class="py-2 px-4 text-center">
                                    <button type="button" @click.prevent="openMedicineModal(med)" class="text-indigo-600 hover:text-indigo-900 mr-2" :title="med.is_global ? 'Ver (Não Editável)' : 'Editar'"><i class="fa-solid fa-pen"></i></button>
                                    <button type="button" @click.prevent="deleteMedicine(med.id)" :disabled="med.is_global" :class="{'opacity-30 cursor-not-allowed': med.is_global}" class="text-red-600 hover:text-red-900" title="Excluir"><i class="fa-solid fa-trash"></i></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-show="activeProfileTab === 'exams_list'">
                <h2 class="text-xl font-semibold border-b pb-2 mb-4">Catálogo de Exames</h2>
                <div class="flex justify-between mb-4">
                    <input type="text" placeholder="Buscar exame..." @input="fetchExams($event.target.value)" class="form-input max-w-xs text-sm">
                    <button type="button" @click="openExamModal(null)" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700"><i class="fa-solid fa-plus mr-2"></i>Adicionar</button>
                </div>
                <div class="border rounded-lg overflow-hidden max-h-96 overflow-y-auto">
                    <table class="min-w-full bg-white text-sm">
                        <thead class="bg-gray-50 sticky top-0">
                            <tr>
                                <th class="py-2 px-4 text-left font-medium text-gray-500">Nome do Exame</th>
                                <th class="py-2 px-4 text-left font-medium text-gray-500">Descrição/Justificativa</th>
                                <th class="py-2 px-4 text-center font-medium text-gray-500 w-20">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-if="exams.length === 0"><td colspan="3" class="p-4 text-center text-gray-500">Nenhum exame cadastrado.</td></tr>
                            <tr v-for="exam in exams" :key="exam.id" class="hover:bg-gray-50">
                                <td class="py-2 px-4 font-medium">
                                    {{ exam.name }}
                                    <span v-if="exam.is_global" class="text-xs text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded ml-1">Global</span>
                                </td>
                                <td class="py-2 px-4 text-gray-600 truncate max-w-xs" :title="exam.description">{{ exam.description }}</td>
                                <td class="py-2 px-4 text-center">
                                    <button type="button" @click.prevent="openExamModal(exam)" class="text-indigo-600 hover:text-indigo-900 mr-2" :title="exam.is_global ? 'Ver (Não Editável)' : 'Editar'"><i class="fa-solid fa-pen"></i></button>
                                    <button type="button" @click.prevent="deleteExam(exam.id)" :disabled="exam.is_global" :class="{'opacity-30 cursor-not-allowed': exam.is_global}" class="text-red-600 hover:text-red-900" title="Excluir"><i class="fa-solid fa-trash"></i></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex justify-end items-center gap-4 mt-6 pt-4 border-t">
                <button type="button" @click="saveProfile" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 font-semibold shadow-sm transition-colors">Salvar Alterações</button>
            </div>

        </form>
    </div>

    <div id="user-anamnesis-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden justify-center p-4 z-50 overflow-y-auto">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl p-6 my-8">
        <button @click="hideModal('user-anamnesis-modal')" type="button" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark fa-lg"></i></button>
            <h2 class="text-xl font-bold mb-4">{{ editingUserAnamnesis.id ? 'Editar Meu Modelo' : 'Novo Modelo de Anamnese' }}</h2>
            <p v-if="editingUserAnamnesis.originalIsGlobal" class="text-xs text-blue-600 mb-4 bg-blue-50 p-2 rounded border border-blue-200">Nota: Você está editando uma cópia de um modelo global. Salvar criará um novo modelo pessoal.</p>
            <form @submit.prevent="saveUserAnamnesisTemplate">
                <div class="mb-4">
                    <label class="block text-sm font-medium">Título do Modelo *</label>
                    <input type="text" v-model="editingUserAnamnesis.title" required class="form-input">
                </div>
                <div>
                    <label class="block text-sm font-medium">Conteúdo da Anamnese</label>
                    <textarea v-model="editingUserAnamnesis.content" rows="15" class="form-input"></textarea>
                </div>
                <div class="flex justify-end gap-4 mt-6">
                    <button type="button" @click="hideModal('user-anamnesis-modal')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancelar</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Salvar Modelo</button>
                </div>
            </form>
        </div>
    </div>
    
    <div id="user-receipt-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden justify-center p-4 z-50 overflow-y-auto">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl p-6 my-8">
            <button @click="hideModal('user-receipt-modal')" type="button" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark fa-lg"></i></button>
            <h2 class="text-xl font-bold mb-4">{{ editingUserReceipt.id ? 'Editar Meu Modelo de Recibo' : 'Novo Modelo de Recibo' }}</h2>
            <p v-if="editingUserReceipt.originalIsGlobal" class="text-xs text-blue-600 mb-4 bg-blue-50 p-2 rounded border border-blue-200">Nota: Você está editando uma cópia de um modelo global. Salvar criará um novo modelo pessoal.</p>
            <form @submit.prevent="saveUserReceiptTemplate">
                <div class="mb-4">
                    <label class="block text-sm font-medium">Título do Modelo *</label>
                    <input type="text" v-model="editingUserReceipt.title" required class="form-input">
                </div>
                <div class="mb-4">
                    <label class="flex items-center">
                        <input type="checkbox" v-model="editingUserReceipt.is_default" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span class="ml-2 text-gray-700">Definir como meu modelo padrão</span>
                    </label>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium">Conteúdo do Recibo</label>
                    <div class="p-2 bg-gray-50 border rounded-md mb-2 text-xs text-gray-600">
                        <strong>Variáveis disponíveis:</strong>
                        [PACIENTE], [CPF], [VALOR], [VALOR_EXTENSO], [DATA], [RECIBO_NUMERO], [DESCRICAO],
                        [USUARIO_NOME], [USUARIO_PROFISSAO], [USUARIO_CPF], [CIDADE], [DATA_GERACAO], [USUARIO_REGISTRO]
                    </div>
                    <textarea v-model="editingUserReceipt.content" rows="15" class="form-input"></textarea>
                </div>
                <div class="flex justify-end gap-4 mt-6">
                    <button type="button" @click="hideModal('user-receipt-modal')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancelar</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Salvar Modelo</button>
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
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Nome do Medicamento *</label>
                        <input type="text" v-model="editingMedicine.name" required class="form-input" placeholder="Ex: Amoxicilina 500mg">
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
                </div>
                <div class="flex justify-end gap-4 mt-6 pt-4 border-t">
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
                <div class="flex justify-end gap-4 mt-6 pt-4 border-t">
                    <button type="button" @click="hideModal('prescription-template-modal')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancelar</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Salvar Modelo</button>
                </div>
            </form>
        </div>
    </div>
    
    <div id="price-list-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden justify-center p-4 z-50 modal-overlay overflow-y-auto">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6 my-8">
            <button @click="hideModal('price-list-modal')" type="button" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark fa-lg"></i></button>
            <h2 class="text-xl font-bold mb-4">{{ editingPriceList.id ? 'Editar Tabela' : 'Nova Tabela de Preços' }}</h2>
            <form @submit.prevent="savePriceList">
                <div>
                    <label class="block text-sm font-medium">Nome da Tabela *</label>
                    <input type="text" v-model="editingPriceList.name" required class="form-input">
                    <p v-if="editingPriceList.id && editingPriceList.originalIsGlobal" class="text-xs text-blue-600 mt-1">Nota: Editar uma tabela global criará uma cópia pessoal para você.</p>
                </div>
                <div class="flex justify-end gap-4 mt-6">
                    <button type="button" @click="hideModal('price-list-modal')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancelar</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <div id="admin-manage-items-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden justify-center p-4 z-40 modal-overlay overflow-y-auto">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-3xl p-6 my-8">
    <button @click="hideModal('admin-manage-items-modal'); activePriceListForItems = null" type="button" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark fa-lg"></i></button>
                <div v-if="activePriceListForItems">
                    <h2 class="text-2xl font-bold mb-2">Itens da Tabela: {{ activePriceListForItems.name }}</h2>
                    <p class="text-sm text-blue-600 mb-6" v-if="activePriceListForItems.is_global">Esta é uma Tabela Global.</p>
                </div>
                <div class="flex justify-end mb-4">
                    <button @click="openPriceItemModal(null)" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm" :disabled="activePriceListForItems && activePriceListForItems.is_global" :class="{'opacity-50 cursor-not-allowed': activePriceListForItems && activePriceListForItems.is_global}" title="Você só pode adicionar itens às suas próprias tabelas."><i class="fa-solid fa-plus"></i><span class="hidden sm:inline ml-2">Adicionar Item</span></button>
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
                                    <button @click="openPriceItemModal(item)" class="text-indigo-600 hover:text-indigo-900 mr-3" :disabled="activePriceListForItems && activePriceListForItems.is_global" :class="{'opacity-50 cursor-not-allowed': activePriceListForItems && activePriceListForItems.is_global}" title="Você só pode editar itens das suas próprias tabelas."><i class="fa-solid fa-pen-to-square"></i></button>
                                    <button @click="deletePriceItem(item.id)" class="text-red-600 hover:text-red-900" :disabled="activePriceListForItems && activePriceListForItems.is_global" :class="{'opacity-50 cursor-not-allowed': activePriceListForItems && activePriceListForItems.is_global}" title="Você só pode excluir itens de suas próprias tabelas."><i class="fa-solid fa-trash-can"></i></button>
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
    
    <div id="price-item-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden justify-center p-4 z-50 modal-overlay overflow-y-auto">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6 my-8">
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

    <div id="user-payment-method-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden justify-center p-4 z-50 overflow-y-auto">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6 my-8">
        <button @click="hideModal('user-payment-method-modal')" type="button" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark fa-lg"></i></button>
            <h2 class="text-xl font-bold mb-4">{{ editingUserPaymentMethod.id ? 'Editar Método' : 'Novo Método Pessoal' }}</h2>
            <p v-if="editingUserPaymentMethod.originalIsGlobal" class="text-xs text-blue-600 mb-4 bg-blue-50 p-2 rounded border border-blue-200">Nota: Você está editando uma cópia de um método global. Salvar criará um novo método pessoal.</p>
            <form @submit.prevent="saveUserPaymentMethod">
                <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium">Nome da Forma de Pagamento *</label>
                    <input type="text" v-model="editingUserPaymentMethod.option_value" required class="form-input">
                </div>
                </div>
                <div class="flex justify-end gap-4 mt-6 pt-4 border-t">
                    <button type="button" @click="hideModal('user-payment-method-modal')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancelar</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Salvar Método</button>
                </div>
            </form>
        </div>
    </div>
</div>

















                  
<div v-if="activeView === 'financeiro_livrocaixa'" v-show="currentUser.finance_enabled == 1 && currentUser.finance_ledger_enabled == 1">
    <div class="bg-white p-4 sm:p-6 rounded-lg shadow">
        <h1 class="text-2xl sm:text-1xl font-bold mb-2 border-b pb-2">Livro Caixa</h1>

        <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4">
            <div class="flex items-center gap-2">
                <label for="ledger-month" class="text-sm font-medium">Mês:</label>
                <select id="ledger-month" v-model.number="ledgerFilters.month" @change="fetchLedgerEntries" class="form-input py-1 px-2 text-sm">
                    <option v-for="m in 12" :key="m" :value="m">{{ String(m).padStart(2, '0') }}</option>
                </select>
                <label for="ledger-year" class="text-sm font-medium ml-2">Ano:</label>
                <input type="number" id="ledger-year" v-model.number="ledgerFilters.year" @change="fetchLedgerEntries" class="form-input py-1 px-2 text-sm w-24">
                <button @click="fetchLedgerEntries" class="p-1 text-gray-500 hover:text-blue-600" title="Atualizar"><i class="fa-solid fa-sync"></i></button>
            </div>
            <div class="flex gap-2">
                <button @click="openLedgerEntryModal('saida')" class="px-3 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 text-sm"><i class="fa-solid fa-arrow-down mr-1 sm:mr-2"></i><span class="hidden sm:inline">Nova Saída</span></button>
                <button @click="openLedgerEntryModal('entrada')" class="px-3 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm"><i class="fa-solid fa-arrow-up mr-1 sm:mr-2"></i><span class="hidden sm:inline">Nova Entrada</span></button>
                <button @click="exportLedgerToXLS" class="px-3 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 text-sm"><i class="fa-solid fa-file-excel mr-1 sm:mr-2"></i><span class="hidden sm:inline">Exportar (Filtros Atuais)</span></button>
            </div>
        </div>
        
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6 text-center">
            <div class="p-4 bg-gray-100 rounded-lg">
                <dt class="text-sm font-medium text-gray-500">Saldo Anterior</dt>
                <dd class="mt-1 text-xl font-semibold" :class="ledgerPreviousBalance >= 0 ? 'text-gray-900' : 'text-red-600'">{{ formatCurrency(ledgerPreviousBalance) }}</dd>
            </div>
            <div class="p-4 bg-green-50 rounded-lg">
                <dt class="text-sm font-medium text-green-600">Total Entradas (Mês)</dt>
                <dd class="mt-1 text-xl font-semibold text-green-700">{{ formatCurrency(ledgerTotals.entradas) }}</dd>
            </div>
            <div class="p-4 bg-red-50 rounded-lg">
                <dt class="text-sm font-medium text-red-600">Total Saídas (Mês)</dt>
                <dd class="mt-1 text-xl font-semibold text-red-700">{{ formatCurrency(ledgerTotals.saidas) }}</dd>
            </div>
            <div class="p-4 bg-blue-50 rounded-lg">
                <dt class="text-sm font-medium text-blue-600">Saldo Final (Mês)</dt>
                <dd class="mt-1 text-xl font-semibold" :class="ledgerTotals.saldoFinalMes >= 0 ? 'text-blue-700' : 'text-red-600'">{{ formatCurrency(ledgerTotals.saldoFinalMes) }}</dd>
            </div>
            </div>


        <div class="overflow-x-auto border rounded-md">
            <table class="min-w-full bg-white text-sm">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="py-2 px-3 text-left font-medium text-gray-600 uppercase w-20">Nº Ordem</th>
                        <th class="py-2 px-3 text-left font-medium text-gray-600 uppercase w-28">Data</th>
                        <th class="py-2 px-3 text-left font-medium text-gray-600 uppercase w-24">Recibo/NFe</th>
                        <th class="py-2 px-3 text-left font-medium text-gray-600 uppercase w-48">{{ labels.patient }}</th>
                        <th class="py-2 px-3 text-left font-medium text-gray-600 uppercase">Descrição</th>
                        <th class="py-2 px-3 text-right font-medium text-gray-600 uppercase w-28">Entrada (R$)</th>
                        <th class="py-2 px-3 text-right font-medium text-gray-600 uppercase w-28">Saída (R$)</th>
                        <th class="py-2 px-3 text-right font-medium text-gray-600 uppercase w-32">Saldo (R$)</th>
                        <th class="py-2 px-3 text-center font-medium text-gray-600 uppercase w-16">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr class="bg-gray-100 font-semibold">
                        <td colspan="7" class="py-2 px-3 text-right">Saldo Anterior:</td>
                        <td class="py-2 px-3 text-right">{{ formatCurrency(ledgerPreviousBalance) }}</td>
                        <td></td>
                    </tr>
                    <tr v-if="ledgerEntries.length === 0">
                        <td colspan="9" class="text-center py-6 text-gray-500">Nenhum lançamento encontrado para este período.</td>
                    </tr>
                    <tr v-else v-for="entry in ledgerEntries" :key="entry.id" class="hover:bg-gray-50">
                        <td class="py-2 px-3 whitespace-nowrap">{{ entry.entry_order }}</td>
                        <td class="py-2 px-3 whitespace-nowrap">{{ formatDateForDisabledList(entry.entry_date) }}</td>
                        <td class="py-2 px-3 whitespace-nowrap">{{ entry.receipt_nfe }}</td>
                        <td class="py-2 px-3 whitespace-nowrap">
                            <a v-if="entry.patient_id" href="#" @click.prevent="openPatientQuickView(entry.patient_id)" class="clickable-patient-name" :title="`Ver dados de ${getPatientName(entry.patient_id)}`">
                                {{ getPatientName(entry.patient_id) }}
                            </a>
                            <span v-else>---</span>
                        </td>
                        <td class="py-2 px-3">{{ entry.description }}</td>
                        <td class="py-2 px-3 text-right text-blue-600">{{ entry.entry_type === 'entrada' ? formatCurrency(entry.amount) : '' }}</td>
                        <td class="py-2 px-3 text-right text-red-600">{{ entry.entry_type === 'saida' ? formatCurrency(entry.amount) : '' }}</td>
                        <td class="py-2 px-3 text-right font-medium" :class="entry.running_balance >= 0 ? 'text-gray-800' : 'text-red-700'">{{ formatCurrency(entry.running_balance) }}</td>
                        <td class="py-2 px-3 text-center">
                            
                            <button @click="openLedgerEntryModal(entry.entry_type, entry)" 
                                    class="text-indigo-600 hover:text-indigo-800 mr-2"
                                    :title="entry.forecast_entry_id ? 'Editar Lançamento (Automático)' : 'Editar Lançamento (Manual)'">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            
                            <button @click="deleteLedgerEntry(entry.id)" class="text-red-600 hover:text-red-800" title="Excluir"><i class="fa-solid fa-trash"></i></button>
                        </td>
                    </tr>
                    <tr class="bg-gray-100 font-semibold border-t-2">
                        <td colspan="7" class="py-2 px-3 text-right">Saldo Final:</td>
                        <td class="py-2 px-3 text-right">{{ formatCurrency(ledgerTotals.saldoFinalTabela) }}</td>
                        <td></td>
                    </tr>
                    </tbody>
            </table>
        </div>
    </div>
</div>



<div v-if="activeView === 'financeiro_previsao'" v-show="currentUser.finance_enabled == 1 && currentUser.finance_forecast_enabled == 1">
     <div class="bg-white p-4 sm:p-6 rounded-lg shadow">
        <h1 class="text-2xl sm:text-1xl font-bold mb-2 border-b pb-2">Previsão de Receitas / Despesas</h1>

       
        <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4">
            <div class="flex items-center gap-2">
                <label for="forecast-month" class="text-sm font-medium">Mês:</label>
                <select id="forecast-month" v-model.number="forecastFilters.month" @change="fetchForecastEntries" class="form-input py-1 px-2 text-sm">
                    <option v-for="m in 12" :key="m" :value="m">{{ String(m).padStart(2, '0') }}</option>
                </select>
                <label for="forecast-year" class="text-sm font-medium ml-2">Ano:</label>
                <input type="number" id="forecast-year" v-model.number="forecastFilters.year" @change="fetchForecastEntries" class="form-input py-1 px-2 text-sm w-24">
                <button @click="fetchForecastEntries" class="p-1 text-gray-500 hover:text-blue-600 ml-2" title="Atualizar"><i class="fa-solid fa-sync"></i></button>
            </div>
            <div class="flex gap-2">
                <button @click="openManualForecastModal('despesa')" class="px-3 py-2 bg-orange-600 text-white rounded-md hover:bg-orange-700 text-sm"><i class="fa-solid fa-arrow-down mr-1 sm:mr-2"></i><span class="hidden sm:inline">Incluir Despesa</span></button>
                <button @click="openManualForecastModal('receita')" class="px-3 py-2 bg-teal-600 text-white rounded-md hover:bg-teal-700 text-sm"><i class="fa-solid fa-arrow-up mr-1 sm:mr-2"></i><span class="hidden sm:inline">Incluir Receita</span></button>
                <button @click="exportForecastToXLS" class="px-3 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 text-sm"><i class="fa-solid fa-file-excel mr-1 sm:mr-2"></i><span class="hidden sm:inline">Exportar (Filtros Atuais)</span></button>
            </div>
        </div>
        <div class="flex justify-start mb-6">
            <div class="flex items-center gap-2">
                <label for="forecast-status" class="text-sm font-medium">Status:</label>
                 <select id="forecast-status" v-model="forecastFilters.status" class="form-input py-1 px-2 text-sm">
                     <option value="all">Todos</option>
                     <template v-for="opt in getOptionsByType('payment_status')" :key="opt.id">
                        <option v-if="opt.option_value !== 'Pago(Parcial)'" :value="opt.option_value" class="status-uppercase">
                            {{ opt.option_value }}
                        </option>
                     </template>
                     <option v-if="!getOptionsByType('payment_status').length" disabled>Carregando...</option>
                 </select>
            </div>
        </div>
       
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6 text-center">
            <div class="p-4 bg-teal-50 rounded-lg">
                <dt class="text-sm font-medium text-teal-600">Total Receitas (Previsto)</dt>
                <dd class="mt-1 text-xl font-semibold text-teal-700">{{ formatCurrency(forecastHeaderTotals.receitasPrevisto) }}</dd>
                <dt class="text-xs font-medium text-gray-500 mt-1">(Realizado: {{ formatCurrency(forecastHeaderTotals.receitasRealizado) }})</dt>
            </div>
            <div class="p-4 bg-orange-50 rounded-lg">
                <dt class="text-sm font-medium text-orange-600">Total Despesas (Previsto)</dt>
                <dd class="mt-1 text-xl font-semibold text-orange-700">{{ formatCurrency(forecastHeaderTotals.despesasPrevisto) }}</dd>
                <dt class="text-xs font-medium text-gray-500 mt-1">(Realizado: {{ formatCurrency(forecastHeaderTotals.despesasRealizado) }})</dt>
            </div>
            <div class="p-4 bg-blue-50 rounded-lg">
                <dt class="text-sm font-medium text-blue-600">Saldo Previsto (Mês)</dt>
                <dd class="mt-1 text-xl font-semibold" :class="forecastHeaderTotals.saldoPrevisto >= 0 ? 'text-blue-700' : 'text-red-600'">{{ formatCurrency(forecastHeaderTotals.saldoPrevisto) }}</dd>
                <dt class="text-xs font-medium text-gray-500 mt-1">(Realizado: {{ formatCurrency(forecastHeaderTotals.saldoRealizado) }})</dt>
            </div>
        </div>


        <div class="overflow-x-auto border rounded-md">
            <table class="min-w-full bg-white text-sm">
                 <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="py-2 px-3 text-left font-medium text-gray-600 uppercase w-28">Data Prev.</th>
                        <th class="py-2 px-3 text-left font-medium text-gray-600 uppercase w-24">Orçam. #</th>
                        <th class="py-2 px-3 text-left font-medium text-gray-600 uppercase">{{ labels.patient }} / Origem</th>
                        <th class="py-2 px-3 text-left font-medium text-gray-600 uppercase">Descrição</th>
                        <th class="py-2 px-3 text-right font-medium text-gray-600 uppercase w-28">Valor Prev.</th>
                        <th class="py-2 px-3 text-right font-medium text-gray-600 uppercase w-28">Valor Pago</th>
                        <th class="py-2 px-3 text-center font-medium text-gray-600 uppercase w-32">Status Pag.</th>
                        <th class="py-2 px-3 text-center font-medium text-gray-600 uppercase w-20">Ações</th>
                    </tr>
                 </thead>
                 <tbody class="divide-y divide-gray-200">
                    <tr v-if="forecastEntries.length === 0">
                        <td colspan="8" class="text-center py-6 text-gray-500">Nenhuma previsão encontrada com os filtros aplicados.</td>
                    </tr>
                    <tr v-else v-for="entry in forecastEntries" :key="entry.id" class="hover:bg-gray-50" 
                        :class="{
                            'font-semibold': entry.payment_status === getDefaultOptionValue('payment_status', 'Em Aberto') || entry.payment_status === getDefaultOptionValue('payment_status', 'Pago(Parcial)'),
                            'opacity-60': entry.payment_status === getDefaultOptionValue('payment_status', 'Pago(Total)')
                        }">
                        <td class="py-2 px-3 whitespace-nowrap">{{ formatDateForDisabledList(entry.entry_date) }}</td>
                        <td class="py-2 px-3 whitespace-nowrap">{{ entry.budget_id || 'Manual' }}</td>
                        <td class="py-2 px-3 whitespace-nowrap">
                            <a v-if="entry.patient_id" href="#" @click.prevent="openPatientQuickView(entry.patient_id)" class="clickable-patient-name" :title="`Ver dados de ${entry.patient_name}`">
                                {{ entry.patient_name }}
                            </a>
                            <span v-else>{{ (entry.forecast_type === 'despesa' ? 'Despesa Manual' : '---') }}</span>
                        </td>
                        <td class="py-2 px-3">{{ entry.description }}</td>
                        <td class="py-2 px-3 text-right" :class="entry.forecast_type === 'receita' ? 'text-blue-600' : 'text-orange-600'">{{ formatCurrency(entry.installment_value) }}</td>
                        <td class="py-2 px-3 text-right font-medium">{{ formatCurrency(entry.received_value) }}</td>
                        <td class="py-2 px-3 text-center">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full status-uppercase" :class="getPaymentStatusClass(entry.payment_status)">
                                {{ entry.payment_status }}
                            </span>
                        </td>
                        <td class="py-2 px-3 text-center">
                             <button v-if="entry.payment_status !== getDefaultOptionValue('payment_status', 'Pago(Total)')" @click="openMarkAsPaidModal(entry)" class="text-green-600 hover:text-green-800 mr-2" title="Marcar como Recebido/Pago"><i class="fa-solid fa-check-circle"></i></button>
                             
                             <button @click="openManualForecastModal(entry.forecast_type, entry)" 
                                     class="text-indigo-600 hover:text-indigo-800 mr-2" 
                                     :class="{'opacity-50 cursor-not-allowed': entry.budget_id}" 
                                     :disabled="entry.budget_id" 
                                     title="Editar Lançamento Manual (Desabilitado para lançamentos de orçamento)">
                                 <i class="fa-solid fa-pen"></i>
                             </button>
                             <button @click="deleteForecastEntry(entry.id, entry.budget_id)" 
                                     class="text-red-600 hover:text-red-800" 
                                     :class="{'opacity-50 cursor-not-allowed': entry.budget_id}" 
                                     :disabled="entry.budget_id" 
                                     title="Excluir Lançamento Manual (Desabilitado para lançamentos de orçamento)">
                                 <i class="fa-solid fa-trash"></i>
                             </button>
                        </td>
                    </tr>
                 </tbody>
            </table>
        </div>
    </div>
</div>


<div v-if="activeView === 'financeiro_recibos'" v-show="currentUser.finance_enabled == 1 && currentUser.finance_ledger_enabled == 1">
    <div class="bg-white p-4 sm:p-6 rounded-lg shadow">
        <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-2 border-b pb-2">
            <h1 class="text-2xl sm:text-1xl font-bold">Gerador de Recibos</h1>
        </div>

        <div class="flex flex-col lg:flex-row gap-6">
            
            <div class="flex-1 lg:w-1/2">
                <h2 class="text-xl font-semibold mb-4 text-orange-600">Lançamentos Pendentes de Recibo</h2>
                
                <div class="mb-4">
                    <input type="text" v-model="receiptSearchPending" @keyup="debouncedFetchPendingReceipts" placeholder="Buscar em pendentes por nome ou descrição..." class="form-input text-sm">
                </div>
                <div class="flex justify-between items-center mb-2" v-if="pendingReceipts.total > 0">
                    <span class="text-xs text-gray-600">Exibindo {{ receiptPaginationPending.currentPage * 10 - 9 }} - {{ Math.min(receiptPaginationPending.currentPage * 10, pendingReceipts.total) }} de {{ pendingReceipts.total }}</span>
                    <button @click="promptDiscardPendingReceipts" :disabled="selectedPendingReceipts.length === 0" class="px-3 py-1 bg-yellow-500 text-white text-xs rounded-md hover:bg-yellow-600 disabled:opacity-50">
                        <i class="fa-solid fa-trash"></i> Descartar ({{ selectedPendingReceipts.length }})
                    </button>
                </div>

                <div class="border rounded-md min-h-[60vh] overflow-y-auto">
                    <div v-if="pendingReceipts.entries.length === 0" class="p-6 text-center text-gray-500">
                        {{ receiptSearchPending ? 'Nenhum lançamento encontrado.' : 'Nenhum lançamento pendente.' }}
                    </div>
                    <ul v-else class="divide-y divide-gray-200">
                        <li v-for="entry in pendingReceipts.entries" :key="entry.id" class="p-3 hover:bg-gray-50 flex items-center gap-3">
                            <input type="checkbox" :value="entry.id" v-model="selectedPendingReceipts" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <div class="flex-1">
                                <p class="font-semibold">
                                    <a v-if="entry.patient_id" href="#" @click.prevent="openPatientQuickView(entry.patient_id)" class="clickable-patient-name" :title="`Ver dados de ${entry.patient_name}`">
                                        {{ entry.patient_name }}
                                    </a>
                                    </p>
                                <p class="text-sm text-gray-600">{{ entry.description }}</p>
                                <p class="text-sm text-gray-500">{{ formatDateForDisabledList(entry.entry_date) }} - <span class="font-medium text-green-700">{{ formatCurrency(entry.amount) }}</span></p>
                            </div>
                            <button @click="openReceiptGeneratorModal(entry)" class="px-3 py-1 bg-green-600 text-white text-sm rounded-md hover:bg-green-700 flex-shrink-0">Gerar</button>
                        </li>
                    </ul>
                </div>
                <div v-if="pendingReceipts.totalPages > 1" class="flex justify-between items-center mt-4">
                    <button @click="receipt_prevPage('pending')" :disabled="receiptPaginationPending.currentPage === 1" class="px-3 py-1 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 disabled:opacity-50 text-sm">
                        Anterior
                    </button>
                    <span class="text-sm font-medium text-gray-700">
                        Página {{ receiptPaginationPending.currentPage }} de {{ pendingReceipts.totalPages }}
                    </span>
                    <button @click="receipt_nextPage('pending')" :disabled="receiptPaginationPending.currentPage === pendingReceipts.totalPages" class="px-3 py-1 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 disabled:opacity-50 text-sm">
                        Próxima
                    </button>
                </div>
            </div>

            <div class="flex-1 lg:w-1/2">
                <h2 class="text-xl font-semibold mb-4 text-blue-600">Recibos Gerados</h2>
                
                <div class="mb-4">
                    <input type="text" v-model="receiptSearchGenerated" @keyup="debouncedFetchGeneratedReceipts" placeholder="Buscar em gerados por nome, nº ou descrição..." class="form-input text-sm">
                </div>
                <div class="flex justify-end items-center gap-2 mb-2" v-if="generatedReceipts.total > 0">
                    <span class="text-xs text-gray-600 mr-auto">Exibindo {{ receiptPaginationGenerated.currentPage * 10 - 9 }} - {{ Math.min(receiptPaginationGenerated.currentPage * 10, generatedReceipts.total) }} de {{ generatedReceipts.total }}</span>
                    <button @click="downloadReceiptAsPDF" class="px-3 py-1 bg-gray-600 text-white text-xs rounded-md hover:bg-gray-700" title="Como salvar em PDF">
                        <i class="fa-solid fa-file-pdf"></i>
                    </button>
                    <button @click="emailSelectedReceipts()" :disabled="selectedGeneratedReceipts.length === 0" class="px-3 py-1 bg-blue-600 text-white text-xs rounded-md hover:bg-blue-700 disabled:opacity-50">
                        <i class="fa-solid fa-paper-plane"></i> Enviar ({{ selectedGeneratedReceipts.length }})
                    </button>
                    <button @click="promptCancelGeneratedReceipts" :disabled="selectedGeneratedReceipts.length === 0" class="px-3 py-1 bg-red-600 text-white text-xs rounded-md hover:bg-red-700 disabled:opacity-50">
                        <i class="fa-solid fa-times"></i> Cancelar ({{ selectedGeneratedReceipts.length }})
                    </button>
                </div>
                
                <div class="border rounded-md min-h-[60vh] overflow-y-auto">
                    <div v-if="generatedReceipts.entries.length === 0" class="p-6 text-center text-gray-500">
                        {{ receiptSearchGenerated ? 'Nenhum recibo encontrado.' : 'Nenhum recibo gerado.' }}
                    </div>
                    <ul v-else class="divide-y divide-gray-200">
                        <li v-for="receipt in generatedReceipts.entries" :key="receipt.id" class="p-3 hover:bg-gray-50 flex items-center gap-3">
                            <input type="checkbox" :value="receipt.id" v-model="selectedGeneratedReceipts" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <div class="flex-1">
                                <p class="font-semibold">
                                    <a v-if="receipt.patient_id" href="#" @click.prevent="openPatientQuickView(receipt.patient_id)" class="clickable-patient-name" :title="`Ver dados de ${receipt.patient_name}`">
                                        {{ receipt.patient_name }}
                                    </a>
                                    </p>
                                <p class="text-sm text-gray-600">Recibo Nº: <span class="font-medium text-gray-800">{{ receipt.receipt_nfe }}</span></p>
                                <p class="text-sm text-gray-500">{{ formatDateForDisabledList(receipt.entry_date) }} - <span class="font-medium text-green-700">{{ formatCurrency(receipt.amount) }}</span></p>
                            </div>
                            <button @click="reprintReceipt(receipt)" class="px-3 py-1 bg-gray-600 text-white text-sm rounded-md hover:bg-gray-700 flex-shrink-0" title="Reimprimir/Baixar"><i class="fa-solid fa-print"></i></button>
                        </li>
                    </ul>
                </div>
                <div v-if="generatedReceipts.totalPages > 1" class="flex justify-between items-center mt-4">
                    <button @click="receipt_prevPage('generated')" :disabled="receiptPaginationGenerated.currentPage === 1" class="px-3 py-1 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 disabled:opacity-50 text-sm">
                        Anterior
                    </button>
                    <span class="text-sm font-medium text-gray-700">
                        Página {{ receiptPaginationGenerated.currentPage }} de {{ receiptPaginationGenerated.totalPages }}
                    </span>
                    <button @click="receipt_nextPage('generated')" :disabled="receiptPaginationGenerated.currentPage === generatedReceipts.totalPages" class="px-3 py-1 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 disabled:opacity-50 text-sm">
                        Próxima
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>


<div v-if="activeView === 'hist_atendimentos'">
    <div class="bg-white p-6 rounded-lg shadow">
        <div class="flex flex-col md:flex-row justify-between md:items-center mb-2 gap-2">
            <h1 class="text-2xl sm:text-1xl font-bold">Histórico de Atendimentos</h1>
        </div>


        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6 pb-4 border-b">
            <div>
                <label class="block text-sm font-medium text-gray-700">Filtrar por Status</label>
                <select v-model="serviceStatusFilter" @change="historicalServicesPagination.currentPage = 1" class="form-input mt-1">
                    <option value="all">Todos os Status</option>
                    <option v-for="opt in getOptionsByType('service_status')" :key="opt.id" :value="opt.option_value" class="status-uppercase">
                        {{ opt.option_value }}
                    </option>
                    <option v-if="!getOptionsByType('service_status').length" disabled>Carregando...</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Filtrar por Nome do {{ labels.patient }}</label>
                <input type="text" v-model="serviceNameFilter" @input="historicalServicesPagination.currentPage = 1" class="form-input mt-1" :placeholder="'Digite o nome do ' + labels.patient.toLowerCase() + '...'">
            </div>

            <div class="flex items-end">
                <button @click="exportHistoricalServicesToXLS" class="w-full px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 text-sm"><i class="fa-solid fa-file-excel mr-2"></i>Exportar (Filtros Atuais)</button>
            </div>
        </div>

        <div class="overflow-x-auto min-h-[22rem]">
            <table class="min-w-full bg-white">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase">{{ labels.patient }}</th>
                        <th @click="sortHistoricalServices('start_date')" class="cursor-pointer py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase hover:bg-gray-100">
                            Início <i :class="sortIcon('start_date')" class="ml-1"></i>
                        </th>
                        <th @click="sortHistoricalServices('end_date')" class="cursor-pointer py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase hover:bg-gray-100">
                            Conclusão <i :class="sortIcon('end_date')" class="ml-1"></i>
                        </th>
                        <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase">Descrição</th>
                        <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase">Status Atend.</th>
                        <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-if="paginatedHistoricalServices.length === 0">
                        <td colspan="6" class="text-center py-8 text-gray-500">Nenhum atendimento encontrado com os filtros aplicados.</td>
                    </tr>
                    <tr v-else v-for="service in paginatedHistoricalServices" :key="'hist-'+service.id">
                        <td class="py-4 px-4 whitespace-nowrap font-medium">
                            <span v-if="getPatientFinanceStatus(service.patient_id)" class="financial-alert-dot-inline" title="Pendência Financeira"></span>
                            <a href="#" @click.prevent="openPatientQuickView(service.patient_id)" class="clickable-patient-name" :title="`Ver dados de ${service.patient_name}`">
                                {{ service.patient_name }}
                            </a>
                        </td>
                        <td class="py-4 px-4 whitespace-nowrap text-sm text-gray-600">{{ formatEntryDate(service.start_date) }}</td>
                        <td class="py-4 px-4 whitespace-nowrap text-sm text-gray-600">{{ service.end_date ? formatEntryDate(service.end_date) : '---' }}</td>
                        <td class="py-4 px-4 whitespace-nowrap text-sm text-gray-600 max-w-xs truncate" :title="service.description">{{ service.description }}</td>
                        <td class="py-4 px-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full status-uppercase" :class="getServiceStatusClass(service.service_status)">
                                {{ service.service_status }}
                                
                            </span>
                        </td>
                        <td class="p-4 text-right flex justify-end gap-2">
                                            <!-- BOTÕES DE DOCUMENTOS RETROATIVOS (CORRIGIDOS) -->
                                            <button @click="generateCertificateFromHistory(service, 'atestado')" class="text-blue-600 hover:text-blue-800 p-1 border border-blue-200 rounded hover:bg-blue-50" title="Imprimir Atestado">
                                                <i class="fa-solid fa-user-doctor"></i>
                                            </button>
                                            <button @click="generateCertificateFromHistory(service, 'declaracao')" class="text-indigo-600 hover:text-indigo-800 p-1 border border-indigo-200 rounded hover:bg-indigo-50" title="Imprimir Declaração de Comparecimento">
                                                <i class="fa-solid fa-file-contract"></i>
                                            </button>
                                            
                                            <!-- Botão de Edição -->
                                            <button @click="openEditHistoricalServiceModal(service)" class="text-indigo-600 hover:text-indigo-900" :title="'Editar Atendimento #' + service.id"><i class="fa-solid fa-pen-to-square"></i></button>
                                            </button>
                                        </td>
                            
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="historicalServicesTotalPages > 1" class="flex justify-between items-center mt-6 pt-4 border-t">
            <button @click="historical_prevPage" :disabled="historicalServicesPagination.currentPage === 1" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 disabled:opacity-50 disabled:cursor-not-allowed">
                <i class="fa-solid fa-chevron-left mr-2"></i> Anterior
            </button>
            <span class="text-sm font-medium text-gray-700">
                Página {{ historicalServicesPagination.currentPage }} de {{ historicalServicesTotalPages }}
            </span>
            <button @click="historical_nextPage" :disabled="historicalServicesPagination.currentPage === historicalServicesTotalPages" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 disabled:opacity-50 disabled:cursor-not-allowed">
                Próxima <i class="fa-solid fa-chevron-right ml-2"></i>
            </button>
        </div>
    </div>
</div>

<div v-if="activeView === 'history_documents'">
    <div class="bg-white p-6 rounded-lg shadow">
        <div class="flex flex-col md:flex-row justify-between md:items-center mb-4 gap-2">
            <h1 class="text-2xl font-bold text-gray-800">Histórico Global de Documentos</h1>
        </div>
        
        <div class="mb-4">
            <input type="text" v-model="prescriptionHistoryFilters.search" placeholder="Buscar por paciente ou tipo..." class="form-input max-w-lg">
        </div>

        <div class="overflow-x-auto min-h-[24rem]">
            <table class="min-w-full bg-white">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                        <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase">Paciente</th>
                        <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                        <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase">Resumo</th>
                        <th class="py-3 px-4 text-center text-xs font-medium text-gray-500 uppercase">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-if="globalPrescriptions.length === 0">
                        <td colspan="5" class="text-center py-8 text-gray-500">Nenhum documento encontrado.</td>
                    </tr>
                    <tr v-else v-for="doc in globalPrescriptions" :key="doc.id" class="hover:bg-gray-50">
                        <td class="py-3 px-4 whitespace-nowrap text-sm text-gray-600">{{ formatEntryDate(doc.created_at) }}</td>
                        <td class="py-3 px-4 font-medium text-gray-800">
                            <a href="#" @click.prevent="openPatientQuickView(doc.patient_id)" class="hover:underline text-blue-600">{{ doc.patient_name }}</a>
                        </td>
                        <td class="py-3 px-4 capitalize text-sm text-gray-600">{{ doc.type }}</td>
                        <td class="py-3 px-4 text-sm text-gray-500 truncate max-w-xs" :title="doc.final_content.replace(/<[^>]*>?/gm, '')">
                            {{ doc.final_content.replace(/<[^>]*>?/gm, '') }}
                        </td>
                        <td class="py-3 px-4 text-center whitespace-nowrap">
                            <button @click="viewDocument(doc)" class="text-gray-500 hover:text-blue-600 mr-3" title="Visualizar/Imprimir">
                                <i class="fa-solid fa-print"></i>
                            </button>
                            <button @click="emailDocument(doc.id)" class="text-gray-500 hover:text-purple-600" title="Enviar por E-mail">
                                <i class="fa-solid fa-envelope"></i>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="prescriptionHistoryTotalPages > 1" class="flex justify-between items-center mt-4 pt-4 border-t">
            <button @click="prescriptionHistoryPagination.currentPage--; fetchGlobalPrescriptions()" :disabled="prescriptionHistoryPagination.currentPage === 1" class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300 disabled:opacity-50">Anterior</button>
            <span class="text-sm text-gray-600">Página {{ prescriptionHistoryPagination.currentPage }} de {{ prescriptionHistoryTotalPages }}</span>
            <button @click="prescriptionHistoryPagination.currentPage++; fetchGlobalPrescriptions()" :disabled="prescriptionHistoryPagination.currentPage === prescriptionHistoryTotalPages" class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300 disabled:opacity-50">Próxima</button>
        </div>
    </div>
</div>

<div v-if="activeView === 'history_appointments'">
    <div class="bg-white p-6 rounded-lg shadow">
        <h1 class="text-2xl font-bold text-gray-800 mb-4">Histórico Global de Agendamentos</h1>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <input type="text" v-model="appointmentHistoryFilters.search" placeholder="Buscar por paciente ou título..." class="form-input">
            
            <select v-model="appointmentHistoryFilters.status" class="form-input">
                <option value="">Todos os Status</option>
                <option value="Agendado">AGENDADO</option>
                <option value="Atendido">ATENDIDO</option> <option value="Cancelado">CANCELADO</option>
                <option value="Não Compareceu">NÃO COMPARECEU</option>
            </select>
            
            <div class="flex items-center">
                 <button @click="fetchGlobalAppointments" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 w-full md:w-auto">
                    <i class="fa-solid fa-filter mr-2"></i> Filtrar
                 </button>
            </div>
        </div>

        <div class="overflow-x-auto min-h-[24rem]">
            <table class="min-w-full bg-white">
                <thead class="bg-gray-50">
                    <tr>
                        <th @click="sortGlobalAppointments('start_time')" class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase cursor-pointer hover:bg-gray-100">
                            Data/Hora <i :class="getSortIcon('globalAppointments', 'start_time')" class="ml-1"></i>
                        </th>
                        <th @click="sortGlobalAppointments('patient_name')" class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase cursor-pointer hover:bg-gray-100">
                            Paciente <i :class="getSortIcon('globalAppointments', 'patient_name')" class="ml-1"></i>
                        </th>
                        <th @click="sortGlobalAppointments('title')" class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase cursor-pointer hover:bg-gray-100">
                            Título <i :class="getSortIcon('globalAppointments', 'title')" class="ml-1"></i>
                        </th>
                        <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase">Notas</th>
                        <th @click="sortGlobalAppointments('status')" class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase cursor-pointer hover:bg-gray-100">
                            Status <i :class="getSortIcon('globalAppointments', 'status')" class="ml-1"></i>
                        </th>
                        <th class="py-3 px-4 text-center text-xs font-medium text-gray-500 uppercase">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-if="globalAppointments.length === 0">
                        <td colspan="6" class="text-center py-8 text-gray-500">Nenhum agendamento encontrado.</td>
                    </tr>
                    <tr v-else v-for="appt in globalAppointments" :key="appt.id" class="hover:bg-gray-50">
                        <td class="py-3 px-4 whitespace-nowrap text-sm text-gray-600">
                            {{ formatEntryDate(appt.start_time) }}
                        </td>
                        <td class="py-3 px-4 font-medium text-gray-800">
                            <a href="#" @click.prevent="openPatientQuickView(appt.patient_id)" class="hover:underline text-blue-600">{{ appt.patient_name }}</a>
                        </td>
                        <td class="py-3 px-4 text-sm text-gray-600">{{ appt.title }}</td>
                        <td class="py-3 px-4 text-sm text-gray-500 truncate max-w-xs" :title="appt.notes">{{ appt.notes }}</td>
                        <td class="py-3 px-4">
                            <span class="px-2 py-1 text-xs font-bold rounded-full status-uppercase" 
                                  :class="getAppointmentStatusLabel(appt).class">
                                {{ getAppointmentStatusLabel(appt).label }}
                            </span>
                        </td>
                        <td class="py-3 px-4 text-center whitespace-nowrap">
                            <button v-if="getAppointmentStatusLabel(appt).label === 'AGENDADO'" 
                                    @click.prevent="sendReminderEmail(appt.id, appt.patient_name)" 
                                    class="text-blue-600 hover:text-blue-800 mr-2" 
                                    title="Enviar Confirmação por E-mail">
                                <i class="fa-solid fa-paper-plane"></i>
                            </button>
                            
                            <button v-if="isAppointmentMissed(appt) || appt.status === 'Não Compareceu'" 
                                   @click.prevent="openAppointmentModal(null, null, null, {id: appt.patient_id, name: appt.patient_name})" 
                                   class="text-orange-600 hover:text-orange-800 mr-2" 
                                   title="Novo Agendamento (Reagendar)">
                                <i class="fa-solid fa-calendar-plus"></i>
                            </button>

                            <button @click="openAppointmentModal(appt)" class="text-blue-600 hover:text-blue-800" title="Ver/Editar">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div v-if="appointmentHistoryTotalPages > 1" class="flex justify-between items-center mt-4 pt-4 border-t">
            <button @click="appointment_history_prevPage" :disabled="appointmentHistoryPagination.currentPage === 1" class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300 disabled:opacity-50">Anterior</button>
            <span class="text-sm text-gray-600">Página {{ appointmentHistoryPagination.currentPage }} de {{ appointmentHistoryTotalPages }}</span>
            <button @click="appointment_history_nextPage" :disabled="appointmentHistoryPagination.currentPage === appointmentHistoryTotalPages" class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300 disabled:opacity-50">Próxima</button>
        </div>
    </div>
</div>

<div v-if="activeView === 'history_budgets'">
    <div class="bg-white p-6 rounded-lg shadow">
        <h1 class="text-2xl font-bold text-gray-800 mb-4">Histórico Global de Orçamentos</h1>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <input type="text" v-model="budgetFilters.id" placeholder="Filtrar por Nº" class="form-input">
            <input type="text" v-model="budgetFilters.patientName" placeholder="Filtrar por Paciente" class="form-input">
            <select v-model="budgetFilters.status" class="form-input">
                <option value="">Todos os Status</option>
                <option v-for="opt in getOptionsByType('budget_status')" :key="opt.id" :value="opt.option_value">{{ opt.option_value }}</option>
            </select>
        </div>

        <div class="overflow-x-auto min-h-[24rem]">
            <table class="min-w-full bg-white">
                <thead class="bg-gray-50">
                    <tr>
                        <th @click="sortBy('id')" class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase cursor-pointer hover:bg-gray-100">Nº <i :class="sortIcon('id')"></i></th>
                        <th @click="sortBy('createdAt')" class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase cursor-pointer hover:bg-gray-100">Data <i :class="sortIcon('createdAt')"></i></th>
                        <th @click="sortBy('patient_name')" class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase cursor-pointer hover:bg-gray-100">Paciente <i :class="sortIcon('patient_name')"></i></th>
                        <th @click="sortBy('final_total')" class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase cursor-pointer hover:bg-gray-100">Valor <i :class="sortIcon('final_total')"></i></th>
                        <th @click="sortBy('status')" class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase cursor-pointer hover:bg-gray-100">Status <i :class="sortIcon('status')"></i></th>
                        <th class="py-3 px-4 text-center text-xs font-medium text-gray-500 uppercase">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-if="filteredAndSortedBudgets.length === 0">
                        <td colspan="6" class="text-center py-8 text-gray-500">Nenhum orçamento encontrado.</td>
                    </tr>
                    <tr v-else v-for="budget in paginatedBudgets" :key="budget.id" class="hover:bg-gray-50">
                        <td class="py-3 px-4 font-bold text-gray-800">{{ budget.id }}</td>
                        <td class="py-3 px-4 text-sm text-gray-600">{{ new Date(budget.createdAt).toLocaleDateString('pt-BR') }}</td>
                        <td class="py-3 px-4 font-medium">
                            <a href="#" @click.prevent="openPatientQuickView(budget.patient_id)" class="hover:underline text-blue-600">{{ budget.patient_name }}</a>
                        </td>
                        <td class="py-3 px-4 font-semibold text-gray-800">{{ formatCurrency(budget.final_total) }}</td>
                        <td class="py-3 px-4"><span class="px-2 py-1 text-xs font-bold rounded-full status-uppercase" :class="getBudgetStatusClass(budget.status)">{{ budget.status }}</span></td>
                        <td class="py-3 px-4 text-center whitespace-nowrap">
                            <button @click="viewBudget(budget.id)" class="text-blue-600 hover:text-blue-800 mr-2" title="Ver Detalhes"><i class="fa-solid fa-eye"></i></button>
                            <button @click="printBudgetById(budget.id)" class="text-gray-600 hover:text-gray-800 mr-2" title="Imprimir"><i class="fa-solid fa-print"></i></button>
                            <button @click="emailBudget(budget.id)" class="text-purple-600 hover:text-purple-800" title="Enviar por E-mail"><i class="fa-solid fa-envelope"></i></button>
                            <button @click="deleteBudget(budget.id)" class="text-gray-400 hover:text-red-600" title="Excluir"><i class="fa-solid fa-trash-can"></i></button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div v-if="budgetTotalPages > 1" class="flex justify-between items-center mt-4 pt-4 border-t">
            <button @click="budget_prevPage" :disabled="budgetPagination.currentPage === 1" class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300 disabled:opacity-50">Anterior</button>
            <span class="text-sm text-gray-600">Página {{ budgetPagination.currentPage }} de {{ budgetTotalPages }}</span>
            <button @click="budget_nextPage" :disabled="budgetPagination.currentPage === budgetTotalPages" class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300 disabled:opacity-50">Próxima</button>
        </div>
    </div>
</div>
</template>
                    
                    
                    
                    
                    
                    
                    
                    
                    
                    
                    
                    
                    
                    
                    
                    
                    
 



<div v-else-if="currentUser && currentUser.isAdmin == 1">
    <div class="min-h-screen flex items-center justify-center p-4 bg-gray-100">
        <p class="text-blue-600">Redirecionando para a área administrativa...</p>
    </div>
</div>
<div v-else>
    <div class="min-h-screen flex items-center justify-center p-4 bg-gray-100">
        <p class="text-gray-500">Verificando acesso...</p>
    </div>
</div>

<div id="confirm-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden items-center justify-center p-4 modal-overlay z-[10001]">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
        <h2 class="text-xl font-bold mb-4">Confirmar Ação</h2>
        <p class="text-gray-700 mb-6">{{ confirmationModal.message }}</p>
        <div class="flex justify-end gap-4">
            <button @click="hideConfirmModal" type="button" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancelar</button>
            <button @click="confirmationModal.onConfirm" type="button" class="px-4 py-2 text-white rounded-md" :class="confirmationModal.confirmButtonClass || 'bg-red-600 hover:bg-red-700'">Sim, Confirmar</button>
        </div>
    </div>
</div>

<div id="delete-reason-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden items-center justify-center p-4 modal-overlay z-50">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
        <h2 class="text-xl font-bold mb-4">Cancelar Agendamento</h2>
        <p class="text-gray-700 mb-4">Por favor, informe o motivo do cancelamento. O agendamento será marcado como "Cancelado" e o paciente será movido para a agenda de espera (se aplicável).</p>
        <div>
            <label for="delete-reason" class="block text-sm font-medium text-gray-700">Motivo *</label>
            <textarea id="delete-reason" v-model="deleteReason" rows="3" class="form-input mt-1" :placeholder="'Ex: ' + labels.patient + ' desmarcou...'"></textarea>
        </div>
        <div class="flex justify-end gap-4 mt-6">
            <button @click="hideModal('delete-reason-modal')" type="button" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Voltar</button>
            <button @click="confirmCancelAppointment" type="button" class="px-4 py-2 bg-yellow-500 text-white rounded-md hover:bg-yellow-600">Confirmar Cancelamento</button>
        </div>
    </div>
</div>

<div id="future-schedule-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden items-center justify-center p-4 modal-overlay z-50" :data-origin="futureScheduleForm.origin">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
        <button @click="hideModal('future-schedule-modal')" type="button" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark fa-lg"></i></button>
        <h2 class="text-xl font-bold mb-4">Agendar Futuro</h2>
        <p class="text-gray-700 mb-4">Selecione a data para o retorno de <strong class="font-semibold">{{ futureScheduleForm.patient_name }}</strong>. O paciente será movido para a "Agenda Espera" nesta data.</p>
        <form @submit.prevent="handleSaveFutureSchedule">
            <div class="space-y-4">
                <div>
                    <label for="future-return-date" class="block text-sm font-medium text-gray-700">Data do Retorno *</label>
                    <input type="date" id="future-return-date" v-model="futureScheduleForm.return_date" class="form-input mt-1" :min="new Date().toLocaleDateString('en-CA')">
                </div>

                <div class="flex flex-wrap gap-2">
                    <button @click.prevent="setFutureDate(2)" type="button" class="px-3 py-1 bg-blue-100 text-blue-700 text-xs font-medium rounded-md hover:bg-blue-200">+2 Meses</button>
                    <button @click.prevent="setFutureDate(3)" type="button" class="px-3 py-1 bg-blue-100 text-blue-700 text-xs font-medium rounded-md hover:bg-blue-200">+3 Meses</button>
                    <button @click.prevent="setFutureDate(4)" type="button" class="px-3 py-1 bg-blue-100 text-blue-700 text-xs font-medium rounded-md hover:bg-blue-200">+4 Meses</button>
                    <button @click.prevent="setFutureDate(5)" type="button" class="px-3 py-1 bg-blue-100 text-blue-700 text-xs font-medium rounded-md hover:bg-blue-200">+5 Meses</button>
                    <button @click.prevent="setFutureDate(6)" type="button" class="px-3 py-1 bg-blue-100 text-blue-700 text-xs font-medium rounded-md hover:bg-blue-200">+6 Meses</button>
                </div>

                <div>
                    <label for="future-reason" class="block text-sm font-medium text-gray-700">Motivo (Opcional)</label>
                    <textarea id="future-reason" v-model="futureScheduleForm.reason" rows="3" class="form-input mt-1" placeholder="Ex: Retorno de 6 meses..."></textarea>
                </div>
            </div>
            <div class="flex justify-end gap-4 mt-6">
                <button @click="hideModal('future-schedule-modal')" type="button" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancelar</button>
                <button @click="handleSaveFutureSchedule" type="button" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Salvar Agendamento</button>
            </div>
        </form>
    </div>
</div>

<div id="patient-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden justify-center p-4 modal-overlay overflow-y-auto z-40">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md sm:max-w-2xl lg:max-w-5xl p-6 my-8">
        <button @click="hideModal('patient-modal')" type="button" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark fa-lg"></i></button>
        <h2 class="text-2xl font-bold mb-6">{{ editingPatient.id ? `Editando ${labels.patient}: ${editingPatient.name}` : `Novo ${labels.patient}` }}</h2>
        <form @submit.prevent="savePatient(editingPatient)">
            <div class="border-b border-gray-200 mb-6">
                <nav class="-mb-px flex space-x-6 overflow-x-auto items-center">
                    <button type="button" @click="activePatientTab = 'main'" :class="{'active': activePatientTab === 'main'}" class="py-2 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 tab-button flex items-center whitespace-nowrap">Dados Principais</button>
                    <button type="button" @click="activePatientTab = 'docs'" :class="{'active': activePatientTab === 'docs'}" class="py-2 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 tab-button flex items-center whitespace-nowrap">Documentação</button>
                    <button type="button" @click="activePatientTab = 'contact'" :class="{'active': activePatientTab === 'contact'}" class="py-2 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 tab-button flex items-center whitespace-nowrap">Endereço</button>
                    <button type="button" @click="exportPatientTabData(activePatientTab, editingPatient)" class="ml-auto text-gray-400 hover:text-blue-600 px-2" title="Exportar dados desta aba"><i class="fa-solid fa-save fa-lg"></i></button>
                </nav>
            </div>

            <div v-show="activePatientTab === 'main'">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-x-4 gap-y-2">

                    <div class="md:col-span-1 flex flex-col items-center pt-2 space-y-4">
                        <div>
                            <img :src="patientPhotoPreview || editingPatient.photo || 'https://placehold.co/150x150/E2E8F0/A0AEC0?text=Foto'" @error="e => e.target.src='https://placehold.co/150x150/E2E8F0/A0AEC0?text=Foto'" class="w-36 h-36 rounded-full object-cover bg-gray-200 mb-4">
                            <input type="file" id="patient-photo" @change="handlePhotoUpload($event, 'patient')" class="hidden" accept="image/*">
                            <div class="flex gap-2 w-full max-w-xs">
                                <button type="button" @click="triggerFileUpload('patient-photo')" class="flex-1 text-sm py-2 bg-gray-200 rounded-md"><i class="fa-solid fa-upload mr-2"></i>Carregar</button>
                                <button type="button" @click="openWebcamModal('patient')" class="flex-1 text-sm py-2 bg-gray-200 rounded-md"><i class="fa-solid fa-camera mr-2"></i>Webcam</button>
                            </div>
                        </div>
                    </div>

                    <div class="md:col-span-2 grid grid-cols-1 sm:grid-cols-3 gap-x-4 gap-y-2">
                        <div class="sm:col-span-3"><label>Nome Completo *</label><input type="text" v-model="editingPatient.name" required class="form-input"></div>

                        <div><label>Apelido / Preferência</label><input type="text" v-model="editingPatient.nickname" class="form-input"></div>

                        <div><label>Celular (DDD)</label><input type="tel" v-model="editingPatient.phone" @input="editingPatient.phone = formatPhone($event.target.value)" placeholder="00-00000-0000" class="form-input"></div>
                        <div><label>Telefone 2 (DDD)</label><input type="tel" v-model="editingPatient.phone2" @input="editingPatient.phone2 = formatPhone($event.target.value)" class="form-input"></div>
                        <div class="sm:col-span-2"><label>Email</label><input type="email" v-model="editingPatient.email" class="form-input"></div>
                        <div><label>Instagram</label><input type="text" v-model="editingPatient.instagram" placeholder="@usuario" class="form-input"></div>

                        <div>
                            <label>Sexo</label>
                            <select v-model="editingPatient.gender" class="form-input">
                                <option :value="null">Selecione...</option>
                                <option v-for="opt in getOptionsByType('gender')" :key="opt.id" :value="opt.option_value"> {{ opt.option_value }} </option>
                            </select>
                        </div>
                        <div>
                            <label>Estado Civil</label>
                            <select v-model="editingPatient.marital_status" class="form-input">
                                <option :value="null">Selecione...</option>
                                <option v-for="opt in getOptionsByType('marital_status')" :key="opt.id" :value="opt.option_value"> {{ opt.option_value }} </option>
                            </select>
                        </div>
                        <div>
                            <label>Data de Nascimento</label>
                            <input type="date" v-model="editingPatient.birthdate" class="form-input">
                        </div>

                        <div class="relative sm:col-span-2">
                            <label>Indicado por</label>
                            <input type="text" v-model="editingPatient.referred_by" @input="searchPatientsForReferredBy" class="form-input" placeholder="Digite nome de outro paciente ou externo...">
                            <div v-if="patientReferredBySearchResults.length > 0" class="absolute z-10 w-full bg-white border rounded-md mt-1 max-h-48 overflow-y-auto shadow-lg">
                                <a v-for="p in patientReferredBySearchResults" :key="p.id" @click.prevent="selectPatientForReferredBy(p)" class="block px-4 py-2 text-sm hover:bg-gray-100 cursor-pointer">{{ p.name }}</a>
                            </div>
                        </div>
                        <div>
                            <label>Idade</label>
                            <input type="text" :value="calculateAge(editingPatient.birthdate) ? calculateAge(editingPatient.birthdate) + ' anos' : '---'" class="form-input bg-gray-100" readonly>
                        </div>
                    </div>
                </div>
            </div>
            <div v-show="activePatientTab === 'docs'">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-x-4 gap-y-2">
                    <div>
                        <label>CPF / CNPJ</label>
                        <input type="text" v-model="editingPatient.cpf"
                               @input="editingPatient.cpf = formatCPF_CNPJ($event.target.value); validateDocument(editingPatient.cpf, 'editingPatient')"
                               placeholder="000.000.000-00" class="form-input"
                               :class="{'is-invalid': editingPatient.isDocumentInvalid}">
                        <p v-if="editingPatient.isDocumentInvalid" class="text-red-600 text-xs mt-1">CPF/CNPJ inválido.</p>
                    </div>
                    <div><label>RG</label><input type="text" v-model="editingPatient.rg" class="form-input"></div>
                    <div><label>Local de Nascimento</label><input type="text" v-model="editingPatient.birth_place" class="form-input"></div>

                    <div class="sm:col-span-2 relative">
                        <label>Nome do Responsável (se aplicável)</label>
                        <input type="text" v-model="patientResponsibleSearchQuery" @keyup="searchPatientsForResponsible" class="form-input" placeholder="Digite nome de outro paciente...">
                        <div v-if="patientResponsibleSearchResults.length > 0 && patientResponsibleSearchQuery.length > 1" class="absolute z-10 w-full bg-white border rounded-md mt-1 max-h-48 overflow-y-auto shadow-lg">
                            <a v-for="p in patientResponsibleSearchResults" :key="p.id" @click.prevent="selectPatientForResponsible(p)" class="block px-4 py-2 text-sm hover:bg-gray-100 cursor-pointer">{{ p.name }}</a>
                        </div>
                        <input type="hidden" v-model="editingPatient.responsible_name">
                    </div>
                    <div>
                        <label>CPF do Responsável</label>
                        <input type="text" v-model="editingPatient.responsible_cpf"
                               @input="editingPatient.responsible_cpf = formatCPF_CNPJ($event.target.value)"
                               placeholder="000.000.000-00" class="form-input">
                    </div>
                    <div class="sm:col-span-3 grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2">
                        <div class="relative">
                            <label>Filiação (Pai)</label>
                            <input type="text" v-model="patientFatherSearchQuery" @keyup="searchPatientsForFather" class="form-input" placeholder="Digite nome de outro paciente...">
                            <div v-if="patientFatherSearchResults.length > 0 && patientFatherSearchQuery.length > 1" class="absolute z-10 w-full bg-white border rounded-md mt-1 max-h-48 overflow-y-auto shadow-lg">
                                <a v-for="p in patientFatherSearchResults" :key="p.id" @click.prevent="selectPatientForFather(p)" class="block px-4 py-2 text-sm hover:bg-gray-100 cursor-pointer">{{ p.name }}</a>
                            </div>
                            <input type="hidden" v-model="editingPatient.parentage_father">
                        </div>

                        <div class="relative">
                            <label>Filiação (Mãe)</label>
                            <input type="text" v-model="patientMotherSearchQuery" @keyup="searchPatientsForMother" class="form-input" placeholder="Digite nome de outro paciente...">
                            <div v-if="patientMotherSearchResults.length > 0 && patientMotherSearchQuery.length > 1" class="absolute z-10 w-full bg-white border rounded-md mt-1 max-h-48 overflow-y-auto shadow-lg">
                                <a v-for="p in patientMotherSearchResults" :key="p.id" @click.prevent="selectPatientForMother(p)" class="block px-4 py-2 text-sm hover:bg-gray-100 cursor-pointer">{{ p.name }}</a>
                            </div>
                            <input type="hidden" v-model="editingPatient.parentage_mother">
                        </div>
                    </div>
                    <div class="sm:col-span-3 grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2 mt-2">
                        <div><label>Convênio Médico</label><input type="text" v-model="editingPatient.health_insurance" class="form-input"></div>
                        <div><label>Nº Conv. Médico</label><input type="text" v-model="editingPatient.insurance_number" class="form-input"></div>
                        <div><label>Convênio Odontológico</label><input type="text" v-model="editingPatient.health_insurance_odont" class="form-input"></div>
                        <div><label>Nº Conv. Odonto</label><input type="text" v-model="editingPatient.insurance_number_odont" class="form-input"></div>
                    </div>
                </div>
            </div>

             <div v-show="activePatientTab === 'contact'">
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-x-4 gap-y-2">
                    <div>
                        <label>CEP</label>
                        <input type="text" v-model="editingPatient.zip_code" 
                               @input="editingPatient.zip_code = formatCEP($event.target.value)" 
                               @blur="fetchAddressByZipCode('patient')" 
                               placeholder="00000-000" class="form-input">
                    </div>
                    <div class="sm:col-span-3"><label>Rua / Avenida</label><input type="text" v-model="editingPatient.street" class="form-input"></div>
                    
                    <div><label>Número</label><input type="text" v-model="editingPatient.street_number" class="form-input"></div>
                    <div class="sm:col-span-3"><label>Bairro</label><input type="text" v-model="editingPatient.neighborhood" class="form-input"></div>
                    
                    <div class="sm:col-span-2"><label>Cidade</label><input type="text" v-model="editingPatient.city" class="form-input"></div>
                    <div><label>Estado</label><input type="text" v-model="editingPatient.state" class="form-input bg-gray-100" readonly></div>
                    <div class="sm:col-span-4"><label>Dados Adicionais (Apto, Loja, etc)</label><input type="text" v-model="editingPatient.address_complement" class="form-input"></div>
                </div>
            </div>
            
            <div class="flex justify-end items-center gap-4 mt-8 pt-4 border-t">
                <button type="button" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300" @click="hideModal('patient-modal')">Cancelar</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Salvar</button>
            </div>
        </form>
    </div>
</div>

<div id="appointment-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden justify-center p-4 modal-overlay overflow-y-auto z-30">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6 my-8">
        <button @click="hideModal('appointment-modal')" type="button" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark fa-lg"></i></button>
        <h2 class="text-2xl font-bold mb-6 flex items-center">
            <i v-if="isAppointmentFinalized(editingAppointment)" class="fa-solid fa-check-double text-green-600 mr-2" title="Atendimento Finalizado/Verificado"></i>
            {{ editingAppointment.id ? 'Editar Agendamento' : 'Novo Agendamento' }}
        </h2>
        <button type="button" @click="openPatientModal(null)" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 flex-shrink-0">
    <i class="fa-solid fa-plus"></i><span class="ml-2 hidden sm:inline">Novo {{ labels.patient }}</span>
</button>
        <div v-if="editingAppointment.status === 'Cancelado'" class="mb-4 p-3 bg-red-100 text-red-800 rounded text-center font-bold border border-red-200">
            <i class="fa-solid fa-ban mr-2"></i>Agendamento Cancelado
        </div>
        <div v-if="editingAppointment.status === 'Não Compareceu'" class="mb-4 p-3 bg-orange-100 text-orange-800 rounded text-center font-bold border border-orange-200">
            <i class="fa-solid fa-user-xmark mr-2"></i>Paciente Não Compareceu
        </div>
        <div v-if="isAppointmentFinalized(editingAppointment)" class="mb-4 p-3 bg-green-100 text-green-800 rounded text-center font-bold border border-green-200">
            <i class="fa-solid fa-check-circle mr-2"></i>Atendimento Finalizado com Sucesso
        </div>

        <form @submit.prevent="saveAppointment(false)">
            <div class="space-y-4">
                <div class="relative">
                    <label>{{ labels.patient }}</label>
                    <input type="text" v-model="patientSearchQuery" @keyup="searchPatientsForAgenda" :placeholder="'Digite para buscar um ' + labels.patient.toLowerCase() + '...'" class="form-input" :disabled="isAppointmentFinalized(editingAppointment) || editingAppointment.status === 'Cancelado' || editingAppointment.status === 'Não Compareceu'">
                    <div v-if="patientSearchResults.length > 0 && patientSearchQuery" class="absolute z-10 w-full bg-white border rounded-md mt-1 max-h-48 overflow-y-auto shadow-lg">
                        <a v-for="p in patientSearchResults" :key="p.id" @click.prevent="selectPatientForAppointment(p)" class="block px-4 py-2 text-sm hover:bg-gray-100 cursor-pointer">{{ p.name }}</a>
                    </div>
                    <div v-if="editingAppointment.patient_id" class="mt-2 flex items-center bg-blue-50 p-2 rounded-md">
                        <i class="fa-solid fa-user text-blue-500"></i>
                        <a href="#" @click.prevent="openPatientQuickView(editingAppointment.patient_id)" class="ml-2 font-semibold text-blue-800 hover:underline" :title="`Ver dados de ${getPatientName(editingAppointment.patient_id)}`">
                            {{ editingAppointment.patient_name || getPatientName(editingAppointment.patient_id) }}
                        </a>
                        <button v-if="!isAppointmentFinalized(editingAppointment) && editingAppointment.status !== 'Cancelado' && editingAppointment.status !== 'Não Compareceu'" type="button" @click="editingAppointment.patient_id = null; patientSearchQuery = ''" class="ml-auto text-red-500 text-xs hover:text-red-700">Remover</button>
                    </div>
                </div>
                <div>
                    <label>Título do Agendamento</label>
                    <input type="text" v-model="editingAppointment.title" required class="form-input" :disabled="isAppointmentFinalized(editingAppointment) || editingAppointment.status === 'Cancelado' || editingAppointment.status === 'Não Compareceu'">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                    <div class="sm:col-span-1">
                        <label>Data</label>
                        <input type="date" v-model="editingAppointment.date" @change="fetchAvailableSlotsForDate" required class="form-input" :disabled="isAppointmentFinalized(editingAppointment) || editingAppointment.status === 'Cancelado' || editingAppointment.status === 'Não Compareceu'">
                    </div>
                    <div class="sm:col-span-1">
                        <label>Início</label>
                        <input type="time" v-model="editingAppointment.start_time" required class="form-input" :disabled="isAppointmentFinalized(editingAppointment) || editingAppointment.status === 'Cancelado' || editingAppointment.status === 'Não Compareceu'">
                    </div>
                    <div class="sm:col-span-1">
                        <label>Fim</label>
                        <input type="time" v-model="editingAppointment.end_time" required class="form-input" :disabled="isAppointmentFinalized(editingAppointment) || editingAppointment.status === 'Cancelado' || editingAppointment.status === 'Não Compareceu'">
                    </div>
                </div>
                <div>
                    <label>Notas</label>
                    <textarea v-model="editingAppointment.notes" rows="3" class="form-input" :disabled="isAppointmentFinalized(editingAppointment) || editingAppointment.status === 'Cancelado' || editingAppointment.status === 'Não Compareceu'"></textarea>
                </div>

                <div v-if="availableTimeSlots.length > 0 && !isAppointmentFinalized(editingAppointment) && editingAppointment.status !== 'Cancelado' && editingAppointment.status !== 'Não Compareceu'" class="pt-4 border-t">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Horários disponíveis para {{ formatDateForDisabledList(editingAppointment.date) }}:</label>
                        <div class="flex flex-wrap gap-2 max-h-32 overflow-y-auto">
                            <button v-for="slot in availableTimeSlots" :key="slot.time" @click.prevent="selectAvailableSlot(slot.time)"
                                    type="button" class="px-3 py-1 text-sm rounded-md"
                                    :class="slot.available ? 'bg-blue-100 text-blue-700 hover:bg-blue-200' : 'bg-gray-200 text-gray-500 cursor-not-allowed line-through'">
                                {{ slot.time }}
                            </button>
                        </div>
                </div>
                <div v-if="availableTimeSlots.length === 0 && editingAppointment.date && !isAppointmentFinalized(editingAppointment) && editingAppointment.status !== 'Cancelado' && editingAppointment.status !== 'Não Compareceu'" class="pt-4 border-t text-sm text-center text-gray-500">
                    Nenhum horário disponível neste dia ({{ formatDateForDisabledList(editingAppointment.date) }}) ou dia desativado.
                </div>

            </div>
            
            <div class="flex flex-col-reverse sm:flex-row sm:justify-between items-center gap-4 mt-8 pt-4 border-t">
                <div class="flex gap-4 flex-wrap w-full sm:w-auto justify-center sm:justify-start">
                    <button v-if="editingAppointment.id && editingAppointment.patient_id && editingAppointment.status !== 'Cancelado' && editingAppointment.status !== 'Não Compareceu' && !isAppointmentActive(editingAppointment.id) && !isAppointmentFinalized(editingAppointment) && !isAppointmentMissed(editingAppointment)" 
                            @click.prevent="startServiceFromAppointment(editingAppointment.id)" type="button" 
                            class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                        <i class="fa-solid fa-play mr-2"></i> Iniciar Atend.
                    </button>
                    
                    <button v-if="editingAppointment.id && editingAppointment.status !== 'Cancelado' && isAppointmentActive(editingAppointment.id)" 
                            @click.prevent="findAndFinishService(editingAppointment, 'appointment-modal')" type="button" 
                            class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                        <i class="fa-solid fa-stop mr-2"></i> Finalizar Atend.
                    </button>

                    <button v-if="(isAppointmentMissed(editingAppointment) || editingAppointment.status === 'Não Compareceu') && !isRescheduled(editingAppointment)" 
                           @click.prevent="rescheduleMissedToWaitingList(editingAppointment)" type="button" 
                           class="px-4 py-2 bg-orange-600 text-white rounded-md hover:bg-orange-700">
                        <i class="fa-solid fa-clock-rotate-left mr-2"></i> Reagendar (Faltou)
                    </button>
                </div>
                
                <div class="flex gap-4 flex-wrap justify-end w-full sm:w-auto">
                    <button v-if="editingAppointment.id && editingAppointment.status !== 'Cancelado' && editingAppointment.status !== 'Não Compareceu' && !isAppointmentFinalized(editingAppointment)" 
                            type="button" class="px-4 py-2 bg-yellow-500 text-white rounded-md hover:bg-yellow-600" 
                            @click.prevent="promptCancelAppointment(editingAppointment)">
                        Cancelar
                    </button>
                    
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700" 
                            :disabled="isAppointmentFinalized(editingAppointment) || editingAppointment.status === 'Cancelado' || editingAppointment.status === 'Não Compareceu'" 
                            :class="{'opacity-50 cursor-not-allowed': isAppointmentFinalized(editingAppointment) || editingAppointment.status === 'Cancelado' || editingAppointment.status === 'Não Compareceu'}">
                        Salvar
                    </button>
                    
                    <button type="button" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300" 
                            @click="hideModal('appointment-modal')">
                        {{ (isAppointmentFinalized(editingAppointment) || editingAppointment.status === 'Cancelado' || editingAppointment.status === 'Não Compareceu') ? 'Fechar' : 'Sair' }}
                    </button>
                </div>
            </div>
            </form>
    </div>
</div>

<div id="webcam-modal" class="fixed inset-0 bg-gray-900 bg-opacity-75 hidden justify-center p-4 z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-4 relative my-8">
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

<div id="price-item-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden justify-center p-4 z-50 modal-overlay overflow-y-auto">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6 my-8">
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

<div id="price-list-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden justify-center p-4 z-50 overflow-y-auto">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6 my-8">
        <button @click="hideModal('price-list-modal')" type="button" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark fa-lg"></i></button>
        <h2 class="text-xl font-bold mb-4">{{ editingPriceList.id ? 'Editar Tabela' : 'Nova Tabela de Preços' }}</h2>
        <form @submit.prevent="savePriceList">
            <div>
                <label class="block text-sm font-medium">Nome da Tabela *</label>
                <input type="text" v-model="editingPriceList.name" required class="form-input">
                <p v-if="editingPriceList.id && editingPriceList.originalIsGlobal" class="text-xs text-blue-600 mt-1">Nota: Editar uma tabela global criará uma cópia pessoal para você.</p>
            </div>
            <div class="flex justify-end gap-4 mt-6">
                <button type="button" @click="hideModal('price-list-modal')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancelar</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Salvar</button>
            </div>
        </form>
    </div>
</div>

<div id="admin-manage-items-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden justify-center p-4 z-40 modal-overlay overflow-y-auto">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-3xl p-6 my-8">
<button @click="hideModal('admin-manage-items-modal'); activePriceListForItems = null" type="button" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark fa-lg"></i></button>
            <div v-if="activePriceListForItems">
                <h2 class="text-2xl font-bold mb-2">Itens da Tabela: {{ activePriceListForItems.name }}</h2>
                <p class="text-sm text-blue-600 mb-6" v-if="activePriceListForItems.is_global">Esta é uma Tabela Global.</p>
            </div>
            <div class="flex justify-end mb-4">
                <button @click="openPriceItemModal(null)" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm" :disabled="activePriceListForItems && activePriceListForItems.is_global" :class="{'opacity-50 cursor-not-allowed': activePriceListForItems && activePriceListForItems.is_global}" title="Você só pode adicionar itens às suas próprias tabelas."><i class="fa-solid fa-plus"></i><span class="hidden sm:inline ml-2">Adicionar Item</span></button>
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
                                <button @click="openPriceItemModal(item)" class="text-indigo-600 hover:text-indigo-900 mr-3" :disabled="activePriceListForItems && activePriceListForItems.is_global" :class="{'opacity-50 cursor-not-allowed': activePriceListForItems && activePriceListForItems.is_global}" title="Você só pode editar itens das suas próprias tabelas."><i class="fa-solid fa-pen-to-square"></i></button>
                                <button @click="deletePriceItem(item.id)" class="text-red-600 hover:text-red-900" :disabled="activePriceListForItems && activePriceListForItems.is_global" :class="{'opacity-50 cursor-not-allowed': activePriceListForItems && activePriceListForItems.is_global}" title="Você só pode excluir itens de suas próprias tabelas."><i class="fa-solid fa-trash-can"></i></button>
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

<div id="new-budget-patient-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden justify-center p-4 z-30 modal-overlay overflow-y-auto">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6 my-8">
        <h2 class="text-xl font-bold mb-4">Selecione um {{ labels.patient }}</h2><button type="button" @click="openPatientModal(null)" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 flex-shrink-0">
    <i class="fa-solid fa-plus"></i><span class="ml-2 hidden sm:inline">Novo {{ labels.patient }}</span>
</button>
        <div class="mb-4">
            <input type="text" v-model="newBudgetPatientSearch" @keyup="searchPatientsForNewBudget" placeholder="Digite para buscar..." class="form-input w-full">
        </div>
        <div class="max-h-64 overflow-y-auto border rounded-md">
            <div v-if="newBudgetPatientResults.length === 0" class="p-4 text-center text-gray-500">Nenhum {{ labels.patient.toLowerCase() }} encontrado.</div>
            <a v-else v-for="p in newBudgetPatientResults" :key="p.id" @click.prevent="selectPatientAndCreateBudget(p)" href="#" class="block px-4 py-3 hover:bg-gray-100 border-b last:border-b-0">
                <div class="font-semibold">{{ p.name }}</div>
                <div class="text-sm text-gray-600">{{ p.cpf || p.phone }}</div>
            </a>
        </div>
        <div class="flex justify-end mt-6">
            <button type="button" @click="hideModal('new-budget-patient-modal')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancelar</button>
        </div>
    </div>
</div>

<div id="standalone-service-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden justify-center p-4 z-30 modal-overlay overflow-y-auto">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6 my-8">
        <h2 class="text-xl font-bold mb-4">Novo Atendimento Avulso</h2> <button type="button" @click="openPatientModal(null)" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 flex-shrink-0">
    <i class="fa-solid fa-plus"></i><span class="ml-2 hidden sm:inline">Novo {{ labels.patient }}</span>
</button>
        <form @submit.prevent="createStandaloneService">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium">{{ labels.patient }} *</label>
                    <input type="text" v-model="standaloneServicePatientSearch" @keyup="searchPatientsForStandaloneService" :placeholder="'Digite para buscar um ' + labels.patient.toLowerCase() + '...'" class="form-input">
                    <div v-if="standaloneServicePatientResults.length > 0 && standaloneServicePatientSearch" class="border rounded-md mt-1 max-h-48 overflow-y-auto">
                        <a v-for="p in standaloneServicePatientResults" :key="p.id" @click.prevent="selectPatientForStandaloneService(p)" class="block px-4 py-2 text-sm hover:bg-gray-100 cursor-pointer">{{ p.name }}</a>
                    </div>
                    <div v-if="newStandaloneService.patient_id" class="mt-2 flex items-center bg-blue-50 p-2 rounded-md">
                        <i class="fa-solid fa-user text-blue-500"></i>
                        <span class="ml-2 font-semibold text-blue-800">{{ getPatientName(newStandaloneService.patient_id) }}</span>
                        <button type="button" @click="newStandaloneService.patient_id = null; standaloneServicePatientSearch = ''" class="ml-auto text-red-500 text-xs">Remover</button>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium">Descrição do Atendimento *</label>
                    <textarea v-model="newStandaloneService.description" required rows="3" class="form-input"></textarea>
                </div>
            </div>
            <div class="flex justify-end gap-4 mt-6 pt-4 border-t">
                <button type="button" @click="hideModal('standalone-service-modal')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancelar</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Criar Atendimento</button>
            </div>
        </form>
    </div>
</div>

<div id="add-to-waiting-list-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden justify-center p-4 z-50 modal-overlay overflow-y-auto">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6 my-8">
        <button @click="hideModal('add-to-waiting-list-modal')" type="button" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark fa-lg"></i></button>
        <h2 class="text-xl font-bold mb-4">Adicionar à Agenda de Espera</h2>
        <form @submit.prevent="handleManualAddToWaitingList">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium">{{ labels.patient }} *</label>
                    <input type="text" v-model="manualWaitingList.patientSearch" @keyup="searchPatientsForWaitingList" :placeholder="'Digite para buscar um ' + labels.patient.toLowerCase() + '...'" class="form-input">
                    <div v-if="manualWaitingList.searchResults.length > 0 && manualWaitingList.patientSearch" class="border rounded-md mt-1 max-h-48 overflow-y-auto">
                        <a v-for="p in manualWaitingList.searchResults" :key="p.id" @click.prevent="selectPatientForWaitingList(p)" class="block px-4 py-2 text-sm hover:bg-gray-100 cursor-pointer">{{ p.name }}</a>
                    </div>
                    <div v-if="manualWaitingList.patientId" class="mt-2 flex items-center bg-blue-50 p-2 rounded-md text-sm">
                        <i class="fa-solid fa-user text-blue-500 mr-2"></i>
                        <span class="font-semibold text-blue-800">{{ getPatientName(manualWaitingList.patientId) }}</span>
                        <button @click="manualWaitingList.patientId = null; manualWaitingList.patientSearch = ''" type="button" class="ml-auto text-red-500 text-xs">Remover</button>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium">Motivo</label>
                    <textarea v-model="manualWaitingList.reason" rows="3" class="form-input" placeholder="Opcional: Descreva o motivo da inclusão..."></textarea>
                </div>
            </div>
            <div class="flex justify-end gap-4 mt-6 pt-4 border-t">
                <button type="button" @click="hideModal('add-to-waiting-list-modal')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancelar</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Adicionar à Lista</button>
            </div>
        </form>
    </div>
</div>

<div id="user-anamnesis-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden justify-center p-4 z-50 overflow-y-auto">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl p-6 my-8">
        <button @click="hideModal('user-anamnesis-modal')" type="button" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark fa-lg"></i></button>
            <h2 class="text-xl font-bold mb-4">{{ editingUserAnamnesis.id ? 'Editar Meu Modelo' : 'Novo Modelo de Anamnese' }}</h2>
            <p v-if="editingUserAnamnesis.originalIsGlobal" class="text-xs text-blue-600 mb-4 bg-blue-50 p-2 rounded border border-blue-200">Nota: Você está editando uma cópia de um modelo global. Salvar criará um novo modelo pessoal.</p>
            <form @submit.prevent="saveUserAnamnesisTemplate">
                <div class="mb-4">
                    <label class="block text-sm font-medium">Título do Modelo *</label>
                    <input type="text" v-model="editingUserAnamnesis.title" required class="form-input">
                </div>
                <div>
                    <label class="block text-sm font-medium">Conteúdo da Anamnese</label>
                    <textarea v-model="editingUserAnamnesis.content" rows="15" class="form-input"></textarea>
                </div>
                <div class="flex justify-end gap-4 mt-6">
                    <button type="button" @click="hideModal('user-anamnesis-modal')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancelar</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Salvar Modelo</button>
                </div>
            </form>
        </div>
</div>

<div id="user-payment-method-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden justify-center p-4 z-50 overflow-y-auto">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6 my-8">
        <button @click="hideModal('user-payment-method-modal')" type="button" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark fa-lg"></i></button>
            <h2 class="text-xl font-bold mb-4">{{ editingUserPaymentMethod.id ? 'Editar Método' : 'Novo Método Pessoal' }}</h2>
            <p v-if="editingUserPaymentMethod.originalIsGlobal" class="text-xs text-blue-600 mb-4 bg-blue-50 p-2 rounded border border-blue-200">Nota: Você está editando uma cópia de um método global. Salvar criará um novo método pessoal.</p>
            <form @submit.prevent="saveUserPaymentMethod">
                <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium">Nome da Forma de Pagamento *</label>
                    <input type="text" v-model="editingUserPaymentMethod.option_value" required class="form-input">
                </div>
                </div>
                <div class="flex justify-end gap-4 mt-6 pt-4 border-t">
                    <button type="button" @click="hideModal('user-payment-method-modal')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancelar</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Salvar Método</button>
                </div>
            </form>
        </div>
</div>

<div id="memed-prescription-modal" class="fixed inset-0 z-[10000] hidden items-center justify-center bg-gray-900 bg-opacity-75 backdrop-blur-sm p-4 modal-overlay">
    <div class="bg-white w-full h-full md:w-[95%] md:h-[90%] md:max-w-7xl md:rounded-xl shadow-2xl relative flex flex-col overflow-hidden border md:border-gray-200">
        
        <div class="relative z-20 flex justify-between items-center px-4 md:px-6 py-3 bg-blue-600 text-white shadow-md flex-shrink-0">
            <div class="flex items-center gap-3">
                <i class="fa-solid fa-file-prescription text-xl md:text-2xl"></i>
                <div class="truncate max-w-[150px] md:max-w-none">
                    <h2 class="text-base md:text-lg font-bold">Prescrição Digital MEMED</h2>
                    <p class="text-xs opacity-90 text-white truncate">Paciente: {{ editingClinicalData ? editingClinicalData.name : '' }}</p>
                </div>
            </div>
            
            <div class="flex items-center gap-2 md:gap-3">
                <button @click="logoutMemed" type="button" class="text-white hover:text-gray-200 bg-red-600 hover:bg-red-700 px-3 md:px-4 py-1.5 md:py-2 rounded-md text-xs md:text-sm font-medium flex items-center gap-1 md:gap-2 transition-colors cursor-pointer" title="Sair da sua conta Memed neste computador">
                    <i class="fa-solid fa-right-from-bracket"></i> <span class="hidden sm:inline">Sair da Memed</span>
                </button>
                
                <button @click="closeMemedModal" type="button" class="text-white hover:text-gray-200 bg-blue-700 hover:bg-blue-800 px-3 md:px-4 py-1.5 md:py-2 rounded-md text-xs md:text-sm font-medium flex items-center gap-1 md:gap-2 transition-colors cursor-pointer">
                    <i class="fa-solid fa-arrow-left"></i> <span class="hidden sm:inline">Voltar ao Prontuário</span>
                </button>
            </div>
        </div>

        <div id="memed-container" class="flex-grow w-full h-full bg-gray-50 relative overflow-hidden">
            <div class="absolute inset-0 flex items-center justify-center text-gray-500 pointer-events-none" id="memed-loading-placeholder">
                <div class="text-center">
                    <i class="fa-solid fa-circle-notch fa-spin text-3xl md:text-4xl text-blue-500 mb-3"></i>
                    <p class="text-sm md:text-base">Carregando módulo de prescrição...</p>
                </div>
            </div>
        </div>

    </div>
</div>


<div id="ledger-entry-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden justify-center p-4 modal-overlay z-50 overflow-y-auto">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6 my-8">
        <button @click="hideModal('ledger-entry-modal')" type="button" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark fa-lg"></i></button>
        <h2 class="text-xl font-bold mb-6">{{ editingLedgerEntry.id ? 'Editar Lançamento' : 'Novo Lançamento' }} - <span :class="editingLedgerEntry.entry_type === 'entrada' ? 'text-blue-600' : 'text-red-600'">{{ editingLedgerEntry.entry_type === 'entrada' ? 'Entrada' : 'Saída' }}</span></h2>
        <form @submit.prevent="saveLedgerEntry">
            <input type="hidden" v-model="editingLedgerEntry.id">
            <input type="hidden" v-model="editingLedgerEntry.entry_type">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 mb-4">
                <div>
                    <label class="block text-sm font-medium">Nº Ordem</label>
                    <input type="text" v-model="editingLedgerEntry.entry_order" placeholder="Manual" class="form-input"
                            :disabled="editingLedgerEntry.id" :class="{'bg-gray-100': editingLedgerEntry.id}">
                    </div>
                <div>
                    <label class="block text-sm font-medium">Data *</label>
                    <input type="date" v-model="editingLedgerEntry.entry_date" required class="form-input"
                            :disabled="editingLedgerEntry.id" :class="{'bg-gray-100': editingLedgerEntry.id}">
                    </div>
                <div>
                    <label class="block text-sm font-medium">Recibo / NFe</label>
                    <input type="text" v-model="editingLedgerEntry.receipt_nfe" class="form-input">
                </div>
            </div>
            
            <div class="relative mb-4">
                <label class="block text-sm font-medium">{{ labels.patient }} (Opcional)</label>
                <input type="text" v-model="ledgerPatientSearch" @keyup="searchPatientsForLedger" :placeholder="'Digite para buscar um ' + labels.patient.toLowerCase() + '...'" class="form-input"
                        :disabled="editingLedgerEntry.id && editingLedgerEntry.patient_id" :class="{'bg-gray-100': editingLedgerEntry.id && editingLedgerEntry.patient_id}">
                <div v-if="ledgerPatientResults.length > 0 && ledgerPatientSearch" class="absolute z-10 w-full bg-white border rounded-md mt-1 max-h-48 overflow-y-auto">
                    <a v-for="p in ledgerPatientResults" :key="p.id" @click.prevent="selectPatientForLedger(p)" class="block px-4 py-2 text-sm hover:bg-gray-100 cursor-pointer">{{ p.name }}</a>
                </div>
                <div v-if="editingLedgerEntry.patient_id && !ledgerPatientSearch" class="mt-2 flex items-center bg-blue-50 p-2 rounded-md text-sm">
                    <i class="fa-solid fa-user text-blue-500 mr-2"></i>
                    <span class="font-semibold text-blue-800">{{ getPatientName(editingLedgerEntry.patient_id) }}</span>
                    <button type="button" @click="editingLedgerEntry.patient_id = null; ledgerPatientSearch = ''" class="ml-auto text-red-500 text-xs"
                            :disabled="editingLedgerEntry.id && editingLedgerEntry.patient_id">Remover</button>
                </div>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium">Descrição *</label>
                <input type="text" v-model="editingLedgerEntry.description" required class="form-input">
            </div>
            <div>
                <label class="block text-sm font-medium">Valor (R$) *</label>
                <input type="number" step="0.01" min="0.01" v-model.number="editingLedgerEntry.amount" required class="form-input" 
                        :class="editingLedgerEntry.entry_type === 'entrada' ? 'text-blue-600 font-semibold' : 'text-red-600 font-semibold'"
                        :disabled="editingLedgerEntry.id" :class="{'bg-gray-100': editingLedgerEntry.id}">
                </div>

            <div class="flex justify-end gap-4 mt-8 pt-4 border-t">
                <button type="button" @click="hideModal('ledger-entry-modal')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancelar</button>
                <button type="submit" class="px-4 py-2 rounded-md text-white" :class="editingLedgerEntry.entry_type === 'entrada' ? 'bg-blue-600 hover:bg-blue-700' : 'bg-red-600 hover:bg-red-700'">
                    {{ editingLedgerEntry.id ? 'Salvar Alteração' : 'Adicionar Lançamento' }}
                </button>
            </div>
        </form>
    </div>
</div>

<div id="manual-forecast-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden justify-center p-4 modal-overlay z-50 overflow-y-auto">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6 my-8">
    <button @click="hideModal('manual-forecast-modal')" type="button" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark fa-lg"></i></button>
    <h2 class="text-xl font-bold mb-6">Incluir {{ editingForecastEntry.forecast_type === 'receita' ? 'Receita' : 'Despesa' }} Manual na Previsão</h2>
    <form @submit.prevent="saveManualForecastEntry">
        <div class="space-y-3">
            <div>
                <label class="block text-sm font-medium">Data *</label>
                <input type="date" v-model="editingForecastEntry.entry_date" required class="form-input">
            </div>
            <div v-if="editingForecastEntry.forecast_type === 'receita'" class="relative">
                    <label class="block text-sm font-medium">{{ labels.patient }} (Opcional)</label>
                    <input type="text" v-model="manualForecastPatientSearch" @keyup="searchPatientsForManualForecast" :placeholder="'Digite para buscar um ' + labels.patient.toLowerCase() + '...'" class="form-input">
                    <div v-if="manualForecastPatientResults.length > 0 && manualForecastPatientSearch" class="absolute z-10 w-full bg-white border rounded-md mt-1 max-h-48 overflow-y-auto">
                        <a v-for="p in manualForecastPatientResults" :key="p.id" @click.prevent="selectPatientForManualForecast(p)" class="block px-4 py-2 text-sm hover:bg-gray-100 cursor-pointer">{{ p.name }}</a>
                    </div>
                    <div v-if="editingForecastEntry.patient_id" class="mt-2 flex items-center bg-blue-50 p-2 rounded-md text-sm">
                        <i class="fa-solid fa-user text-blue-500 mr-2"></i>
                        <span class="font-semibold text-blue-800">{{ getPatientName(editingForecastEntry.patient_id) }}</span>
                        <button type="button" @click="editingForecastEntry.patient_id = null; manualForecastPatientSearch = ''" class="ml-auto text-red-500 text-xs">Remover</button>
                    </div>
            </div>
            <div>
                <label class="block text-sm font-medium">Descrição *</label>
                <input type="text" v-model="editingForecastEntry.description" required class="form-input">
            </div>
            <div>
                <label class="block text-sm font-medium">Valor (R$) *</label>
                <input type="number" step="0.01" min="0.01" v-model.number="editingForecastEntry.installment_value" required class="form-input">
            </div>
        </div>
            <div class="flex justify-end gap-4 mt-8 pt-4 border-t">
            <button type="button" @click="hideModal('manual-forecast-modal')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancelar</button>
            <button type="submit" class="px-4 py-2 rounded-md text-white" :class="editingForecastEntry.forecast_type === 'receita' ? 'bg-teal-600 hover:bg-teal-700' : 'bg-orange-600 hover:bg-orange-700'">
                {{ editingForecastEntry.id ? 'Salvar Alteração' : 'Adicionar à Previsão' }}
            </button>
        </div>
    </form>
</div>
</div>

<div id="mark-as-paid-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden justify-center p-4 modal-overlay z-50 overflow-y-auto">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6 my-8">
    <button @click="hideModal('mark-as-paid-modal')" type="button" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark fa-lg"></i></button>
    <h2 class="text-xl font-bold mb-4">{{ editingPaymentForecast.forecast_type === 'receita' ? 'Registrar Recebimento' : 'Registrar Pagamento de Despesa' }}</h2>
    
    <div v-if="editingPaymentForecast.id">
        <div class="mb-4 p-3 bg-gray-50 rounded-md border text-sm">
            <p class="truncate"><strong>Descrição:</strong> {{ editingPaymentForecast.original_description }}</p>
            <p class="mt-1"><strong>Valor Pendente:</strong> <span class="font-semibold text-red-600">{{ formatCurrency(editingPaymentForecast.pending_value) }}</span></p>
        </div>

        <form @submit.prevent="markForecastAsPaid">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium">Data do {{ editingPaymentForecast.forecast_type === 'receita' ? 'Recebimento' : 'Pagamento' }} *</label>
                    <input type="date" v-model="editingPaymentForecast.payment_date" required class="form-input">
                </div>
                
                <div>
                    <label class="block text-sm font-medium">Forma de Pagamento *</label>
                    <select v-model="editingPaymentForecast.payment_method" required class="form-input">
                        <option v-for="method in entryPaymentMethods" :key="method.id" :value="method.name">
                            {{ method.name }}
                        </option>
                        <option v-if="!entryPaymentMethods.length" disabled>Carregando...</option>
                    </select>
                    </div>

                <div>
                    <label class="block text-sm font-medium">{{ editingPaymentForecast.forecast_type === 'receita' ? 'Valor Recebido (Bruto) *' : 'Valor Pago *' }}</label>
                    <input type="number" step="0.01" min="0.01" v-model.number="editingPaymentForecast.received_value" required class="form-input text-lg font-semibold" :class="editingPaymentForecast.forecast_type === 'receita' ? 'text-blue-600' : 'text-orange-600'">
                    <button v-if="editingPaymentForecast.forecast_type === 'receita'" type="button" @click="editingPaymentForecast.received_value = editingPaymentForecast.pending_value" class="text-xs text-blue-600 hover:underline mt-1">Usar valor total pendente</button>
                    <p v-if="editingPaymentForecast.forecast_type === 'despesa'" class="text-xs text-gray-500 mt-1">Se o valor pago for maior que o pendente, a diferença será lançada como "Encargos".</p>
                </div>
                
                <div v-if="editingPaymentForecast.forecast_type === 'receita' && (editingPaymentForecast.payment_method.toLowerCase().includes('cartão') || editingPaymentForecast.payment_method.toLowerCase().includes('cartao'))" class="pt-4 border-t">
                    <label class="block text-sm font-medium text-gray-700">Valor Líquido Recebido *</label>
                    <input type="number" step="0.01" min="0" v-model.number="editingPaymentForecast.net_received_value_manual" required class="form-input text-lg font-semibold text-green-600">
                    <p class="text-xs text-gray-500 mt-1">Informe o valor exato que caiu na conta (Bruto - Taxas).</p>
                </div>
                </div>

                <div class="flex justify-end gap-4 mt-8 pt-4 border-t">
                <button type="button" @click="hideModal('mark-as-paid-modal')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancelar</button>
                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                    {{ editingPaymentForecast.forecast_type === 'receita' ? 'Confirmar Recebimento' : 'Confirmar Pagamento' }}
                </button>
            </div>
        </form>
    </div>
</div>
</div>

<div id="finish-service-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden items-center justify-center p-4 modal-overlay z-[70]">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
        <h2 class="text-xl font-bold mb-4">Finalizar Atendimento</h2>
        <p class="text-gray-700 mb-6">
            Deseja finalizar o atendimento de <strong v-if="serviceToFinish">{{ serviceToFinish.patient_name }}</strong>?
            <br><br>
            Você pode finalizar e já deixar o retorno agendado, ou apenas finalizar.
        </p>
        <div class="flex flex-col gap-3">
            <button @click="confirmFinishService(true)" type="button" class="w-full px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 font-medium">
                <i class="fa-solid fa-calendar-check mr-2"></i> Finalizar e Ir para Reagendamento
            </button>
            <button @click="confirmFinishService(false)" type="button" class="w-full px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 font-medium">
                <i class="fa-solid fa-check mr-2"></i> Apenas Finalizar (Sem Reagendar)
            </button>
            <button @click="hideModal('finish-service-modal')" type="button" class="w-full px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">
                Cancelar
            </button>
        </div>
    </div>
</div>

<div id="finish-treatment-reason-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden items-center justify-center p-4 modal-overlay z-[80]">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
        <h2 class="text-xl font-bold mb-4">Finalizar Tratamento</h2>
        <p class="text-gray-700 mb-4">Por favor, informe o motivo do término do tratamento. O paciente será removido da Agenda de Espera e o último atendimento será marcado como "Tratamento Finalizado".</p>
        <div>
            <label for="finish-reason" class="block text-sm font-medium text-gray-700">Motivo *</label>
            <textarea id="finish-reason" v-model="finishTreatmentReason" rows="3" class="form-input mt-1" placeholder="Ex: Tratamento concluído com sucesso..."></textarea>
        </div>
        <div class="flex justify-end gap-4 mt-6">
            <button @click="hideModal('finish-treatment-reason-modal')" type="button" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Voltar</button>
            <button @click="confirmFinishTreatmentFromWaitingList" type="button" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">Confirmar Término</button>
        </div>
    </div>
</div>

<div id="finish-service-direct-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden items-center justify-center p-4 modal-overlay z-[70]">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
        <h2 class="text-xl font-bold mb-4">Concluir Atendimento</h2>
        <p class="text-gray-700 mb-6">
            Como deseja classificar a conclusão deste atendimento para <strong v-if="serviceToFinish">{{ serviceToFinish.patient_name }}</strong>?
        </p>
        <div class="flex flex-col gap-3">
            <button @click="confirmFinishDirect('service')" type="button" class="w-full px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 font-medium">
                <i class="fa-solid fa-check mr-2"></i> FINALIZAR ATENDIMENTO
            </button>
            <button @click="confirmFinishDirect('treatment')" type="button" class="w-full px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 font-medium">
                <i class="fa-solid fa-check-double mr-2"></i> FINALIZAR TRATAMENTO
            </button>
            <button @click="hideModal('finish-service-direct-modal')" type="button" class="w-full px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">
                Cancelar
            </button>
        </div>
    </div>
</div>























                
<div id="user-receipt-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden justify-center p-4 z-50 overflow-y-auto">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl p-6 my-8">
        <button @click="hideModal('user-receipt-modal')" type="button" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark fa-lg"></i></button>
        <h2 class="text-xl font-bold mb-4">{{ editingUserReceipt.id ? 'Editar Meu Modelo de Recibo' : 'Novo Modelo de Recibo' }}</h2>
        <p v-if="editingUserReceipt.originalIsGlobal" class="text-xs text-blue-600 mb-4 bg-blue-50 p-2 rounded border border-blue-200">Nota: Você está editando uma cópia de um modelo global. Salvar criará um novo modelo pessoal.</p>
        <form @submit.prevent="saveUserReceiptTemplate">
            <div class="mb-4">
                <label class="block text-sm font-medium">Título do Modelo *</label>
                <input type="text" v-model="editingUserReceipt.title" required class="form-input">
            </div>
            <div class="mb-4">
                <label class="flex items-center">
                    <input type="checkbox" v-model="editingUserReceipt.is_default" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <span class="ml-2 text-gray-700">Definir como meu modelo padrão</span>
                </label>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium">Conteúdo do Recibo</label>
                <div class="p-2 bg-gray-50 border rounded-md mb-2 text-xs text-gray-600">
                    <strong>Variáveis disponíveis:</strong>
                    [PACIENTE], [CPF], [VALOR], [VALOR_EXTENSO], [DATA], [RECIBO_NUMERO], [DESCRICAO],
                    [USUARIO_NOME], [USUARIO_PROFISSAO], [USUARIO_CPF], [CIDADE], [DATA_GERACAO], [USUARIO_REGISTRO]
                </div>
                <textarea v-model="editingUserReceipt.content" rows="15" class="form-input"></textarea>
            </div>
            <div class="flex justify-end gap-4 mt-6">
                <button type="button" @click="hideModal('user-receipt-modal')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancelar</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Salvar Modelo</button>
            </div>
        </form>
    </div>
</div>

<div id="receipt-generator-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden justify-center p-4 z-50 overflow-y-auto">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6 my-8">
        <button @click="hideModal('receipt-generator-modal')" type="button" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark fa-lg"></i></button>
        <h2 class="text-xl font-bold mb-6">Gerar Recibo</h2>

        <form @submit.prevent="generateAndPrintReceipt(false)">
            <div class="space-y-3">

                <div v-if="receiptGenerator.isAvulso" class="relative">
                    <label class="block text-sm font-medium">{{ labels.patient }} *</label>
                    <input type="text" v-model="receiptPatientSearch" @keyup="searchPatientsForReceipt" :placeholder="'Digite para buscar um ' + labels.patient.toLowerCase() + '...'" class="form-input">
                    <div v-if="receiptPatientResults.length > 0 && receiptPatientSearch" class="absolute z-10 w-full bg-white border rounded-md mt-1 max-h-48 overflow-y-auto">
                        <a v-for="p in receiptPatientResults" :key="p.id" @click.prevent="selectPatientForReceipt(p)" class="block px-4 py-2 text-sm hover:bg-gray-100 cursor-pointer">{{ p.name }}</a>
                    </div>
                    <div v-if="receiptGenerator.patient_id && !receiptPatientSearch" class="mt-2 flex items-center bg-blue-50 p-2 rounded-md text-sm">
                        <i class="fa-solid fa-user text-blue-500 mr-2"></i>
                        <span class="font-semibold text-blue-800">{{ receiptGenerator.patient_name }}</span>
                        <button @click="receiptGenerator.patient_id = null; receiptGenerator.patient_name = ''; receiptPatientSearch = '';" type="button" class="ml-auto text-red-500 text-xs">Remover</button>
                    </div>
                </div>
                <div v-else>
                    <label class="block text-sm font-medium">{{ labels.patient }} / Responsável *</label>
                    <input type="text" v-model="receiptGenerator.patient_name" class="form-input bg-gray-100" readonly>
                </div>

                <div>
                    <label class="block text-sm font-medium">CPF do Pagador *</label>
                    <input type="text" v-model="receiptGenerator.patient_cpf" placeholder="CPF (do paciente ou responsável)" required class="form-input"
                           :disabled="!receiptGenerator.isAvulso" :class="{'bg-gray-100': !receiptGenerator.isAvulso}">
                </div>
                <div>
                    <label class="block text-sm font-medium">Descrição do Serviço *</label>
                    <input type="text" v-model="receiptGenerator.description" required class="form-input">
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-sm font-medium">Valor (R$) *</label>
                        <input type="number" step="0.01" min="0.01" v-model.number="receiptGenerator.amount" required class="form-input"
                               :disabled="!receiptGenerator.isAvulso" :class="{'bg-gray-100': !receiptGenerator.isAvulso}">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Data do Pagamento *</label>
                        <input type="date" v-model="receiptGenerator.date" required class="form-input"
                               :disabled="!receiptGenerator.isAvulso" :class="{'bg-gray-100': !receiptGenerator.isAvulso}">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium">Modelo de Recibo *</label>
                    <select v-model="receiptGenerator.template_id" required class="form-input">
                        <option :value="null" disabled>Selecione um modelo...</option>
                        <option v-for="template in userReceiptTemplates.filter(t => !t.is_global)" :key="'user-'+template.id" :value="template.id">
                            {{ template.title }} {{ template.is_default ? '(Padrão)' : '' }}
                        </option>
                        <option disabled class="font-bold">--- Globais ---</option>
                        <option v-for="template in userReceiptTemplates.filter(t => t.is_global)" :key="'global-'+template.id" :value="template.id">
                            {{ template.title }} {{ template.is_default ? '(Padrão Global)' : '' }}
                        </option>
                        <option v-if="userReceiptTemplates.length === 0" disabled>Nenhum modelo encontrado.</option>
                    </select>
                </div>
            </div>

            <div class="flex justify-end gap-4 mt-8 pt-4 border-t">
                <button type="button" @click="hideModal('receipt-generator-modal')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancelar</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    {{ receiptGenerator.isAvulso ? 'Gerar Recibo Avulso' : 'Gerar e Imprimir Recibo' }}
                </button>
            </div>
        </form>
    </div>
</div>

<div id="patient-quick-view-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden justify-center p-4 modal-overlay z-50 overflow-y-auto">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6 relative my-8">
        <button @click="hideModal('patient-quick-view-modal')" type="button" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark fa-lg"></i></button>

        <div v-if="quickViewPatient">
            <div class="flex items-center space-x-4 mb-4">
                <img :src="quickViewPatient.photo || 'https://placehold.co/64x64/E2E8F0/A0AEC0?text=Foto'" @error="e => e.target.src='https://placehold.co/64x64/E2E8F0/A0AEC0?text=Foto'" class="w-16 h-16 rounded-full object-cover bg-gray-200">
                <div>
                    <h2 class="text-xl font-bold">{{ quickViewPatient.name }}</h2>
                    <p class="text-sm text-gray-600">{{ quickViewPatient.nickname }}</p>
                </div>
            </div>

            <div class="space-y-2 text-sm border-t pt-4">
                <div class="flex justify-between">
                    <span class="font-medium text-gray-500">Celular:</span>
                    <span class="font-semibold text-gray-800">{{ quickViewPatient.phone || '---' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="font-medium text-gray-500">Email:</span>
                    <span class="font-semibold text-gray-800">{{ quickViewPatient.email || '---' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="font-medium text-gray-500">CPF:</span>
                    <span class="font-semibold text-gray-800">{{ quickViewPatient.cpf || '---' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="font-medium text-gray-500">Data Nasc.:</span>
                    <span class="font-semibold text-gray-800">{{ quickViewPatient.birthdate ? formatDateForDisabledList(quickViewPatient.birthdate) : '---' }} ({{ calculateAge(quickViewPatient.birthdate) || '?' }} anos)</span>
                </div>
                <div class="flex justify-between">
                    <span class="font-medium text-gray-500">Responsável:</span>
                    <span class="font-semibold text-gray-800">{{ quickViewPatient.responsible_name || '---' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="font-medium text-gray-500">CPF Resp.:</span>
                    <span class="font-semibold text-gray-800">{{ quickViewPatient.responsible_cpf || '---' }}</span>
                </div>
            </div>

            <div class="flex gap-2 mt-6 pt-4 border-t">
                <button @click="openPatientModal(quickViewPatient); hideModal('patient-quick-view-modal');" type="button" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Ver Cadastro Completo</button>
                <button @click="openClinicalModalByPatientId(quickViewPatient.id); hideModal('patient-quick-view-modal');" type="button" class="flex-1 px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">{{ labels.clinicalData }}</button>
            </div>
        </div>

    </div>
</div>

<div id="edit-historical-service-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden justify-center p-4 modal-overlay z-50 overflow-y-auto">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6 my-8">
        <button @click="hideModal('edit-historical-service-modal')" type="button" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark fa-lg"></i></button>
        <h2 class="text-xl font-bold mb-4">Editar Atendimento Histórico</h2>

        <div v-if="editingHistoricalService.id">
            <p class="mb-4 text-sm">
                Editando atendimento <strong>#{{ editingHistoricalService.id }}</strong> de
                <a href="#" @click.prevent="openPatientQuickView(editingHistoricalService.patient_id)" class="clickable-patient-name" :title="`Ver dados de ${editingHistoricalService.patient_name}`">
                    {{ editingHistoricalService.patient_name }}
                </a>
            </p>

            <form @submit.prevent="updateHistoricalService">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Descrição do Atendimento *</label>
                        <textarea v-model="editingHistoricalService.description" required rows="3" class="form-input"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Status do Atendimento *</label>
                        <select v-model="editingHistoricalService.service_status" class="form-input">
                            <option v-for="opt in getOptionsByType('service_status')" :key="opt.id" :value="opt.option_value">
                                {{ opt.option_value }}
                            </option>
                            <option v-if="!getOptionsByType('service_status').length" disabled>Carregando...</option>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">
                            Nota: Alterar o status para "Em Atendimento" irá reabrir este serviço na lista de "Atendimentos Ativos".
                        </p>
                    </div>
                </div>

                <div class="flex justify-end gap-4 mt-8 pt-4 border-t">
                    <button type="button" @click="hideModal('edit-historical-service-modal')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancelar</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="odontogram-modal" class="fixed inset-0 bg-gray-900 bg-opacity-75 hidden items-center justify-center p-2 sm:p-4 z-[60] overflow-hidden">
    <div class="bg-white w-full h-full md:max-w-7xl md:h-[90vh] md:rounded-xl shadow-2xl relative flex flex-col overflow-hidden">
        
        <div class="flex justify-between items-center px-6 py-4 bg-gray-100 border-b flex-shrink-0">
            <div class="flex flex-col">
                <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                    <i class="fa-solid fa-tooth text-blue-600"></i> Odontograma
                </h2>
                <div class="flex items-center gap-4 mt-1">
                    <p class="text-sm text-gray-600">Paciente: <strong>{{ editingClinicalData ? editingClinicalData.name : '' }}</strong></p>
                    
                    <div class="flex items-center gap-2 bg-white px-2 py-1 rounded border border-gray-300 shadow-sm" v-if="odontogramVersions && odontogramVersions.length > 0">
                        <i class="fa-solid fa-code-branch text-gray-400 text-xs"></i>
                        <select v-model="currentOdontogramVersionId" @change="changeOdontogramVersion" class="text-xs border-none p-0 pr-6 focus:ring-0 font-semibold text-blue-900 cursor-pointer bg-transparent outline-none">
                            <option v-for="v in odontogramVersions" :key="v.id" :value="v.id">
                                {{ v.name }} ({{ new Date(v.created_at).toLocaleDateString('pt-BR') }})
                            </option>
                        </select>
                        <div class="h-4 w-px bg-gray-300 mx-1"></div>
                        <button @click="createNewOdontogramVersion" class="text-green-600 hover:text-green-800 px-1" title="Nova Versão"><i class="fa-solid fa-plus-circle"></i></button>
                        <button @click="deleteCurrentOdontogramVersion" class="text-red-400 hover:text-red-600 px-1" title="Excluir Versão"><i class="fa-solid fa-trash-alt"></i></button>
                    </div>
                    <div v-else>
                         <button @click="createNewOdontogramVersion" class="px-3 py-1 bg-blue-100 text-blue-700 rounded text-xs font-bold hover:bg-blue-200 shadow-sm">
                             <i class="fa-solid fa-plus mr-1"></i> Criar Odontograma Inicial
                         </button>
                    </div>
                </div>
            </div>
            
            <div class="flex items-center gap-3">
                <button @click="openDiagnosisConfigModal" class="text-gray-600 hover:text-blue-600 text-sm underline mr-2">
                    <i class="fa-solid fa-cog"></i> Configurar Diagnósticos
                </button>
                <button @click="hideModal('odontogram-modal'); openClinicalModal(editingClinicalData, 'odontogram')" class="text-gray-500 hover:text-gray-700">
                    <i class="fa-solid fa-xmark fa-2x"></i>
                </button>
            </div>
        </div>

        <div class="flex flex-col md:flex-row flex-grow overflow-hidden">
            
            <div class="flex-grow bg-white p-4 overflow-y-auto relative flex flex-col items-center select-none">
                
                <div v-if="isLoadingOdontogram" class="absolute inset-0 bg-white bg-opacity-80 flex items-center justify-center z-10">
                    <div class="animate-spin rounded-full h-12 w-12 border-t-4 border-b-4 border-blue-500"></div>
                </div>

                <div class="w-full max-w-4xl mb-4 flex justify-between items-center bg-blue-50 p-2 rounded border border-blue-100">
                    <div class="text-sm text-blue-800">
                        <span class="font-bold">Modo Atual:</span> 
                        <span v-if="selectedDiagnosis" class="ml-2 px-2 py-1 rounded text-white text-xs font-bold" :style="{backgroundColor: selectedDiagnosis.color}">{{ selectedDiagnosis.name }}</span>
                        <span v-else class="ml-2 text-gray-500">(Selecione um diagnóstico ao lado)</span>
                    </div>
                    <div class="text-xs text-gray-500">
                        Clique na face ou no centro do dente para aplicar.
                    </div>
                </div>

                <div class="flex flex-col gap-8 scale-95 md:scale-100 origin-top">
                    
                    <div class="flex gap-8 justify-center">
                        <div class="flex gap-1">
                            <div v-for="t in [18,17,16,15,14,13,12,11]" :key="t" class="flex flex-col items-center group">
                                <div class="text-xs font-bold mb-1 text-gray-500" :style="{color: getToothLabelColor(t)}">{{ t }}</div>
                                <div class="w-10 h-10 bg-gray-100 border border-gray-300 relative grid grid-cols-3 grid-rows-3 cursor-pointer shadow-sm hover:shadow-md transition-shadow">
                                    <div @click="handleToothClick(t, 'V')" class="col-start-2 row-start-1 border-b border-gray-300 hover:opacity-70 transition-colors" :style="{backgroundColor: getFaceColor(t, 'V')}"></div>
                                    
                                    <div @click="handleToothClick(t, 'L')" class="col-start-2 row-start-3 border-t border-gray-300 hover:opacity-70 transition-colors" :style="{backgroundColor: getFaceColor(t, 'L')}"></div>
                                    
                                    <div @click="handleToothClick(t, 'D')" class="col-start-1 row-start-2 border-r border-gray-300 hover:opacity-70 transition-colors" :style="{backgroundColor: getFaceColor(t, 'D')}"></div>
                                    
                                    <div @click="handleToothClick(t, 'M')" class="col-start-3 row-start-2 border-l border-gray-300 hover:opacity-70 transition-colors" :style="{backgroundColor: getFaceColor(t, 'M')}"></div>
                                    
                                    <div @click="handleToothClick(t, 'O')" class="col-start-2 row-start-2 hover:opacity-70 transition-colors" :style="{backgroundColor: getFaceColor(t, 'O')}"></div>
                                </div>
                                <div @click="handleToothClick(t, null)" class="w-4 h-4 mt-1 rounded-full border border-gray-300 cursor-pointer hover:bg-gray-200" :style="{backgroundColor: getFaceColor(t, null)}" title="Raiz / Dente Geral"></div>
                            </div>
                        </div>
                        
                        <div class="flex gap-1">
                            <div v-for="t in [21,22,23,24,25,26,27,28]" :key="t" class="flex flex-col items-center group">
                                <div class="text-xs font-bold mb-1 text-gray-500" :style="{color: getToothLabelColor(t)}">{{ t }}</div>
                                <div class="w-10 h-10 bg-gray-100 border border-gray-300 relative grid grid-cols-3 grid-rows-3 cursor-pointer shadow-sm hover:shadow-md transition-shadow">
                                    <div @click="handleToothClick(t, 'V')" class="col-start-2 row-start-1 border-b border-gray-300 hover:opacity-70 transition-colors" :style="{backgroundColor: getFaceColor(t, 'V')}"></div>
                                    <div @click="handleToothClick(t, 'L')" class="col-start-2 row-start-3 border-t border-gray-300 hover:opacity-70 transition-colors" :style="{backgroundColor: getFaceColor(t, 'L')}"></div>
                                    <div @click="handleToothClick(t, 'M')" class="col-start-1 row-start-2 border-r border-gray-300 hover:opacity-70 transition-colors" :style="{backgroundColor: getFaceColor(t, 'M')}"></div>
                                    <div @click="handleToothClick(t, 'D')" class="col-start-3 row-start-2 border-l border-gray-300 hover:opacity-70 transition-colors" :style="{backgroundColor: getFaceColor(t, 'D')}"></div>
                                    <div @click="handleToothClick(t, 'O')" class="col-start-2 row-start-2 hover:opacity-70 transition-colors" :style="{backgroundColor: getFaceColor(t, 'O')}"></div>
                                </div>
                                <div @click="handleToothClick(t, null)" class="w-4 h-4 mt-1 rounded-full border border-gray-300 cursor-pointer hover:bg-gray-200" :style="{backgroundColor: getFaceColor(t, null)}" title="Raiz / Dente Geral"></div>
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-8 justify-center">
                        <div class="flex gap-1">
                            <div v-for="t in [48,47,46,45,44,43,42,41]" :key="t" class="flex flex-col-reverse items-center group">
                                <div class="text-xs font-bold mt-1 text-gray-500" :style="{color: getToothLabelColor(t)}">{{ t }}</div>
                                <div class="w-10 h-10 bg-gray-100 border border-gray-300 relative grid grid-cols-3 grid-rows-3 cursor-pointer shadow-sm hover:shadow-md transition-shadow">
                                    <div @click="handleToothClick(t, 'V')" class="col-start-2 row-start-1 border-b border-gray-300 hover:opacity-70 transition-colors" :style="{backgroundColor: getFaceColor(t, 'V')}"></div>
                                    <div @click="handleToothClick(t, 'L')" class="col-start-2 row-start-3 border-t border-gray-300 hover:opacity-70 transition-colors" :style="{backgroundColor: getFaceColor(t, 'L')}"></div>
                                    <div @click="handleToothClick(t, 'D')" class="col-start-1 row-start-2 border-r border-gray-300 hover:opacity-70 transition-colors" :style="{backgroundColor: getFaceColor(t, 'D')}"></div>
                                    <div @click="handleToothClick(t, 'M')" class="col-start-3 row-start-2 border-l border-gray-300 hover:opacity-70 transition-colors" :style="{backgroundColor: getFaceColor(t, 'M')}"></div>
                                    <div @click="handleToothClick(t, 'O')" class="col-start-2 row-start-2 hover:opacity-70 transition-colors" :style="{backgroundColor: getFaceColor(t, 'O')}"></div>
                                </div>
                                <div @click="handleToothClick(t, null)" class="w-4 h-4 mb-1 rounded-full border border-gray-300 cursor-pointer hover:bg-gray-200" :style="{backgroundColor: getFaceColor(t, null)}" title="Raiz / Dente Geral"></div>
                            </div>
                        </div>
                        
                        <div class="flex gap-1">
                            <div v-for="t in [31,32,33,34,35,36,37,38]" :key="t" class="flex flex-col-reverse items-center group">
                                <div class="text-xs font-bold mt-1 text-gray-500" :style="{color: getToothLabelColor(t)}">{{ t }}</div>
                                <div class="w-10 h-10 bg-gray-100 border border-gray-300 relative grid grid-cols-3 grid-rows-3 cursor-pointer shadow-sm hover:shadow-md transition-shadow">
                                    <div @click="handleToothClick(t, 'V')" class="col-start-2 row-start-1 border-b border-gray-300 hover:opacity-70 transition-colors" :style="{backgroundColor: getFaceColor(t, 'V')}"></div>
                                    <div @click="handleToothClick(t, 'L')" class="col-start-2 row-start-3 border-t border-gray-300 hover:opacity-70 transition-colors" :style="{backgroundColor: getFaceColor(t, 'L')}"></div>
                                    <div @click="handleToothClick(t, 'M')" class="col-start-1 row-start-2 border-r border-gray-300 hover:opacity-70 transition-colors" :style="{backgroundColor: getFaceColor(t, 'M')}"></div>
                                    <div @click="handleToothClick(t, 'D')" class="col-start-3 row-start-2 border-l border-gray-300 hover:opacity-70 transition-colors" :style="{backgroundColor: getFaceColor(t, 'D')}"></div>
                                    <div @click="handleToothClick(t, 'O')" class="col-start-2 row-start-2 hover:opacity-70 transition-colors" :style="{backgroundColor: getFaceColor(t, 'O')}"></div>
                                </div>
                                <div @click="handleToothClick(t, null)" class="w-4 h-4 mb-1 rounded-full border border-gray-300 cursor-pointer hover:bg-gray-200" :style="{backgroundColor: getFaceColor(t, null)}" title="Raiz / Dente Geral"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="absolute bottom-4 left-4 text-xs text-gray-400 border p-2 rounded">
                    <p><strong>Legenda Faces:</strong></p>
                    <p>Centro: Oclusal/Incisal</p>
                    <p>Topo: Vestibular</p>
                    <p>Baixo: Lingual/Palatina</p>
                    <p>Laterais: Mesial/Distal (conforme quadrante)</p>
                    <p>Círculo Externo: Raiz / Dente Todo</p>
                </div>

            </div>

            <div class="w-full md:w-80 bg-gray-50 border-l border-gray-200 flex flex-col h-[40vh] md:h-auto">
                
                <div class="p-4 border-b border-gray-200 bg-white flex-shrink-0 max-h-[40%] overflow-y-auto">
                    <h3 class="font-bold text-sm text-gray-700 mb-2">Ferramentas</h3>
                    <div class="grid grid-cols-2 gap-2">
                        <button v-for="d in dentalDiagnoses" :key="d.id" 
                                @click="selectDiagnosis(d)"
                                class="flex items-center p-2 rounded border text-xs text-left hover:bg-gray-100 transition-colors"
                                :class="{'ring-2 ring-blue-500 bg-blue-50': selectedDiagnosis && selectedDiagnosis.id === d.id}">
                            <span class="w-3 h-3 rounded-full mr-2 flex-shrink-0" :style="{backgroundColor: d.color}"></span>
                            <span class="truncate">{{ d.name }}</span>
                        </button>
                    </div>
                    <p v-if="!dentalDiagnoses.length" class="text-xs text-gray-500 mt-2 text-center">Nenhum diagnóstico cadastrado.</p>
                </div>

                <div class="flex-grow p-4 overflow-y-auto bg-gray-50">
                    <h3 class="font-bold text-sm text-gray-700 mb-2">Histórico do Paciente</h3>
                    <div v-if="odontogramEntries.length === 0" class="text-center text-gray-400 text-xs py-4">
                        Nenhum registro.
                    </div>
                    <ul v-else class="space-y-2">
                        <li v-for="entry in odontogramEntries" :key="entry.id" class="bg-white p-2 rounded border shadow-sm text-xs flex justify-between items-start group">
                            <div>
                                <span class="font-bold text-gray-800">Dente {{ entry.tooth_number }}</span>
                                <span v-if="entry.face" class="ml-1 font-mono text-gray-500">({{ entry.face }})</span>
                                <span v-else class="ml-1 text-gray-500">(Geral)</span>
                                <br>
                                <span class="px-1.5 py-0.5 rounded text-white font-medium" :style="{backgroundColor: entry.diagnosis_color}" style="font-size: 10px;">{{ entry.diagnosis_name }}</span>
                                <p class="text-gray-400 mt-1" style="font-size: 10px;">{{ new Date(entry.created_at).toLocaleDateString('pt-BR') }}</p>
                            </div>
                            <button @click="removeOdontogramEntry(entry.id)" class="text-gray-300 hover:text-red-500 p-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="diagnosis-config-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden items-center justify-center p-4 z-[70]">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
        <h2 class="text-xl font-bold mb-4">Configurar Diagnósticos</h2>
        
        <form @submit.prevent="saveDiagnosis" class="mb-6 p-3 bg-gray-50 rounded border">
            <div class="grid grid-cols-2 gap-3 mb-3">
                <div class="col-span-2">
                    <label class="block text-xs font-medium text-gray-700">Nome</label>
                    <input type="text" v-model="editingDiagnosis.name" required class="form-input text-sm" placeholder="Ex: Restauração Resina">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Cor</label>
                    <input type="color" v-model="editingDiagnosis.color" class="w-full h-8 p-0 border-0 rounded cursor-pointer">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Tipo</label>
                    <select v-model="editingDiagnosis.type" class="form-input text-sm">
                        <option value="face">Face Específica</option>
                        <option value="tooth">Dente Inteiro</option>
                        <option value="root">Raiz</option>
                    </select>
                </div>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="px-3 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700">Salvar</button>
            </div>
        </form>

        <div class="max-h-64 overflow-y-auto border rounded">
            <table class="min-w-full text-xs">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="p-2 text-left">Nome</th>
                        <th class="p-2 text-center">Cor</th>
                        <th class="p-2 text-center">Tipo</th>
                        <th class="p-2 text-center">Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="d in dentalDiagnoses" :key="d.id" class="border-b hover:bg-gray-50">
                        <td class="p-2">
                            {{ d.name }}
                            <span v-if="d.is_global" class="text-blue-500 ml-1" title="Global (Não editável)">*</span>
                        </td>
                        <td class="p-2 text-center"><div class="w-4 h-4 rounded-full mx-auto" :style="{backgroundColor: d.color}"></div></td>
                        <td class="p-2 text-center capitalize">{{ d.type === 'tooth' ? 'Dente' : (d.type === 'root' ? 'Raiz' : 'Face') }}</td>
                        <td class="p-2 text-center">
                            <button v-if="!d.is_global" @click="deleteDiagnosis(d.id)" class="text-red-500 hover:text-red-700"><i class="fa-solid fa-trash"></i></button>
                            <span v-else class="text-gray-300"><i class="fa-solid fa-lock"></i></span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="flex justify-end mt-4">
            <button @click="hideModal('diagnosis-config-modal')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300 text-sm">Fechar</button>
        </div>
    </div>
</div>


<div id="clinical-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden justify-center p-4 modal-overlay overflow-y-auto z-40">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md sm:max-w-2xl lg:max-w-5xl p-6 my-8 relative">
        <button @click="hideModal('clinical-modal')" type="button" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark fa-lg"></i></button>
        
        <h2 class="text-2xl font-bold mb-6">{{ labels.clinicalData }}: 
            <a href="#" @click.prevent="openPatientQuickView(editingClinicalData.id)" class="clickable-patient-name" :title="`Ver dados de ${editingClinicalData.name}`">
                {{ editingClinicalData.name }}
            </a>
            </h2>
        <form @submit.prevent="savePatient(editingClinicalData, true)">
            <div class="border-b border-gray-200 mb-6">
                <nav class="-mb-px flex space-x-6 overflow-x-auto items-center">
                    <button type="button" @click="activeClinicalTab = 'anamnesis'" :class="{'active': activeClinicalTab === 'anamnesis'}" class="py-2 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 tab-button flex items-center whitespace-nowrap">{{ labels.anamnesis }}</button>
                    <button type="button" @click="exportPatientTabData(activeClinicalTab, editingClinicalData)" class="ml-auto text-gray-400 hover:text-blue-600 px-2" title="Exportar dados desta aba"><i class="fa-solid fa-save fa-lg"></i></button>
                    <button type="button" @click="activeClinicalTab = 'evolution'" :class="{'active': activeClinicalTab === 'evolution'}" class="py-2 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 tab-button flex items-center whitespace-nowrap">{{ labels.evolution }}</button>
                    <button type="button" @click="exportPatientTabData(activeClinicalTab, editingClinicalData)" class="ml-auto text-gray-400 hover:text-blue-600 px-2" title="Exportar dados desta aba"><i class="fa-solid fa-save fa-lg"></i></button>
                    <button type="button" @click="activeClinicalTab = 'exams'" :class="{'active': activeClinicalTab === 'exams'}" class="py-2 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 tab-button flex items-center whitespace-nowrap">{{ labels.exams }}</button>
                    <button type="button" @click="exportPatientTabData(activeClinicalTab, editingClinicalData)" class="ml-auto text-gray-400 hover:text-blue-600 px-2" title="Exportar dados desta aba"><i class="fa-solid fa-save fa-lg"></i></button>
                    
                    <button v-if="currentUser.odontogram_enabled == 1 && currentUser.system_version === 'Saude'" type="button" @click="activeClinicalTab = 'odontogram'" :class="{'active': activeClinicalTab === 'odontogram'}" class="py-2 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 tab-button flex items-center whitespace-nowrap">
                        <i class="fa-solid fa-tooth mr-1"></i> Odontograma
                    </button>

                    <button v-if="currentUser.system_version === 'Saude'" type="button" @click="activeClinicalTab = 'prescriptions'; fetchPatientPrescriptions(editingClinicalData.id)" :class="{'active': activeClinicalTab === 'prescriptions'}" class="py-2 px-1 border-b-2 border-transparent text-green-600 hover:text-green-800 hover:border-green-300 tab-button flex items-center whitespace-nowrap font-medium">
                        <i class="fa-solid fa-file-prescription mr-1"></i> Prescrições
                    </button>

                    <button type="button" @click="activeClinicalTab = 'budgets'; fetchPatientBudgets(editingClinicalData.id)" :class="{'active': activeClinicalTab === 'budgets'}" class="py-2 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 tab-button flex items-center whitespace-nowrap">Orçamentos</button>
                    <button type="button" @click="activeClinicalTab = 'documents'; fetchPatientPrescriptions(editingClinicalData.id)" :class="{'active': activeClinicalTab === 'documents'}" class="py-2 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 tab-button flex items-center whitespace-nowrap">Hist. Documentos</button>
                    <button type="button" @click="activeClinicalTab = 'appointments'; fetchPatientAppointments(editingClinicalData.id)" :class="{'active': activeClinicalTab === 'appointments'}" class="py-2 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 tab-button flex items-center whitespace-nowrap">Hist. Agendamentos</button>
                    
                    <button type="button" @click="activeClinicalTab = 'receipts'; fetchPatientReceipts(editingClinicalData.id); fetchUserReceiptTemplates()" :class="{'active': activeClinicalTab === 'receipts'}" class="py-2 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 tab-button flex items-center whitespace-nowrap">Hist. Recibos</button>
                    
                    <button type="button" @click="activeClinicalTab = 'services'; fetchPatientServices(editingClinicalData.id)" :class="{'active': activeClinicalTab === 'services'}" class="py-2 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 tab-button flex items-center whitespace-nowrap">Hist. Atendimentos</button>
                    
                    <button type="button" @click="exportPatientTabData(activeClinicalTab, editingClinicalData)" class="ml-auto text-gray-400 hover:text-blue-600 px-2" title="Exportar dados desta aba"><i class="fa-solid fa-save fa-lg"></i></button>
                </nav>
            </div>
            
            <div v-show="activeClinicalTab === 'anamnesis'">
                <div v-if="currentUser.system_version === 'Saude'" class="mb-4 p-3 bg-blue-50 border border-blue-100 rounded-md shadow-sm">
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="text-sm font-bold text-blue-800 flex items-center gap-2"><i class="fa-solid fa-heart-pulse"></i> Dados Vitais e Antropometria</h3>
                        <button type="button" @click="saveMeasurements" class="px-3 py-1 bg-green-600 text-white text-xs font-bold rounded hover:bg-green-700 transition-colors flex items-center gap-1 shadow-sm">
                            <i class="fa-solid fa-save"></i> Salvar Medidas
                        </button>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-8 gap-3">
    <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Altura (cm)</label>
        <input type="number" v-model="editingClinicalData.measure_height" class="form-input text-sm py-1 px-2" placeholder="Ex: 175">
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Peso (Kg)</label>
        <input type="number" step="0.1" v-model="editingClinicalData.measure_weight" class="form-input text-sm py-1 px-2" placeholder="Ex: 70.5">
    </div>
    
    <div class="relative">
        <label class="block text-xs font-medium text-gray-600 mb-1">IMC Calc.</label>
        <div class="flex items-center justify-center h-[30px] px-2 border rounded text-xs font-bold shadow-sm select-none"
             :class="{
                'bg-gray-100 text-gray-400': !editingClinicalData.measure_weight || !editingClinicalData.measure_height,
                'bg-yellow-100 text-yellow-700 border-yellow-200': (editingClinicalData.measure_weight / ((editingClinicalData.measure_height/100)*(editingClinicalData.measure_height/100))) < 18.5,
                'bg-green-100 text-green-700 border-green-200': (editingClinicalData.measure_weight / ((editingClinicalData.measure_height/100)*(editingClinicalData.measure_height/100))) >= 18.5 && (editingClinicalData.measure_weight / ((editingClinicalData.measure_height/100)*(editingClinicalData.measure_height/100))) < 25,
                'bg-orange-100 text-orange-700 border-orange-200': (editingClinicalData.measure_weight / ((editingClinicalData.measure_height/100)*(editingClinicalData.measure_height/100))) >= 25 && (editingClinicalData.measure_weight / ((editingClinicalData.measure_height/100)*(editingClinicalData.measure_height/100))) < 30,
                'bg-red-100 text-red-700 border-red-200': (editingClinicalData.measure_weight / ((editingClinicalData.measure_height/100)*(editingClinicalData.measure_height/100))) >= 30
             }">
            <span v-if="editingClinicalData.measure_weight && editingClinicalData.measure_height">
                {{ (editingClinicalData.measure_weight / ((editingClinicalData.measure_height/100)*(editingClinicalData.measure_height/100))).toFixed(1) }}
            </span>
            <span v-else>---</span>
        </div>
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Circ. Abd (cm)</label>
        <input type="number" v-model="editingClinicalData.measure_abd_circ" class="form-input text-sm py-1 px-2" placeholder="Ex: 90">
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">PA (mmHg)</label>
        <input type="text" v-model="editingClinicalData.measure_pa" maxlength="7" class="form-input text-sm py-1 px-2" placeholder="120/80" @blur="formatPA">
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">FR (ipm)</label>
        <input type="number" v-model="editingClinicalData.measure_fr" class="form-input text-sm py-1 px-2" placeholder="Ex: 18">
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">FC (bpm)</label>
        <input type="number" v-model="editingClinicalData.measure_fc" class="form-input text-sm py-1 px-2" placeholder="Ex: 80">
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Glicemia</label>
        <input type="number" v-model="editingClinicalData.measure_gc" class="form-input text-sm py-1 px-2" placeholder="mg/dL">
    </div>
</div>
                </div>

                <textarea v-model="editingClinicalData.anamnesisContent" rows="15" class="w-full rounded-md border-gray-300 shadow-sm" placeholder="Detalhes do anamnese..."></textarea>
            </div>
            
            
            <div v-show="activeClinicalTab === 'odontogram'">
                 <div class="flex flex-col items-center justify-center py-10 space-y-4">
                     <i class="fa-solid fa-tooth text-6xl text-blue-200"></i>
                     <p class="text-gray-600 text-lg">Clique abaixo para abrir o Odontograma Interativo</p>
                     <button type="button" @click="openOdontogramModal" class="px-6 py-3 bg-blue-600 text-white rounded-full hover:bg-blue-700 shadow-lg transform hover:scale-105 transition-transform font-semibold">
                         <i class="fa-solid fa-up-right-from-square mr-2"></i> Abrir Odontograma
                     </button>
                     <p class="text-xs text-gray-400 mt-2">Uma nova janela sobreposta será aberta para facilitar a visualização.</p>
                 </div>
            </div>
            
            
            
            <div v-show="activeClinicalTab === 'evolution'">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="font-semibold text-lg mb-2">Histórico de {{ labels.evolution }}</h3>
                        <div class="bg-gray-50 border rounded-md h-96 overflow-y-auto p-3 space-y-4">
                            <div v-if="clinicalEvolutions.length === 0" class="flex items-center justify-center h-full text-center text-gray-500">Nenhuma evolução registrada.</div>
                            <div v-else v-for="entry in clinicalEvolutions" :key="entry.id" class="text-sm">
                                <p class="font-semibold text-gray-500 border-b pb-1 mb-1 flex justify-between items-center">
                                    <span>{{ formatEntryDate(entry.created_at) }}</span>
                                </p>
                                <p class="whitespace-pre-wrap text-gray-800">{{ entry.content }}</p>
                            </div>
                        </div>
                    </div>
                    <div>
                        <h3 class="font-semibold text-lg mb-2">Nova Entrada</h3>
                        <textarea v-model="newEvolutionEntry" rows="15" class="w-full rounded-md border-gray-300 shadow-sm" :placeholder="`Digite a nova evolução do ${labels.patient.toLowerCase()} aqui...`"></textarea>
                        
                        <div class="flex gap-2 mt-2">
                            <button @click.prevent="sendEvolutionEmail('today')" type="button" class="text-sm px-3 py-1 bg-blue-100 text-blue-700 rounded-md hover:bg-blue-200"><i class="fa-solid fa-envelope mr-1"></i> Enviar Evolução (Hoje)</button>
                            <button @click.prevent="sendEvolutionEmail('all')" type="button" class="text-sm px-3 py-1 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200"><i class="fa-solid fa-envelope-open-text mr-1"></i> Enviar Histórico Completo</button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-show="activeClinicalTab === 'exams'">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="font-semibold text-lg mb-2">Histórico de {{ labels.exams }}</h3>
                        <div class="bg-gray-50 border rounded-md h-96 overflow-y-auto p-3 space-y-4">
                            <div v-if="clinicalExams.length === 0" class="flex items-center justify-center h-full text-center text-gray-500">Nenhuma anotação interna registrada.</div>
                            <div v-else v-for="entry in clinicalExams" :key="entry.id" class="text-sm">
                                <p class="font-semibold text-gray-500 border-b pb-1 mb-1">{{ formatEntryDate(entry.created_at) }}</p>
                                <p class="whitespace-pre-wrap text-gray-800">{{ entry.content }}</p>
                            </div>
                        </div>
                    </div>
                    <div>
                        <h3 class="font-semibold text-lg mb-2">Nova {{ labels.exam_singular }} Interna</h3>
                        <textarea v-model="newExamEntry" rows="15" class="w-full rounded-md border-gray-300 shadow-sm" :placeholder="`Digite a nova ${labels.exam_singular.toLowerCase()} interna aqui...`"></textarea>
                    </div>
                </div>
            </div>

            <div v-show="activeClinicalTab === 'prescriptions'">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    
                    <div class="bg-white border border-gray-200 rounded-lg p-6 text-center hover:shadow-md transition-shadow">
                        <i class="fa-solid fa-pen-to-square text-4xl text-blue-600 mb-3"></i>
                        <h3 class="font-bold text-lg text-gray-800">Prescrição Manual</h3>
                        <p class="text-sm text-gray-600 mb-4">Crie receitas, pedidos de exames e atestados para impressão.</p>
                        
                        <div class="flex flex-col gap-2">
                            <button type="button" @click="openPrescriptionGenerator(editingClinicalData, 'receita')" class="px-4 py-2 bg-blue-600 text-white font-semibold rounded-full hover:bg-blue-700 w-full">
                                Nova Receita
                            </button>
                            <button type="button" @click="openPrescriptionGenerator(editingClinicalData, 'exame')" class="px-4 py-2 bg-teal-600 text-white font-semibold rounded-full hover:bg-teal-700 w-full">
                                Solicitar Exames
                            </button>
                            <button type="button" @click="openLetterModal()" class="px-4 py-2 bg-gray-600 text-white font-semibold rounded-full hover:bg-gray-700 w-full">
                                Cartas / Risco Cir.
                            </button>
                        </div>
                    </div>

                    <div class="bg-green-50 border border-green-200 rounded-lg p-6 text-center hover:shadow-md transition-shadow">
                        <i class="fa-solid fa-laptop-medical text-4xl text-green-600 mb-3"></i>
                        <h3 class="font-bold text-lg text-gray-800">Prescrição Digital (MEMED)</h3>
                        <p class="text-sm text-gray-600 mb-4">Emita receitas digitais com validade jurídica, exames e atestados.</p>
                        <button v-if="currentUser.memed_enabled == 1 || currentUser.memed_enabled == '1'" @click="openMemed(editingClinicalData)" type="button" class="px-6 py-2 bg-green-600 text-white font-semibold rounded-full hover:bg-green-700 w-full">
                            Nova Prescrição Digital
                        </button>
                        <p v-else class="text-xs text-red-500 mt-2">Funcionalidade desativada. Ative em Configurações.</p>
                    </div>
                </div>
            </div>

            <div v-show="activeClinicalTab === 'documents'">
                <h3 class="text-lg font-bold mb-4 text-gray-700">Histórico de Documentos Emitidos</h3>
                <div class="overflow-x-auto border rounded-md max-h-96 overflow-y-auto">
                    <table class="min-w-full bg-white text-sm">
                        <thead class="bg-gray-50 sticky top-0">
                            <tr>
                                <th class="py-2 px-4 text-left font-medium text-gray-600 uppercase">Data</th>
                                <th class="py-2 px-4 text-left font-medium text-gray-600 uppercase">Tipo</th>
                                <th class="py-2 px-4 text-left font-medium text-gray-600 uppercase">Resumo</th>
                                <th class="py-2 px-4 text-center font-medium text-gray-600 uppercase">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-if="!editingClinicalData.prescriptions || editingClinicalData.prescriptions.length === 0">
                                <td colspan="4" class="text-center py-6 text-gray-500">Nenhum documento gerado.</td>
                            </tr>
                            <tr v-else v-for="presc in editingClinicalData.prescriptions" :key="presc.id">
                                <td class="py-2 px-4">{{ formatEntryDate(presc.created_at) }}</td>
                                <td class="py-2 px-4 capitalize">{{ presc.type }}</td>
                                <td class="py-2 px-4 truncate max-w-xs" :title="presc.final_content">{{ presc.final_content.replace(/<[^>]*>?/gm, '') }}</td>
                                <td class="py-2 px-4 text-center">
                                    <button type="button" @click="viewDocument(presc)" class="text-gray-500 hover:text-blue-600 mr-2" title="Visualizar / Imprimir">
                                        <i class="fa-solid fa-print"></i>
                                    </button>
                                    <button type="button" @click="emailDocument(presc.id)" class="text-gray-500 hover:text-purple-600" title="Enviar por E-mail">
                                        <i class="fa-solid fa-envelope"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-show="activeClinicalTab === 'appointments'">
                <h3 class="font-semibold text-lg">Histórico de Agendamentos</h3>
                <div class="bg-gray-50 border rounded-md h-96 overflow-y-auto mt-4">
                    <div v-if="!patientAppointments || patientAppointments.length === 0" class="flex items-center justify-center h-full text-center text-gray-500">
                        Nenhum agendamento encontrado para este paciente.
                    </div>
                    <table v-else class="min-w-full bg-white text-sm">
                        <thead class="bg-gray-100 border-b">
                            <tr>
                                <th class="py-2 px-3 text-left font-medium text-gray-600 uppercase">Data</th>
                                <th class="py-2 px-3 text-left font-medium text-gray-600 uppercase">Título</th>
                                <th class="py-2 px-3 text-left font-medium text-gray-600 uppercase">Status</th>
                                <th class="py-2 px-3 text-left font-medium text-gray-600 uppercase">Notas</th>
                                <th class="py-2 px-3 text-left font-medium text-gray-600 uppercase">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="appt in patientAppointments" :key="appt.id" class="hover:bg-gray-50">
                                <td class="py-2 px-3 whitespace-nowrap">{{ formatEntryDate(appt.start_time) }}</td>
                                <td class="py-2 px-3">
                                    <i v-if="isAppointmentFinalized(appt)" class="fa-solid fa-check-double text-green-600 mr-1" title="Atendimento Finalizado/Verificado"></i>
                                    {{ appt.title }}
                                </td>
                                <td class="py-2 px-3 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full status-uppercase" :class="getAppointmentStatusLabel(appt).class">
                                        {{ getAppointmentStatusLabel(appt).label }}
                                    </span>
                                </td>
                                <td class="py-2 px-3 truncate" :title="appt.notes">{{ appt.notes }}</td>
                                <td class="py-2 px-3">
                                    <button v-if="getAppointmentStatusLabel(appt).label === 'AGENDADO'" @click.prevent="sendReminderEmail(appt.id, editingClinicalData.name)" type="button" class="text-blue-600 hover:text-blue-800 mr-2" title="Enviar Confirmação por E-mail">
                                        <i class="fa-solid fa-paper-plane"></i>
                                    </button>
                                    
                                    <button v-if="isAppointmentMissed(appt) || appt.status === 'Não Compareceu'" 
                                           @click.prevent="rescheduleMissedAppointmentDirectly(appt)" type="button" 
                                           class="text-orange-600 hover:text-orange-800" title="Reagendar (Faltou)">
                                        <i class="fa-solid fa-clock-rotate-left"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div v-show="activeClinicalTab === 'services'">
                <h3 class="font-semibold text-lg">Histórico de Atendimentos</h3>
                <div class="bg-gray-50 border rounded-md h-96 overflow-y-auto mt-4">
                    <div v-if="!patientServices || patientServices.length === 0" class="flex items-center justify-center h-full text-center text-gray-500">
                        Nenhum atendimento (serviço) encontrado para este paciente.
                    </div>
                    <table v-else class="min-w-full bg-white text-sm">
                        <thead class="bg-gray-100 border-b">
                            <tr>
                                <th class="py-2 px-3 text-left font-medium text-gray-600 uppercase">Início</th>
                                <th class="py-2 px-3 text-left font-medium text-gray-600 uppercase">Descrição</th>
                                <th class="py-2 px-3 text-left font-medium text-gray-600 uppercase">Status</th>
                                <th class="py-2 px-3 text-left font-medium text-gray-600 uppercase">Conclusão</th>
                                <th class="py-2 px-3 text-center font-medium text-gray-600 uppercase">Ações/Docs</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="service in patientServices" :key="service.id" class="hover:bg-gray-50">
                                <td class="py-2 px-3 whitespace-nowrap">{{ formatEntryDate(service.start_date) }}</td>
                                <td class="py-2 px-3">{{ service.description }}</td>
                                <td class="py-2 px-3 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full status-uppercase" :class="getServiceStatusClass(service.service_status)">
                                        {{ service.service_status }}
                                    </span>
                                </td>
                                <td class="py-2 px-3 whitespace-nowrap">{{ service.end_date ? formatEntryDate(service.end_date) : '---' }}</td>
                                <td class="p-4 text-right flex justify-end gap-2">
                                            <!-- BOTÕES DE DOCUMENTOS RETROATIVOS (CORRIGIDOS) -->
                                            <button @click="generateCertificateFromHistory(service, 'atestado')" class="text-blue-600 hover:text-blue-800 p-1 border border-blue-200 rounded hover:bg-blue-50" title="Imprimir Atestado">
                                                <i class="fa-solid fa-user-doctor"></i>
                                            </button>
                                            <button @click="generateCertificateFromHistory(service, 'declaracao')" class="text-indigo-600 hover:text-indigo-800 p-1 border border-indigo-200 rounded hover:bg-indigo-50" title="Imprimir Declaração de Comparecimento">
                                                <i class="fa-solid fa-file-contract"></i>
                                            </button>
                                            
                                            <!-- Botão de Edição -->
                                            <button @click="openEditHistoricalServiceModal(service)" class="text-indigo-600 hover:text-indigo-900" :title="'Editar Atendimento #' + service.id"><i class="fa-solid fa-pen-to-square"></i></button>
                                            </button>
                                        </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-show="activeClinicalTab === 'receipts'">
                <h3 class="font-semibold text-lg mb-4">Histórico de Recibos</h3>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    
                    <div>
                        <h4 class="font-medium text-orange-600 mb-2">Pendentes de Recibo</h4>
                        <div class="bg-gray-50 border rounded-md h-96 overflow-y-auto">
                            <div v-if="!patientReceipts.pending || patientReceipts.pending.length === 0" class="flex items-center justify-center h-full text-center text-gray-500 p-4">
                                Nenhum lançamento pendente para este paciente.
                            </div>
                            <ul v-else class="divide-y divide-gray-200">
                                <li v-for="entry in patientReceipts.pending" :key="entry.id" class="p-3 hover:bg-gray-50 flex items-center gap-3">
                                    <div class="flex-1">
                                        <p class="text-sm font-semibold">{{ entry.description }}</p>
                                        <p class="text-xs text-gray-500">{{ formatDateForDisabledList(entry.entry_date) }} - <span class="font-medium text-green-700">{{ formatCurrency(entry.amount) }}</span></p>
                                    </div>
                                    <button @click.prevent="openReceiptGeneratorModal(entry)" class="px-3 py-1 bg-green-600 text-white text-xs rounded-md hover:bg-green-700 flex-shrink-0">Gerar</button>
                                </li>
                            </ul>
                        </div>
                    </div>
                    
                    <div>
                        <h4 class="font-medium text-blue-600 mb-2">Recibos Gerados</h4>
                        <div class="bg-gray-50 border rounded-md h-96 overflow-y-auto">
                            <div v-if="!patientReceipts.generated || patientReceipts.generated.length === 0" class="flex items-center justify-center h-full text-center text-gray-500 p-4">
                                Nenhum recibo gerado para este paciente.
                            </div>
                            <ul v-else class="divide-y divide-gray-200">
                                <li v-for="receipt in patientReceipts.generated" :key="receipt.id" class="p-3 hover:bg-gray-50 flex items-center gap-3">
                                    <div class="flex-1">
                                        <p class="text-sm font-semibold">Recibo Nº: <span class="text-gray-800">{{ receipt.receipt_nfe }}</span></p>
                                        <p class="text-xs text-gray-600 truncate" :title="receipt.description">{{ receipt.description }}</p>
                                        <p class="text-xs text-gray-500">{{ formatDateForDisabledList(receipt.entry_date) }} - <span class="font-medium text-green-700">{{ formatCurrency(receipt.amount) }}</span></p>
                                    </div>
                                    <div class="flex flex-col sm:flex-row gap-1 flex-shrink-0">
                                        <button @click.prevent="reprintReceipt(receipt)" class="px-2 py-1 bg-gray-600 text-white text-xs rounded-md hover:bg-gray-700" title="Imprimir/Salvar PDF"><i class="fa-solid fa-print"></i></button>
                                        <button @click.prevent="emailSelectedReceipts(receipt.id)" class="px-2 py-1 bg-blue-600 text-white text-xs rounded-md hover:bg-blue-700" title="Enviar por E-mail"><i class="fa-solid fa-paper-plane"></i></button>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>

                </div>
            </div>
                
                
            <div v-show="activeClinicalTab === 'budgets'">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-semibold text-lg">Histórico de Orçamentos</h3>
                    <button @click.prevent="openBudgetForm(editingClinicalData)" type="button" class="px-3 py-1 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm">
                        <i class="fa-solid fa-plus mr-1"></i> Novo Orçamento
                    </button>
                </div>
                <div class="overflow-x-auto modal-tab-list border rounded-md">
                    <table class="min-w-full bg-white">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                                <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase">Valor</th>
                                <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="budget in patientBudgets" :key="budget.id">
                                <td class="py-4 px-4 whitespace-nowrap">{{ new Date(budget.createdAt).toLocaleDateString('pt-BR') }}</td>
                                <td class="py-4 px-4 whitespace-nowrap">{{ formatCurrency(budget.final_total) }}</td>
                                <td class="py-4 px-4 whitespace-nowrap"><span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full status-uppercase" :class="getBudgetStatusClass(budget.status)">{{ budget.status }}</span></td>
                                
                                
                                <td class="py-4 px-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex items-center gap-3">
                                        <button @click.prevent="viewBudget(budget.id)" class="text-blue-600 hover:text-blue-900" title="Ver Detalhes / Editar"><i class="fa-solid fa-eye"></i></button>
                                        
                                        <button @click.prevent="printBudgetById(budget.id)" class="text-gray-600 hover:text-blue-900" title="Imprimir Orçamento"><i class="fa-solid fa-print"></i></button>
                                        <button v-if="budget.status !== defaultBudgetStatusApproved" @click.prevent="updateBudgetStatus(budget, defaultBudgetStatusApproved)" class="text-green-500 hover:text-green-700" title="Aprovar"><i class="fa-solid fa-check"></i></button>
                                        <button v-if="budget.status !== defaultBudgetStatusNegotiation" @click.prevent="updateBudgetStatus(budget, defaultBudgetStatusNegotiation)" class="text-blue-500 hover:text-blue-700" title="Marcar como 'Em Negociação'"><i class="fa-solid fa-user-clock"></i></button>
                                        <button v-if="budget.status !== defaultBudgetStatusRejected" @click.prevent="updateBudgetStatus(budget, defaultBudgetStatusRejected)" class="text-red-500 hover:text-red-700" title="Reprovar"><i class="fa-solid fa-times"></i></button>

                                        <div class="relative">
                                            <button @click.prevent="toggleStatusMenu(budget)" class="p-1 rounded-full hover:bg-gray-200 focus:outline-none" title="Alterar Status">
                                                <i class="fa-solid fa-ellipsis-v text-gray-600"></i>
                                            </button>
                                            <div v-if="budget.showStatusMenu" class="origin-top-right absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-10">
                                                <div class="py-1" role="menu" aria-orientation="vertical">
                                                    <span class="block px-4 pt-2 pb-1 text-xs text-gray-500">Alterar status para:</span>
                                                    <a v-for="opt in getOptionsByType('budget_status')" :key="opt.id" href="#" @click.prevent="updateBudgetStatus(budget, opt.option_value)" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 status-uppercase">
                                                        <i :class="{
                                                            'fa-solid fa-check w-5 text-green-500': opt.option_value === defaultBudgetStatusApproved,
                                                            'fa-solid fa-user-clock w-5 text-blue-500': opt.option_value === defaultBudgetStatusNegotiation,
                                                            'fa-solid fa-times w-5 text-red-500': opt.option_value === defaultBudgetStatusRejected,
                                                            'fa-solid fa-ban w-5 text-gray-500': opt.option_value === defaultBudgetStatusCanceled,
                                                            'fa-solid fa-hourglass-half w-5 text-yellow-500': opt.option_value === defaultBudgetStatusPending || opt.option_value === defaultBudgetStatusWaitingApproval,
                                                            'fa-solid fa-question-circle w-5 text-gray-400': ![defaultBudgetStatusApproved, defaultBudgetStatusNegotiation, defaultBudgetStatusRejected, defaultBudgetStatusCanceled, defaultBudgetStatusPending, defaultBudgetStatusWaitingApproval].includes(opt.option_value)
                                                        }"></i>
                                                        {{ opt.option_value }}
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                        <button @click.prevent="deleteBudget(budget.id)" class="text-gray-400 hover:text-red-600" title="Excluir"><i class="fa-solid fa-trash-can"></i></button>
                                    </div>
                                </td>
                                </tr>
                            <tr v-if="patientBudgets.length === 0">
                                <td colspan="4" class="text-center py-8 text-gray-500">Nenhum orçamento encontrado para este {{ labels.patient.toLowerCase() }}.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="flex justify-end items-center gap-4 mt-8 pt-4 border-t">
                <button type="button" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300" @click="hideModal('clinical-modal')">Fechar</button>
                <button v-if="activeClinicalTab !== 'budgets' && activeClinicalTab !== 'appointments' && activeClinicalTab !== 'services' && activeClinicalTab !== 'receipts' && activeClinicalTab !== 'prescriptions' && activeClinicalTab !== 'documents'" type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Salvar Alterações</button>
            </div>
        </form>
    </div>
</div>






















<div id="letters-selection-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden justify-center p-4 modal-overlay z-[60] overflow-y-auto">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6 my-8 relative">
        <button @click="hideModal('letters-selection-modal')" type="button" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark fa-lg"></i></button>
        
        <h2 class="text-xl font-bold mb-2 text-gray-800"><i class="fa-solid fa-file-signature mr-2"></i>Cartas e Risco Cirúrgico</h2>
        <p class="text-sm text-gray-500 mb-6">Selecione um modelo abaixo para carregar e editar:</p>

        <div v-if="isLoading" class="text-center py-4"><i class="fa-solid fa-circle-notch fa-spin"></i> Carregando modelos...</div>
        
        <div v-else class="space-y-2 max-h-[60vh] overflow-y-auto pr-2">
            <div v-if="!getLettersTemplates().length" class="text-center text-gray-400 py-4 border-2 border-dashed rounded">
                Nenhum modelo do tipo "Outros" encontrado.
            </div>

            <button v-for="tpl in getLettersTemplates()" :key="tpl.id" 
                    @click="selectLetterTemplate(tpl)"
                    class="w-full text-left p-3 rounded border border-gray-200 hover:bg-blue-50 hover:border-blue-300 transition-colors group">
                <div class="font-bold text-gray-700 group-hover:text-blue-700">{{ tpl.title }}</div>
                <div class="text-xs text-gray-400 truncate">{{ tpl.content.substring(0, 60) }}...</div>
            </button>
        </div>
        
        <div class="mt-6 pt-4 border-t flex justify-end">
            <button @click="hideModal('letters-selection-modal')" class="px-4 py-2 bg-gray-200 rounded text-gray-700 hover:bg-gray-300">Fechar</button>
        </div>
    </div>
</div>

<div id="letter-editor-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden justify-center p-4 modal-overlay z-[70] overflow-y-auto">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl p-6 my-8 flex flex-col h-[90vh]">
        <div class="flex justify-between items-center mb-4 flex-shrink-0">
            <h2 class="text-xl font-bold text-gray-800"><i class="fa-solid fa-pen-to-square mr-2"></i>Editar Documento</h2>
            <button @click="hideModal('letter-editor-modal')" type="button" class="text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark fa-lg"></i></button>
        </div>
        
        <div class="flex-grow flex flex-col overflow-hidden" v-if="editingLetter">
            <label class="block text-sm font-medium text-gray-700 mb-2">Conteúdo do Documento (Editável)</label>
            <div class="flex-grow border rounded-md p-4 bg-gray-50 overflow-y-auto">
                <textarea v-model="editingLetter.content" class="w-full h-full p-2 bg-white border-0 focus:ring-0 resize-none font-sans text-lg leading-relaxed" spellcheck="false"></textarea>
            </div>
            <p class="text-xs text-gray-500 mt-2"><i class="fa-solid fa-info-circle"></i> As variáveis já foram substituídas. O que você vê aqui é o que será impresso.</p>
        </div>

        <div class="flex justify-end gap-4 mt-4 pt-4 border-t flex-shrink-0">
            <button @click="hideModal('letter-editor-modal')" type="button" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancelar</button>
            <button @click="printLetter" type="button" class="px-6 py-2 bg-blue-600 text-white font-bold rounded-md hover:bg-blue-700 shadow-md">
                <i class="fa-solid fa-print mr-2"></i> Imprimir / Salvar
            </button>
        </div>
    </div>
</div>


<div id="maintenance-auth-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden justify-center p-4 modal-overlay z-[60]">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6 my-8">
        <h2 class="text-xl font-bold mb-4 text-red-600"><i class="fa-solid fa-triangle-exclamation mr-2"></i>Confirmação de Segurança</h2>
        <p class="text-gray-700 mb-4 font-medium">Esta ação apagará dados permanentemente e não poderá ser desfeita.</p>
        
        <p v-if="maintenanceAuth.mode === 'financial'" class="text-sm text-gray-600 mb-4 bg-red-50 p-2 rounded border border-red-100">
            Para limpeza financeira total, é necessário confirmar sua identidade duplamente.
        </p>

        <form @submit.prevent="performMaintenance">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Senha Administrativa do Usuário</label>
                    <input type="password" v-model="maintenanceAuth.admin_password" required class="form-input mt-1" placeholder="Senha de Manutenção">
                </div>

                <div v-if="maintenanceAuth.mode === 'financial'">
                    <label class="block text-sm font-medium text-gray-700">Sua Senha de Login</label>
                    <input type="password" v-model="maintenanceAuth.login_password" required class="form-input mt-1" placeholder="Confirme sua senha de acesso">
                </div>
            </div>

            <div class="flex justify-end gap-4 mt-6 pt-4 border-t">
                <button type="button" @click="hideModal('maintenance-auth-modal')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancelar</button>
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 font-bold">
                    CONFIRMAR EXCLUSÃO
                </button>
            </div>
        </form>
    </div>
</div>

<div id="prescription-generator-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden justify-center p-4 modal-overlay z-50 overflow-y-auto">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-6xl p-6 my-8 relative">
        <button @click="closePrescriptionGenerator" type="button" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark fa-lg"></i></button>
        
        <h2 class="text-2xl font-bold mb-2 capitalize border-b pb-2">Pedido de {{ prescriptionForm.type }}</h2>

        <div class="mb-6 flex justify-between items-center bg-blue-50 p-3 rounded-md border border-blue-100">
            <span class="text-sm text-blue-800 font-medium"><i class="fa-solid fa-lightbulb mr-2"></i>Agilize o atendimento:</span>
            <select @change="applyPrescriptionTemplate($event.target.value); $event.target.value=''" class="form-select text-sm border-blue-300 rounded-md text-blue-900 w-64">
                <option value="" selected disabled>Carregar Padrão de Receita...</option>
                <option v-for="tpl in prescriptionTemplates.filter(t => t.type === prescriptionForm.type)" :key="tpl.id" :value="tpl.id">
                  {{ tpl.title }} {{ tpl.is_global ? '(Global)' : '' }}
                </option>
            </select>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 flex flex-col gap-4">
                <h3 class="font-semibold text-gray-700 flex items-center"><i class="fa-solid fa-plus-circle mr-2"></i> Adicionar Item</h3>
                
                <div class="relative">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Buscar Medicamento/Exame</label>
                    <div class="flex gap-2">
                        <input type="text" v-model="medicineSearchQuery" @input="searchMedicines($event.target.value)" placeholder="Digite para buscar..." class="form-input text-sm flex-grow">
                        <button v-if="medicineSearchQuery" @click="medicineSearchQuery = ''; medicines = []" class="text-gray-400 hover:text-gray-600"><i class="fa-solid fa-times"></i></button>
                    </div>
                    <div v-if="medicines.length > 0 && medicineSearchQuery" class="absolute z-10 w-full bg-white border rounded-md mt-1 max-h-48 overflow-y-auto shadow-lg">
                        <a v-for="med in medicines" :key="med.id" @click.prevent="selectMedicine(med)" class="block px-4 py-2 text-sm hover:bg-blue-50 cursor-pointer border-b last:border-0">
                            <div class="font-semibold text-gray-800">
                                {{ med.name }} 
                                <span v-if="med.source === 'external'" class="text-xs text-orange-500 ml-1 font-normal">(Banco Nacional)</span>
                            </div>
                            <div class="text-xs text-gray-500 truncate">{{ med.presentation || med.instructions }}</div>
                        </a>
                    </div>
                    <div v-if="medicines.length === 0 && medicineSearchQuery.length >= 3" class="absolute z-10 w-full bg-white border rounded-md mt-1 p-2 text-center text-gray-500 shadow-lg text-xs">
                        Nenhum medicamento encontrado.
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600">Nome do Item *</label>
                        <input type="text" v-model="tempPrescriptionItem.name" class="form-input text-sm" placeholder="Ex: Amoxicilina 500mg">
                    </div>
                    
                    <div v-if="prescriptionForm.type === 'receita'" class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600">Apresentação</label>
                            <input type="text" v-model="tempPrescriptionItem.presentation" class="form-input text-sm" placeholder="Ex: Caixa com 21 comp.">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600">Via de Administração</label>
                            <select v-model="tempPrescriptionItem.route" class="form-input text-sm">
                                <option value="">Selecione...</option>
                                <option v-for="opt in getOptionsByType('administration_route')" :key="opt.id" :value="opt.option_value">{{ opt.option_value }}</option>
                            </select>
                        </div>
                    </div>

                    <div v-if="prescriptionForm.type === 'receita'" class="grid grid-cols-1">
                        <label class="block text-xs font-medium text-gray-600">Posologia / Instruções *</label>
                        <textarea v-model="tempPrescriptionItem.instructions" rows="3" class="form-input text-sm" placeholder="Ex: Tomar 1 comprimido de 8 em 8 horas..."></textarea>
                    </div>
                    <div v-else class="grid grid-cols-1">
                         <label class="block text-xs font-medium text-gray-600">Descrição / Justificativa</label>
                        <textarea v-model="tempPrescriptionItem.instructions" rows="3" class="form-input text-sm" placeholder="Detalhes do exame ou atestado..."></textarea>
                    </div>

                    <div v-if="prescriptionForm.type === 'receita'">
                        <label class="block text-xs font-medium text-gray-600">Duração</label>
                        <input type="text" v-model="tempPrescriptionItem.duration" class="form-input text-sm" placeholder="Ex: 7 dias">
                    </div>
                </div>

                <button type="button" @click="addPrescriptionItem" class="mt-2 w-full py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 font-medium text-sm shadow-sm">
                    <i class="fa-solid fa-arrow-right mr-1"></i> Adicionar à Lista
                </button>
            </div>

            <div class="flex flex-col h-full">
                <h3 class="font-semibold text-gray-700 mb-2 flex items-center justify-between">
                    <span><i class="fa-solid fa-list-ul mr-2"></i> Itens da Prescrição</span>
                    <button v-if="prescriptionForm.items.length > 0 || prescriptionForm.recommendations" @click="clearPrescription" class="text-xs text-red-500 hover:underline">Limpar Tudo</button>
                </h3>
                
                <div class="flex-grow border rounded-md bg-white overflow-y-auto p-4 space-y-4 min-h-[300px] shadow-inner">
                    <div v-if="prescriptionForm.items.length === 0" class="text-center text-gray-400 py-10">
                        <i class="fa-solid fa-prescription-bottle-alt text-4xl mb-2 opacity-50"></i>
                        <p>Nenhum item adicionado ainda.</p>
                    </div>

                    <div v-else>
                        <div v-for="(item, idx) in prescriptionForm.items" :key="idx" class="group relative border-b last:border-0 pb-3 mb-3">
                            <div class="flex justify-between items-start">
                                <div>
                                    <div class="font-bold text-gray-800 text-lg">{{ item.name }}</div>
                                    <div class="text-sm text-gray-600" v-if="item.presentation">{{ item.presentation }}</div>
                                    <div class="text-xs font-semibold text-blue-700 mt-1 uppercase bg-blue-50 inline-block px-1 rounded" v-if="item.route">{{ item.route }}</div>
                                </div>
                                <button @click="removePrescriptionItem(idx)" class="text-gray-300 hover:text-red-500 p-1"><i class="fa-solid fa-trash"></i></button>
                            </div>
                            <div class="mt-2 text-gray-700 bg-gray-50 p-2 rounded text-sm border-l-4 border-blue-200">
                                <span class="font-semibold">Uso:</span> {{ item.instructions }}
                            </div>
                            <div class="mt-1 text-sm text-gray-500" v-if="item.duration">
                                <span class="font-medium">Duração:</span> {{ item.duration }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-4 border-t">
                    <div class="flex justify-between items-end mb-2">
                         <label class="block text-sm font-medium text-gray-700">Recomendações / Rodapé</label>
                         <select v-model="selectedRecommendationTemplate" @change="applyRecommendationTemplate" class="form-select text-xs w-1/2 border-gray-300 rounded">
                             <option :value="null">Carregar Modelo...</option>
                             <option v-for="tpl in recommendationTemplates" :key="tpl.id" :value="tpl">{{ tpl.title }}</option>
                         </select>
                    </div>
                    <textarea v-model="prescriptionForm.recommendations" rows="3" class="form-input text-sm bg-yellow-50 border-yellow-200 text-gray-700" placeholder="Ex: Suspender uso em caso de alergia..."></textarea>
                </div>
            </div>
        </div>

        <div class="flex justify-between items-center gap-4 mt-6 pt-6 border-t bg-gray-50 -mx-6 -mb-6 p-6 rounded-b-lg">
            <button type="button" @click="savePrescriptionAsModel" class="px-4 py-2 bg-indigo-100 text-indigo-700 rounded-md hover:bg-indigo-200 font-medium">
                <i class="fa-solid fa-bookmark mr-1"></i> Salvar como Modelo
            </button>

            <div class="flex gap-3">
                <button type="button" @click="closePrescriptionGenerator" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50">Cancelar</button>
                <button type="button" @click="saveAndPrintPrescription" class="px-6 py-2 bg-green-600 text-white font-bold rounded-md hover:bg-green-700 shadow-lg transform hover:scale-105 transition-all">
                    <i class="fa-solid fa-print mr-2"></i> Emitir Pedido de {{ prescriptionForm.type }}
                </button>
            </div>
        </div>
    </div>
</div>

<div id="ledger-entry-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden justify-center p-4 modal-overlay z-50 overflow-y-auto">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6 my-8">
        <button @click="hideModal('ledger-entry-modal')" type="button" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark fa-lg"></i></button>
        <h2 class="text-xl font-bold mb-6">{{ editingLedgerEntry.id ? 'Editar Lançamento' : 'Novo Lançamento' }} - <span :class="editingLedgerEntry.entry_type === 'entrada' ? 'text-blue-600' : 'text-red-600'">{{ editingLedgerEntry.entry_type === 'entrada' ? 'Entrada' : 'Saída' }}</span></h2>
        <form @submit.prevent="saveLedgerEntry">
            <input type="hidden" v-model="editingLedgerEntry.id">
            <input type="hidden" v-model="editingLedgerEntry.entry_type">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 mb-4">
                <div>
                    <label class="block text-sm font-medium">Nº Ordem</label>
                    <input type="text" v-model="editingLedgerEntry.entry_order" placeholder="Manual" class="form-input"
                            :disabled="editingLedgerEntry.id" :class="{'bg-gray-100': editingLedgerEntry.id}">
                    </div>
                <div>
                    <label class="block text-sm font-medium">Data *</label>
                    <input type="date" v-model="editingLedgerEntry.entry_date" required class="form-input"
                            :disabled="editingLedgerEntry.id" :class="{'bg-gray-100': editingLedgerEntry.id}">
                    </div>
                <div>
                    <label class="block text-sm font-medium">Recibo / NFe</label>
                    <input type="text" v-model="editingLedgerEntry.receipt_nfe" class="form-input">
                </div>
            </div>
            
            <div class="relative mb-4">
                <label class="block text-sm font-medium">{{ labels.patient }} (Opcional)</label>
                <input type="text" v-model="ledgerPatientSearch" @keyup="searchPatientsForLedger" :placeholder="'Digite para buscar um ' + labels.patient.toLowerCase() + '...'" class="form-input"
                        :disabled="editingLedgerEntry.id && editingLedgerEntry.patient_id" :class="{'bg-gray-100': editingLedgerEntry.id && editingLedgerEntry.patient_id}">
                <div v-if="ledgerPatientResults.length > 0 && ledgerPatientSearch" class="absolute z-10 w-full bg-white border rounded-md mt-1 max-h-48 overflow-y-auto">
                    <a v-for="p in ledgerPatientResults" :key="p.id" @click.prevent="selectPatientForLedger(p)" class="block px-4 py-2 text-sm hover:bg-gray-100 cursor-pointer">{{ p.name }}</a>
                </div>
                <div v-if="editingLedgerEntry.patient_id && !ledgerPatientSearch" class="mt-2 flex items-center bg-blue-50 p-2 rounded-md text-sm">
                    <i class="fa-solid fa-user text-blue-500 mr-2"></i>
                    <span class="font-semibold text-blue-800">{{ getPatientName(editingLedgerEntry.patient_id) }}</span>
                    <button type="button" @click="editingLedgerEntry.patient_id = null; ledgerPatientSearch = ''" class="ml-auto text-red-500 text-xs"
                            :disabled="editingLedgerEntry.id && editingLedgerEntry.patient_id">Remover</button>
                </div>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium">Descrição *</label>
                <input type="text" v-model="editingLedgerEntry.description" required class="form-input">
            </div>
            <div>
                <label class="block text-sm font-medium">Valor (R$) *</label>
                <input type="number" step="0.01" min="0.01" v-model.number="editingLedgerEntry.amount" required class="form-input" 
                        :class="editingLedgerEntry.entry_type === 'entrada' ? 'text-blue-600 font-semibold' : 'text-red-600 font-semibold'"
                        :disabled="editingLedgerEntry.id" :class="{'bg-gray-100': editingLedgerEntry.id}">
                </div>

            <div class="flex justify-end gap-4 mt-8 pt-4 border-t">
                <button type="button" @click="hideModal('ledger-entry-modal')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancelar</button>
                <button type="submit" class="px-4 py-2 rounded-md text-white" :class="editingLedgerEntry.entry_type === 'entrada' ? 'bg-blue-600 hover:bg-blue-700' : 'bg-red-600 hover:bg-red-700'">
                    {{ editingLedgerEntry.id ? 'Salvar Alteração' : 'Adicionar Lançamento' }}
                </button>
            </div>
        </form>
    </div>
</div>

<div id="manual-forecast-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden justify-center p-4 modal-overlay z-50 overflow-y-auto">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6 my-8">
    <button @click="hideModal('manual-forecast-modal')" type="button" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark fa-lg"></i></button>
    <h2 class="text-xl font-bold mb-6">Incluir {{ editingForecastEntry.forecast_type === 'receita' ? 'Receita' : 'Despesa' }} Manual na Previsão</h2>
    <form @submit.prevent="saveManualForecastEntry">
        <div class="space-y-3">
            <div>
                <label class="block text-sm font-medium">Data *</label>
                <input type="date" v-model="editingForecastEntry.entry_date" required class="form-input">
            </div>
            <div v-if="editingForecastEntry.forecast_type === 'receita'" class="relative">
                    <label class="block text-sm font-medium">{{ labels.patient }} (Opcional)</label>
                    <input type="text" v-model="manualForecastPatientSearch" @keyup="searchPatientsForManualForecast" :placeholder="'Digite para buscar um ' + labels.patient.toLowerCase() + '...'" class="form-input">
                    <div v-if="manualForecastPatientResults.length > 0 && manualForecastPatientSearch" class="absolute z-10 w-full bg-white border rounded-md mt-1 max-h-48 overflow-y-auto">
                        <a v-for="p in manualForecastPatientResults" :key="p.id" @click.prevent="selectPatientForManualForecast(p)" class="block px-4 py-2 text-sm hover:bg-gray-100 cursor-pointer">{{ p.name }}</a>
                    </div>
                    <div v-if="editingForecastEntry.patient_id" class="mt-2 flex items-center bg-blue-50 p-2 rounded-md text-sm">
                        <i class="fa-solid fa-user text-blue-500 mr-2"></i>
                        <span class="font-semibold text-blue-800">{{ getPatientName(editingForecastEntry.patient_id) }}</span>
                        <button type="button" @click="editingForecastEntry.patient_id = null; manualForecastPatientSearch = ''" class="ml-auto text-red-500 text-xs">Remover</button>
                    </div>
            </div>
            <div>
                <label class="block text-sm font-medium">Descrição *</label>
                <input type="text" v-model="editingForecastEntry.description" required class="form-input">
            </div>
            <div>
                <label class="block text-sm font-medium">Valor (R$) *</label>
                <input type="number" step="0.01" min="0.01" v-model.number="editingForecastEntry.installment_value" required class="form-input">
            </div>
        </div>
            <div class="flex justify-end gap-4 mt-8 pt-4 border-t">
            <button type="button" @click="hideModal('manual-forecast-modal')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancelar</button>
            <button type="submit" class="px-4 py-2 rounded-md text-white" :class="editingForecastEntry.forecast_type === 'receita' ? 'bg-teal-600 hover:bg-teal-700' : 'bg-orange-600 hover:bg-orange-700'">
                {{ editingForecastEntry.id ? 'Salvar Alteração' : 'Adicionar à Previsão' }}
            </button>
        </div>
    </form>
</div>
</div>

<div id="mark-as-paid-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden justify-center p-4 modal-overlay z-50 overflow-y-auto">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6 my-8">
    <button @click="hideModal('mark-as-paid-modal')" type="button" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark fa-lg"></i></button>
    <h2 class="text-xl font-bold mb-4">{{ editingPaymentForecast.forecast_type === 'receita' ? 'Registrar Recebimento' : 'Registrar Pagamento de Despesa' }}</h2>
    
    <div v-if="editingPaymentForecast.id">
        <div class="mb-4 p-3 bg-gray-50 rounded-md border text-sm">
            <p class="truncate"><strong>Descrição:</strong> {{ editingPaymentForecast.original_description }}</p>
            <p class="mt-1"><strong>Valor Pendente:</strong> <span class="font-semibold text-red-600">{{ formatCurrency(editingPaymentForecast.pending_value) }}</span></p>
        </div>

        <form @submit.prevent="markForecastAsPaid">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium">Data do {{ editingPaymentForecast.forecast_type === 'receita' ? 'Recebimento' : 'Pagamento' }} *</label>
                    <input type="date" v-model="editingPaymentForecast.payment_date" required class="form-input">
                </div>
                
                <div>
                    <label class="block text-sm font-medium">Forma de Pagamento *</label>
                    <select v-model="editingPaymentForecast.payment_method" required class="form-input">
                        <option v-for="method in entryPaymentMethods" :key="method.id" :value="method.name">
                            {{ method.name }}
                        </option>
                        <option v-if="!entryPaymentMethods.length" disabled>Carregando...</option>
                    </select>
                    </div>

                <div>
                    <label class="block text-sm font-medium">{{ editingPaymentForecast.forecast_type === 'receita' ? 'Valor Recebido (Bruto) *' : 'Valor Pago *' }}</label>
                    <input type="number" step="0.01" min="0.01" v-model.number="editingPaymentForecast.received_value" required class="form-input text-lg font-semibold" :class="editingPaymentForecast.forecast_type === 'receita' ? 'text-blue-600' : 'text-orange-600'">
                    <button v-if="editingPaymentForecast.forecast_type === 'receita'" type="button" @click="editingPaymentForecast.received_value = editingPaymentForecast.pending_value" class="text-xs text-blue-600 hover:underline mt-1">Usar valor total pendente</button>
                    <p v-if="editingPaymentForecast.forecast_type === 'despesa'" class="text-xs text-gray-500 mt-1">Se o valor pago for maior que o pendente, a diferença será lançada como "Encargos".</p>
                </div>
                
                <div v-if="editingPaymentForecast.forecast_type === 'receita' && (editingPaymentForecast.payment_method.toLowerCase().includes('cartão') || editingPaymentForecast.payment_method.toLowerCase().includes('cartao'))" class="pt-4 border-t">
                    <label class="block text-sm font-medium text-gray-700">Valor Líquido Recebido *</label>
                    <input type="number" step="0.01" min="0" v-model.number="editingPaymentForecast.net_received_value_manual" required class="form-input text-lg font-semibold text-green-600">
                    <p class="text-xs text-gray-500 mt-1">Informe o valor exato que caiu na conta (Bruto - Taxas).</p>
                </div>
                </div>

                <div class="flex justify-end gap-4 mt-8 pt-4 border-t">
                <button type="button" @click="hideModal('mark-as-paid-modal')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancelar</button>
                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                    {{ editingPaymentForecast.forecast_type === 'receita' ? 'Confirmar Recebimento' : 'Confirmar Pagamento' }}
                </button>
            </div>
        </form>
    </div>
</div>


<div id="certificate-options-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden items-center justify-center p-4 modal-overlay z-[70]">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
        <h2 class="text-xl font-bold mb-4">Emitir {{ editingHistoricalService.certType === 'atestado' ? 'Atestado' : 'Declaração' }}</h2>
        
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Tipo de Atividade</label>
                <select v-model="editingHistoricalService.certActivity" class="form-input mt-1 w-full">
                    <option value="">Selecione...</option>
                    <option v-for="opt in getOptionsByType('activity_type')" :key="opt.id" :value="opt.option_value">
                        {{ opt.option_value }}
                    </option>
                </select>
                <p class="text-xs text-gray-500 mt-1">Ex: Laborais, Escolares, Desportivas...</p>
            </div>

            <div v-if="editingHistoricalService.certType === 'atestado'">
                <label class="block text-sm font-medium text-gray-700">Dias de Repouso</label>
                <input type="number" v-model="editingHistoricalService.certDays" class="form-input mt-1 w-full" placeholder="Ex: 1">
                <p class="text-xs text-gray-500 mt-1">Deixe em branco ou 0 para "retornar às atividades".</p>
            </div>
        </div>

        <div class="flex justify-end gap-4 mt-6 pt-4 border-t">
            <button @click="hideModal('certificate-options-modal')" type="button" class="px-4 py-2 bg-gray-200 rounded text-gray-700 hover:bg-gray-300">Cancelar</button>
            <button @click="generateCertificateDoc" type="button" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                <i class="fa-solid fa-print mr-2"></i> Emitir Documento
            </button>
        </div>
    </div>
</div>



<div id="edit-historical-service-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden items-center justify-center p-4 modal-overlay z-[60]">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6">
        <h2 class="text-xl font-bold mb-4">Editar Atendimento (Histórico)</h2>
        <form @submit.prevent="updateHistoricalService">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">Status</label>
                <select v-model="editingHistoricalService.service_status" class="form-input w-full">
                    <option v-for="opt in getOptionsByType('service_status')" :key="opt.id" :value="opt.option_value">{{ opt.option_value }}</option>
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">Descrição</label>
                <textarea v-model="editingHistoricalService.description" rows="4" class="form-input w-full"></textarea>
            </div>
            <div class="flex justify-end gap-4">
                <button type="button" @click="hideModal('edit-historical-service-modal')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancelar</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Salvar Alterações</button>
            </div>
        </form>
    </div>
</div>

</div> <script type="module" src="./Logic/app.js"></script>
</body>
</html>