<div
    x-data="{
        dismissed: localStorage.getItem('sitekit_ftp_tip_dismissed') === 'true',
        dismiss() {
            this.dismissed = true;
            localStorage.setItem('sitekit_ftp_tip_dismissed', 'true');
        }
    }"
    x-show="!dismissed"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="mb-4 rounded-lg bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-800 p-4"
>
    <div class="flex items-start justify-between gap-4">
        <div class="flex items-start gap-3">
            <div class="flex-shrink-0">
                <x-heroicon-o-light-bulb class="w-5 h-5 text-primary-500" />
            </div>
            <div class="text-sm text-primary-800 dark:text-primary-200">
                <span class="font-medium">Tip:</span>
                Uploaded files via FTP/SFTP? Click the <span class="font-semibold">"Clear Cache"</span> button to see your changes immediately.
            </div>
        </div>
        <button
            type="button"
            x-on:click="dismiss()"
            class="flex-shrink-0 text-primary-500 hover:text-primary-700 dark:hover:text-primary-300 transition"
        >
            <x-heroicon-o-x-mark class="w-5 h-5" />
            <span class="sr-only">Dismiss</span>
        </button>
    </div>
</div>
