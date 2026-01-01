<x-filament::section>
    <x-slot name="heading">
        Backup Storage
    </x-slot>
    <x-slot name="description">
        Configure where your database backups are stored. Cloud storage keeps backups safe even if your server fails.
    </x-slot>

    <form wire:submit="updateBackupStorageSettings">
        <div class="space-y-6">
            {{-- Storage Driver Selection --}}
            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Storage Driver</label>
                <div class="mt-2 space-y-2">
                    <label class="flex items-center">
                        <input type="radio" wire:model.live="backup_storage_driver" value="local" class="rounded-full border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700">
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Local Server (Default)</span>
                    </label>
                    <label class="flex items-center">
                        <input type="radio" wire:model.live="backup_storage_driver" value="s3" class="rounded-full border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700">
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Amazon S3</span>
                    </label>
                    <label class="flex items-center">
                        <input type="radio" wire:model.live="backup_storage_driver" value="r2" class="rounded-full border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700">
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Cloudflare R2</span>
                    </label>
                </div>
                @error('backup_storage_driver')
                    <p class="mt-1 text-sm text-danger-600">{{ $message }}</p>
                @enderror
                @if (session('storage_test_success'))
                    <p class="mt-1 text-sm text-success-600">{{ session('storage_test_success') }}</p>
                @endif
            </div>

            {{-- Cloud Storage Settings --}}
            @if ($backup_storage_driver !== 'local')
                <div class="space-y-4 border-t pt-6 dark:border-gray-700">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-cloud class="h-5 w-5 text-gray-400" />
                        <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100">
                            {{ $backup_storage_driver === 's3' ? 'Amazon S3' : 'Cloudflare R2' }} Configuration
                        </h4>
                    </div>

                    @if ($backup_storage_driver === 'r2')
                        {{-- R2 Endpoint --}}
                        <div>
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">R2 Endpoint URL</label>
                            <x-filament::input.wrapper class="mt-1">
                                <x-filament::input
                                    type="url"
                                    wire:model="backup_s3_endpoint"
                                    placeholder="https://your-account-id.r2.cloudflarestorage.com"
                                />
                            </x-filament::input.wrapper>
                            @error('backup_s3_endpoint')
                                <p class="mt-1 text-sm text-danger-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Find this in your Cloudflare R2 bucket settings
                            </p>
                        </div>
                    @else
                        {{-- Optional S3 Endpoint for custom providers --}}
                        <div>
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Custom Endpoint (Optional)</label>
                            <x-filament::input.wrapper class="mt-1">
                                <x-filament::input
                                    type="url"
                                    wire:model="backup_s3_endpoint"
                                    placeholder="Leave empty for Amazon S3"
                                />
                            </x-filament::input.wrapper>
                            @error('backup_s3_endpoint')
                                <p class="mt-1 text-sm text-danger-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Only needed for S3-compatible providers (MinIO, DigitalOcean Spaces, etc.)
                            </p>
                        </div>
                    @endif

                    {{-- Region --}}
                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Region</label>
                        <x-filament::input.wrapper class="mt-1">
                            <x-filament::input
                                type="text"
                                wire:model="backup_s3_region"
                                placeholder="{{ $backup_storage_driver === 'r2' ? 'auto' : 'us-east-1' }}"
                            />
                        </x-filament::input.wrapper>
                        @error('backup_s3_region')
                            <p class="mt-1 text-sm text-danger-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Bucket --}}
                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Bucket Name</label>
                        <x-filament::input.wrapper class="mt-1">
                            <x-filament::input
                                type="text"
                                wire:model="backup_s3_bucket"
                                placeholder="my-backup-bucket"
                            />
                        </x-filament::input.wrapper>
                        @error('backup_s3_bucket')
                            <p class="mt-1 text-sm text-danger-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Access Key --}}
                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            Access Key ID
                            @if ($team->backup_s3_key)
                                <span class="text-xs text-success-600">(configured)</span>
                            @endif
                        </label>
                        <x-filament::input.wrapper class="mt-1">
                            <x-filament::input
                                type="password"
                                wire:model="backup_s3_key"
                                placeholder="{{ $team->backup_s3_key ? 'Leave empty to keep current' : 'Enter access key' }}"
                            />
                        </x-filament::input.wrapper>
                        @error('backup_s3_key')
                            <p class="mt-1 text-sm text-danger-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Secret Key --}}
                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            Secret Access Key
                            @if ($team->backup_s3_secret)
                                <span class="text-xs text-success-600">(configured)</span>
                            @endif
                        </label>
                        <x-filament::input.wrapper class="mt-1">
                            <x-filament::input
                                type="password"
                                wire:model="backup_s3_secret"
                                placeholder="{{ $team->backup_s3_secret ? 'Leave empty to keep current' : 'Enter secret key' }}"
                            />
                        </x-filament::input.wrapper>
                        @error('backup_s3_secret')
                            <p class="mt-1 text-sm text-danger-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Current Status --}}
                    @if ($team->hasCloudBackupStorage())
                        <div class="rounded-lg bg-success-50 dark:bg-success-900/20 p-4">
                            <div class="flex items-center gap-2">
                                <x-heroicon-o-check-circle class="h-5 w-5 text-success-500" />
                                <span class="text-sm font-medium text-success-700 dark:text-success-400">
                                    Cloud storage is configured
                                </span>
                            </div>
                            <p class="mt-1 text-sm text-success-600 dark:text-success-500">
                                New backups will automatically be uploaded to {{ $team->backup_storage_driver === 's3' ? 'Amazon S3' : 'Cloudflare R2' }}.
                            </p>
                        </div>
                    @endif
                </div>
            @else
                <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Backups are stored on your server in <code class="text-xs bg-gray-200 dark:bg-gray-700 px-1 rounded">/home/*/backups/</code>.
                        For additional safety, consider using cloud storage.
                    </p>
                </div>
            @endif

            {{-- Actions --}}
            <div class="flex items-center gap-4 border-t pt-6 dark:border-gray-700">
                <x-filament::button type="submit">
                    Save Storage Settings
                </x-filament::button>

                @if ($backup_storage_driver !== 'local' && $team->hasCloudBackupStorage())
                    <x-filament::button
                        type="button"
                        wire:click="testConnection"
                        color="gray"
                    >
                        Test Connection
                    </x-filament::button>
                @endif

                <x-action-message class="text-sm text-success-600" on="saved">
                    Saved.
                </x-action-message>
            </div>
        </div>
    </form>
</x-filament::section>
