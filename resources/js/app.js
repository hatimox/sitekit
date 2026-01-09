// Lazy load code editor when needed
window.loadCodeEditor = async function() {
    if (window.codeEditor) return;
    await import('./code-editor.js');
};

// Auto-load when codeEditor is referenced
document.addEventListener('alpine:init', () => {
    Alpine.data('codeEditor', (config) => ({
        async init() {
            await window.loadCodeEditor();
            // Re-initialize with the actual codeEditor component
            const editorComponent = window.codeEditor(config);
            Object.assign(this, editorComponent);
            editorComponent.init.call(this);
        }
    }));
});
