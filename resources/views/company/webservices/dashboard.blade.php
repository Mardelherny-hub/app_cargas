<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    📊 Dashboard de Webservices - {{ $company->legal_name }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    Métricas y estadísticas de envíos a aduanas
                </p>
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('company.webservices.index') }}" 
                   class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg transition-colors">
                    ← Dashboard Principal
                </a>
                <a href="{{ route('company.webservices.history') }}" 
                   class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors">
                    📋 Historial Completo
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Filtros de fecha --}}
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">🔍 Filtros de Período</h3>
                </div>
                <div class="p-6">
                    <form method="GET" action="{{ route('company.webservices.dashboard') }}" class="flex items-end space-x-4">
                        <div>
                            <label for="date_from" class="block text-sm font-medium text-gray-700">Fecha Desde</label>
                            <input type="date" name="date_from" id="date_from" value="{{ $dateFrom }}" 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                   max="{{ date('Y-m-d') }}">
                        </div>
                        <div>
                            <label for="date_to" class="block text-sm font-medium text-gray-700">Fecha Hasta</label>
                            <input type="date" name="date_to" id="date_to" value="{{ $dateTo }}" 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                   max="{{ date('Y-m-d') }}">
                        </div>
                        <div class="flex space-x-2">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                🔍 Filtrar
                            </button>
                            <a href="{{ route('company.webservices.dashboard') }}" 
                               class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded-md text-sm font-medium">
                                🔄 Limpiar
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Cards de resumen --}}
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="bg-white overflow-hidden shadow-sm rounded-lg border-l-4 border-blue-500">
                    <div class="p-6 text-center">
                        <div class="text-3xl font-bold text-blue-600 mb-1">{{ number_format($metrics['summary']['total_transactions']) }}</div>
                        <div class="text-sm text-gray-600">Total Transacciones</div>
                        <div class="text-blue-500 text-2xl mt-2">📊</div>
                    </div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm rounded-lg border-l-4 border-green-500">
                    <div class="p-6 text-center">
                        <div class="text-3xl font-bold text-green-600 mb-1">{{ $metrics['summary']['success_rate'] }}%</div>
                        <div class="text-sm text-gray-600">Tasa de Éxito</div>
                        <div class="text-xs text-gray-500">{{ number_format($metrics['summary']['success_transactions']) }} exitosas</div>
                        <div class="text-green-500 text-2xl mt-2">✅</div>
                    </div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm rounded-lg border-l-4 border-yellow-500">
                    <div class="p-6 text-center">
                        <div class="text-3xl font-bold text-yellow-600 mb-1">{{ number_format($metrics['summary']['avg_response_time_ms']) }}</div>
                        <div class="text-sm text-gray-600">Tiempo Promedio (ms)</div>
                        <div class="text-yellow-500 text-2xl mt-2">⏱️</div>
                    </div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm rounded-lg border-l-4 border-indigo-500">
                    <div class="p-6 text-center">
                        <div class="text-3xl font-bold text-indigo-600 mb-1">{{ number_format($metrics['summary']['pending_transactions']) }}</div>
                        <div class="text-sm text-gray-600">Pendientes</div>
                        <div class="text-indigo-500 text-2xl mt-2">⏳</div>
                    </div>
                </div>
            </div>

            {{-- Gráficos principales --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {{-- Gráfico de estado (Donut) --}}
                <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">🎯 Estado de Transacciones</h3>
                    </div>
                    <div class="p-6">
                        <canvas id="statusChart" class="w-full h-64"></canvas>
                    </div>
                </div>

                {{-- Gráfico por país --}}
                <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">🌍 Transacciones por País</h3>
                    </div>
                    <div class="p-6">
                        <canvas id="countryChart" class="w-full h-64"></canvas>
                    </div>
                </div>
            </div>

            {{-- Evolución temporal --}}
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">📈 Evolución Últimos 7 Días</h3>
                </div>
                <div class="p-6">
                    <canvas id="timelineChart" class="w-full h-80"></canvas>
                </div>
            </div>

            {{-- Gráficos por tipo de webservice --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 bg-white overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">📋 Transacciones por Tipo de Webservice</h3>
                    </div>
                    <div class="p-6">
                        <canvas id="typeChart" class="w-full h-64"></canvas>
                    </div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">⚡ Tiempo de Respuesta por Tipo</h3>
                    </div>
                    <div class="p-6">
                        <div class="space-y-3">
                            @forelse($metrics['by_type']['avg_response_time'] as $type => $time)
                            <div class="flex justify-between items-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                    {{ strtoupper($type) }}
                                </span>
                                <span class="text-sm font-medium text-gray-900">{{ number_format($time) }}ms</span>
                            </div>
                            @empty
                            <p class="text-sm text-gray-500 text-center">No hay datos de tiempo de respuesta</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            {{-- Confirmaciones específicas por país --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-white overflow-hidden shadow-sm rounded-lg border-l-4 border-blue-500">
                    <div class="p-6 border-b border-gray-200 bg-blue-50">
                        <h3 class="text-lg font-semibold text-blue-900">🇦🇷 Argentina (AFIP)</h3>
                    </div>
                    <div class="p-6">
                        <div class="flex justify-between items-center">
                            <div>
                                <div class="text-3xl font-bold text-blue-600 mb-1">{{ number_format($metrics['by_country']['argentina_confirmations']) }}</div>
                                <div class="text-sm text-gray-600">Confirmaciones TitEnvío</div>
                            </div>
                            <div class="text-blue-500 text-4xl">🏛️</div>
                        </div>
                        <div class="mt-4 pt-4 border-t border-gray-200">
                            <div class="text-xs text-gray-500 space-y-1">
                                <div>Total transacciones AR: <span class="font-medium">{{ $metrics['by_country']['transactions']['AR'] ?? 0 }}</span></div>
                                <div>Exitosas: <span class="font-medium text-green-600">{{ $metrics['by_country']['success']['AR'] ?? 0 }}</span></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm rounded-lg border-l-4 border-green-500">
                    <div class="p-6 border-b border-gray-200 bg-green-50">
                        <h3 class="text-lg font-semibold text-green-900">🇵🇾 Paraguay (DNA)</h3>
                    </div>
                    <div class="p-6">
                        <div class="flex justify-between items-center">
                            <div>
                                <div class="text-3xl font-bold text-green-600 mb-1">{{ number_format($metrics['by_country']['paraguay_confirmations']) }}</div>
                                <div class="text-sm text-gray-600">Referencias GDSF</div>
                            </div>
                            <div class="text-green-500 text-4xl">🏢</div>
                        </div>
                        <div class="mt-4 pt-4 border-t border-gray-200">
                            <div class="text-xs text-gray-500 space-y-1">
                                <div>Total transacciones PY: <span class="font-medium">{{ $metrics['by_country']['transactions']['PY'] ?? 0 }}</span></div>
                                <div>Exitosas: <span class="font-medium text-green-600">{{ $metrics['by_country']['success']['PY'] ?? 0 }}</span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Transacciones recientes y estados pendientes --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 bg-white overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">🕒 Transacciones Recientes</h3>
                    </div>
                    <div class="p-6">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">País</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Viaje</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usuario</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tiempo</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Confirmación</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @forelse($metrics['recent_transactions'] as $transaction)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                {{ strtoupper($transaction['webservice_type']) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $transaction['country'] === 'AR' ? '🇦🇷 AR' : '🇵🇾 PY' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @if($transaction['status'] === 'success')
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">{{ $transaction['status'] }}</span>
                                            @elseif(in_array($transaction['status'], ['error', 'expired']))
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">{{ $transaction['status'] }}</span>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">{{ $transaction['status'] }}</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900">{{ $transaction['voyage_number'] }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $transaction['user_name'] }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $transaction['created_at'] }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $transaction['response_time_ms'] ? number_format($transaction['response_time_ms']) . 'ms' : '-' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900">
                                            @if($transaction['argentina_tit_envio'])
                                                <span class="text-green-600">{{ $transaction['argentina_tit_envio'] }}</span>
                                            @elseif($transaction['paraguay_reference'])
                                                <span class="text-green-600">{{ $transaction['paraguay_reference'] }}</span>
                                            @elseif($transaction['confirmation_number'])
                                                <span class="text-blue-600">{{ $transaction['confirmation_number'] }}</span>
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="8" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                            📭 No hay transacciones en el período seleccionado
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">⚠️ Estados Pendientes</h3>
                    </div>
                    <div class="p-6">
                        <div class="space-y-4">
                            @forelse($metrics['pending_voyage_statuses'] as $status)
                            <div class="border-l-4 border-yellow-400 bg-yellow-50 p-4 rounded-r-lg">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h4 class="text-sm font-medium text-gray-900">{{ $status['voyage_number'] }}</h4>
                                        <p class="text-xs text-gray-600">
                                            {{ $status['country'] === 'AR' ? '🇦🇷' : '🇵🇾' }} {{ strtoupper($status['country']) }} - {{ $status['webservice_type'] }}
                                        </p>
                                    </div>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $status['status'] === 'error' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800' }}">
                                        {{ $status['status'] }}
                                    </span>
                                </div>
                                @if($status['error_message'])
                                <p class="text-xs text-red-600 mt-2">{{ $status['error_message'] }}</p>
                                @endif
                                <p class="text-xs text-gray-500 mt-1">{{ $status['updated_at'] }}</p>
                            </div>
                            @empty
                            <div class="text-center text-gray-500 py-8">
                                <div class="text-4xl mb-2">✅</div>
                                <p class="text-sm">No hay estados pendientes</p>
                            </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <script>
        console.log('Chart.js disponible:', typeof Chart !== 'undefined');
        console.log('Datos del dashboard:', @json($metrics));

        document.addEventListener('DOMContentLoaded', function() {
            // Configuración general de Chart.js
            Chart.defaults.font.size = 12;
            Chart.defaults.plugins.legend.position = 'bottom';

            // 1. Gráfico de estado (Donut)
            const statusCtx = document.getElementById('statusChart').getContext('2d');
            new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Exitosas', 'Con Error', 'Pendientes'],
                    datasets: [{
                        data: [
                            {{ $metrics['summary']['success_transactions'] }},
                            {{ $metrics['summary']['error_transactions'] }},
                            {{ $metrics['summary']['pending_transactions'] }}
                        ],
                        backgroundColor: ['#10b981', '#ef4444', '#f59e0b'],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });

            // 2. Gráfico por país (Bar)
            const countryCtx = document.getElementById('countryChart').getContext('2d');
            new Chart(countryCtx, {
                type: 'bar',
                data: {
                    labels: ['Argentina (AFIP)', 'Paraguay (DNA)'],
                    datasets: [
                        {
                            label: 'Total',
                            data: [
                                {{ $metrics['by_country']['transactions']['AR'] ?? 0 }},
                                {{ $metrics['by_country']['transactions']['PY'] ?? 0 }}
                            ],
                            backgroundColor: ['#3b82f6', '#10b981']
                        },
                        {
                            label: 'Exitosas',
                            data: [
                                {{ $metrics['by_country']['success']['AR'] ?? 0 }},
                                {{ $metrics['by_country']['success']['PY'] ?? 0 }}
                            ],
                            backgroundColor: ['#1d4ed8', '#047857']
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            // 3. Gráfico por tipo de webservice (Horizontal Bar) - CORREGIDO
            const typeCtx = document.getElementById('typeChart').getContext('2d');
            
            // Preparar datos de tipos
            const typeLabels = [];
            const typeData = [];
            const typeSuccessData = [];

            @if($metrics['by_type']['transactions']->count() > 0)
                @foreach($metrics['by_type']['transactions'] as $transaction)
                    typeLabels.push('{{ strtoupper($transaction->webservice_type) }}');
                    typeData.push({{ $transaction->total }});
                    typeSuccessData.push({{ $metrics['by_type']['success'][$transaction->webservice_type] ?? 0 }});
                @endforeach
            @endif

            new Chart(typeCtx, {
                type: 'bar',
                data: {
                    labels: typeLabels,
                    datasets: [
                        {
                            label: 'Total',
                            data: typeData,
                            backgroundColor: '#6b7280'
                        },
                        {
                            label: 'Exitosas',
                            data: typeSuccessData,
                            backgroundColor: '#10b981'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    scales: {
                        x: {
                            beginAtZero: true
                        }
                    }
                }
            });

            // 4. Gráfico de evolución temporal (Line) - CORREGIDO
            const timelineCtx = document.getElementById('timelineChart').getContext('2d');
            
            // Preparar datos temporales
            const timelineLabels = [];
            const timelineTotal = [];
            const timelineSuccess = [];

            @if($metrics['timeline']->count() > 0)
                @foreach($metrics['timeline'] as $day)
                    timelineLabels.push('{{ \Carbon\Carbon::parse($day->date)->format('d/m') }}');
                    timelineTotal.push({{ $day->total }});
                    timelineSuccess.push({{ $day->success }});
                @endforeach
            @else
                // Si no hay datos, mostrar días vacíos
                for(let i = 6; i >= 0; i--) {
                    const date = new Date();
                    date.setDate(date.getDate() - i);
                    timelineLabels.push(date.getDate() + '/' + (date.getMonth() + 1));
                    timelineTotal.push(0);
                    timelineSuccess.push(0);
                }
            @endif

            new Chart(timelineCtx, {
                type: 'line',
                data: {
                    labels: timelineLabels,
                    datasets: [
                        {
                            label: 'Total',
                            data: timelineTotal,
                            borderColor: '#3b82f6',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'Exitosas',
                            data: timelineSuccess,
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            // Debug: Mostrar datos en consola
            console.log('Dashboard Debug:', {
                summary: {!! json_encode($metrics['summary']) !!},
                by_country: {!! json_encode($metrics['by_country']) !!},
                by_type_count: {{ $metrics['by_type']['transactions']->count() }},
                timeline_count: {{ $metrics['timeline']->count() }}
            });

            // Auto-refresh cada 5 minutos
            setInterval(function() {
                location.reload();
            }, 300000);
        });
    </script>
</x-app-layout>

