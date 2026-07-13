<div>
    @if($isOpen)
        <div class="fixed inset-0 z-50 bg-zinc-950/50" wire:key="book-detail-copy-drawer-{{ $book->id }}">
            <button type="button" wire:click="close" class="absolute inset-0 cursor-default" aria-label="Uždaryti"></button>

            <aside class="absolute inset-y-0 right-0 z-10 flex h-full w-96 max-w-[calc(100vw-1rem)] flex-col overflow-hidden border-l border-zinc-200 bg-white shadow-2xl sm:w-[32rem] dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex items-start justify-between gap-4 border-b border-zinc-200 px-6 py-5 dark:border-zinc-800">
                    <div>
                        <h2 class="text-xl font-bold text-zinc-950 dark:text-white">Pridėti kopiją</h2>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Nauja kopija bus pridėta prie pasirinktos knygos.</p>
                    </div>

                    <button type="button" wire:click="close" aria-label="Uždaryti" class="inline-flex size-10 shrink-0 items-center justify-center rounded-2xl border border-zinc-200 bg-white text-zinc-500 shadow-sm transition hover:bg-zinc-50 hover:text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-white">
                        <flux:icon.x-mark class="size-5" />
                    </button>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto px-6 py-5">
                    <livewire:manage.book-copies.book-copy-form
                        :selected-book="$book"
                        :selected-library-id="$selectedLibraryId"
                        :drawer-mode="true"
                        :key="'book-detail-copy-form-'.$book->id.'-'.$selectedLibraryId"
                    />
                </div>
            </aside>
        </div>
    @endif
</div>
