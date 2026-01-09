<div wire:poll.500ms="pollJobStatus">
    <x-filament::section collapsible>
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-heroicon-o-folder class="h-5 w-5" />
                <span>File Manager</span>
            </div>
        </x-slot>
        <x-slot name="description">
            Browse, edit, and manage files in your web application.
        </x-slot>

        <div class="space-y-4">
            {{-- Breadcrumb Navigation --}}
            <div class="flex items-center gap-2 text-sm overflow-x-auto pb-2">
                @foreach($breadcrumbs as $index => $crumb)
                    @if($index > 0)
                        <x-heroicon-o-chevron-right class="h-4 w-4 text-gray-400 flex-shrink-0" />
                    @endif
                    @if($index === count($breadcrumbs) - 1)
                        <span class="font-medium text-gray-900 dark:text-white flex-shrink-0">{{ $crumb['name'] }}</span>
                    @else
                        <button
                            wire:click="navigateToPath('{{ $crumb['path'] }}')"
                            class="text-primary-600 hover:text-primary-700 hover:underline flex-shrink-0"
                        >
                            {{ $crumb['name'] }}
                        </button>
                    @endif
                @endforeach
            </div>

            {{-- Toolbar --}}
            <div class="flex flex-wrap items-center gap-2">
                <x-filament::button
                    wire:click="navigateUp"
                    size="sm"
                    color="gray"
                    icon="heroicon-o-arrow-up"
                    :disabled="count($breadcrumbs) <= 1"
                >
                    Up
                </x-filament::button>

                <x-filament::button
                    wire:click="refresh"
                    size="sm"
                    color="gray"
                    icon="heroicon-o-arrow-path"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove wire:target="refresh, loadDirectory">Refresh</span>
                    <span wire:loading wire:target="refresh, loadDirectory">Loading...</span>
                </x-filament::button>

                <div class="flex-1"></div>

                <x-filament::button
                    x-on:click="$dispatch('open-modal', { id: 'new-file-modal' })"
                    size="sm"
                    color="gray"
                    icon="heroicon-o-document-plus"
                >
                    New File
                </x-filament::button>

                <x-filament::button
                    x-on:click="$dispatch('open-modal', { id: 'new-folder-modal' })"
                    size="sm"
                    color="gray"
                    icon="heroicon-o-folder-plus"
                >
                    New Folder
                </x-filament::button>

                @if(count($selectedFiles) > 0)
                    <x-filament::button
                        wire:click="confirmDelete({{ Js::from($selectedFiles) }})"
                        size="sm"
                        color="danger"
                        icon="heroicon-o-trash"
                    >
                        Delete ({{ count($selectedFiles) }})
                    </x-filament::button>

                    <x-filament::button
                        wire:click="clearSelection"
                        size="sm"
                        color="gray"
                    >
                        Clear
                    </x-filament::button>
                @endif
            </div>

            {{-- Error Message --}}
            @if($errorMessage)
                <div class="rounded-lg bg-danger-50 dark:bg-danger-900/20 p-4 text-danger-700 dark:text-danger-400">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-exclamation-circle class="h-5 w-5" />
                        <span>{{ $errorMessage }}</span>
                    </div>
                </div>
            @endif

            {{-- File List --}}
            <div class="border rounded-lg dark:border-gray-700 overflow-hidden">
                @if($isLoading)
                    <div class="p-8 text-center">
                        <x-filament::loading-indicator class="h-8 w-8 mx-auto" />
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Loading files...</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-800 text-left">
                                <tr>
                                    <th class="px-4 py-3 w-8">
                                        <span class="sr-only">Select</span>
                                    </th>
                                    <th class="px-4 py-3">Name</th>
                                    <th class="px-4 py-3 text-right w-24">Size</th>
                                    <th class="px-4 py-3 text-right w-40">Modified</th>
                                    <th class="px-4 py-3 text-right w-24">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse($files as $file)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors {{ in_array($file['name'], $selectedFiles) ? 'bg-primary-50 dark:bg-primary-900/20' : '' }}">
                                        <td class="px-4 py-2">
                                            <input
                                                type="checkbox"
                                                wire:click="toggleFileSelection({{ Js::from($file['name']) }})"
                                                @checked(in_array($file['name'], $selectedFiles))
                                                class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                            />
                                        </td>
                                        <td class="px-4 py-2">
                                            @if($file['type'] === 'directory')
                                                <button
                                                    wire:click="navigateTo({{ Js::from($file['name']) }})"
                                                    class="flex items-center gap-2 text-primary-600 hover:text-primary-700 hover:underline"
                                                >
                                                    <x-dynamic-component :component="$this->getFileIcon($file)" class="h-5 w-5 text-yellow-500" />
                                                    <span>{{ $file['name'] }}</span>
                                                </button>
                                            @else
                                                <div class="flex items-center gap-2">
                                                    <x-dynamic-component :component="$this->getFileIcon($file)" class="h-5 w-5 text-gray-400" />
                                                    <span class="text-gray-900 dark:text-white">{{ $file['name'] }}</span>
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-right text-gray-500 dark:text-gray-400 tabular-nums">
                                            {{ $this->formatFileSize($file['size'] ?? null) }}
                                        </td>
                                        <td class="px-4 py-2 text-right text-gray-500 dark:text-gray-400">
                                            @if(isset($file['modified_at']))
                                                {{ \Carbon\Carbon::parse($file['modified_at'])->diffForHumans() }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-right">
                                            <div class="flex items-center justify-end gap-1">
                                                @if($file['type'] === 'file')
                                                    <button
                                                        wire:click="openFile({{ Js::from($file['name']) }}, {{ $file['size'] ?? 0 }})"
                                                        wire:loading.attr="disabled"
                                                        wire:target="openFile"
                                                        class="p-1.5 text-gray-500 hover:text-primary-600 hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition-colors disabled:opacity-50"
                                                        title="Edit"
                                                    >
                                                        <x-heroicon-o-pencil-square class="h-4 w-4" wire:loading.remove wire:target="openFile" />
                                                        <x-filament::loading-indicator class="h-4 w-4" wire:loading wire:target="openFile" />
                                                    </button>
                                                @endif
                                                <button
                                                    wire:click="confirmDelete([{{ Js::from($file['name']) }}])"
                                                    class="p-1.5 text-gray-500 hover:text-danger-600 hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition-colors"
                                                    title="Delete"
                                                >
                                                    <x-heroicon-o-trash class="h-4 w-4" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">
                                            <x-heroicon-o-folder-open class="h-12 w-12 mx-auto mb-4 opacity-50" />
                                            <p>This directory is empty.</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- File count --}}
            @if(!$isLoading && count($files) > 0)
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ count($files) }} {{ Str::plural('item', count($files)) }}
                </p>
            @endif
        </div>
    </x-filament::section>

    {{-- File Editor Modal --}}
    <x-filament::modal
        id="file-editor-modal"
        :close-by-clicking-away="false"
        width="5xl"
    >
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-heroicon-o-document-text class="h-5 w-5" />
                <span>{{ $editingFileName ?? 'Edit File' }}</span>
                @if($this->hasUnsavedChanges())
                    <span class="text-xs bg-warning-100 text-warning-700 dark:bg-warning-900/50 dark:text-warning-400 px-2 py-0.5 rounded-full">
                        Unsaved
                    </span>
                @endif
            </div>
        </x-slot>

        <x-slot name="description">
            {{ $editingFile }} ({{ strlen($fileContent) }} bytes)
        </x-slot>

        @if($isEditorLoading)
            <div class="h-[500px] flex items-center justify-center bg-gray-900 rounded-lg border border-gray-700">
                <div class="text-center">
                    <x-filament::loading-indicator class="h-8 w-8 mx-auto" />
                    <p class="mt-2 text-sm text-gray-500">Loading file content...</p>
                </div>
            </div>
        @else
            <div
                x-data
                x-init="
                    (async () => {
                        await window.loadCodeEditor();
                        const editor = window.codeEditor({
                            content: @js($fileContent),
                            filename: @js($editingFileName ?? '')
                        });
                        Object.assign($data, editor);
                        editor.init.call($data);
                    })()
                "
                x-on:editor-change.debounce.300ms="$wire.set('fileContent', $event.detail.content)"
                x-on:editor-save="$wire.saveFile()"
                class="h-[500px] rounded-lg overflow-hidden border border-gray-700"
                wire:ignore
            >
                <div x-ref="editor" class="h-full"></div>
            </div>
        @endif
        <p class="text-xs text-gray-500 mt-2">
            Press <kbd class="px-1.5 py-0.5 bg-gray-700 rounded text-gray-300">Ctrl+S</kbd> / <kbd class="px-1.5 py-0.5 bg-gray-700 rounded text-gray-300">Cmd+S</kbd> to save
        </p>

        <x-slot name="footerActions">
            <x-filament::button
                x-on:click="$dispatch('close-modal', { id: 'file-editor-modal' })"
                color="gray"
            >
                Cancel
            </x-filament::button>

            <x-filament::button
                wire:click="saveFile"
                :disabled="$isSaving || $isEditorLoading"
                icon="heroicon-o-check"
            >
                <span wire:loading.remove wire:target="saveFile">Save Changes</span>
                <span wire:loading wire:target="saveFile">Saving...</span>
            </x-filament::button>
        </x-slot>
    </x-filament::modal>

    {{-- New Folder Modal --}}
    <x-filament::modal
        id="new-folder-modal"
        width="md"
    >
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-heroicon-o-folder-plus class="h-5 w-5" />
                <span>Create New Folder</span>
            </div>
        </x-slot>

        <div class="space-y-4">
            <div>
                <label for="newFolderName" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Folder Name
                </label>
                <input
                    type="text"
                    id="newFolderName"
                    wire:model="newFolderName"
                    wire:keydown.enter="createFolder"
                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-primary-500 focus:ring-primary-500"
                    placeholder="my-folder"
                    autofocus
                />
            </div>
        </div>

        <x-slot name="footerActions">
            <x-filament::button
                x-on:click="$dispatch('close-modal', { id: 'new-folder-modal' })"
                color="gray"
            >
                Cancel
            </x-filament::button>

            <x-filament::button
                x-on:click="$wire.createFolder().then(() => $dispatch('close-modal', { id: 'new-folder-modal' }))"
                icon="heroicon-o-folder-plus"
            >
                Create Folder
            </x-filament::button>
        </x-slot>
    </x-filament::modal>

    {{-- New File Modal --}}
    <x-filament::modal
        id="new-file-modal"
        width="md"
    >
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-heroicon-o-document-plus class="h-5 w-5" />
                <span>Create New File</span>
            </div>
        </x-slot>

        <div class="space-y-4">
            <div>
                <label for="newFileName" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    File Name
                </label>
                <input
                    type="text"
                    id="newFileName"
                    wire:model="newFileName"
                    wire:keydown.enter="createFile"
                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-primary-500 focus:ring-primary-500"
                    placeholder="example.php"
                    autofocus
                />
            </div>
        </div>

        <x-slot name="footerActions">
            <x-filament::button
                x-on:click="$dispatch('close-modal', { id: 'new-file-modal' })"
                color="gray"
            >
                Cancel
            </x-filament::button>

            <x-filament::button
                x-on:click="$wire.createFile().then(() => $dispatch('close-modal', { id: 'new-file-modal' }))"
                icon="heroicon-o-document-plus"
            >
                Create File
            </x-filament::button>
        </x-slot>
    </x-filament::modal>

    {{-- Delete Confirmation Modal --}}
    <x-filament::modal
        id="delete-confirm-modal"
        width="md"
    >
        <x-slot name="heading">
            <div class="flex items-center gap-2 text-danger-600">
                <x-heroicon-o-exclamation-triangle class="h-5 w-5" />
                <span>Confirm Deletion</span>
            </div>
        </x-slot>

        <div class="space-y-4">
            <p class="text-gray-600 dark:text-gray-400">
                Are you sure you want to delete the following {{ count($filesToDelete) }} {{ Str::plural('item', count($filesToDelete)) }}?
                This action cannot be undone.
            </p>

            <ul class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 space-y-1 max-h-48 overflow-y-auto">
                @foreach($filesToDelete as $file)
                    <li class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <x-heroicon-o-document class="h-4 w-4 text-gray-400" />
                        {{ $file }}
                    </li>
                @endforeach
            </ul>
        </div>

        <x-slot name="footerActions">
            <x-filament::button
                x-on:click="$dispatch('close-modal', { id: 'delete-confirm-modal' }); $wire.closeDeleteModal()"
                color="gray"
            >
                Cancel
            </x-filament::button>

            <x-filament::button
                x-on:click="$wire.deleteFiles().then(() => $dispatch('close-modal', { id: 'delete-confirm-modal' }))"
                color="danger"
                icon="heroicon-o-trash"
            >
                Delete
            </x-filament::button>
        </x-slot>
    </x-filament::modal>
</div>
