export function showToast(title, message, type = 'success', duration = 4000) {
    this.toast = { title, message, type, visible: true };
    setTimeout(() => { this.toast.visible = false; }, duration);
}

export function triggerFileUpload(elementId) {
    document.getElementById(elementId)?.click();
}

export function showModal(id) {
    document.getElementById(id)?.classList.replace('hidden', 'flex');
}

export function hideModal(id) {
    const modalElement = document.getElementById(id);
    if (modalElement) {
        modalElement.classList.replace('flex', 'hidden');
    }

     if (id === 'register-modal') { this.isGoogleRegister = false; this.passwordStrength = 0; this.passwordFeedback = ''; this.userPhotoPreview = null; this.userPhotoFile = null; }
     if (id === 'forgot-password-modal') { this.passwordStrength = 0; this.passwordFeedback = ''; this.resetPasswordForm = { email: '', token: '', password: '' }; this.passwordResetStep = 1;}
     if (id === 'user-modal') { this.passwordStrength = 0; this.passwordFeedback = ''; this.userAnamnesisTemplates = []; this.userReceiptTemplates = []; this.editingUser = {}; this.userPhotoPreview = null; this.userPhotoFile = null; this.logoPreview = null; this.logoFile = null; }
     
     if (id === 'appointment-modal') {
        // --- LÓGICA DE SEGURANÇA: CANCELAMENTO DO REAGENDAMENTO ---
        // Se o usuário fechou o modal (X ou Sair) e existe um serviço aguardando reagendamento,
        // significa que o fluxo não foi concluído com sucesso.
        // Movemos o atendimento para a Agenda de Espera.
        if (this.serviceAwaitingReschedule && this.serviceAwaitingReschedule.id) {
            if (typeof this.handleRescheduleCancellation === 'function') {
                this.handleRescheduleCancellation();
            }
        }
        // --- FIM ---

        this.editingAppointment = {};
        this.patientSearchQuery = '';
        this.patientSearchResults = [];
     }
     
     if (id === 'clinical-modal') {
        this.editingClinicalData = {};
        this.newEvolutionEntry = '';
        this.newExamEntry = '';
        this.patientAppointments = [];
     }
     if (id === 'patient-modal') {
         this.editingPatient = this.getNewPatientTemplate();
         this.patientPhotoPreview = null;
         this.patientPhotoFile = null;
     }
     if (id === 'standalone-service-modal') {
        this.newStandaloneService = { patient_id: null, description: '' };
        this.standaloneServicePatientSearch = '';
     }
     if (id === 'price-list-modal') {
        this.editingPriceList = { make_global: false, user_id: null };
     }
     if (id === 'price-item-modal') {
        this.editingPriceItem = {};
     }
     if (id === 'budget-form-modal') {
        this.editingBudgetForm = { fields: {} };
     }
     if (id === 'anamnesis-modal') {
         this.editingAnamnesis = { make_global: false, assign_to_user_id: null };
     }
     if (id === 'receipt-template-modal') {
        this.editingReceipt = { make_global: false, assign_to_user_id: null, is_default: false };
     }
     if (id === 'recommendation-template-modal') {
        this.editingRecommendation = {};
     }
     if (id === 'medicine-modal') {
         this.editingMedicine = {};
     }
     if (id === 'exam-modal') {
         this.editingExam = {};
     }
     if (id === 'prescription-template-modal') {
         this.editingPrescriptionTemplate = {};
     }
     if (id === 'prescription-generator-modal') {
         this.closePrescriptionGenerator();
     }
     if (id === 'memed-prescription-modal') {
        this.closeMemedModal();
     }
     if (id === 'finish-service-modal') {
        this.serviceToFinish = null;
     }
     // Modais de User Profile / Configurações
     if (id === 'user-anamnesis-modal') {
        this.editingUserAnamnesis = { originalIsGlobal: false };
     }
     if (id === 'user-receipt-modal') {
        this.editingUserReceipt = { originalIsGlobal: false, is_default: false };
     }
     if (id === 'user-payment-method-modal') {
        this.editingUserPaymentMethod = { id: null, option_value: '', originalIsGlobal: false };
     }
     if (id === 'ledger-entry-modal') {
        this.editingLedgerEntry = { id: null, entry_type: 'entrada', description: '', amount: null };
        this.ledgerPatientSearch = '';
     }
     if (id === 'manual-forecast-modal') {
        this.editingForecastEntry = { id: null, forecast_type: 'receita' };
        this.manualForecastPatientSearch = '';
     }
     if (id === 'mark-as-paid-modal') {
        this.editingPaymentForecast = { id: null };
     }
     if (id === 'edit-historical-service-modal') {
        this.editingHistoricalService = {};
     }
     if (id === 'future-schedule-modal') {
         this.futureScheduleForm = { id: null, patient_id: null, return_date: '', reason: '' };
     }
     if (id === 'add-to-waiting-list-modal') {
         this.manualWaitingList = { patientSearch: '', patientId: null, reason: '', searchResults: [] };
     }
     if (id === 'new-budget-patient-modal') {
         this.newBudgetPatientSearch = '';
         this.newBudgetPatientResults = [];
     }
     if (id === 'maintenance-auth-modal') {
         this.maintenanceAuth = { mode: 'simple', action: null, admin_password: '', login_password: '' };
     }
}

export function showConfirmModal(message, onConfirm, confirmButtonClass = 'bg-red-600 hover:bg-red-700', confirmButtonText = 'Sim, Confirmar') {
    this.confirmationModal = {
        visible: true,
        message,
        onConfirm: () => {
            onConfirm();
            this.hideConfirmModal();
        },
        confirmButtonClass,
        confirmButtonText
    };
    this.showModal('confirm-modal');
}

export function hideConfirmModal() {
    this.confirmationModal = { 
        visible: false, 
        message: '', 
        onConfirm: null, 
        confirmButtonClass: 'bg-red-600 hover:bg-red-700',
        confirmButtonText: 'Sim, Confirmar'
    };
    this.hideModal('confirm-modal');
}

export function openWebcamModal(target) {
    this.activePhotoTarget = target;
    const video = this.$refs.webcamVideo;
    
    // Cleanup any existing stream first
    if (this.webcamStream) {
        this.webcamStream.getTracks().forEach(track => track.stop());
    }

    navigator.mediaDevices.getUserMedia({ video: true, audio: false })
        .then(stream => {
            this.webcamStream = stream;
            video.srcObject = stream;
            video.play();
            this.showModal('webcam-modal');
        })
        .catch(err => {
            console.error("Erro ao acessar a webcam: ", err);
            this.showToast('Erro', 'Não foi possível acessar a webcam. Verifique as permissões do navegador.', 'error');
        });
}

export function closeWebcamModal() {
    if (this.webcamStream) {
        this.webcamStream.getTracks().forEach(track => track.stop());
    }
    this.webcamStream = null;
    this.hideModal('webcam-modal');
}

export function capturePhoto() {
    const video = this.$refs.webcamVideo;
    const canvas = this.$refs.webcamCanvas;
    
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    
    const context = canvas.getContext('2d');
    context.drawImage(video, 0, 0, canvas.width, canvas.height);
    canvas.toBlob(blob => {
        if (!blob) {
            this.showToast('Erro', 'Falha ao gerar imagem da webcam.', 'error');
            return;
        }
        const file = new File([blob], "webcam-photo.jpg", { type: "image/jpeg" });
        let targetFile, targetPreview;
        if (this.activePhotoTarget === 'register' || this.activePhotoTarget === 'user') {
            targetFile = 'userPhotoFile';
            targetPreview = 'userPhotoPreview';
        } else {
            targetFile = 'patientPhotoFile';
            targetPreview = 'patientPhotoPreview';
        }
        this[targetFile] = file;
        const reader = new FileReader();
        reader.onload = (e) => { this[targetPreview] = e.target.result; };
        reader.readAsDataURL(file);
        this.closeWebcamModal();
    }, 'image/jpeg');
}

export function openPatientQuickView(patientId) {
    if (!patientId) return;

    let patient = this.patients.find(p => p.id == patientId);

    if (patient) {
        this.quickViewPatient = { ...patient };
        this.showModal('patient-quick-view-modal');
        return;
    }

    this.isLoading = true;
    try {
        this.apiRequest('getPatientDetails', { patientId }, false, 'GET').then(res => {
            if (res.success && res.patient) {
                this.quickViewPatient = res.patient;
                this.showModal('patient-quick-view-modal');
            } else {
                this.showToast('Erro', res.error || 'Não foi possível carregar os dados do paciente.', 'error');
            }
        }).finally(() => {
             this.isLoading = false;
        });
    } catch (e) {
        this.isLoading = false;
        this.showToast('Erro', 'Erro de comunicação ao buscar dados do paciente.', 'error');
    }
}

export function debouncedFetchPendingReceipts() {
    if (this.receiptSearchTimeout) clearTimeout(this.receiptSearchTimeout);
    this.receiptSearchTimeout = setTimeout(() => {
        this.receiptPaginationPending.currentPage = 1;
        this.fetchLedgerEntriesForReceipts();
    }, 400);
}

export function debouncedFetchGeneratedReceipts() {
    if (this.receiptSearchTimeout) clearTimeout(this.receiptSearchTimeout);
    this.receiptSearchTimeout = setTimeout(() => {
        this.receiptPaginationGenerated.currentPage = 1;
        this.fetchGeneratedReceipts();
    }, 400);
}