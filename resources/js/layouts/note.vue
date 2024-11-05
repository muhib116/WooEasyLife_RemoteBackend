<template>

<div class="bg-white dark:bg-gray-700 shadow-box rounded-md">
    <div
        class="py-3 px-5 font-semibold border-b dark:border-gray-500 mih-h-[60px]"
    >
        Commands
    </div>
    <table
        class="table w-full"
    >
        <thead>
            <tr>
                <th class="">Command</th>
                <th class="">Exit Code</th>
                <th class="">Happened</th>
                <th class="!text-right">Action</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td title="list"><code>list</code></td>
                <td class="table-fit text-center text-muted">
                    0
                </td>
                <td
                    data-timeago="2024-09-28 01:57:33"
                    title="2024-09-28 01:57:33"
                    class="table-fit text-muted"
                >
                    0s ago
                </td>
                <td>
                    <div class="flex justify-end">
                        <Icon name="PhArrowCircleRight" />
                    </div>
                </td>
            </tr>
        </tbody>
    </table>
    <!-- <div class="w-full text-center bg-gray-50 py-2 cursor-pointer select-none">
        Load Older Entries
    </div> -->
    <!-- not found
    <div class="min-h-56 bg-[#f3f4f6] py-10 grid place-content-center">
        <Icon name="PhCookie" size="250" />
    </div> -->
</div>
</template>