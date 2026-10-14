<form
    method="POST"
    action="{{ route('ui-modules.toggle-hidden') }}"
    class="border-t border-gray-200 px-4 py-3 dark:border-white/10"
>
    @csrf
    <input type="hidden" name="show_hidden_modules" value="0">
    <label class="flex items-center gap-2 text-xs font-medium text-gray-500 dark:text-gray-400">
        <input
            type="checkbox"
            name="show_hidden_modules"
            value="1"
            onchange="this.form.submit()"
            @checked(\App\Support\UiModules::hiddenModulesVisible())
            class="h-3.5 w-3.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-white/20 dark:bg-white/5"
        >
        Show Damage and adjustments
    </label>
</form>
