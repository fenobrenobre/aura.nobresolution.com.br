<div v-show="activeAdminUserCustomTab === 'funcionalidades'">
                            <h3 class="text-lg font-semibold border-b pb-2 mb-4">Ativação de Funcionalidades</h3>
                            <div class="space-y-4">
                                
                                <div>
                                    <label class="block text-sm font-medium">Agenda (Geral)</label>
                                    <div class="flex items-center mt-2">
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" v-model="editingUser.agenda_enabled" :true-value="1" :false-value="0" class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                                        </label>
                                        <span class="ml-3 font-medium">{{ editingUser.agenda_enabled == 1 ? 'Ativada' : 'Desativada' }}</span>
                                    </div>
                                </div>

                                <div class="pt-4 border-t" :class="{'opacity-50': editingUser.agenda_enabled != 1}">
                                    <label class="block text-sm font-medium">Menu Aniversariantes</label>
                                    <div class="flex items-center mt-2">
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" v-model="editingUser.birthday_list_enabled" :true-value="1" :false-value="0" class="sr-only peer" :disabled="editingUser.agenda_enabled != 1">
                                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                                        </label>
                                        <span class="ml-3 font-medium">{{ editingUser.birthday_list_enabled == 1 && editingUser.agenda_enabled == 1 ? 'Ativado' : 'Desativado' }}</span>
                                    </div>
                                </div>

                                <div class="pt-4 border-t" :class="{'opacity-50': editingUser.agenda_enabled != 1}">
                                    <label class="block text-sm font-medium">Agenda Espera/Não Resolvidos</label>
                                    <div class="flex items-center mt-2">
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" v-model="editingUser.waiting_list_enabled" @change="handleWaitingListChange" :true-value="1" :false-value="0" class="sr-only peer" :disabled="editingUser.agenda_enabled != 1">
                                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                                        </label>
                                        <span class="ml-3 font-medium">{{ editingUser.waiting_list_enabled == 1 && editingUser.agenda_enabled == 1 ? 'Ativada' : 'Desativada' }}</span>
                                    </div>
                                </div>
                            
                                <div class="pt-4 border-t" :class="{'opacity-50': editingUser.agenda_enabled != 1 || editingUser.waiting_list_enabled != 1}">
                                    <label class="block text-sm font-medium">Agenda Futura</label>
                                    <div class="flex items-center mt-2">
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" v-model="editingUser.future_schedule_enabled" @change="handleFutureScheduleChange" :true-value="1" :false-value="0" class="sr-only peer" :disabled="editingUser.agenda_enabled != 1 || editingUser.waiting_list_enabled != 1">
                                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                                        </label>
                                        <span class="ml-3 font-medium">{{ editingUser.future_schedule_enabled == 1 && editingUser.agenda_enabled == 1 && editingUser.waiting_list_enabled == 1 ? 'Ativada' : 'Desativada' }}</span>
                                    </div>
                                </div>
                                
                                <div class="pt-4 border-t">
                                    <label class="block text-sm font-medium">Integração MEMED</label>
                                    <div class="flex items-center mt-2">
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" v-model="editingUser.memed_enabled" :true-value="1" :false-value="0" class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                                        </label>
                                        <span class="ml-3 font-medium">{{ editingUser.memed_enabled == 1 ? 'Ativado' : 'Desativado' }}</span>
                                    </div>
                                </div>

                                <div class="pt-4 border-t" v-if="editingUser.system_version === 'Saude'">
                                    <label class="block text-sm font-medium">Odontograma Interativo</label>
                                    <div class="flex items-center mt-2">
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" v-model="editingUser.odontogram_enabled" :true-value="1" :false-value="0" class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                                        </label>
                                        <span class="ml-3 font-medium">{{ editingUser.odontogram_enabled == 1 ? 'Ativado' : 'Desativado' }}</span>
                                    </div>
                                </div>

                                <div class="pt-4 border-t">
                                    <label class="block text-sm font-medium">Módulo Financeiro</label>
                                    <div class="flex items-center mt-2">
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" v-model="editingUser.finance_enabled" :true-value="1" :false-value="0" class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                                        </label>
                                        <span class="ml-3 font-medium">{{ editingUser.finance_enabled == 1 ? 'Ativado' : 'Desativado' }}</span>
                                    </div>
                                </div>

                                <div class="ml-4" v-if="editingUser.finance_enabled == 1">
                                    <label class="block text-sm font-medium">Livro Caixa</label>
                                    <div class="flex items-center mt-2">
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" v-model="editingUser.finance_ledger_enabled" :true-value="1" :false-value="0" class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                                        </label>
                                        <span class="ml-3 font-medium">{{ editingUser.finance_ledger_enabled == 1 ? 'Ativado' : 'Desativado' }}</span>
                                    </div>
                                </div>

                                <div class="ml-4" v-if="editingUser.finance_enabled == 1">
                                    <label class="block text-sm font-medium">Previsão Receitas/Desp.</label>
                                    <div class="flex items-center mt-2">
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" v-model="editingUser.finance_forecast_enabled" :true-value="1" :false-value="0" class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                                        </label>
                                        <span class="ml-3 font-medium">{{ editingUser.finance_forecast_enabled == 1 ? 'Ativado' : 'Desativado' }}</span>
                                    </div>
                                </div>
                            </div> 
                        </div>

                        <div v-show="activeAdminUserCustomTab === 'horarios'">
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                <div>
                                    <h3 class="text-lg font-semibold border-b pb-2 mb-4">Horário Semanal</h3>
                                    <div v-if="editingUser.weekly_schedule" class="space-y-4">
                                        <div v-for="(day, index) in weekDaysNames" :key="index" class="border-b pb-3 last:border-b-0 last:pb-0">
                                            <h3 class="font-semibold text-sm mb-2">{{ day }}</h3>
                                            <div class="flex items-center space-x-2 mb-2">
                                                <input type="checkbox" :id="'day-1-'+index" v-model="editingUser.weekly_schedule[index].enabled" class="h-5 w-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                                <label :for="'day-1-'+index" class="w-12 text-sm text-gray-600">Turno 1</label>
                                                <input type="time" v-model="editingUser.weekly_schedule[index].start" class="form-input p-1 text-sm w-24" :disabled="!editingUser.weekly_schedule[index].enabled">
                                                <span class="text-gray-500">às</span>
                                                <input type="time" v-model="editingUser.weekly_schedule[index].end" class="form-input p-1 text-sm w-24" :disabled="!editingUser.weekly_schedule[index].enabled">
                                            </div>
                                            <div class="flex items-center space-x-2">
                                                <input type="checkbox" :id="'day-2-'+index" v-model="editingUser.weekly_schedule[index].enabled2" class="h-5 w-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                                <label :for="'day-2-'+index" class="w-12 text-sm text-gray-600">Turno 2</label>
                                                <input type="time" v-model="editingUser.weekly_schedule[index].start2" class="form-input p-1 text-sm w-24" :disabled="!editingUser.weekly_schedule[index].enabled2">
                                                <span class="text-gray-500">às</span>
                                                <input type="time" v-model="editingUser.weekly_schedule[index].end2" class="form-input p-1 text-sm w-24" :disabled="!editingUser.weekly_schedule[index].enabled2">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <h3 class="text-lg font-semibold border-b pb-2 mb-4">Datas Desativadas</h3>
                                    <div class="flex items-center gap-2 mb-4"> <input type="date" v-model="newDisabledDate" class="form-input flex-grow"> <button type="button" @click="addDisabledDate" class="px-3 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"><i class="fa-solid fa-plus"></i></button> </div>
                                    <div class="max-h-48 overflow-y-auto space-y-2"> <p v-if="!editingUser.disabled_dates || editingUser.disabled_dates.length === 0" class="text-center text-sm text-gray-500 py-4">Nenhuma data desativada adicionada.</p> <div v-else v-for="date in editingUser.disabled_dates" :key="date" class="flex justify-between items-center bg-gray-50 p-2 rounded"> <span class="text-sm font-medium">{{ formatDateForDisabledList(date) }}</span> <button type="button" @click="removeDisabledDate(date)" class="text-red-500 hover:text-red-700 text-xs"><i class="fa-solid fa-times"></i></button> </div> </div>
                                </div>
                            </div>
                        </div>

                        <div v-show="activeAdminUserCustomTab === 'payment_methods'">
                            <h3 class="text-lg font-semibold border-b pb-2 mb-4">Formas de Pagamento</h3>
                            <div class="flex justify-end mb-4">
                                <button @click="openUserPaymentMethodModal(null)" type="button" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">
                                    <i class="fa-solid fa-plus mr-2"></i>Adicionar Método Pessoal para este Usuário
                                </button>
                            </div>

                            <div class="border rounded-lg overflow-hidden">
                                <table class="min-w-full bg-white">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase w-10">Usar</th>
                                            <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase">Nome</th>
                                            <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase">Proprietário</th>
                                            <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <tr v-if="!userPaymentMethods || userPaymentMethods.length === 0">
                                            <td colspan="4" class="p-4 text-center text-gray-500">Nenhuma forma de pagamento disponível.</td>
                                        </tr>
                                        <tr v-for="method in userPaymentMethods" :key="method.id" class="hover:bg-gray-50">
                                            <td class="py-3 px-3 text-center">
                                                <input type="checkbox" :value="method.id" v-model="editingUser.enabled_payment_methods" 
                                                       class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                       :disabled="method.is_default && method.is_global">
                                            </td>
                                            <td class="py-3 px-3 font-medium text-gray-900">{{ method.option_value }}</td>
                                            <td class="py-3 px-3">
                                                <span class="text-xs px-2 py-0.5 rounded-full" 
                                                      :class="method.is_global ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'">
                                                      {{ method.is_global ? 'Global' : 'Pessoal' }}
                                                </span>
                                            </td>
                                            <td class="py-3 px-3 text-sm">
                                                <button @click.prevent="openUserPaymentMethodModal(method)" 
                                                        :disabled="method.is_global"
                                                        :class="{'opacity-30 cursor-not-allowed': method.is_global}"
                                                        class="text-indigo-600 hover:text-indigo-900 mr-3">
                                                    <i class="fa-solid fa-pen"></i>
                                                </button>
                                                <button @click.prevent="deleteUserPaymentMethod(method.id)" 
                                                        :disabled="method.is_global || method.is_default"
                                                        :class="{'opacity-30 cursor-not-allowed': method.is_global || method.is_default}"
                                                        class="text-red-600 hover:text-red-900">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div v-show="activeAdminUserCustomTab === 'comunicacoes'">
                            <h3 class="text-lg font-semibold border-b pb-2 mb-4">Comunicações por E-mail</h3>
                            <div class="space-y-6">
                                <div>
                                    <div class="flex items-center justify-between">
                                        <label class="block text-sm font-medium">Confirmação de Agendamento (Imediato)</label>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" v-model="editingUser.schedule_email_enabled" :true-value="1" :false-value="0" class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                                        </label>
                                    </div>
                                    <textarea v-model="editingUser.schedule_email_template" rows="4" class="form-input mt-2" placeholder="Ex: Olá [PACIENTE], seu agendamento..."></textarea>
                                </div>

                                <div class="pt-4 border-t">
                                    <div class="flex items-center justify-between">
                                        <label class="block text-sm font-medium">Lembrete de Agendamento</label>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" v-model="editingUser.reminder_email_enabled" :true-value="1" :false-value="0" class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                                        </label>
                                    </div>
                                    <div class="flex gap-4 mt-2">
                                        <label class="flex items-center text-sm">
                                            <input type="checkbox" value="24" v-model="editingUser.reminder_email_hours" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                            <span class="ml-2 text-gray-700">24h antes</span>
                                        </label>
                                        <label class="flex items-center text-sm">
                                            <input type="checkbox" value="48" v-model="editingUser.reminder_email_hours" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                            <span class="ml-2 text-gray-700">48h antes</span>
                                        </label>
                                    </div>
                                    <textarea v-model="editingUser.reminder_email_template" rows="4" class="form-input mt-2" placeholder="Ex: Lembrete do agendamento..."></textarea>
                                </div>
                                
                                <div class="pt-4 border-t" v-if="editingUser.future_schedule_enabled == 1">
                                    <div class="flex items-center justify-between">
                                        <label class="block text-sm font-medium">Notificação de Agenda Futura</label>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" v-model="editingUser.future_schedule_email_enabled" :true-value="1" :false-value="0" class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                                        </label>
                                    </div>
                                    <textarea v-model="editingUser.future_schedule_email_template" rows="4" class="form-input mt-2" placeholder="Ex: Olá [PACIENTE], seu retorno..."></textarea>
                                </div>

                                <div class="pt-4 border-t">
                                    <div class="flex items-center justify-between">
                                        <label class="block text-sm font-medium">Mensagem de Aniversário</label>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" v-model="editingUser.birthday_email_enabled" :true-value="1" :false-value="0" class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                                        </label>
                                    </div>
                                    <div class="mt-2">
                                        <label class="block text-sm font-medium">Horário de Envio</label>
                                        <input type="time" v-model="editingUser.birthday_email_time" class="form-input p-1 text-sm w-32">
                                    </div>
                                    <textarea v-model="editingUser.birthday_email_template" rows="4" class="form-input mt-2" placeholder="Ex: Feliz aniversário!"></textarea>
                                </div>
                            </div>
                        </div>

                        <div v-show="activeAdminUserCustomTab === 'integrations'">
                            <h3 class="text-lg font-semibold border-b pb-2 mb-4">Integrações Externas</h3>
                            <div class="space-y-6">
                                <div>
                                    <div class="flex justify-between items-center mb-4">
                                        <span class="font-medium flex items-center gap-2"><i class="fa-brands fa-google text-red-500"></i> Google Calendar</span>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" v-model="editingUser.google_calendar_enabled" :true-value="1" :false-value="0" class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 peer-checked:bg-green-600 transition-all"></div>
                                        </label>
                                    </div>
                                    <div class="grid grid-cols-1 gap-3" v-if="editingUser.google_calendar_enabled == 1">
                                        <div><label class="block text-xs font-medium">Client ID</label><input type="text" v-model="editingUser.google_client_id" class="form-input text-sm"></div>
                                        <div><label class="block text-xs font-medium">Client Secret</label><input type="password" v-model="editingUser.google_client_secret" class="form-input text-sm"></div>
                                    </div>
                                </div>
                                <div class="pt-4 border-t">
                                    <div class="flex justify-between items-center mb-4">
                                        <span class="font-medium flex items-center gap-2"><i class="fa-solid fa-file-prescription text-green-600"></i> Memed (Prescrição)</span>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" v-model="editingUser.memed_enabled" :true-value="1" :false-value="0" class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 peer-checked:bg-green-600 transition-all"></div>
                                        </label>
                                    </div>
                                </div>
                                <div class="pt-4 border-t">
                                    <div class="flex justify-between items-center mb-4">
                                        <span class="font-medium flex items-center gap-2"><i class="fa-solid fa-tooth text-blue-500"></i> Odontograma</span>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" v-model="editingUser.odontogram_enabled" :true-value="1" :false-value="0" class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 peer-checked:bg-green-600 transition-all"></div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-show="activeAdminUserCustomTab === 'maintenance'">
                            <h3 class="text-lg font-semibold border-b pb-2 mb-4">Manutenção e Acesso</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Status do Contratante</label>
                                    <div class="mt-2 flex items-center">
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" v-model="isUserActive" class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                                        </label>
                                        <span class="ml-3 font-medium" :class="isUserActive ? 'text-green-700' : 'text-red-700'">{{ isUserActive ? 'Ativo' : 'Inativo' }}</span>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Data de Desativação (Trial)</label>
                                    <input type="datetime-local" v-model="editingUser.deactivationDate" class="form-input">
                                </div>
                                <div class="md:col-span-2 pt-4 border-t">
                                    <label class="block text-sm font-medium text-gray-700">Senha Administrativa (Limpeza de Dados)</label>
                                    <p class="text-xs text-gray-500 mb-2">Defina uma senha para que o usuário possa realizar limpezas de banco de dados.</p>
                                    <input type="text" v-model="editingUser.admin_password" class="form-input" placeholder="Gerada automaticamente se vazio">
                                </div>
                                <div class="md:col-span-2 pt-4 border-t">
                                    <label class="block text-sm font-medium text-gray-700">Permissão de Administrador</label>
                                    <div class="mt-2 flex items-center">
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" v-model="editingUser.isAdmin" :true-value="1" :false-value="0" class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                        </label>
                                        <span class="ml-3 font-medium">{{ editingUser.isAdmin == 1 ? 'Sim, é Admin' : 'Não, é Usuário' }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div> <div class="flex justify-end items-center gap-4 mt-8 pt-4 border-t">
                        <button type="button" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300" @click="hideModal('user-modal')">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Salvar</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="profession-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden items-center justify-center p-4 z-50">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
                <h2 class="text-xl font-bold mb-4">{{ editingProfession.id ? 'Editar Profissão' : 'Nova Profissão' }}</h2>
                <form @submit.prevent="saveProfession">
                    <div><label class="block text-sm font-medium">Nome da Profissão</label><input type="text" v-model="editingProfession.name" required class="form-input"></div>
                    <div class="flex justify-end gap-4 mt-6">
                        <button type="button" @click="hideModal('profession-modal')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Salvar</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="anamnesis-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden items-center justify-center p-4 z-50 overflow-y-auto">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl p-6 my-8">
                <button @click="hideModal('anamnesis-modal')" type="button" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark fa-lg"></i></button>
                <h2 class="text-xl font-bold mb-4">{{ editingAnamnesis.id ? 'Editar Modelo Anamnese' : 'Novo Modelo de Anamnese' }}</h2>
                <form @submit.prevent="saveAnamnesisTemplate">
                    <div class="mb-4"><label class="block text-sm font-medium">Título do Modelo *</label><input type="text" v-model="editingAnamnesis.title" required class="form-input"></div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2">Proprietário</label>
                        <div class="flex items-center space-x-4">
                            <label class="flex items-center">
                                <input type="checkbox" v-model="editingAnamnesis.make_global" @change="editingAnamnesis.assign_to_user_id = null" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-gray-700">Tornar Global (visível para todos)</span>
                            </label>
                            <select v-model="editingAnamnesis.assign_to_user_id" class="form-input flex-1" :disabled="editingAnamnesis.make_global">
                                <option :value="null">-- Atribuir a Usuário --</option>
                                <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                            </select>
                        </div>
                    </div>
                    <div><label class="block text-sm font-medium">Conteúdo da Anamnese</label><textarea v-model="editingAnamnesis.content" rows="15" class="form-input"></textarea></div>
                    <div class="flex justify-end gap-4 mt-6">
                        <button type="button" @click="hideModal('anamnesis-modal')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Salvar Modelo</button>
                    </div>
                </form>
            </div>
        </div>
        
        <div id="receipt-template-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden items-center justify-center p-4 z-50 overflow-y-auto">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl p-6 my-8">
                <button @click="hideModal('receipt-template-modal')" type="button" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark fa-lg"></i></button>
                <h2 class="text-xl font-bold mb-4">{{ editingReceipt.id ? 'Editar Modelo Recibo' : 'Novo Modelo de Recibo' }}</h2>
                <form @submit.prevent="saveReceiptTemplate">
                    <div class="mb-4"><label class="block text-sm font-medium">Título do Modelo *</label><input type="text" v-model="editingReceipt.title" required class="form-input"></div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2">Proprietário</label>
                        <div class="flex items-center space-x-4">
                            <label class="flex items-center">
                                <input type="checkbox" v-model="editingReceipt.make_global" @change="editingReceipt.assign_to_user_id = null" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-gray-700">Tornar Global (visível para todos)</span>
                            </label>
                            <select v-model="editingReceipt.assign_to_user_id" class="form-input flex-1" :disabled="editingReceipt.make_global">
                                <option :value="null">-- Atribuir a Usuário --</option>
                                <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="flex items-center">
                            <input type="checkbox" v-model="editingReceipt.is_default" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="ml-2 text-gray-700">Tornar Padrão (para o dono selecionado, ou global)</span>
                        </label>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium">Conteúdo do Recibo</label>
                        <div class="p-2 bg-gray-50 border rounded-md mb-2 text-xs text-gray-600">
                            <strong>Variáveis disponíveis:</strong>
                            [PACIENTE], [CPF], [VALOR], [VALOR_EXTENSO], [DATA], [RECIBO_NUMERO], [DESCRICAO], 
                            [USUARIO_NOME], [USUARIO_PROFISSAO], [USUARIO_CPF], [CIDADE], [DATA_GERACAO]
                        </div>
                        <textarea v-model="editingReceipt.content" rows="15" class="form-input"></textarea>
                    </div>
                    <div class="flex justify-end gap-4 mt-6">
                        <button type="button" @click="hideModal('receipt-template-modal')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Salvar Modelo</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="recommendation-template-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden items-center justify-center p-4 z-50 overflow-y-auto">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl p-6 my-8">
                <button @click="hideModal('recommendation-template-modal')" type="button" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark fa-lg"></i></button>
                <h2 class="text-xl font-bold mb-4">{{ editingRecommendation.id ? 'Editar Recomendação' : 'Nova Recomendação' }}</h2>
                <form @submit.prevent="saveRecommendationTemplate">
                    <div class="mb-4"><label class="block text-sm font-medium">Título *</label><input type="text" v-model="editingRecommendation.title" required class="form-input"></div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2">Proprietário</label>
                        <div class="flex items-center space-x-4">
                            <label class="flex items-center">
                                <input type="checkbox" v-model="editingRecommendation.make_global" @change="editingRecommendation.assign_to_user_id = null" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-gray-700">Tornar Global (visível para todos)</span>
                            </label>
                            <select v-model="editingRecommendation.assign_to_user_id" class="form-input flex-1" :disabled="editingRecommendation.make_global">
                                <option :value="null">-- Atribuir a Usuário --</option>
                                <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                            </select>
                        </div>
                    </div>
                    <div><label class="block text-sm font-medium">Conteúdo (Texto do Rodapé)</label><textarea v-model="editingRecommendation.content" rows="5" class="form-input"></textarea></div>
                    <div class="flex justify-end gap-4 mt-6">
                        <button type="button" @click="hideModal('recommendation-template-modal')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Salvar</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="budget-form-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden items-center justify-center p-4 z-50">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6">
                <h2 class="text-xl font-bold mb-4">{{ editingBudgetForm.id ? 'Editar Formulário' : 'Novo Formulário de Orçamento' }}</h2>
                <form @submit.prevent="saveBudgetForm">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium">Nome do Formulário</label>
                            <input type="text" v-model="editingBudgetForm.name" required class="form-input">
                        </div>
                        <div>
                            <label class="block text-sm font-medium">Identificador Único</label>
                            <input type="text" v-model="editingBudgetForm.identifier" required class="form-input" :disabled="editingBudgetForm.id <= 2">
                            <p class="text-xs text-gray-500 mt-1">Usado pelo sistema. Apenas letras, números e underscore (_). Não pode ser alterado para os formulários padrão.</p>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium mb-2">Campos Visíveis</h3>
                            <label class="flex items-center">
                                <input type="checkbox" v-model="editingBudgetForm.fields.region" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-gray-700">Exibir campo "Região"</span>
                            </label>
                        </div>
                    </div>
                    <div class="flex justify-end gap-4 mt-6 pt-4 border-t">
                        <button type="button" @click="hideModal('budget-form-modal')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Salvar</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="price-item-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden items-center justify-center p-4 z-50">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
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

        <div id="price-list-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden items-center justify-center p-4 z-50 overflow-y-auto">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6 my-8">
                <button @click="hideModal('price-list-modal')" type="button" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark fa-lg"></i></button>
                <h2 class="text-xl font-bold mb-4">{{ editingPriceList.id ? 'Editar Tabela' : 'Nova Tabela de Preços' }}</h2>
                <form @submit.prevent="savePriceList">
                    <div class="mb-4 space-y-4">
                         <div>
                             <label class="block text-sm font-medium">Nome da Tabela *</label>
                             <input type="text" v-model="editingPriceList.name" required class="form-input">
                         </div>
                         <label class="flex items-center">
                             <input type="checkbox" v-model="editingPriceList.make_global" @change="editingPriceList.user_id = null" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                             <span class="ml-2 text-gray-700">Tornar Global (visível para todos)</span>
                         </label>
                         <div>
                             <label class="block text-sm font-medium">Atribuir a:</label>
                             <select v-model="editingPriceList.user_id" class="form-input" :disabled="editingPriceList.make_global">
                                 <option :value="null">-- Nenhum (se global) --</option>
                                 <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                             </select>
                         </div>
                    </div>
                    <div class="flex justify-end gap-4 mt-6">
                        <button type="button" @click="hideModal('price-list-modal')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Salvar</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="admin-manage-items-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden items-center justify-center p-4 z-40 modal-overlay overflow-y-auto">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-3xl p-6 my-8">
                 <button @click="hideModal('admin-manage-items-modal'); activePriceListForItems = null" type="button" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark fa-lg"></i></button>
                 <div v-if="activePriceListForItems">
                     <h2 class="text-2xl font-bold mb-2">Gerenciando Itens da Tabela: {{ activePriceListForItems.name }}</h2>
                     <p class="text-sm text-gray-600 mb-6" v-if="currentUser.isAdmin && !activePriceListForItems.is_global">Proprietário: {{ activePriceListForItems.user_name }}</p>
                     <p class="text-sm text-blue-600 mb-6" v-if="activePriceListForItems.is_global">Esta é uma Tabela Global.</p>
                 </div>
                 <div class="flex justify-end mb-4">
                     <button @click="openPriceItemModal(null)" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm"><i class="fa-solid fa-plus"></i><span class="hidden sm:inline ml-2">Adicionar Item</span></button>
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
                                     <button @click="openPriceItemModal(item)" class="text-indigo-600 hover:text-indigo-900 mr-3"><i class="fa-solid fa-pen-to-square"></i></button>
                                     <button @click="deletePriceItem(item.id)" class="text-red-600 hover:text-red-900"><i class="fa-solid fa-trash-can"></i></button>
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

        <div id="custom-field-option-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden items-center justify-center p-4 z-50">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
                <button @click="hideModal('custom-field-option-modal')" type="button" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark fa-lg"></i></button>
                <h2 class="text-xl font-bold mb-4">{{ editingCustomFieldOption.id ? 'Editar Opção' : 'Nova Opção' }}</h2>
                <p class="text-sm text-gray-600 mb-4">Para o campo: <strong class="capitalize">{{ editingCustomFieldOption.field_type?.replace('_', ' ') }}</strong></p>
                <form @submit.prevent="saveCustomFieldOption">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium">Valor da Opção *</label>
                            <input type="text" v-model="editingCustomFieldOption.option_value" required class="form-input">
                        </div>
                        
                        <div v-if="editingCustomFieldOption.field_type === 'payment_method'" class="space-y-4 pt-4 border-t">
                            <div>
                                <label class="block text-sm font-medium mb-2">Proprietário</label>
                                <div class="flex items-center space-x-4">
                                    <label class="flex items-center">
                                        <input type="checkbox" v-model="editingCustomFieldOption.make_global" @change="editingCustomFieldOption.assign_to_user_id = null" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-gray-700">Tornar Global</span>
                                    </label>
                                    <select v-model="editingCustomFieldOption.assign_to_user_id" class="form-input flex-1" :disabled="editingCustomFieldOption.make_global">
                                        <option :value="null">-- Atribuir a Usuário --</option>
                                        <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        </div>
                    <div class="flex justify-end gap-4 mt-6">
                        <button type="button" @click="hideModal('custom-field-option-modal')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Salvar Opção</button>
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
                        <div class="relative">
                            <label class="block text-sm font-medium text-gray-700">Nome do Medicamento *</label>
                            <div class="flex gap-2">
                                <input type="text" v-model="editingMedicine.name" @input="searchMedicines(editingMedicine.name)" required class="form-input flex-grow" placeholder="Digite para buscar ou cadastrar...">
                                <button v-if="editingMedicine.name" type="button" @click="editingMedicine.name = ''; medicines = []" class="text-gray-400 hover:text-gray-600"><i class="fa-solid fa-times"></i></button>
                            </div>
                            
                            <div v-if="medicines.length > 0 && editingMedicine.name && medicines[0].name !== editingMedicine.name" class="absolute z-10 w-full bg-white border rounded-md mt-1 max-h-48 overflow-y-auto shadow-lg">
                                <a v-for="med in medicines" :key="med.id" @click.prevent="selectMedicineForAdmin(med)" class="block px-4 py-2 text-sm hover:bg-blue-50 cursor-pointer border-b last:border-0">
                                    <div class="font-semibold text-gray-800">
                                        {{ med.name }}
                                        <span v-if="med.source === 'external'" class="text-xs text-orange-500 ml-1 font-normal">(Banco Nacional)</span>
                                    </div>
                                    <div class="text-xs text-gray-500 truncate">{{ med.presentation || med.instructions }}</div>
                                </a>
                            </div>
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

                        <div class="mb-4 pt-4 border-t">
                            <label class="block text-sm font-medium mb-2">Proprietário</label>
                            <div class="flex items-center space-x-4">
                                <label class="flex items-center">
                                    <input type="checkbox" v-model="editingMedicine.make_global" @change="editingMedicine.assign_to_user_id = null" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <span class="ml-2 text-gray-700">Tornar Global (visível para todos)</span>
                                </label>
                                <select v-model="editingMedicine.assign_to_user_id" class="form-input flex-1" :disabled="editingMedicine.make_global">
                                    <option :value="null">-- Atribuir a Usuário --</option>
                                    <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                                </select>
                            </div>
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
                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-2">Proprietário</label>
                            <div class="flex items-center space-x-4">
                                <label class="flex items-center">
                                    <input type="checkbox" v-model="editingExam.make_global" @change="editingExam.assign_to_user_id = null" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <span class="ml-2 text-gray-700">Tornar Global (visível para todos)</span>
                                </label>
                                <select v-model="editingExam.assign_to_user_id" class="form-input flex-1" :disabled="editingExam.make_global">
                                    <option :value="null">-- Atribuir a Usuário --</option>
                                    <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-4 mt-6">
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
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2">Proprietário</label>
                        <div class="flex items-center space-x-4">
                            <label class="flex items-center">
                                <input type="checkbox" v-model="editingPrescriptionTemplate.make_global" @change="editingPrescriptionTemplate.assign_to_user_id = null" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-gray-700">Tornar Global (visível para todos)</span>
                            </label>
                            <select v-model="editingPrescriptionTemplate.assign_to_user_id" class="form-input flex-1" :disabled="editingPrescriptionTemplate.make_global">
                                <option :value="null">-- Atribuir a Usuário --</option>
                                <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-end gap-4 mt-6">
                        <button type="button" @click="hideModal('prescription-template-modal')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Salvar Modelo</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="webcam-modal" class="fixed inset-0 bg-gray-900 bg-opacity-75 hidden items-center justify-center p-4 z-50">
             <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-4 relative">
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

        <div id="admin-manage-specialties-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden justify-center p-4 z-50 modal-overlay overflow-y-auto">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6 my-8">
                <button @click="hideModal('admin-manage-specialties-modal'); activeProfessionForSpecialties = null" type="button" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark fa-lg"></i></button>
                
                <div v-if="activeProfessionForSpecialties">
                    <h2 class="text-xl font-bold mb-2">Especialidades: {{ activeProfessionForSpecialties.name }}</h2>
                    <p class="text-sm text-gray-500 mb-4">Gerencie as especialidades vinculadas a esta profissão.</p>
                    
                    <div class="flex justify-end mb-4">
                         <button @click="openSpecialtyModal(null)" class="px-3 py-1 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700"><i class="fa-solid fa-plus mr-1"></i> Nova Especialidade</button>
                    </div>

                    <div class="border rounded-md max-h-64 overflow-y-auto">
                        <ul class="divide-y divide-gray-200">
                            <li v-for="spec in specialties" :key="spec.id" class="flex justify-between items-center p-3 hover:bg-gray-50">
                                <span class="text-sm text-gray-800">{{ spec.name }}</span>
                                <div>
                                    <button @click="openSpecialtyModal(spec)" class="text-indigo-600 hover:text-indigo-900 mr-3"><i class="fa-solid fa-pen"></i></button>
                                    <button @click="deleteSpecialty(spec.id)" class="text-red-600 hover:text-red-900"><i class="fa-solid fa-trash"></i></button>
                                </div>
                            </li>
                            <li v-if="!specialties.length" class="p-4 text-center text-gray-500 text-sm">Nenhuma especialidade cadastrada.</li>
                        </ul>
                    </div>
                </div>
                
                <div class="flex justify-end mt-6">
                    <button type="button" @click="hideModal('admin-manage-specialties-modal')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Fechar</button>
                </div>
            </div>
        </div>
        
        <div id="specialty-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden items-center justify-center p-4 z-[60]">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-sm p-6">
                <h2 class="text-lg font-bold mb-4">{{ editingSpecialty.id ? 'Editar Especialidade' : 'Nova Especialidade' }}</h2>
                <form @submit.prevent="saveSpecialty">
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">Nome</label>
                        <input type="text" v-model="editingSpecialty.name" required class="form-input w-full">
                    </div>
                    <div class="flex justify-end gap-3">
                        <button type="button" @click="hideModal('specialty-modal')" class="px-3 py-1.5 bg-gray-200 text-gray-800 rounded hover:bg-gray-300 text-sm">Cancelar</button>
                        <button type="submit" class="px-3 py-1.5 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
        
        <div id="user-payment-method-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden items-center justify-center p-4 z-[70]">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
                <button @click="hideModal('user-payment-method-modal')" type="button" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark fa-lg"></i></button>
                <h2 class="text-xl font-bold mb-4">{{ editingUserPaymentMethod.id ? 'Editar Método de Pagamento' : 'Novo Método de Pagamento' }}</h2>
                <p v-if="editingUserPaymentMethod.originalIsGlobal" class="text-xs text-blue-600 mb-4 bg-blue-50 p-2 rounded border border-blue-200">Nota: Você está editando uma cópia de um método global. Salvar criará um novo método pessoal para este usuário.</p>
                <form @submit.prevent="saveUserPaymentMethod">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium">Nome do Método *</label>
                            <input type="text" v-model="editingUserPaymentMethod.option_value" required class="form-input" placeholder="Ex: Cartão de Crédito 3x">
                        </div>
                    </div>
                    <div class="flex justify-end gap-4 mt-6">
                        <button type="button" @click="hideModal('user-payment-method-modal')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Salvar Método</button>
                    </div>
                </form>
            </div>
        </div>
    
    </div>

    <script type="module" src="./Logic/app.js"></script>
</body>
</html>