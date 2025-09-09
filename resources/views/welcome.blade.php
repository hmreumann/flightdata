<x-guest-layout>
    <div class="bg-gradient-to-br from-sky-50 to-blue-100 min-h-screen">
        <!-- Hero Section -->
        <div class="relative overflow-hidden">
            <!-- Background Pattern -->
            <div class="absolute inset-0 opacity-5">
                <svg class="h-full w-full" viewBox="0 0 100 100" fill="none">
                    <defs>
                        <pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse">
                            <path d="M 10 0 L 0 0 0 10" fill="none" stroke="currentColor" stroke-width="1"/>
                        </pattern>
                    </defs>
                    <rect width="100" height="100" fill="url(#grid)" />
                </svg>
            </div>

            <!-- Navigation -->
            <nav class="relative z-10 px-6 pt-8">
                <div class="max-w-7xl mx-auto flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <!-- Flight Icon -->
                        <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center">
                            <x-logo class="w-6 h-6 text-white" />
                        </div>
                        <h1 class="text-2xl font-bold text-gray-900">{{ __('Flight Data') }}</h1>
                    </div>
                    
                    @if (Route::has('login'))
                        <div class="flex items-center gap-4">
                            @auth
                                <a href="{{ url('/dashboard') }}" class="text-gray-600 hover:text-gray-900 font-medium transition-colors">
                                    {{ __('Dashboard') }}
                                </a>
                            @else
                                <a href="{{ route('login') }}" class="text-gray-600 hover:text-gray-900 font-medium transition-colors">
                                    {{ __('Sign In') }}
                                </a>
                                @if (Route::has('register'))
                                    <a href="{{ route('register') }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors font-medium">
                                        {{ __('Get Started') }}
                                    </a>
                                @endif
                            @endauth
                        </div>
                    @endif
                </div>
            </nav>

            <!-- Hero Content -->
            <div class="relative z-10 px-6 pt-20 pb-32">
                <div class="max-w-7xl mx-auto text-center">
                    <h2 class="text-5xl lg:text-6xl font-bold text-gray-900 mb-6">
                        {{ __('Professional Flight Management') }}
                    </h2>
                    <p class="text-xl text-gray-600 mb-8 max-w-3xl mx-auto">
                        {{ __('Streamline your aviation operations with comprehensive flight tracking, crew scheduling, and aircraft management in one powerful platform.') }}
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="bg-blue-600 text-white px-8 py-4 rounded-lg hover:bg-blue-700 transition-colors font-semibold text-lg">
                                {{ __('Start Free Trial') }}
                            </a>
                        @endif
                        <a href="#features" class="bg-white text-gray-900 px-8 py-4 rounded-lg hover:bg-gray-50 transition-colors font-semibold text-lg border border-gray-200">
                            {{ __('Learn More') }}
                        </a>
                    </div>
                </div>
            </div>

            <!-- Hero Illustration -->
            <div class="absolute inset-x-0 bottom-0 h-64 bg-gradient-to-t from-white to-transparent"></div>
        </div>

        <!-- Features Section -->
        <div id="features" class="bg-white py-20">
            <div class="max-w-7xl mx-auto px-6">
                <div class="text-center mb-16">
                    <h3 class="text-3xl font-bold text-gray-900 mb-4">{{ __('Everything You Need') }}</h3>
                    <p class="text-xl text-gray-600">{{ __('Comprehensive tools for modern aviation management') }}</p>
                </div>

                <div class="grid lg:grid-cols-3 gap-8">
                    <!-- Flight Tracking -->
                    <div class="bg-gray-50 rounded-xl p-8 hover:shadow-lg transition-shadow">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mb-6">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </div>
                        <h4 class="text-xl font-semibold text-gray-900 mb-4">{{ __('Real-time Flight Tracking') }}</h4>
                        <p class="text-gray-600">{{ __('Monitor aircraft positions, flight paths, and status updates with live GPS tracking and comprehensive flight data.') }}</p>
                    </div>

                    <!-- Crew Management -->
                    <div class="bg-gray-50 rounded-xl p-8 hover:shadow-lg transition-shadow">
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mb-6">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/>
                            </svg>
                        </div>
                        <h4 class="text-xl font-semibold text-gray-900 mb-4">{{ __('Crew Scheduling') }}</h4>
                        <p class="text-gray-600">{{ __('Efficiently manage pilot and crew assignments, track certifications, and ensure compliance with regulations.') }}</p>
                    </div>

                    <!-- Aircraft Management -->
                    <div class="bg-gray-50 rounded-xl p-8 hover:shadow-lg transition-shadow">
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mb-6">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </div>
                        <h4 class="text-xl font-semibold text-gray-900 mb-4">{{ __('Aircraft Maintenance') }}</h4>
                        <p class="text-gray-600">{{ __('Track maintenance schedules, inspection requirements, and service history to ensure airworthiness and safety.') }}</p>
                    </div>

                    <!-- Route Planning -->
                    <div class="bg-gray-50 rounded-xl p-8 hover:shadow-lg transition-shadow">
                        <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center mb-6">
                            <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                            </svg>
                        </div>
                        <h4 class="text-xl font-semibold text-gray-900 mb-4">{{ __('Route Planning') }}</h4>
                        <p class="text-gray-600">{{ __('Optimize flight paths, calculate fuel requirements, and plan efficient routes with weather and airspace considerations.') }}</p>
                    </div>

                    <!-- Reporting -->
                    <div class="bg-gray-50 rounded-xl p-8 hover:shadow-lg transition-shadow">
                        <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mb-6">
                            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                        </div>
                        <h4 class="text-xl font-semibold text-gray-900 mb-4">{{ __('Analytics & Reporting') }}</h4>
                        <p class="text-gray-600">{{ __('Generate detailed reports on flight operations, costs, efficiency metrics, and compliance documentation.') }}</p>
                    </div>

                    <!-- Safety Management -->
                    <div class="bg-gray-50 rounded-xl p-8 hover:shadow-lg transition-shadow">
                        <div class="w-12 h-12 bg-teal-100 rounded-lg flex items-center justify-center mb-6">
                            <svg class="w-6 h-6 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                        </div>
                        <h4 class="text-xl font-semibold text-gray-900 mb-4">{{ __('Safety Management') }}</h4>
                        <p class="text-gray-600">{{ __('Comprehensive safety protocols, incident reporting, and risk assessment tools to maintain the highest safety standards.') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- CTA Section -->
        <div class="bg-blue-600 py-20">
            <div class="max-w-4xl mx-auto text-center px-6">
                <h3 class="text-3xl font-bold text-white mb-4">{{ __('Ready to Streamline Your Flight Operations?') }}</h3>
                <p class="text-xl text-blue-100 mb-8">{{ __('Join thousands of aviation professionals who trust Flight Data for their management needs.') }}</p>
                @if (Route::has('register'))
                    <a href="{{ route('register') }}" class="bg-white text-blue-600 px-8 py-4 rounded-lg hover:bg-gray-100 transition-colors font-semibold text-lg inline-block">
                        {{ __('Start Your Free Trial Today') }}
                    </a>
                @endif
            </div>
        </div>

        <!-- Footer -->
        <footer class="bg-gray-900 py-12">
            <div class="max-w-7xl mx-auto px-6">
                <div class="flex flex-col md:flex-row items-center justify-between">
                    <div class="flex items-center gap-3 mb-4 md:mb-0">
                        <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center">
                            <x-logo class="w-5 h-5 text-white" />
                        </div>
                        <span class="text-xl font-bold text-white">{{ __('Flight Data') }}</span>
                    </div>
                    <p class="text-gray-400 text-sm">
                        © {{ date('Y') }} {{ __('Flight Data') }}. {{ __('All rights reserved.') }}
                    </p>
                </div>
            </div>
        </footer>
    </div>
</x-guest-layout>
