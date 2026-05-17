<x-filament-panels::page>
    <x-filament::section>
        <form wire:submit="submit" class="space-y-6">
            @if (! $useRecoveryCode)
                <div class="space-y-2">
                    <label for="two-factor-code" class="text-sm font-medium text-gray-950 dark:text-white">
                        Authenticator code
                    </label>

                    <x-filament::input.wrapper :valid="! $errors->has('code')">
                        <x-filament::input
                            id="two-factor-code"
                            inputmode="numeric"
                            maxlength="8"
                            type="text"
                            wire:model="code"
                            autocomplete="one-time-code"
                        />
                    </x-filament::input.wrapper>

                    @error('code')
                        <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                    @enderror
                </div>

                <x-filament::link tag="button" type="button" wire:click="$set('useRecoveryCode', true)">
                    Use a recovery code
                </x-filament::link>
            @else
                <div class="space-y-2">
                    <label for="two-factor-recovery-code" class="text-sm font-medium text-gray-950 dark:text-white">
                        Recovery code
                    </label>

                    <x-filament::input.wrapper :valid="! $errors->has('recoveryCode')">
                        <x-filament::input
                            id="two-factor-recovery-code"
                            type="password"
                            wire:model="recoveryCode"
                            autocomplete="one-time-code"
                        />
                    </x-filament::input.wrapper>

                    @error('recoveryCode')
                        <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                    @enderror
                </div>

                <x-filament::link tag="button" type="button" wire:click="$set('useRecoveryCode', false)">
                    Use an authenticator code
                </x-filament::link>
            @endif

            <x-filament::button type="submit">
                Continue
            </x-filament::button>
        </form>
    </x-filament::section>
</x-filament-panels::page>
