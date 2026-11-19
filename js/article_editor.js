document.addEventListener('DOMContentLoaded', () => {
    if (window.Tagify) {
        const subcategoryInput = document.getElementById('subcategory-input');
        const tagInput = document.getElementById('tags-input');

        if (subcategoryInput) {
            new Tagify(subcategoryInput, { dropdown: { enabled: 0 } });
        }

        if (tagInput) {
            new Tagify(tagInput, {
                whitelist: window.clubTags || [],
                dropdown: { enabled: 0, maxItems: 20, closeOnSelect: false },
            });
        }
    }

    if (window.tinymce) {
        tinymce.init({
            selector: '.rich-editor',
            height: 420,
            menubar: false,
            plugins: 'autolink lists link table code preview',
            toolbar: 'undo redo | blocks | bold italic link | bullist numlist | table | preview code',
            branding: false,
        });
    }

    document.querySelectorAll('[data-toggle]').forEach((toggle) => {
        const target = document.getElementById(toggle.dataset.toggle);
        const sync = () => {
            if (target) {
                target.hidden = !toggle.checked;
            }
        };
        toggle.addEventListener('change', sync);
        sync();
    });

    document.querySelectorAll('.media-input').forEach((input) => {
        input.addEventListener('change', () => {
            const preview = document.getElementById(input.dataset.preview);
            const file = input.files && input.files[0];
            if (!preview || !file || !file.type.startsWith('image/')) {
                return;
            }

            const reader = new FileReader();
            reader.onload = (event) => {
                preview.src = event.target.result;
            };
            reader.readAsDataURL(file);
        });
    });

    document.querySelectorAll('form.article-form').forEach((form) => {
        form.addEventListener('submit', () => {
            if (window.tinymce) {
                tinymce.triggerSave();
            }
        });
    });

    const previewButton = document.getElementById('draft-preview');
    const modal = document.getElementById('preview-modal');
    if (previewButton && modal) {
        previewButton.addEventListener('click', () => {
            const title = document.querySelector('input[name="title"]')?.value || 'Untitled';
            const editor = window.tinymce && tinymce.editors.length ? tinymce.editors[0] : null;
            const contentField = document.querySelector('.rich-editor');
            const content = editor ? editor.getContent() : (contentField?.value || '');

            document.getElementById('preview-title').textContent = title;
            document.getElementById('preview-content').innerHTML = content;
            modal.hidden = false;
        });

        modal.querySelector('.editor-preview-modal__close')?.addEventListener('click', () => {
            modal.hidden = true;
        });

        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                modal.hidden = true;
            }
        });
    }
});
