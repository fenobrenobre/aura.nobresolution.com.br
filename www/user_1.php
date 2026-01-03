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
