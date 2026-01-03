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