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