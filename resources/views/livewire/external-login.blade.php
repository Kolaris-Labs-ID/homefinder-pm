<div class="h-screen overflow-hidden bg-gray-50 flex items-center justify-center">
    <div class="w-full max-w-md">
        <!-- Logo/Brand -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">{{ config('app.name') }}</h1>
            <p class="text-gray-600">Client Portal Access</p>
        </div>

        <!-- Login Card -->
        <div class="bg-white rounded-lg shadow-sm border p-8">
            <h2 class="text-xl font-semibold text-gray-900 mb-6 text-center">Enter Access Code</h2>
            
            <form wire:submit.prevent="authenticate" class="space-y-6">
                <!-- Password Input -->
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                        Access Password
                    </label>
                    <input 
                        type="password" 
                        id="password" 
                        wire:model="password" 
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                        placeholder="Enter your access password"
                        required
                    >
                </div>

                <!-- Error Message -->
                @if($error)
                    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
                        {{ $error }}
                    </div>
                @endif

                <!-- Submit Button -->
                <button 
                    type="submit" 
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-4 rounded-lg transition-colors focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                >
                    Access Portal
                </button>
            </form>
            
            <!-- Footer -->
            <div class="mt-6 text-center">
                <p class="text-xs text-gray-500">
                    Powered by {{ config('app.name') }}
                </p>
            </div>
        </div>
    </div>
</div>