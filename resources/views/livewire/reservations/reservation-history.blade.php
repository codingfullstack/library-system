<div class="space-y-4">
    @if($message)
        <x-ui.alert type="info" class="mb-4">
            {{ $message }}
        </x-ui.alert>
    @endif

    @error('reservation')
        <x-ui.alert type="error" class="mb-4">
            {{ $message }}
        </x-ui.alert>
    @enderror

    <div wire:loading.class="opacity-60" class="transition">
        @if($reservations->count() === 0)
            <x-ui.empty-state
                title="Rezervacijų nėra"
                description="Ši knyga dar nebuvo rezervuota."
            />
        @else
            <div class="space-y-4">
                @foreach($reservations as $reservation)
                    @php
                        $isCurrent = $reservation->id === $currentReservationId;
                        $isPending = $reservation->isPending();
                        $status = $isPending ? 'rezervuota' : $reservation->status;
                        $statusLabel = match (true) {
                            $isCurrent => 'Rezervuota',
                            $isPending => 'Laukia eilėje',
                            $reservation->status === 'įvykdyta' => 'Įvykdyta',
                            $reservation->status === 'atšaukta' => 'Atšaukta',
                            $reservation->status === 'pasibaigusi' => 'Pasibaigusi',
                            default => $reservation->status,
                        };
                    @endphp

                    <div class="rounded-lg border border-zinc-200 p-5 dark:border-zinc-800" wire:key="reservation-history-{{ $reservation->id }}">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h3 class="text-base font-semibold text-zinc-950 dark:text-white">
                                    {{ $reservation->user?->name ?: 'Nežinomas narys' }}
                                </h3>
                                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ $reservation->user?->membership_number ?: ($reservation->user?->email ?: '-') }}
                                </p>
                            </div>

                            <x-ui.status-badge :status="$status" :label="$statusLabel" />
                        </div>

                        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="app-muted-card">
                                <p class="app-label">Rezervuota</p>
                                <p class="mt-1 text-sm font-medium text-zinc-950 dark:text-white">
                                    {{ $reservation->created_at?->format('Y-m-d H:i') ?: '-' }}
                                </p>
                            </div>

                            <div class="app-muted-card">
                                <p class="app-label">{{ $isCurrent ? 'Galioja iki' : 'Būsena eilėje' }}</p>
                                <p class="mt-1 text-sm font-medium text-zinc-950 dark:text-white">
                                    @if ($isCurrent)
                                        {{ $reservation->expires_at?->format('Y-m-d H:i') ?: '-' }}
                                    @elseif ($isPending)
                                        Laukia savo eiles
                                    @else
                                        -
                                    @endif
                                </p>
                            </div>
                        </div>

                        @if ($reservation->notes)
                            <div class="mt-4 rounded-lg bg-zinc-50 p-3 text-sm text-zinc-700 dark:bg-zinc-800/50 dark:text-zinc-300">
                                <span class="font-medium text-zinc-950 dark:text-white">Pastabos:</span>
                                {{ $reservation->notes }}
                            </div>
                        @endif

                        @if ($isCurrent && $canIssueCurrent)
                            <div class="mt-4 flex justify-end gap-2">
                                <button
                                    type="button"
                                    wire:click="issueFirstInQueue"
                                    wire:loading.attr="disabled"
                                    class="app-button-primary"
                                >
                                    Išduoti pirmam eilėje
                                </button>
                            </div>
                        @elseif ($isCurrent && $canManage && $unavailableIssueMessage)
                            <div class="mt-4 rounded-lg bg-amber-50 p-3 text-sm font-medium text-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
                                {{ $unavailableIssueMessage }}
                            </div>
                        @endif

                        @if ($isPending && $canManage)
                            <div class="mt-4 flex justify-end">
                                <livewire:reservations.cancel-reservation-form
                                    :reservation="$reservation"
                                    :key="'reservation-history-cancel-'.$reservation->id"
                                />
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            @if ($reservations->hasPages())
                <div class="pt-2">
                    {{ $reservations->appends(['tab' => 'reservations'])->links() }}
                </div>
            @endif
        @endif
    </div>
</div>







