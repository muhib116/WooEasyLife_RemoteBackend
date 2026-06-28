<template>
    <details class="rounded-xl border border-gray-100 bg-slate-50 text-sm dark:border-gray-700 dark:bg-slate-900/40">
        <summary
            class="cursor-pointer px-4 py-3 font-medium text-gray-700 dark:text-gray-200"
        >
            What do website status badges mean?
        </summary>
        <ul class="space-y-2 border-t border-gray-100 px-4 py-3 text-xs text-gray-600 dark:border-gray-700 dark:text-gray-300">
            <li>
                <strong>Connected</strong> — Plan and license are active; the plugin has called the API.
            </li>
            <li>
                <strong>Configured</strong> — Plan and license are ready; waiting for the first plugin connection.
            </li>
            <li>
                <strong>Setup needed</strong> — Missing a plan, license, or domain alignment.
            </li>
            <li>
                <strong>Inactive</strong> — Expired, disabled, or token/order quota exhausted.
            </li>
        </ul>
    </details>
</template>
