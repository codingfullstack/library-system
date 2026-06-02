<div class="inline-block">
    @if(! $reservation->isPending())
        <span class="inline-flex rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-700 dark:bg-red-500/15 dark:text-red-300">
            Atšaukta
        </span>
    @elseif(! $isOpen)
        <div class="flex items-center gap-2">
            @if($compact)
                <button type="button" wire:click="open" title="Atšaukti rezervaciją" aria-label="Atšaukti rezervaciją" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-zinc-200 bg-white text-zinc-500 transition hover:text-red-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400 dark:hover:text-red-300">
                    <flux:icon.x-circle class="size-4" />
                </button>
            @else
                <button type="button" wire:click="open" class="app-button-danger">
                    Atšaukti
                </button>
            @endif

            @if($message)
                <span class="text-xs font-medium text-emerald-700 dark:text-emerald-300">{{ $message }}</span>
            @endif
        </div>
    @else
        <div class="w-full max-w-md rounded-lg border border-zinc-200 bg-white p-4 text-left shadow-sm dark:border-zinc-800 dark:bg-zinc-950/95">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h4 class="text-sm font-semibold text-zinc-950 dark:text-white">Atšaukti rezervaciją</h4>
                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                        {{ $reservation->book?->title ?: 'Nežinoma knyga' }}
                    </p>
                </div>

                <button type="button" wire:click="close" class="text-sm font-medium text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">
                    Uždaryti
                </button>
            </div>

            <div class="mt-4">
                <label for="cancel-reason-{{ $reservation->id }}" class="app-label">
                    {{ $requiresReason ? 'Atšaukimo priežastis' : 'Priežastis (neprivaloma)' }}
                </label>
                <textarea
                    id="cancel-reason-{{ $reservation->id }}"
                    wire:model="reason"
                    rows="3"
                    class="app-input mt-2"
                    placeholder="Parašykite, kodėl rezervacija atšaukiama"
                ></textarea>
                @error('reason')
                    <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
                @error('reservation')
                    <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="mt-4 flex justify-end gap-2">
                <button type="button" wire:click="close" class="app-button-secondary">Grįžti</button>
                <button type="button" wire:click="save" wire:loading.attr="disabled" class="app-button-danger">
                    <span wire:loading.remove wire:target="save">Patvirtinti atšaukimą</span>
                    <span wire:loading wire:target="save">Atšaukiama...</span>
                </button>
            </div>
        </div>
    @endif
</div>
