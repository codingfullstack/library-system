<div class="pt-2">
    @if($canReturn)
        @if(! $confirming)
            <button type="button" wire:click="confirm" class="app-button-secondary w-full">
                Grazinti kopija
            </button>
        @else
            <div class="space-y-4 rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-900/40 dark:bg-amber-500/10">
                <div>
                    <h4 class="text-sm font-semibold text-zinc-950 dark:text-white">Patvirtinti grazinima</h4>
                    <p class="mt-1 text-xs leading-5 text-zinc-600 dark:text-zinc-300">
                        Kopija #{{ $bookCopy->id }} bus pazymeta kaip laisva, o aktyvus isdavimas bus uzdarytas.
                    </p>
                </div>

                @error('bookCopy')
                    <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror

                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <button type="button" wire:click="cancel" class="app-button-secondary w-full">
                        Atsaukti
                    </button>

                    <button type="button" wire:click="save" class="app-button-primary w-full" wire:loading.attr="disabled">
                        <span wire:loading.remove>Patvirtinti</span>
                        <span wire:loading>Grazinama...</span>
                    </button>
                </div>
            </div>
        @endif
    @endif
</div>
