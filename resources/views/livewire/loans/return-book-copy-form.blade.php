<div class="pt-2">
    @if($canReturn)
        @if(! $confirming)
            <button type="button" wire:click="confirm" class="app-button-secondary w-full">
                Grąžinti kopiją
            </button>
        @else
            <div class="space-y-4 rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-900/40 dark:bg-amber-500/10">
                <div>
                    <h4 class="text-sm font-semibold text-zinc-950 dark:text-white">Patvirtinti grąžinimą</h4>
                    <p class="mt-1 text-xs leading-5 text-zinc-600 dark:text-zinc-300">
                        {{ $bookCopy->inventory_code ?: ('Kopija #'.$bookCopy->id) }} bus pažymėta kaip laisva, o aktyvus išdavimas bus uždarytas.
                    </p>
                </div>

                @error('bookCopy')
                    <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror

                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <button type="button" wire:click="cancel" class="app-button-secondary w-full">
                        Atšaukti
                    </button>

                    <button type="button" wire:click="save" class="app-button-primary w-full" wire:loading.attr="disabled">
                        <span wire:loading.remove>Patvirtinti</span>
                        <span wire:loading>Grąžinama...</span>
                    </button>
                </div>
            </div>
        @endif
    @elseif($returnUnavailableTitle)
        <button
            type="button"
            class="app-button-secondary w-full opacity-60"
            title="{{ $returnUnavailableTitle }}"
            disabled
        >
            Grąžinti negalima
        </button>
    @endif
</div>







