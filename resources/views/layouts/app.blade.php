<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <!-- Styles -->
        @livewireStyles
    </head>
    <body class="font-sans antialiased">
        <x-banner />

        @php
            $isCompanyRoute = request()->routeIs('company.*');
        @endphp

        <div class="min-h-screen bg-gray-100">
            {{-- Navegación superior existente --}}
            <x-navigation />

            @if($isCompanyRoute)
                {{-- Layout con Sidebar para rutas company.* --}}
                <div class="flex">
                    {{-- Incluir sidebar component --}}
                    <x-company-sidebar />

                    {{-- Contenido principal con sidebar --}}
                    <div class="flex-1 lg:ml-64">
                        {{-- Page Heading --}}
                        @if (isset($header))
                            <header class="bg-white shadow">
                                <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                                    {{ $header }}
                                </div>
                            </header>
                        @endif

                        {{-- Page Content --}}
                        <main>
                            {{ $slot }}
                        </main>
                    </div>
                </div>
            @else
                {{-- Layout original para todas las demás rutas --}}
                <!-- Page Heading -->
                @if (isset($header))
                    <header class="bg-white shadow">
                        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endif

                <!-- Page Content -->
                <main>
                    {{ $slot }}
                </main>
            @endif
        </div>

        @stack('modals')
        @stack('scripts')

        @livewireScripts
    </body>
</html>