import { EditorView, basicSetup } from 'codemirror';
import { EditorState } from '@codemirror/state';
import { oneDark } from '@codemirror/theme-one-dark';
import { javascript } from '@codemirror/lang-javascript';
import { php } from '@codemirror/lang-php';
import { html } from '@codemirror/lang-html';
import { css } from '@codemirror/lang-css';
import { json } from '@codemirror/lang-json';
import { markdown } from '@codemirror/lang-markdown';
import { xml } from '@codemirror/lang-xml';
import { sql } from '@codemirror/lang-sql';
import { keymap } from '@codemirror/view';

// Language detection based on file extension
function getLanguageExtension(filename) {
    if (!filename) return [];

    const ext = filename.split('.').pop()?.toLowerCase();

    switch (ext) {
        case 'js':
        case 'jsx':
        case 'mjs':
        case 'cjs':
            return [javascript()];
        case 'ts':
        case 'tsx':
            return [javascript({ typescript: true })];
        case 'php':
        case 'phtml':
            return [php()];
        case 'html':
        case 'htm':
        case 'blade.php':
            return [html()];
        case 'css':
        case 'scss':
        case 'less':
            return [css()];
        case 'json':
            return [json()];
        case 'md':
        case 'markdown':
            return [markdown()];
        case 'xml':
        case 'svg':
            return [xml()];
        case 'sql':
            return [sql()];
        case 'vue':
        case 'svelte':
            return [html()];
        default:
            return [];
    }
}

// Check if filename is a blade file
function isBladeFile(filename) {
    return filename?.endsWith('.blade.php');
}

// Alpine.js component for CodeMirror
window.codeEditor = function(config = {}) {
    return {
        editor: null,
        content: config.content || '',
        filename: config.filename || '',
        readonly: config.readonly || false,

        init() {
            this.createEditor();

            // Watch for content changes from Livewire
            this.$watch('content', (value) => {
                if (this.editor && value !== this.getContent()) {
                    this.setContent(value);
                }
            });

            // Watch for filename changes to update language
            this.$watch('filename', () => {
                this.recreateEditor();
            });
        },

        createEditor() {
            const container = this.$refs.editor;
            if (!container) return;

            // Clear any existing editor
            container.innerHTML = '';

            // Determine language extension
            let langExtension = [];
            if (isBladeFile(this.filename)) {
                langExtension = [html()];
            } else {
                langExtension = getLanguageExtension(this.filename);
            }

            // Create custom keybindings
            const customKeymap = keymap.of([
                {
                    key: 'Mod-s',
                    run: () => {
                        // Dispatch save event
                        this.$dispatch('editor-save', { content: this.getContent() });
                        return true;
                    }
                }
            ]);

            // Create editor state
            const state = EditorState.create({
                doc: this.content,
                extensions: [
                    basicSetup,
                    oneDark,
                    ...langExtension,
                    customKeymap,
                    EditorView.updateListener.of((update) => {
                        if (update.docChanged) {
                            this.$dispatch('editor-change', { content: this.getContent() });
                        }
                    }),
                    EditorView.theme({
                        '&': {
                            height: '100%',
                            fontSize: '14px',
                        },
                        '.cm-scroller': {
                            overflow: 'auto',
                            fontFamily: 'ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, "Liberation Mono", monospace',
                        },
                        '.cm-content': {
                            padding: '10px 0',
                        },
                        '.cm-gutters': {
                            backgroundColor: '#21252b',
                            borderRight: '1px solid #181a1f',
                        },
                        '.cm-activeLineGutter': {
                            backgroundColor: '#2c313a',
                        },
                    }),
                    EditorState.readOnly.of(this.readonly),
                ],
            });

            // Create editor view
            this.editor = new EditorView({
                state,
                parent: container,
            });
        },

        recreateEditor() {
            if (this.editor) {
                const content = this.getContent();
                this.editor.destroy();
                this.content = content;
                this.createEditor();
            }
        },

        getContent() {
            return this.editor?.state.doc.toString() || '';
        },

        setContent(value) {
            if (!this.editor) return;

            this.editor.dispatch({
                changes: {
                    from: 0,
                    to: this.editor.state.doc.length,
                    insert: value,
                },
            });
        },

        focus() {
            this.editor?.focus();
        },

        destroy() {
            this.editor?.destroy();
            this.editor = null;
        }
    };
};
