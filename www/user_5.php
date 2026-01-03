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