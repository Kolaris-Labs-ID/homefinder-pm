<div class="flex items-center gap-1">
    <select
        wire:model.live="statusFilter"
        class="fi-select-input block w-full border-none bg-transparent py-1.5 pe-8 text-sm text-gray-950 transition duration-75 focus:ring-0 dark:text-white [&_optgroup]:bg-white [&_optgroup]:dark:bg-gray-900 [&_option]:bg-white [&_option]:dark:bg-gray-900"
        style="min-width: 100px;"
    >
        <option value="all">Status</option>
        <option value="pending">Pending</option>
        <option value="in_progress">In Progress</option>
        <option value="done">Done</option>
    </select>
</div>
