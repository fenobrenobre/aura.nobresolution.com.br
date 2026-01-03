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