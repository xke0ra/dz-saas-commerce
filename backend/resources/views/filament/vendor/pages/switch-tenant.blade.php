<x-filament-panels::page>
    @if (session('status'))
        <div class="rounded-lg border border-success-200 bg-success-50 px-4 py-3 text-sm text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-300">
            {{ session('status') }}
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Current tenant</div>
            <div class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">
                {{ $currentTenant?->name ?? 'No tenant selected' }}
            </div>
            <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ $currentTenant?->slug ?? 'Select a tenant to scope vendor resources.' }}
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900 xl:col-span-2">
            <div class="flex flex-col gap-1">
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">Available tenants</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Only tenants available to your account can be selected.
                </p>
            </div>

            <div class="mt-4 divide-y divide-gray-100 overflow-hidden rounded-lg border border-gray-200 dark:divide-white/10 dark:border-white/10">
                @forelse ($availableTenants as $tenant)
                    <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div class="font-medium text-gray-950 dark:text-white">{{ $tenant->name }}</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">{{ $tenant->slug }}</div>
                        </div>

                        <form method="POST" action="{{ $switchRoute }}">
                            @csrf
                            <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">

                            <button
                                type="submit"
                                @disabled($currentTenant?->is($tenant))
                                class="rounded-lg bg-primary-600 px-3 py-2 text-sm font-semibold text-white hover:bg-primary-500 disabled:cursor-not-allowed disabled:bg-gray-200 disabled:text-gray-500 dark:disabled:bg-white/10 dark:disabled:text-gray-400"
                            >
                                {{ $currentTenant?->is($tenant) ? 'Current' : 'Switch' }}
                            </button>
                        </form>
                    </div>
                @empty
                    <div class="p-6 text-center text-sm text-gray-500 dark:text-gray-400">
                        No tenants are available for this account.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</x-filament-panels::page>
