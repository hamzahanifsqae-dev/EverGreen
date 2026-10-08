<x-filament-panels::page>
    <div class="space-y-6 stats-dashboard">
        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 stats-card dark:bg-slate-900 dark:text-slate-100 dark:ring-slate-700/40">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-lg font-semibold text-slate-900 dark:text-slate-100">Dedicated alert recipients</p>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Ops emails only — not notification templates, not customer contacts.
                    </p>
                </div>
                @if ($this->lastSentLabel())
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-200">
                        Last sent {{ $this->lastSentLabel() }}
                    </span>
                @else
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-200">
                        Never sent
                    </span>
                @endif
            </div>
        </div>

        <form wire:submit="save">
            {{ $this->form }}
        </form>
    </div>
</x-filament-panels::page>
