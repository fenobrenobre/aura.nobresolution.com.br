export function navigateAgenda(direction) { 
    // Garante que agendaDate é uma data válida, ou usa hoje
    const baseDate = (this.agendaDate instanceof Date && !isNaN(this.agendaDate)) ? this.agendaDate : new Date();
    const newDate = new Date(baseDate); 
    
    const increment = this.agendaView === 'day' ? 1 : 7; 
    newDate.setDate(newDate.getDate() + (direction * increment)); 
    this.agendaDate = newDate; 
    
    // Força busca imediata ao navegar
    this.fetchAppointments();
}

export async function fetchAppointments() { 
    if (!this.currentUser || !this.currentUser.id) {
        console.warn("fetchAppointments: Usuário não identificado. Abortando.");
        return; 
    }
    
    // Garante que agendaDate existe
    if (!this.agendaDate) {
        this.agendaDate = new Date();
    }

    const start = new Date(this.agendaDate); 
    const end = new Date(this.agendaDate); 
    
    if (this.agendaView === 'day') { 
        start.setHours(0, 0, 0, 0); 
        end.setHours(23, 59, 59, 999); 
    } else { 
        // Ajusta para o início da semana (Domingo)
        start.setDate(start.getDate() - start.getDay()); 
        start.setHours(0, 0, 0, 0); 
        // Ajusta para o fim da semana (Sábado)
        end.setDate(end.getDate() - end.getDay() + 6); 
        end.setHours(23, 59, 59, 999); 
    } 
    
    // Adiciona parâmetro timestamp para evitar cache do navegador
    const params = { 
        start: start.toISOString(), 
        end: end.toISOString(),
        _: new Date().getTime() 
    };

    this.isLoading = true;
    const res = await this.apiRequest('getAppointments', params, false, 'GET'); 
    this.isLoading = false;

    if (res.success) { 
        this.appointments = res.appointments.map(a => {
            // Conversão robusta de datas para evitar "Invalid Date" no Safari/Firefox
            const startStr = (a.start_time || '').replace(' ', 'T');
            const endStr = (a.end_time || '').replace(' ', 'T');
            return { 
                ...a, 
                start: new Date(startStr), 
                end: new Date(endStr) 
            };
        }); 
        
        // Força atualização da view
        this.$nextTick(() => {
            if (this.agendaView === 'day') {
                if (this.processedDayAppointments) { /* no-op */ }
            }
        });
    } else {
        this.appointments = [];
        this.showToast('Erro', res.error || 'Falha ao carregar agenda.', 'error');
    }
}

export function getAppointmentStyle(appt) { 
    if (!appt || !appt.start || !appt.end) return {};
    
    const dayStartHour = (typeof this.gridStartHour === 'number') ? this.gridStartHour : 8;

    const startHour = appt.start.getHours();
    const startMin = appt.start.getMinutes();
    
    const topPositionInMinutes = (startHour * 60 + startMin) - (dayStartHour * 60); 
    const top = (topPositionInMinutes / 60) * 4 + 'rem'; 
    
    const durationInMinutes = (appt.end.getTime() - appt.start.getTime()) / (1000 * 60); 
    const height = (durationInMinutes / 60) * 4 + 'rem'; 
    
    const width = `${appt.width || 100}%`; 
    const left = `${appt.left || 0}%`; 
    
    return { top, height, width, left }; 
}

export function getAppointmentClass(appt, viewType) { 
    let baseClass = `absolute p-1 cursor-pointer overflow-hidden transition-all duration-200 ${viewType === 'day' ? 'rounded-r-md' : 'rounded-r-sm'}`; 
    const borderColorClass = viewType === 'day' ? 'border-l-4' : 'border-l-2';

    const statusInProgress = this.getDefaultOptionValue('service_status', 'Em Atendimento');
    const statusTreatment = this.getDefaultOptionValue('service_status', 'Agenda Espera/Não Resolvidos');
    const statusFinalized = this.getDefaultOptionValue('service_status', 'Finalizado');
    const statusFuture = 'AGENDA FUTURA';
    const statusTratamentoFinalizado = 'TRATAMENTO FINALIZADO'; // Novo Status

    if (appt.status === 'Cancelado') {
        return `${baseClass} bg-gray-200 ${borderColorClass} border-gray-400 opacity-70`;
    }
    
    if (appt.status === 'Não Compareceu') {
        return `${baseClass} bg-orange-100 ${borderColorClass} border-orange-500 text-orange-800`;
    }

    if (appt.service_status) {
        if (appt.service_status === statusInProgress) {
            return `${baseClass} bg-green-100 ${borderColorClass} border-green-500`;
        }
        // Adicionado TRATAMENTO FINALIZADO aqui para receber a cor verde
        if (appt.service_status === statusFinalized || appt.service_status === statusTreatment || appt.service_status === statusTratamentoFinalizado) {
            return `${baseClass} bg-green-300 ${borderColorClass} border-green-600`;
        }
        if (appt.service_status === statusFuture) {
            return `${baseClass} bg-purple-100 ${borderColorClass} border-purple-500 text-purple-800`;
        }
    }
    
    if (appt.conflict_color_index !== undefined) { 
        const colorIndex = appt.conflict_color_index % this.conflictColors.length; 
        return `${baseClass} ${this.conflictColors[colorIndex]} ${borderColorClass}`; 
    } 
    
    return `${baseClass} bg-blue-100 ${borderColorClass} border-blue-500`;
}

export function processAppointmentsForLayout(appointments) { 
    if (!appointments || appointments.length === 0) return []; 
    
    const sorted = appointments.map(a => ({...a})).sort((a, b) => a.start - b.start || b.end - a.end); 
    
    let conflictGroups = []; 
    sorted.forEach(appt => { 
        let placed = false; 
        for (let i = conflictGroups.length - 1; i >= 0; i--) { 
            const group = conflictGroups[i]; 
            if (appt.start < group.end && appt.end > group.start) { 
                group.appts.push(appt); 
                group.start = Math.min(group.start, appt.start); 
                group.end = Math.max(group.end, appt.end); 
                placed = true; 
                break; 
            } 
        } 
        if (!placed) { 
            conflictGroups.push({ appts: [appt], start: appt.start, end: appt.end }); 
        } 
    }); 
    
    conflictGroups.forEach(group => { 
        const groupAppts = group.appts.sort((a, b) => a.start - b.start || b.end - a.end); 
        const columns = []; 
        groupAppts.forEach((appt, index) => { 
            let colIndex = 0; 
            while (columns[colIndex] && appt.start < columns[colIndex]) { 
                colIndex++; 
            } 
            appt.col = colIndex; 
            columns[colIndex] = appt.end; 
            
            if (groupAppts.length > 1) { 
                appt.conflict_color_index = index; 
            } 
        }); 
        const numCols = columns.length; 
        groupAppts.forEach(appt => { 
            appt.width = 100 / numCols; 
            appt.left = appt.col * appt.width; 
        }); 
    }); 
    return sorted; 
}

export function getAppointmentsForDay(date) { 
    if (!this.appointments) return []; 
    if (!this.currentUser || this.currentUser.agenda_enabled != 1) {
        return [];
    }
    
    const dayOfWeek = date.getDay(); 
    const schedule = (this.currentUser && this.currentUser.weekly_schedule) 
        ? this.currentUser.weekly_schedule[dayOfWeek] 
        : { enabled: true, start: '08:00', end: '18:00', enabled2: false };
    
    if (this.isDateDisabled(date)) {
        return [];
    }
    
    if (!schedule || (!schedule.enabled && !schedule.enabled2)) { 
        return []; 
    }
    
    const dayAppts = this.appointments.filter(appt => { 
        if (!appt.start) return false;
        
        const apptDate = appt.start; 
        const dateMatch = apptDate.getFullYear() === date.getFullYear() && 
                          apptDate.getMonth() === date.getMonth() && 
                          apptDate.getDate() === date.getDate(); 
        if (!dateMatch) return false; 
        
        return true;
    }); 
    
    return this.processAppointmentsForLayout(dayAppts); 
}

export function openAppointmentModal(appt, date = null, time = null, patient = null) { 
    if (this.currentUser.agenda_enabled != 1) {
        this.showToast('Aviso', 'A sua agenda geral está desativada. Ative-a em Configurações > Funcionalidades para criar agendamentos.', 'error', 6000);
        return;
    }

    let targetDate = date || this.agendaDate; 
    let targetTime = time; 
    
    if (!appt && time !== null) { 
        const [slotH, slotM] = time.split(':').map(Number);
        const slotStart = new Date(targetDate);
        slotStart.setHours(slotH, slotM, 0, 0);
        const slotMinutes = parseInt(this.currentUser?.appointment_slot_minutes) || 30;
        const slotEnd = new Date(slotStart.getTime() + slotMinutes * 60000);

        const hasConflict = this.appointments.some(a => {
            if (a.status === 'Cancelado') return false;
            return (a.start < slotEnd && a.end > slotStart);
        });

        if (!this.isTimeSlotEnabled(targetDate, time) && !hasConflict) {
            this.showToast('Aviso', 'Este horário não está disponível para agendamento (fora do expediente).', 'error'); 
            return; 
        }
    } 
    
    if (appt) { 
        const resolvedPatientName = appt.patient_name || this.getPatientName(appt.patient_id) || 'Paciente';
        
        this.editingAppointment = { 
            ...appt, 
            patient_name: resolvedPatientName, 
            date: appt.start.toLocaleDateString('en-CA'), 
            start_time: `${String(appt.start.getHours()).padStart(2, '0')}:${String(appt.start.getMinutes()).padStart(2, '0')}`, 
            end_time: `${String(appt.end.getHours()).padStart(2, '0')}:${String(appt.end.getMinutes()).padStart(2, '0')}`,
            status: appt.status || 'Agendado' 
        }; 
        this.fetchAvailableSlotsForDate();
    } else if (patient) { 
        const p_id = patient.patient_id || patient.id;
        const p_name = patient.patient_name || patient.name;

        const slotMinutes = parseInt(this.currentUser?.appointment_slot_minutes) || 30; 
        const defaultDate = this.agendaDate || new Date(); 
        let startDate = new Date(defaultDate); 
        startDate.setHours(this.gridStartHour, 0, 0, 0); 
        
        let foundSlot = false; 
        while(startDate.getHours() < this.gridEndHour) { 
            const currentTime = startDate.toTimeString().substring(0,5); 
            if (this.isTimeSlotEnabled(startDate, currentTime)) { 
                const conflicts = this.appointments.some(a => a.start < new Date(startDate.getTime() + slotMinutes * 60000) && a.end > startDate && a.status !== 'Cancelado' ); 
                if (!conflicts) { 
                    foundSlot = true; 
                    break; 
                } 
            } 
            startDate.setMinutes(startDate.getMinutes() + slotMinutes); 
        } 
        if (!foundSlot) { 
            startDate = new Date(defaultDate); 
            startDate.setHours(this.gridStartHour, 0, 0, 0); 
        } 
        const endDate = new Date(startDate.getTime() + slotMinutes * 60000); 
        this.editingAppointment = { 
            id: null, 
            patient_id: p_id, 
            patient_name: p_name,
            title: `Consulta ${p_name}`, 
            date: startDate.toLocaleDateString('en-CA'), 
            start_time: startDate.toTimeString().substring(0, 5), 
            end_time: endDate.toTimeString().substring(0, 5), 
            notes: '',
            status: 'Agendado'
        }; 
        this.fetchAvailableSlotsForDate();
    } else {
        const slotMinutes = parseInt(this.currentUser?.appointment_slot_minutes) || 30; 
        let startH = this.gridStartHour;
        let startM = 0;

        if (targetTime) {
             [startH, startM] = targetTime.split(':').map(Number);
        }

        const startDate = new Date(targetDate); 
        startDate.setHours(startH, startM, 0, 0); 
        const endDate = new Date(startDate.getTime() + slotMinutes * 60000); 
        
        this.editingAppointment = { 
            id: null, 
            patient_id: null, 
            title: '', 
            date: startDate.toLocaleDateString('en-CA'), 
            start_time: startDate.toTimeString().substring(0, 5), 
            end_time: endDate.toTimeString().substring(0, 5), 
            notes: '',
            status: 'Agendado'
        }; 
        this.fetchAvailableSlotsForDate();
    } 
    this.patientSearchQuery = ''; 
    this.patientSearchResults = []; 
    this.showModal('appointment-modal'); 
}

export function scheduleFromFutureSchedule(item) {
    this.reschedulingPatient = { 
        id: item.patient_id, 
        name: item.patient_name, 
        origin: 'futureSchedule', 
        futureScheduleId: item.id,
        serviceId: item.service_id || null 
    };
    this.openAppointmentModal(null, null, null, { id: item.patient_id, name: item.patient_name });
}

export function searchPatientsForAgenda() { 
    if (!this.patientSearchQuery) { 
        this.patientSearchResults = []; 
        return; 
    } 
    const searchTerm = this.patientSearchQuery.toLowerCase();
    this.patientSearchResults = this.patients.filter(p => 
        p.name.toLowerCase().startsWith(searchTerm) || 
        p.name.toLowerCase().includes(' ' + searchTerm)
    ); 
}

export function selectPatientForAppointment(patient) { 
    this.editingAppointment.patient_id = patient.id; 
    this.editingAppointment.title = `Atendimento ${patient.name}`; 
    this.editingAppointment.patient_name = patient.name;
    this.patientSearchQuery = ''; 
    this.patientSearchResults = []; 
}

// ** CRÍTICO: Esta função é usada no HTML para ocultar o botão de reagendamento **
export function isRescheduled(appt) {
    if (!appt || !appt.notes) return false;
    return appt.notes.includes('REAGENDADO COM SUCESSO');
}

export async function saveAppointment(force = false, payloadToForce = null) { 
    let payload;
    if (force && payloadToForce) {
        payload = payloadToForce;
        payload.force = true;
    } else {
        const { date, start_time, end_time } = this.editingAppointment; 
        const startLocal = `${date} ${start_time}:00`; 
        const endLocal = `${date} ${end_time}:00`; 
        
        const startDateTime = new Date(startLocal.replace(' ', 'T')); 
        if (isNaN(startDateTime)) { 
            this.showToast('Erro', 'Data ou hora de início inválida.', 'error'); 
            return; 
        } 
        
        payload = { 
            id: this.editingAppointment.id || null,
            patient_id: this.editingAppointment.patient_id || null,
            title: this.editingAppointment.title,
            notes: this.editingAppointment.notes || null,
            status: this.editingAppointment.status || 'Agendado',
            start_time: startLocal, 
            end_time: endLocal, 
            force: force, 
            reschedule_origin: this.reschedulingPatient.origin || null,
            future_schedule_id: this.reschedulingPatient.futureScheduleId || null,
            origin_service_id: this.reschedulingPatient.serviceId || null,
            waiting_list_id: this.reschedulingPatient.waitingListId || null,
            old_appointment_id: this.reschedulingPatient.oldAppointmentId || null,
            skip_waiting_list: this.reschedulingPatient.oldAppointmentId ? true : false
        }; 
    }
    
    const res = await this.apiRequest('saveAppointment', payload); 
    
    if (res.success) { 
        this.showToast('Sucesso!', 'Agendamento salvo.', 'success'); 
        
        const origin = this.reschedulingPatient.origin;
        const oldAppointmentId = this.reschedulingPatient.oldAppointmentId;
        const oldAppointmentData = this.reschedulingPatient.oldAppointment;
        
        // --- NOVO FLUXO: Finalização de Serviço e Limpeza de Espera ---
        if (this.serviceAwaitingReschedule?.id) {
            const serviceId = this.serviceAwaitingReschedule.id;
            const patientId = this.serviceAwaitingReschedule.patient_id;
            
            // 1. Finaliza o serviço (set status para 'Finalizado')
            const finalStatus = this.getDefaultOptionValue('service_status', 'Finalizado');
            await this.apiRequest('updateActiveService', {
                id: serviceId,
                service_status: finalStatus,
            }, false); 
            
            // 2. Remove da lista de espera (a entrada que foi criada quando clicou em Finalizar)
            await this.apiRequest('removeFromWaitingList', {
                patientId: patientId,
                serviceId: serviceId
            }, false);
            
            // 3. Limpa o estado temporário
            this.serviceAwaitingReschedule = { id: null, patient_id: null };
            
            this.showToast('Sucesso', 'Atendimento finalizado e novo reagendamento criado.', 'success');
        }
        // --- FIM NOVO FLUXO ---
        
        // ** ATUALIZAÇÃO DO AGENDAMENTO ORIGINAL (REAGENDADO COM SUCESSO) **
        if (oldAppointmentData || oldAppointmentId) {
             const tag = 'REAGENDADO COM SUCESSO';
             
             let apptToUpdate = oldAppointmentData;
             if (!apptToUpdate && oldAppointmentId) {
                 apptToUpdate = this.appointments.find(a => a.id == oldAppointmentId);
             }

             if (apptToUpdate) {
                 let currentNotes = apptToUpdate.notes || '';
                 if (!currentNotes.includes(tag)) {
                     let newNotes = currentNotes;
                     if (newNotes.includes('[NÃO COMPARECEU]')) {
                         newNotes = newNotes.replace('[NÃO COMPARECEU]', `[NÃO COMPARECEU] - ${tag}`);
                     } else {
                         newNotes = newNotes ? `${newNotes} - ${tag}` : tag;
                     }
                     
                     const startStr = typeof apptToUpdate.start_time === 'string' ? apptToUpdate.start_time : 
                                      (apptToUpdate.start.toLocaleDateString('en-CA') + ' ' + apptToUpdate.start.toTimeString().substring(0,8));
                                      
                     const endStr = typeof apptToUpdate.end_time === 'string' ? apptToUpdate.end_time : 
                                    (apptToUpdate.end.toLocaleDateString('en-CA') + ' ' + apptToUpdate.end.toTimeString().substring(0,8));

                     const updatePayload = {
                         id: apptToUpdate.id,
                         patient_id: apptToUpdate.patient_id,
                         title: apptToUpdate.title,
                         notes: newNotes,
                         status: apptToUpdate.status, 
                         start_time: startStr,
                         end_time: endStr,
                         force: true,
                         skip_waiting_list: true 
                     };
                     
                     this.apiRequest('saveAppointment', updatePayload, false).then(() => {
                         if (this.activeView === 'agenda') this.fetchAppointments(); 
                         if (this.activeView === 'waiting_list') this.fetchWaitingList(); 
                     });
                 }
             }
        }

        this.reschedulingPatient = { serviceId: null, origin: null, futureScheduleId: null, waitingListId: null, oldAppointmentId: null, oldAppointment: null }; 
        
        this.hideModal('appointment-modal'); 
        this.fetchAppointments(); 
        
        if (origin === 'futureSchedule') {
            this.fetchFutureSchedule();
        } else if (origin === 'waitingList') {
            this.fetchWaitingList(); 
        }

    } else if (res.conflict) { 
        this.showConfirmModal(res.error, () => { 
            this.hideConfirmModal(); 
            this.saveAppointment(true, payload); 
        }); 
    } 
}

export function promptCancelAppointment(appointment) {
    if (!appointment.patient_id) {
        this.showConfirmModal('Tem certeza que deseja remover este bloqueio/agendamento?', async () => {
            const payload = { 
                id: appointment.id,
                status: 'Cancelado', 
                start_time: appointment.start_time || appointment.start.toISOString(),
                end_time: appointment.end_time || appointment.end.toISOString(),
                force: true
            };
            const res = await this.apiRequest('deleteAppointment', { id: appointment.id }); 
            if (res.success) {
                this.showToast('Sucesso', 'Agendamento removido.', 'success');
                this.fetchAppointments();
            }
            this.hideConfirmModal();
            this.hideModal('appointment-modal');
        }, 'bg-red-600 hover:bg-red-700');
    } else {
        this.deletingAppointment = appointment;
        this.deleteReason = ''; 
        this.hideModal('appointment-modal'); 
        this.showModal('delete-reason-modal');
    }
}

export async function confirmCancelAppointment() {
    if (!this.deleteReason.trim()) { 
        this.showToast('Obrigatório', 'Por favor, informe o motivo do cancelamento.', 'error'); 
        return; 
    }
    const appointmentToCancel = this.deletingAppointment;
    const reason = this.deleteReason;
    
    const startStr = appointmentToCancel.start.toLocaleDateString('en-CA') + ' ' + 
                     String(appointmentToCancel.start.getHours()).padStart(2, '0') + ':' + 
                     String(appointmentToCancel.start.getMinutes()).padStart(2, '0') + ':00';
                     
    const endStr = appointmentToCancel.end.toLocaleDateString('en-CA') + ' ' + 
                   String(appointmentToCancel.end.getHours()).padStart(2, '0') + ':' + 
                   String(appointmentToCancel.end.getMinutes()).padStart(2, '0') + ':00';

    const payload = { 
        id: appointmentToCancel.id,
        patient_id: appointmentToCancel.patient_id,
        title: appointmentToCancel.title,
        notes: `[CANCELADO: ${reason}] ${appointmentToCancel.notes || ''}`.trim(),
        status: 'Cancelado',
        start_time: startStr, 
        end_time: endStr,
        force: true
    };

    const res = await this.apiRequest('saveAppointment', payload); 
    
    if (res.success) { 
        this.showToast('Sucesso', 'Agendamento cancelado.', 'success'); 
        this.hideModal('delete-reason-modal'); 
        this.fetchAppointments();

        if (this.currentUser.waiting_list_enabled == 1 && appointmentToCancel.patient_id) {
            const reasonForWL = `Agendamento cancelado em ${new Date().toLocaleDateString('pt-BR')}. Motivo: ${reason}`;
            const wlRes = await this.apiRequest('addToWaitingList', {
                userId: this.currentUser.id,
                patientId: appointmentToCancel.patient_id,
                reason: reasonForWL
            });
            if (wlRes.success) {
                 this.showToast('Info', 'Paciente adicionado à Agenda de Espera automaticamente.', 'info');
                 this.fetchWaitingList();
            }
            this.reschedulingPatient = { serviceId: null, origin: null };
        }
    } else { 
        this.hideModal('delete-reason-modal'); 
    }
    this.deletingAppointment = null; 
    this.deleteReason = ''; 
}

export function fetchAvailableSlotsForDate() {
    this.availableTimeSlots = [];
    if (!this.editingAppointment || !this.editingAppointment.date) {
        return;
    }
    try {
        const selectedDate = new Date(this.editingAppointment.date + 'T00:00:00');
        if (isNaN(selectedDate.getTime())) {
             this.availableTimeSlots = [];
             return;
        }
        const slotMinutes = parseInt(this.currentUser?.appointment_slot_minutes) || 30;
        const apptsOnThisDate = this.appointments.filter(a => {
            if (!a.start) return false;
            const apptDate = a.start;
            return apptDate.getFullYear() === selectedDate.getFullYear() &&
                   apptDate.getMonth() === selectedDate.getMonth() &&
                   apptDate.getDate() === selectedDate.getDate();
        });
        this.availableTimeSlots = this.timeSlots.map(slot => {
            const isEnabled = this.isTimeSlotEnabled(selectedDate, slot.time);
            const [slotH, slotM] = slot.time.split(':').map(Number);
            const slotStart = new Date(selectedDate);
            slotStart.setHours(slotH, slotM, 0, 0);
            const slotEnd = new Date(slotStart.getTime() + slotMinutes * 60000);
            const hasConflict = apptsOnThisDate.some(a => {
                if (this.editingAppointment.id && a.id === this.editingAppointment.id) {
                    return false;
                }
                if (a.status === 'Cancelado') {
                    return false;
                }
                return (a.start < slotEnd && a.end > slotStart);
            });
            return { time: slot.time, available: isEnabled && !hasConflict };
        });
    } catch (e) {
        this.availableTimeSlots = [];
    }
}

export function selectAvailableSlot(time) {
    const slotMinutes = parseInt(this.currentUser?.appointment_slot_minutes) || 30;
    const [h, m] = time.split(':');
    
    const startDate = new Date(this.editingAppointment.date + 'T00:00:00');
    startDate.setHours(parseInt(h), parseInt(m), 0, 0);
    
    const endDate = new Date(startDate.getTime() + slotMinutes * 60000);

    this.editingAppointment.start_time = startDate.toTimeString().substring(0, 5);
    this.editingAppointment.end_time = endDate.toTimeString().substring(0, 5);
}

export function isAppointmentActive(appointmentId) {
    if (!appointmentId || !this.activeServices) {
        return false;
    }
    return this.activeServices.some(service => service.appointment_id == appointmentId);
}

export function isAppointmentFinalized(appt) {
    const statusFinalized = this.getDefaultOptionValue('service_status', 'Finalizado');
    // ** CORREÇÃO: Adiciona "TRATAMENTO FINALIZADO" como status final **
    return appt.service_status === statusFinalized || appt.service_status === 'TRATAMENTO FINALIZADO';
}

export function isAppointmentMissed(appt) {
    if (!appt || !appt.end || !appt.patient_id) return false;
    if (appt.status === 'Não Compareceu') return true;
    const now = new Date();
    const toleranceMinutes = this.currentUser?.missed_appointment_tolerance ?? 60;
    const tolerance = new Date(appt.end.getTime() + toleranceMinutes * 60000);
    return tolerance < now && appt.status !== 'Cancelado' && !appt.service_status;
}

// ** FUNÇÃO ATUALIZADA: Reagendamento Direto **
export async function rescheduleMissedToWaitingList(appt) {
    const patientName = appt.patient_name || this.getPatientName(appt.patient_id);
    
    // 1. Atualiza status para "Não Compareceu" Imediatamente (Sem confirmação)
    const startStr = appt.start.toLocaleDateString('en-CA') + ' ' + 
                     String(appt.start.getHours()).padStart(2, '0') + ':' + 
                     String(appt.start.getMinutes()).padStart(2, '0') + ':00';
    const endStr = appt.end.toLocaleDateString('en-CA') + ' ' + 
                   String(appt.end.getHours()).padStart(2, '0') + ':' + 
                   String(appt.end.getMinutes()).padStart(2, '0') + ':00';

    const payload = { 
        id: appt.id,
        patient_id: appt.patient_id,
        title: appt.title,
        // Adiciona [NÃO COMPARECEU] na nota se não tiver
        notes: (appt.notes || '').includes('[NÃO COMPARECEU]') ? appt.notes : `[NÃO COMPARECEU] ${appt.notes || ''}`.trim(),
        status: 'Não Compareceu',
        start_time: startStr, 
        end_time: endStr,
        force: true,
        // ** IMPORTANTE: Não cria novo lançamento na espera neste momento **
        skip_waiting_list: true 
    };
    
    const updateRes = await this.apiRequest('saveAppointment', payload);
    
    if (updateRes.success) {
        this.fetchAppointments(); // Atualiza a visualização da agenda
        
        // 2. Define o contexto do reagendamento
        this.reschedulingPatient = { 
            id: appt.patient_id, 
            name: patientName, 
            origin: null, 
            serviceId: null,
            oldAppointmentId: appt.id, 
            oldAppointment: appt // Guarda objeto para atualização futura
        };

        // 3. Abre DIRETO o modal de Novo Agendamento
        this.openAppointmentModal(null, null, null, { id: appt.patient_id, name: patientName });

    } else {
         this.showToast('Erro', 'Falha ao atualizar status do agendamento.', 'error');
    }
}