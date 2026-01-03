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