<x-layouts::app :title="__('Book Copy QR')">
    <div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">
                Knygos egzempliorius
            </h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                Egzemplioriaus informacija ir QR kodas
            </p>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div
                class="lg:col-span-2 rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <h2 class="mb-6 text-xl font-semibold text-zinc-900 dark:text-white">
                    {{ $copy->book->title ?? 'Knyga' }}
                </h2>

                <dl class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">ID</dt>
                        <dd class="mt-1 text-base text-zinc-900 dark:text-white">{{ $copy->id }}</dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Inventory code</dt>
                        <dd class="mt-1 text-base text-zinc-900 dark:text-white">{{ $copy->inventory_code ?? '—' }}</dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Barcode</dt>
                        <dd class="mt-1 text-base text-zinc-900 dark:text-white">{{ $copy->barcode ?? '—' }}</dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Status</dt>
                        <dd class="mt-1">
                            {{ $copy->status ?? '—' }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Condition</dt>
                        <dd class="mt-1 text-base text-zinc-900 dark:text-white">{{ $copy->condition_status ?? '—' }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Branch</dt>
                        <dd class="mt-1 text-base text-zinc-900 dark:text-white">
                            {{ $copy->branch->name ?? $copy->branch_id ?? '—' }}</dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Location ID</dt>
                        <dd class="mt-1 text-base text-zinc-900 dark:text-white">{{ $copy->location_id ?? '—' }}</dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Acquired at</dt>
                        <dd class="mt-1 text-base text-zinc-900 dark:text-white">{{ $copy->acquired_at ?? '—' }}</dd>
                    </div>

                    <div class="sm:col-span-2">
                        <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Notes</dt>
                        <dd class="mt-1 text-base text-zinc-900 dark:text-white">{{ $copy->notes ?? '—' }}</dd>
                    </div>
                </dl>
            </div>

            <div
                class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <h2 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-white">
                    QR kodas
                </h2>

                <div class="flex flex-col items-center">
                    <div class="rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-700">
                        <img src="{{ route('book-copies.qr', $copy->id) }}" alt="Book copy QR" class="h-56 w-56">
                    </div>

                    <div class="mt-6 flex w-full flex-col gap-3">
                        <a href="{{ route('book-copies.qr', $copy->id) }}" target="_blank"
                            class="inline-flex items-center justify-center rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium transition hover:bg-zinc-700 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
                            Atidaryti QR
                        </a>

                        <!-- <button
                            type="button"
                            onclick="window.print()"
                            class="inline-flex items-center justify-center rounded-xl border border-neutral-300 px-4 py-2.5 text-sm font-medium text-zinc-700 transition hover:bg-neutral-100 dark:border-neutral-700 dark:text-zinc-200 dark:hover:bg-neutral-800"
                        >
                            Spausdinti
                        </button> -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts::app>