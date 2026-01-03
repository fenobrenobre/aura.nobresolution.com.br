import { handleGoogleCredentialResponse, jwt_decode as decodeJwt } from './auth.js';
window.handleGoogleCredentialResponse = handleGoogleCredentialResponse;
window.jwt_decode = decodeJwt;

import *as api from './api.js';
import * as auth from './auth.js';
import * as admin from './admin.js';
import * as agenda from './agenda.js';
import * as anamnesis from './anamnesis.js';
import * as budget from './budget.js';
import * as patient from './patient.js';
import * as priceList from './priceList.js';
import * as services from './services.js';
import * as ui from './ui.js';
import * as user from './user.js';
import *as utils from './utils.js';
import * as finance from './finance.js';
import * as receipt from './receipt.js';
import * as memed from './memed.js';
import * as prescription from './prescription.js'; 
import * as odontogram from './odontogram.js';

const app = Vue.createApp({
    data() {
        return {
            API_URL: '../../BackEnd/api.php',
            currentUser: null, 
            activeView: 'agenda', 
            activeAdminView: 'users', 
            activeRegisterTab: 'rules',
            activeRegisterSubTab: 'main', 
            activePatientTab: 'main', 
            activeUserTab: 'main', 
            
            // Nova variável para controlar as sub-abas de configuração dentro do modal de edição de usuário no Admin
            activeAdminUserCustomTab: 'general',

            activeClinicalTab: 'anamnesis', 
            activeBudgetTab: 'list',
            activeServiceTab: 'details',
            activeCustomFieldTab: 'professions',
            activeProfileTab: 'system',
            isSidebarOpen: false, 
            isLoading: false,
            googleClientId: null,
            toast: { visible: false, message: '', title: '', type: 'success' },
            loginForm: { email: '', password: '' },
            registerForm: utils.getNewRegisterTemplate(),
            publicAnamnesisTemplates: [],
            publicBudgetForms: [],
            publicReceiptTemplates: [],
            publicPrescriptionTemplates: [],
            isGoogleRegister: false,
            passwordResetStep: 1, 
            resetPasswordForm: { email: '', token: '', password: '' },
            users: [], patients: [], professions: [],
            
            specialties: [],
            editingSpecialty: {},
            activeProfessionForSpecialties: null,

            anamnesisTemplates: [],
            userAnamnesisTemplates: [],
            appointments: [], priceLists: [], priceItems: [], allPriceLists: [], budgets: [], patientBudgets: [],
            patientAppointments: [],
            patientServices: [],
            patientReceipts: { pending: [], generated: [] },
            selectedBirthdays: [],
            budgetForms: [],
            activeServices: [],
            allServices: [],
            waitingList: [],
            birthdayList: [],
            birthdayChecklist: {},
            serviceStatusFilter: 'all',
            serviceNameFilter: '',
            serviceSortBy: 'start_date',
            serviceSortOrder: 'desc',
            
            // Variáveis de controle de Reagendamento/Finalização
            reschedulingPatient: { serviceId: null, origin: null, futureScheduleId: null, waitingListId: null, oldAppointmentId: null },
            serviceToFinish: null, 
            modalToCloseAfterFinish: null, 
            
            deletingAppointment: null,
            deleteReason: '',

            finishTreatmentReason: '',
            itemToFinishTreatment: null,
            
            editingUser: {},
            editingPatient: utils.getNewPatientTemplate(),
            
            editingClinicalData: { 
                name: '', 
                anamnesisContent: '', 
                clinical_history: [], 
                prescriptions: [],
                measure_height: '',
                measure_weight: '',
                measure_abd_circ: '',
                measure_pa: '',
                measure_fr: '',
                measure_fc: '',
                measure_gc: ''
            }, 
            
            editingProfession: {},
            customFieldOptions: [],
            editingCustomFieldOption: {},
            editingAnamnesis: { make_global: false, assign_to_user_id: null },
            editingUserAnamnesis: { originalIsGlobal: false },
            editingAppointment: {},
            availableTimeSlots: [],
            editingPriceList: { make_global: false, user_id: null },
            editingPriceItem: {},
            editingBudgetForm: { fields: {} },
            editingHistoricalService: {}, 
            viewingActiveService: { budget_form_data: null, anamnesisContent: '', durationString: '' },
            activeServiceBudgetItems: [],
            editingProfile: {},
            adminSettings: { 
                trialDays: 15, 
                registrationNotes: '',
                data_retention_history: '12',
                data_retention_agenda: '12',
                data_retention_budgets: '12',
                welcome_email_template: '',
                adminNotificationEmail: ''
            },
            publicTrialDays: null, publicRegistrationNotes: '',
            patientSearchTerm: '', selectedPatients: [],
            
            patientSearchQuery: '', 
            patientSearchResults: [],
            patientReferredBySearchQuery: '', 
            patientReferredBySearchResults: [],
            patientResponsibleSearchQuery: '',
            patientResponsibleSearchResults: [],
            patientFatherSearchQuery: '',
            patientFatherSearchResults: [],
            patientMotherSearchQuery: '',
            patientMotherSearchResults: [],
            
            userPhotoPreview: null, userPhotoFile: null, logoPreview: null, logoFile: null, patientPhotoPreview: null, patientPhotoFile: null,
            timezones: ['America/Noronha', 'America/Belem', 'America/Fortaleza', 'America/Recife', 'America/Araguaina', 'America/Maceio', 'America/Bahia', 'America/Sao_Paulo', 'America/Campo_Grande', 'America/Cuiaba', 'America/Santarem', 'America/Porto_Velho', 'America/Boa_Vista', 'America/Manaus', 'America/Eirunepe', 'America/Rio_Branco'],
            newEvolutionEntry: '', newExamEntry: '',
            currentTimeString: '', clockInterval: null,
            trialCountdown: null, countdownInterval: null,
            serviceDurationInterval: null,
            agendaDate: new Date(), agendaView: 'day',
            confirmationModal: { visible: false, message: '', onConfirm: null, confirmButtonClass: 'bg-red-600 hover:bg-red-700' },
            webcamStream: null, activePhotoTarget: null,
            conflictColors: ['bg-yellow-100 border-yellow-500', 'bg-orange-100 border-orange-500', 'bg-red-100 border-red-500', 'bg-purple-100 border-purple-500', 'bg-pink-100 border-pink-500'],
            newBudget: {
                patient_id: null, patient_name: '', price_list_id: null,
                items: [{ region: '', description: '', value: 0, increment: 0, discount: 0 }],
                recurring_items: [],
                subtotal: 0, total: 0, 
                payment_details: [],
                recurring_payment_details: []
            },
            procedureSearch: { query: '', results: [], index: -1, activeIndex: -1 },
            activePriceListForItems: null,
            adminUserSearch: '',
            adminPriceListSearch: '',
            budgetFilters: { id: '', patientName: '', status: '', sortBy: 'createdAt', sortOrder: 'desc' },
            newBudgetPatientSearch: '',
            newBudgetPatientResults: [],
            newStandaloneService: { patient_id: null, description: '' },
            standaloneServicePatientSearch: '',
            standaloneServicePatientResults: [],
            weekDaysNames: ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'],
            newDisabledDate: '',
            manualWaitingList: { patientSearch: '', patientId: null, reason: '', searchResults: [] },

            activeFinanceTab: 'livrocaixa',
            ledgerEntries: [],
            ledgerPreviousBalance: 0, 
            forecastEntries: [],
            entryPaymentMethods: [], 
            editingLedgerEntry: {
                id: null,
                entry_order: null,
                entry_date: new Date().toLocaleDateString('en-CA'), 
                receipt_nfe: '',
                description: '',
                entry_type: 'entrada',
                amount: null,
                patient_id: null
            },
            editingForecastEntry: {
                id: null,
                entry_date: new Date().toLocaleDateString('en-CA'),
                budget_id: null,
                patient_id: null,
                patient_name: '',
                description: '',
                forecast_type: 'receita',
                total_value: 0,
                installment_value: 0,
                received_value: 0
            },
            
            editingPaymentForecast: { 
                id: null,
                original_description: '',
                forecast_type: 'receita',
                pending_value: 0,
                payment_date: new Date().toLocaleDateString('en-CA'),
                received_value: 0, 
                payment_method: null, 
                net_received_value_manual: 0 
            },

            ledgerFilters: {
                month: new Date().getMonth() + 1, 
                year: new Date().getFullYear()
            },
            forecastFilters: {
                status: 'Em Aberto',
                month: new Date().getMonth() + 1, 
                year: new Date().getFullYear()
            },
            
            forecastHeaderTotals: {
                receitasPrevisto: 0, receitasRealizado: 0, 
                despesasPrevisto: 0, despesasRealizado: 0,
                saldoPrevisto: 0, saldoRealizado: 0
            },

            manualForecastPatientSearch: '',
            manualForecastPatientResults: [],
            ledgerPatientSearch: '',
            ledgerPatientResults: [],

            receiptTemplates: [],
            userReceiptTemplates: [],
            
            pendingReceipts: { entries: [], total: 0, totalPages: 1 },
            generatedReceipts: { entries: [], total: 0, totalPages: 1 },
            
            editingReceipt: { make_global: false, assign_to_user_id: null, is_default: false },
            editingUserReceipt: { originalIsGlobal: false, is_default: false },
            receiptGenerator: {
                isAvulso: false,
                ledger_entry_id: null,
                patient_id: null,
                patient_name: '',
                patient_cpf: '',
                description: '',
                amount: null,
                date: null,
                template_id: null
            },
            receiptPatientSearch: '',
            receiptPatientResults: [],
            
            receiptSearchPending: '',
            receiptSearchGenerated: '',
            receiptPaginationPending: { currentPage: 1, itemsPerPage: 4 },
            receiptPaginationGenerated: { currentPage: 1, itemsPerPage: 4 },
            receiptSearchTimeout: null,

            selectedPendingReceipts: [],
            selectedGeneratedReceipts: [],

            futureScheduleList: [],
            futureScheduleFilters: {
                search: '',
                sortBy: 'return_date',
                sortOrder: 'asc'
            },
            futureSchedulePagination: {
                currentPage: 1,
                itemsPerPage: 5
            },
            futureScheduleTotalPages: 1,
            futureScheduleTotal: 0,
            
            futureScheduleForm: {
                id: null,
                patient_id: null,
                patient_name: '',
                service_id: null,
                return_date: '',
                reason: '',
                origin: null,
                waiting_list_id: null
            },
            
            futureScheduleSearchTimeout: null,
            
            waitingListFilters: {
                search: '',
                sortBy: 'added_at',
                sortOrder: 'asc'
            },
            waitingListSearchTimeout: null,

            pagination: { 
                currentPage: 1, 
                itemsPerPage: 9
            },
            
            activeServicesPagination: { 
                currentPage: 1, 
                itemsPerPage: 6
            },
            historicalServicesPagination: { 
                currentPage: 1,
                itemsPerPage: 5
            },
            
            budgetPagination: {
                currentPage: 1,
                itemsPerPage: 6
            },

            passwordStrength: 0,
            passwordFeedback: '',
            
            isPatientQuickViewOpen: false,
            quickViewPatient: null,
            
            userPaymentMethods: [], 
            editingUserPaymentMethod: { id: null, option_value: '', originalIsGlobal: false }, 

            maintenance: {
                clinicalPeriod: '12',
                receiptTarget: 'generated',
                receiptPeriod: '12'
            },
            maintenanceAuth: {
                mode: 'simple', 
                action: null,  
                admin_password: '',
                login_password: ''
            },

            isMemedInitialized: false,

            medicines: [],
            exams: [],
            prescriptionTemplates: [],
            editingMedicine: {},
            editingExam: {},
            editingPrescriptionTemplate: {},
            
            // --- CORREÇÃO: Variáveis Adicionadas para Cartas e Documentos ---
            editingLetter: { title: '', content: '', patient_id: null },
            letterPatient: null,
            // -------------------------------------------------------------

            prescriptionForm: { 
                patient_id: null, 
                type: 'receita', 
                items: [], 
                recommendations: '' 
            },
            
            tempPrescriptionItem: { 
                name: '', 
                presentation: '', 
                route: '', 
                instructions: '', 
                duration: '' 
            },
            
            medicineSearchQuery: '',
            
            recommendationTemplates: [],
            selectedRecommendationTemplate: null,
            editingRecommendation: {},

            globalPrescriptions: [],
            prescriptionHistoryFilters: { search: '' },
            prescriptionHistoryPagination: { currentPage: 1, itemsPerPage: 10 },
            prescriptionHistoryTotal: 0,
            prescriptionHistoryTotalPages: 1,
            prescriptionHistorySearchTimeout: null,

            globalAppointments: [],
            appointmentHistoryFilters: {
                search: '',
                status: '',
                sortBy: 'start_time',
                sortOrder: 'desc'
            },
            appointmentHistoryPagination: {
                currentPage: 1,
                itemsPerPage: 10
            },
            appointmentHistoryTotal: 0,
            appointmentHistoryTotalPages: 1,
            appointmentHistorySearchTimeout: null,
            
            // --- VARIÁVEIS DO ODONTOGRAMA (NOVO) ---
            dentalDiagnoses: [],
            selectedDiagnosis: null,
            odontogramEntries: [],
            isLoadingOdontogram: false,
            editingDiagnosis: { id: null, name: '', color: '#FF0000', type: 'face' },
            
            // --- CORREÇÃO: Variáveis Adicionadas para Odontograma ---
            odontogramVersions: [],
            currentOdontogramVersionId: null
            // --------------------------------------------------------
        }
    },
    watch: {
        activeAdminView(newView) {
            if (newView === 'custom_fields') {
                this.fetchProfessions();
                this.fetchCustomFieldOptions();
                this.activeCustomFieldTab = 'professions';
            }
            if (newView === 'settings') this.fetchAdminSettings();
            if (newView === 'price_lists') { this.fetchAllPriceListsAdmin(); this.fetchUsers(); }
            if (newView === 'budget_forms') { this.fetchBudgetForms(); }
            if (newView === 'anamnesis') { this.fetchAnamnesisTemplates(); this.fetchUsers();}
            if (newView === 'receipts') { this.fetchReceiptTemplates(); this.fetchUsers();}
            if (newView === 'recommendations') { this.fetchRecommendationTemplates('admin'); }
            
            if (newView === 'medicines') this.fetchMedicines('admin');
            if (newView === 'exams') this.fetchExams('admin');
            if (newView === 'prescription_templates') this.fetchPrescriptionTemplates('admin');
        },
        activeView(newView) {
            if (newView === 'settings') {
                this.openProfileForEditing();
                this.fetchPriceLists();
                this.fetchBudgetForms();
                this.fetchUserAnamnesisTemplates();
                this.fetchUserReceiptTemplates();
                this.fetchMedicines();
                this.fetchExams();
                this.fetchPrescriptionTemplates();
                this.fetchRecommendationTemplates();
                if(!this.customFieldOptions.length && this.currentUser.isAdmin) this.fetchCustomFieldOptions();
                if (this.currentUser.memed_enabled == 1) { this.preloadMemed(); }
            }
            if (newView === 'agenda') this.fetchAppointments();
            if (newView === 'patients') {
                this.fetchPatients().then(() => { this.fetchBirthdays(); });
            }
            if (newView === 'budgets') this.fetchBudgets();
            if (newView === 'active_services') {
                this.activeServicesPagination.currentPage = 1; 
                this.fetchActiveServices();
            }
            if (newView === 'hist_atendimentos') {
                this.historicalServicesPagination.currentPage = 1; 
                this.fetchAllServices();
            }
            if (newView === 'history_documents') {
                this.prescriptionHistoryFilters.search = '';
                this.prescriptionHistoryPagination.currentPage = 1;
                this.fetchGlobalPrescriptions();
            }
            if (newView === 'history_receipts') {
                this.receiptSearchGenerated = '';
                this.receiptPaginationGenerated.currentPage = 1;
                this.fetchGeneratedReceipts();
            }
            if (newView === 'history_budgets') {
                this.budgetFilters = { id: '', patientName: '', status: '', sortBy: 'createdAt', sortOrder: 'desc' };
                this.budgetPagination.currentPage = 1;
                this.fetchBudgets();
            }
            if (newView === 'history_appointments') {
                this.appointmentHistoryPagination.currentPage = 1;
                this.fetchGlobalAppointments();
            }
            if (newView === 'waiting_list') this.fetchWaitingList();
            if (newView === 'future_schedule') {
                this.futureScheduleFilters.search = '';
                this.futureSchedulePagination.currentPage = 1;
                this.fetchFutureSchedule();
            }
            if (newView === 'birthdays') {
                if(this.patients.length === 0) {
                    this.fetchPatients().then(() => { this.fetchBirthdays(); });
                } else {
                    this.fetchBirthdays();
                }
            }
            if (newView === 'financeiro_livrocaixa') this.fetchLedgerEntries();
            if (newView === 'financeiro_previsao') {
                this.fetchForecastEntries();
                this.fetchEntryPaymentMethods(); 
            }
            if (newView === 'financeiro_recibos') { 
                this.fetchLedgerEntriesForReceipts(); 
                this.fetchGeneratedReceipts(); 
                this.fetchUserReceiptTemplates(); 
                this.selectedPendingReceipts = [];
                this.selectedGeneratedReceipts = [];
            }
        },
        activeProfileTab(newTab) {
            if (newTab === 'payment_methods' && this.activeView === 'settings') {
                this.fetchUserPaymentMethods();
            }
            if (newTab === 'medicines') this.fetchMedicines();
            if (newTab === 'exams_list') this.fetchExams();
            if (newTab === 'prescription_templates') this.fetchPrescriptionTemplates();
        },
        patients: { handler() { this.updateBirthdayChecklist(); }, deep: true },
        birthdayList: { handler() { this.updateBirthdayChecklist(); }, deep: true },
        'budgetFilters.id'() { this.budgetPagination.currentPage = 1; this.fetchBudgets(); },
        'budgetFilters.status'() { this.budgetPagination.currentPage = 1; this.fetchBudgets(); },
        'budgetFilters.patientName'() { this.budgetPagination.currentPage = 1; },
        'budgetFilters.sortBy'() { this.budgetPagination.currentPage = 1; },
        'budgetFilters.sortOrder'() { this.budgetPagination.currentPage = 1; },
        'forecastFilters.month'() { this.fetchForecastEntries(); },
        'forecastFilters.year'() { this.fetchForecastEntries(); },
        'forecastFilters.status'() { this.fetchForecastEntries(); }, 
        agendaDate() { this.fetchAppointments(); },
        agendaView() { this.agendaDate = new Date(); this.fetchAppointments(); },
        'editingAppointment.date': async function(newDate, oldDate) { 
            if (newDate !== oldDate && this.editingAppointment && document.getElementById('appointment-modal')?.classList.contains('flex')) {
                const selectedDate = new Date(newDate + 'T00:00:00');
                if (isNaN(selectedDate.getTime())) return; 
                const startOfDay = new Date(selectedDate);
                startOfDay.setHours(0, 0, 0, 0);
                const endOfDay = new Date(selectedDate);
                endOfDay.setHours(23, 59, 59, 999);
                const res = await this.apiRequest('getAppointments', { start: startOfDay.toISOString(), end: endOfDay.toISOString() }, false, 'GET');
                if (res.success) { 
                    this.appointments = res.appointments.map(a => ({ ...a, start: new Date(a.start_time.replace(' ', 'T')), end: new Date(a.end_time.replace(' ', 'T')) })); 
                } else {
                    this.appointments = []; 
                }
                this.fetchAvailableSlotsForDate();
            }
        },
        'futureScheduleFilters.search'() {
            if (this.futureScheduleSearchTimeout) clearTimeout(this.futureScheduleSearchTimeout);
            this.futureScheduleSearchTimeout = setTimeout(() => { this.futureSchedulePagination.currentPage = 1; this.fetchFutureSchedule(); }, 400);
        },
        'waitingListFilters.search'() { this.debouncedSearchWaitingList(); },
        'prescriptionHistoryFilters.search'() {
             if (this.prescriptionHistorySearchTimeout) clearTimeout(this.prescriptionHistorySearchTimeout);
             this.prescriptionHistorySearchTimeout = setTimeout(() => { this.prescriptionHistoryPagination.currentPage = 1; this.fetchGlobalPrescriptions(); }, 400);
        },
        'appointmentHistoryFilters.search'() {
             if (this.appointmentHistorySearchTimeout) clearTimeout(this.appointmentHistorySearchTimeout);
             this.appointmentHistorySearchTimeout = setTimeout(() => { this.appointmentHistoryPagination.currentPage = 1; this.fetchGlobalAppointments(); }, 400);
        },
        'appointmentHistoryFilters.status'() { this.appointmentHistoryPagination.currentPage = 1; this.fetchGlobalAppointments(); },
        'registerForm.password'(newPassword) { this.checkPasswordStrength(newPassword); },
        'resetPasswordForm.password'(newPassword) { this.checkPasswordStrength(newPassword); },
        'editingUser.password'(newPassword) { if (document.getElementById('user-modal')?.classList.contains('flex')) { this.checkPasswordStrength(newPassword); } else { this.passwordStrength = 0; this.passwordFeedback = ''; } },
        'editingProfile.password'(newPassword) { if (this.activeView === 'settings') { this.checkPasswordStrength(newPassword); } else { this.passwordStrength = 0; this.passwordFeedback = ''; } },
        'editingPaymentForecast.received_value'(newVal) { if (this.editingPaymentForecast.forecast_type === 'receita') { this.editingPaymentForecast.net_received_value_manual = newVal; } },
        currentUser: {
            handler(newUser, oldUser) {
                if (newUser && (!oldUser || newUser.id !== oldUser.id)) {
                    newUser.reminder_email_hours = Array.isArray(newUser.reminder_email_hours) ? newUser.reminder_email_hours : ['24'];
                    newUser.birthday_email_time = newUser.birthday_email_time ? newUser.birthday_email_time.substring(0, 5) : '09:00';
                    newUser.finance_enabled = newUser.finance_enabled ?? 1;
                    newUser.finance_ledger_enabled = newUser.finance_ledger_enabled ?? 1;
                    newUser.finance_forecast_enabled = newUser.finance_forecast_enabled ?? 1;
                    newUser.default_receipt_template_id = newUser.default_receipt_template_id ?? null;
                    newUser.professional_register = newUser.professional_register ?? null;
                    newUser.future_schedule_enabled = newUser.future_schedule_enabled ?? 0;
                    newUser.agenda_enabled = newUser.agenda_enabled ?? 1; 
                    newUser.enabled_payment_methods = Array.isArray(newUser.enabled_payment_methods) ? newUser.enabled_payment_methods.map(String) : null;
                    newUser.memed_enabled = newUser.memed_enabled ?? 0;
                    if (window.location.pathname.endsWith('user.php')) {
                        const cleanPath = window.location.pathname.replace('user.php', '');
                        window.history.replaceState(null, '', cleanPath);
                    }
                }
            },
            deep: true,
            immediate: true
        },
        receiptSearchPending() {
            if (this.receiptSearchTimeout) clearTimeout(this.receiptSearchTimeout);
            this.receiptSearchTimeout = setTimeout(() => { this.receiptPaginationPending.currentPage = 1; this.fetchLedgerEntriesForReceipts(); }, 400);
        },
        receiptSearchGenerated() {
            if (this.receiptSearchTimeout) clearTimeout(this.receiptSearchTimeout);
            this.receiptSearchTimeout = setTimeout(() => { this.receiptPaginationGenerated.currentPage = 1; this.fetchGeneratedReceipts(); }, 400);
        },
    },
    computed: {
        currentDaySchedule() { 
            if (!this.currentUser || !this.currentUser.weekly_schedule) return { enabled: false, start: '08:00', end: '12:00', enabled2: false, start2: '14:00', end2: '18:00' }; 
            const dayOfWeek = this.agendaDate.getDay(); 
            return this.currentUser.weekly_schedule[dayOfWeek] || { enabled: false, start: '08:00', end: '12:00', enabled2: false, start2: '14:00', end2: '18:00' }; 
        },
        gridStartHour() {
            if (!this.currentUser || !this.currentUser.weekly_schedule) return 8;
            let minHour = 24;
            for (const day in this.currentUser.weekly_schedule) {
                const schedule = this.currentUser.weekly_schedule[day];
                if (schedule.enabled) {
                    const hour = parseInt(schedule.start.split(':')[0]);
                    if (hour < minHour) minHour = hour;
                }
                if (schedule.enabled2) {
                    const hour = parseInt(schedule.start2.split(':')[0]);
                    if (hour < minHour) minHour = hour;
                }
            }
            return minHour < 24 ? minHour : 8;
        },
        gridEndHour() {
            if (!this.currentUser || !this.currentUser.weekly_schedule) return 18;
            let maxHour = 0;
            let lastEndTime = null;
            for (const day in this.currentUser.weekly_schedule) {
                const schedule = this.currentUser.weekly_schedule[day];
                if (schedule.enabled) {
                    const hour = parseInt(schedule.end.split(':')[0]);
                    if (hour > maxHour) { maxHour = hour; lastEndTime = schedule.end; }
                }
                if (schedule.enabled2) {
                    const hour = parseInt(schedule.end2.split(':')[0]); 
                    if (hour > maxHour) { maxHour = hour; lastEndTime = schedule.end2; }
                }
            }
            return maxHour > 0 ? (lastEndTime && lastEndTime.endsWith(':00') ? maxHour : maxHour + 1) : 18;
        },
        hourLabels() { 
            const labels = []; 
            for (let h = this.gridStartHour; h < this.gridEndHour; h++) { 
                labels.push({ hour: h, label: `${String(h).padStart(2, '0')}:00`, top: ((h - this.gridStartHour) * 4) + 'rem' }); 
            } 
            return labels; 
        },
        timeSlots() { 
            const slots = []; 
            const slotMinutes = parseInt(this.currentUser?.appointment_slot_minutes) || 30; 
            for (let h = this.gridStartHour; h < this.gridEndHour; h++) { 
                for (let m = 0; m < 60; m += slotMinutes) { 
                    slots.push({ time: `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}` }); 
                } 
            } 
            return slots; 
        },
        dayAppointments() { return this.getAppointmentsForDay(this.agendaDate); },
        processedDayAppointments() { return this.processAppointmentsForLayout(this.dayAppointments); },
        weekDays() { 
            const startOfWeek = new Date(this.agendaDate); 
            startOfWeek.setHours(0, 0, 0, 0); 
            startOfWeek.setDate(startOfWeek.getDate() - startOfWeek.getDay()); 
            return Array.from({ length: 7 }).map((_, i) => { 
                const date = new Date(startOfWeek); 
                date.setDate(date.getDate() + i); 
                const dayOfWeek = date.getDay(); 
                const schedule = this.currentUser?.weekly_schedule ? this.currentUser.weekly_schedule[dayOfWeek] : { enabled: true, enabled2: false }; 
                const isEnabled = (schedule.enabled || schedule.enabled2);
                const isSpecificallyDisabled = this.isDateDisabled(date); 
                return { name: date.toLocaleDateString('pt-BR', { weekday: 'short' }), date, enabled: isEnabled && !isSpecificallyDisabled }; 
            }); 
        },
        agendaTitle() { 
            if (this.agendaView === 'day') { 
                return this.agendaDate.toLocaleDateString('pt-BR', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }); 
            } else { 
                const start = new Date(this.agendaDate); 
                const end = new Date(this.agendaDate); 
                start.setDate(start.getDate() - start.getDay()); 
                end.setDate(end.getDate() - end.getDay() + 6); 
                return `${start.toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' })} - ${end.toLocaleDateString('pt-BR', { day: '2-digit', month: 'short', year: 'numeric' })}`; 
            } 
        },

        totalPages() {
            if (!this.patients || this.patients.length === 0) return 1;
            return Math.ceil(this.patients.length / this.pagination.itemsPerPage);
        },
        paginatedPatients() {
            const start = (this.pagination.currentPage - 1) * this.pagination.itemsPerPage;
            const end = start + this.pagination.itemsPerPage;
            return this.patients.slice(start, end);
        },
        activeServicesTotalPages() {
            if (!this.activeServices || this.activeServices.length === 0) return 1;
            return Math.ceil(this.activeServices.length / this.activeServicesPagination.itemsPerPage);
        },
        paginatedActiveServices() {
            const start = (this.activeServicesPagination.currentPage - 1) * this.activeServicesPagination.itemsPerPage;
            const end = start + this.activeServicesPagination.itemsPerPage;
            return this.activeServices.slice(start, end);
        },
        historicalServicesTotalPages() {
            if (!this.filteredHistoricalServices || this.filteredHistoricalServices.length === 0) return 1;
            return Math.ceil(this.filteredHistoricalServices.length / this.historicalServicesPagination.itemsPerPage);
        },
        paginatedHistoricalServices() {
            const start = (this.historicalServicesPagination.currentPage - 1) * this.historicalServicesPagination.itemsPerPage;
            const end = start + this.historicalServicesPagination.itemsPerPage;
            return this.filteredHistoricalServices.slice(start, end);
        },
        budgetTotalPages() {
            if (!this.filteredAndSortedBudgets || this.filteredAndSortedBudgets.length === 0) return 1;
            return Math.ceil(this.filteredAndSortedBudgets.length / this.budgetPagination.itemsPerPage);
        },
        paginatedBudgets() {
            if (!this.filteredAndSortedBudgets) return [];
            const start = (this.budgetPagination.currentPage - 1) * this.budgetPagination.itemsPerPage;
            const end = start + this.budgetPagination.itemsPerPage;
            return this.filteredAndSortedBudgets.slice(start, end);
        },

        birthdaysToday() {
            if (!this.birthdayList) return [];
            const today = new Date();
            const todayMonth = today.getMonth() + 1;
            const todayDay = today.getDate();
            return this.birthdayList.filter(p => {
                if (!p.birthdate) return false;
                const birthDate = new Date(p.birthdate + 'T00:00:00');
                return birthDate.getMonth() + 1 === todayMonth && birthDate.getDate() === todayDay;
            });
        },
        birthdaysNext15Days() {
            if (!this.birthdayList) return [];
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const todayTime = today.getTime();
            const todayMonth = today.getMonth() + 1;
            const todayDay = today.getDate();

            const in15Days = new Date(today.getTime() + 15 * 24 * 60 * 60 * 1000);

            return this.birthdayList.filter(p => {
                if (!p.birthdate) return false;
                const birthDate = new Date(p.birthdate + 'T00:00:00');
                const birthMonth = birthDate.getMonth() + 1;
                const birthDay = birthDate.getDate();

                if (birthMonth === todayMonth && birthDay === todayDay) return false;

                const thisYearBirthday = new Date(today.getFullYear(), birthDate.getMonth(), birthDate.getDate());
                thisYearBirthday.setHours(0, 0, 0, 0);

                if (thisYearBirthday.getTime() < todayTime) {
                    thisYearBirthday.setFullYear(today.getFullYear() + 1);
                }

                return thisYearBirthday.getTime() > todayTime && thisYearBirthday.getTime() <= in15Days.getTime();
            }).sort((a, b) => {
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                const todayTime = today.getTime();

                const dateA = new Date(a.birthdate + 'T00:00:00');
                const thisYearBirthdayA = new Date(today.getFullYear(), dateA.getMonth(), dateA.getDate());
                if (thisYearBirthdayA.getTime() < todayTime) { thisYearBirthdayA.setFullYear(today.getFullYear() + 1); }

                const dateB = new Date(b.birthdate + 'T00:00:00');
                const thisYearBirthdayB = new Date(today.getFullYear(), dateB.getMonth(), dateB.getDate());
                if (thisYearBirthdayB.getTime() < todayTime) { thisYearBirthdayB.setFullYear(today.getFullYear() + 1); }
                
                return thisYearBirthdayA.getTime() - thisYearBirthdayB.getTime();
            });
        },

        defaultBudgetStatusNegotiation() { return this.getDefaultOptionValue('budget_status', 'Em Negociação'); },
        defaultBudgetStatusApproved() { return this.getDefaultOptionValue('budget_status', 'Aprovado'); },
        defaultBudgetStatusRejected() { return this.getDefaultOptionValue('budget_status', 'Reprovado'); },
        defaultBudgetStatusCanceled() { return this.getDefaultOptionValue('budget_status', 'Cancelado'); },
        defaultPaymentMethod() { return this.getDefaultOptionValue('payment_method', 'Pix/Transferência'); },

        ledgerTotals() { 
            let entradas = 0;
            let saidas = 0;
            this.ledgerEntries.forEach(entry => {
                if (entry.entry_type === 'entrada') {
                    entradas += parseFloat(entry.amount || 0);
                } else if (entry.entry_type === 'saida') {
                    saidas += parseFloat(entry.amount || 0);
                }
            });
            const saldoFinalTabela = this.ledgerPreviousBalance + entradas - saidas;
            const saldoFinalMes = entradas - saidas;
            return { entradas, saidas, saldoFinalTabela, saldoFinalMes };
        },
        forecastTotals() { 
            let receitasPrevisto = 0, receitasRealizado = 0, despesasPrevisto = 0, despesasRealizado = 0;
            this.forecastEntries.forEach(entry => {
                if (entry.forecast_type === 'receita') {
                    receitasPrevisto += parseFloat(entry.installment_value || 0);
                    receitasRealizado += parseFloat(entry.received_value || 0);
                } else if (entry.forecast_type === 'despesa') {
                    despesasPrevisto += parseFloat(entry.installment_value || 0);
                    despesasRealizado += parseFloat(entry.received_value || 0);
                }
            });
            const saldoPrevisto = receitasPrevisto - despesasPrevisto;
            const saldoRealizado = receitasRealizado - despesasRealizado;
            return { receitasPrevisto, receitasRealizado, despesasPrevisto, despesasRealizado, saldoPrevisto, saldoRealizado };
        },
        
        budgetSubtotal() { return this.newBudget.items.reduce((sum, item) => sum + (( (parseFloat(item.value) || 0) + (parseFloat(item.increment) || 0) ) * (parseInt(item.quantity) || 1)), 0); },
        budgetRecurringTotal() { return this.newBudget.recurring_items.reduce((sum, item) => { const itemValue = ((parseFloat(item.value) || 0) + (parseFloat(item.increment) || 0)) * (parseInt(item.quantity) || 1); const itemDiscount = parseFloat(item.discount) || 0; return sum + (itemValue - itemDiscount); }, 0); },
        budgetTotal() { const itemDiscounts = this.newBudget.items.reduce((sum, item) => sum + (parseFloat(item.discount) || 0), 0); return this.budgetSubtotal + this.budgetRecurringTotal - itemDiscounts; },
        budgetTotalMainItems() { const subtotal = this.newBudget.items.reduce((sum, item) => sum + (( (parseFloat(item.value) || 0) + (parseFloat(item.increment) || 0) ) * (parseInt(item.quantity) || 1)), 0); const itemDiscounts = this.newBudget.items.reduce((sum, item) => sum + (parseFloat(item.discount) || 0), 0); return Math.max(0, subtotal - itemDiscounts); },
        budgetPaymentDetailsTotal() { return this.newBudget.payment_details.reduce((sum, detail) => sum + (parseFloat(detail.value) || 0), 0); },
        budgetPaymentDetailsRemaining() { return Math.max(0, this.budgetTotalMainItems - this.budgetPaymentDetailsTotal); },
        budgetTotalRecurringItems() { return this.budgetRecurringTotal; },
        budgetRecurringPaymentDetailsTotal() { return this.newBudget.recurring_payment_details.reduce((sum, detail) => sum + (parseFloat(detail.value) || 0), 0); },
        budgetRecurringPaymentDetailsRemaining() { return Math.max(0, this.budgetTotalRecurringItems - this.budgetRecurringPaymentDetailsTotal); },
        
        activeServiceBudgetSubtotal() { const budgetData = this.viewingActiveService.budget_details || this.viewingActiveService.budget_form_data; if (!budgetData || !budgetData.items) return 0; return budgetData.items.reduce((sum, item) => sum + (( (parseFloat(item.value) || 0) + (parseFloat(item.increment) || 0) ) * (parseInt(item.quantity) || 1)), 0); },
        activeServiceBudgetRecurringTotal() { const budgetData = this.viewingActiveService.budget_details || this.viewingActiveService.budget_form_data; if (!budgetData || !budgetData.recurring_items) return 0; return budgetData.recurring_items.reduce((sum, item) => { const itemValue = ((parseFloat(item.value) || 0) + (parseFloat(item.increment) || 0)) * (parseInt(item.quantity) || 1); const itemDiscount = parseFloat(item.discount) || 0; return sum + (itemValue - itemDiscount); }, 0); },
        activeServiceBudgetTotal() { const budgetData = this.viewingActiveService.budget_details || this.viewingActiveService.budget_form_data; if (!budgetData) return 0; const itemDiscounts = (budgetData.items || []).reduce((sum, item) => sum + (parseFloat(item.discount) || 0), 0); const finalDiscount = parseFloat(budgetData.final_discount) || 0; return this.activeServiceBudgetSubtotal + this.activeServiceBudgetRecurringTotal - itemDiscounts - finalDiscount; },

        filteredAndSortedBudgets() {
            let filtered = [...this.budgets];
            if (this.budgetFilters.id) { filtered = filtered.filter(budget => String(budget.id).includes(String(this.budgetFilters.id))); }
            if (this.budgetFilters.patientName) { const searchTerm = this.budgetFilters.patientName.toLowerCase(); filtered = filtered.filter(budget => budget.patient_name.toLowerCase().includes(searchTerm)); }
            if (this.budgetFilters.status) { filtered = filtered.filter(budget => budget.status === this.budgetFilters.status); }
            const sortBy = this.budgetFilters.sortBy; const sortOrder = this.budgetFilters.sortOrder;
            filtered.sort((a, b) => {
                let valA = a[sortBy]; let valB = b[sortBy];
                if (sortBy === 'createdAt') { valA = new Date(valA).getTime(); valB = new Date(valB).getTime(); }
                else if (sortBy === 'final_total' || sortBy === 'id') { valA = parseFloat(valA); valB = parseFloat(valB); }
                else { valA = String(valA || '').toLowerCase(); valB = String(valB || '').toLowerCase(); }
                if (valA < valB) { return sortOrder === 'asc' ? -1 : 1; } if (valA > valB) { return sortOrder === 'asc' ? 1 : -1; } return 0;
            });
            return filtered;
        },

        filteredHistoricalServices() {
            let filtered = [...this.allServices];
            if (this.serviceStatusFilter !== 'all') { filtered = filtered.filter(service => service.service_status === this.serviceStatusFilter); }
            if (this.serviceNameFilter) { const searchTerm = this.serviceNameFilter.toLowerCase(); filtered = filtered.filter(service => service.patient_name.toLowerCase().includes(searchTerm)); }
            filtered.sort((a, b) => {
                let valA = a[this.serviceSortBy]; let valB = b[this.serviceSortBy];
                if (this.serviceSortBy === 'start_date' || this.serviceSortBy === 'end_date') {
                    try { valA = new Date(String(valA).replace(' ', 'T')).getTime(); } catch (e) { valA = null; }
                    try { valB = new Date(String(valB).replace(' ', 'T')).getTime(); } catch (e) { valB = null; }
                    if (!valA) valA = 0; if (!valB) valB = 0;
                } else { valA = String(valA || '').toLowerCase(); valB = String(valB || '').toLowerCase(); }
                if (valA < valB) { return this.serviceSortOrder === 'asc' ? -1 : 1; } if (valA > valB) { return this.serviceSortOrder === 'asc' ? 1 : -1; } return 0;
            });
            return filtered;
        },
        filteredWaitingList() {
            if (!this.waitingListFilters.search) { return this.waitingList; }
            const searchTerm = this.waitingListFilters.search.toLowerCase();
            return this.waitingList.filter(item => item.name.toLowerCase().includes(searchTerm) || (item.reason && item.reason.toLowerCase().includes(searchTerm)));
        },
        sortedWaitingList() {
            const sortBy = this.waitingListFilters.sortBy; const sortOrder = this.waitingListFilters.sortOrder;
            return [...this.filteredWaitingList].sort((a, b) => {
                let valA = a[sortBy]; let valB = b[sortBy];
                if (sortBy === 'added_at') { try { valA = new Date(String(valA).replace(' ', 'T')).getTime(); } catch (e) { valA = 0; } try { valB = new Date(String(valB).replace(' ', 'T')).getTime(); } catch (e) { valB = 0; } } else { valA = String(valA || '').toLowerCase(); valB = String(valB || '').toLowerCase(); }
                if (valA < valB) return sortOrder === 'asc' ? -1 : 1; if (valA > valB) return sortOrder === 'asc' ? 1 : -1; return 0;
            });
        },
        sortedFutureScheduleList() {
            const sortBy = this.futureScheduleFilters.sortBy; const sortOrder = this.futureScheduleFilters.sortOrder;
            return [...this.futureScheduleList].sort((a, b) => {
                let valA = a[sortBy]; let valB = b[sortBy];
                if (sortBy === 'return_date') { try { valA = new Date(String(valA) + 'T00:00:00').getTime(); } catch (e) { valA = 0; } try { valB = new Date(String(valB) + 'T00:00:00').getTime(); } catch (e) { valB = 0; } } else { valA = String(valA || '').toLowerCase(); valB = String(valB || '').toLowerCase(); }
                if (valA < valB) return sortOrder === 'asc' ? -1 : 1; if (valA > valB) return sortOrder === 'asc' ? 1 : -1; return 0;
            });
        },

        filteredAllPriceLists() { if (!this.adminPriceListSearch) return this.allPriceLists; const search = this.adminPriceListSearch.toLowerCase(); return this.allPriceLists.filter(list => list.name.toLowerCase().includes(search) || (list.user_name && list.user_name.toLowerCase().includes(search)) || (list.user_email && list.user_email.toLowerCase().includes(search))); },
        customFieldTypes() { return [ { type: 'periodicity', label: 'Periodicidade (Orçamento)' }, { type: 'measurement_unit', label: 'Tipos de Medida (Tabela Preço)' }, { type: 'gender', label: 'Sexo (Cadastros)' }, { type: 'marital_status', label: 'Estado Civil (Cadastros)' }, { type: 'budget_status', label: 'Status (Orçamento)' }, { type: 'service_status', label: 'Status (Atendimento)' }, { type: 'payment_status', label: 'Status (Pagamento)' }, { type: 'payment_method', label: 'Forma de Pagamento (Orçamento)' } ]; },
        labels() { if (this.currentUser?.system_version === 'Tecnica') { return { patients: 'Clientes', patient: 'Cliente', clinicalData: 'Registros Técnicos', anamnesis: 'Anotações Iniciais', evolution: 'Desenvolvimento Técnico', exams: 'Anotações Internas', exam_singular: 'Anotação' }; } return { patients: 'Pacientes', patient: 'Paciente', clinicalData: 'Dados Clínicos', anamnesis: 'Anamnese', evolution: 'Evolução Clínica', exams: 'Anotações Internas', exam_singular: 'Anotação' }; },
        isUserActive: { get() { return this.editingUser.status === 'active'; }, set(val) { this.editingUser.status = val ? 'active' : 'inactive'; } },
        
        isRegisterTabMainValid() { const form = this.registerForm; return form.name && form.professionalName && form.email && form.password && this.passwordStrength >= 3 && form.phone; },
        isRegisterTabDocsValid() { const form = this.registerForm; return form.cpf && !form.isDocumentInvalid && form.profession; },
        isRegisterTabContactValid() { const form = this.registerForm; return form.zip_code && form.street && form.street_number && form.neighborhood && form.city && form.state; },
        isRegisterTabCustomValid() { const form = this.registerForm; return form.timezone && form.system_version; },
        clinicalEvolutions() { if (!this.editingClinicalData.clinical_history) return []; return this.editingClinicalData.clinical_history.filter(e => e.entry_type === 'EVOLUTION'); },
        clinicalExams() { if (!this.editingClinicalData.clinical_history) return []; return this.editingClinicalData.clinical_history.filter(e => e.entry_type === 'EXAM'); }
    },
    created() {
        const userJson = sessionStorage.getItem('currentUser');
        let parsedUser = null;
        if (userJson) {
            try {
                parsedUser = JSON.parse(userJson);
            } catch (e) {
                sessionStorage.removeItem('currentUser');
                if (!window.location.pathname.endsWith('index.php') && !window.location.pathname.endsWith('index.php')) {
                    window.location.href = 'index.php';
                    return;
                }
            }
        }

        const currentPage = window.location.pathname.split('/').pop() || 'index.php';

        if (parsedUser) {
            if (currentPage === 'index.php' || currentPage === 'index.php') {
                if (parsedUser.isAdmin == 1) {
                    window.location.replace('admin.php');
                } else {
                    window.location.replace('user.php');
                }
                return;
            } else if (currentPage === 'admin.php' && parsedUser.isAdmin != 1) {
                window.location.replace('user.php');
                return;
            } else if (currentPage === 'user.php' && parsedUser.isAdmin == 1) {
                window.location.replace('admin.php');
                return;
            }

            if (window.location.pathname.endsWith('user.php')) {
                const cleanPath = window.location.pathname.replace('user.php', '');
                window.history.replaceState(null, '', cleanPath);
            }

            this.currentUser = parsedUser;
            this.currentUser.weekly_schedule = this.ensureValidSchedule(this.currentUser.weekly_schedule);
            this.currentUser.disabled_dates = Array.isArray(this.currentUser.disabled_dates) ? this.currentUser.disabled_dates : [];
            this.currentUser.finance_enabled = this.currentUser.finance_enabled ?? 1;
            this.currentUser.finance_ledger_enabled = this.currentUser.finance_ledger_enabled ?? 1;
            this.currentUser.finance_forecast_enabled = this.currentUser.finance_forecast_enabled ?? 1;
            this.currentUser.default_receipt_template_id = this.currentUser.default_receipt_template_id ?? null;
            this.currentUser.professional_register = this.currentUser.professional_register ?? null;
            this.currentUser.future_schedule_enabled = this.currentUser.future_schedule_enabled ?? 0;
            this.currentUser.agenda_enabled = this.currentUser.agenda_enabled ?? 1; 
            this.currentUser.enabled_payment_methods = Array.isArray(this.currentUser.enabled_payment_methods) ? this.currentUser.enabled_payment_methods.map(String) : null;
            
            this.currentUser.memed_enabled = this.currentUser.memed_enabled ?? 0;
            
            if (this.currentUser.agenda_enabled != 1 && this.activeView === 'agenda') {
                this.activeView = 'patients';
            }
            
            this.startClockUpdater();
            this.startTrialCountdown();
            
            if (this.currentUser.isAdmin == 1 && currentPage === 'admin.php') {
                 this.fetchPublicConfig().then(() => {
                     this.fetchUsers();
                     if (this.activeAdminView === 'settings') { this.fetchAdminSettings(); }
                     else if (this.activeAdminView === 'anamnesis') { this.fetchAnamnesisTemplates(); this.fetchUsers();}
                     else if (this.activeAdminView === 'receipts') { this.fetchReceiptTemplates(); this.fetchUsers();}
                     else if (this.activeAdminView === 'price_lists') { this.fetchAllPriceListsAdmin(); this.fetchUsers();}
                     else if (this.activeAdminView === 'budget_forms') { this.fetchBudgetForms(); }
                     else if (this.activeAdminView === 'custom_fields') { this.fetchProfessions(); this.fetchCustomFieldOptions(); }
                     else if (this.activeAdminView === 'medicines') this.fetchMedicines('admin');
                     else if (this.activeAdminView === 'exams') this.fetchExams('admin');
                     else if (this.activeAdminView === 'prescription_templates') this.fetchPrescriptionTemplates('admin');
                 });
             } else if (this.currentUser.isAdmin != 1 && currentPage === 'user.php'){
                 
                 this.fetchPublicConfig().then(() => {
                     this.fetchProfessions().then(() => {
                         if (this.activeView === 'settings') {
                            this.openProfileForEditing();
                            this.fetchPriceLists();
                            this.fetchBudgetForms();
                            this.fetchUserAnamnesisTemplates();
                            this.fetchUserReceiptTemplates();
                            this.fetchMedicines();
                            this.fetchExams();
                            this.fetchPrescriptionTemplates();
                         }
                     });
                     
                     if (this.currentUser.memed_enabled == 1) {
                         setTimeout(() => { this.preloadMemed(); }, 1000);
                     }
                     
                     if (this.activeView === 'agenda') {
                         this.fetchAppointments().catch(err => console.error("Erro inicial ao carregar agenda:", err));
                     }
                     
                     Promise.allSettled([
                         this.fetchPatients(),
                         this.fetchActiveServices(),
                         this.fetchWaitingList()
                     ]).then(() => {
                         this.fetchBirthdays();
                         if (this.activeView === 'agenda' && this.appointments.length === 0) {
                             this.fetchAppointments();
                         }
                     });

                     if (this.activeView === 'future_schedule') { this.fetchFutureSchedule(); }
                     else if (this.activeView === 'hist_atendimentos') { this.fetchAllServices(); }
                     else if (this.activeView === 'budgets') { this.fetchBudgets(); }
                     else if (this.activeView === 'financeiro_livrocaixa') { this.fetchLedgerEntries(); }
                     else if (this.activeView === 'financeiro_previsao') {
                         this.fetchForecastEntries();
                         this.fetchEntryPaymentMethods(); 
                     }
                     else if (this.activeView === 'financeiro_recibos') { 
                         this.fetchLedgerEntriesForReceipts(); 
                         this.fetchGeneratedReceipts(); 
                         this.fetchUserReceiptTemplates(); 
                     }
                 });
             }

        } else {
            if (currentPage !== 'index.php' && currentPage !== 'index.php') {
                window.location.replace('index.php');
                return;
            }
            if (!sessionStorage.getItem('csrf_token')) {
                this.initSession();
            }
            this.fetchPublicConfig();
            this.startDateTimeUpdater();
             const urlParams = new URLSearchParams(window.location.search);
             const resetToken = urlParams.get('reset_token');
             const resetEmail = urlParams.get('email');
             if (resetToken && resetEmail && currentPage !== 'redefinicao.php') { 
                 this.resetPasswordForm.email = resetEmail;
                 this.resetPasswordForm.token = resetToken;
                 this.passwordResetStep = 2;
                 this.openPasswordResetModal();
                 window.history.replaceState({}, document.title, window.location.pathname);
             }
        }
    },
    methods: {
        ...api,
        ...auth,
        ...admin,
        ...agenda,
        ...anamnesis,
        ...budget,
        ...patient,
        ...priceList,
        ...services,
        ...ui,
        ...user,
        ...utils,
        ...finance,
        ...receipt,
        ...memed,
        ...prescription,
        ...odontogram,
