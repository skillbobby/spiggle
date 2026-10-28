/* Spiggle API Inspector — progressive enhancements. */
(() => {
    const escapeHtml = (value) =>
        value
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;');

    const JSON_TOKEN = /("(?:\\u[a-fA-F0-9]{4}|\\[^u]|[^\\"])*")(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?/g;

    const highlight = (element) => {
        if (!(element instanceof HTMLElement) || element.dataset.aiHighlighted === 'true') {
            return;
        }

        const text = element.textContent ?? '';

        if (text.length === 0 || text.length > 300_000) {
            return;
        }

        try {
            JSON.parse(text);
        } catch {
            return;
        }

        let output = '';
        let lastIndex = 0;

        text.replace(JSON_TOKEN, (match, stringToken, colon, booleanToken, offset) => {
            output += escapeHtml(text.slice(lastIndex, offset));

            if (stringToken) {
                output += `<span class="ai-json-key">${escapeHtml(stringToken)}</span>`;

                if (colon) {
                    output += `<span class="ai-json-punct">${escapeHtml(colon)}</span>`;
                }
            } else if (booleanToken) {
                const className = booleanToken === 'null' ? 'ai-json-null' : 'ai-json-boolean';
                output += `<span class="${className}">${escapeHtml(booleanToken)}</span>`;
            } else {
                output += `<span class="ai-json-number">${escapeHtml(match)}</span>`;
            }

            lastIndex = offset + match.length;
        });

        output += escapeHtml(text.slice(lastIndex));

        element.innerHTML = output;
        element.dataset.aiHighlighted = 'true';
    };

    const highlightWithin = (root = document) => {
        if (root instanceof HTMLElement && root.matches('[data-ai-json]')) {
            highlight(root);
        }

        root.querySelectorAll?.('[data-ai-json]').forEach(highlight);
    };

    const copy = async (value, button) => {
        try {
            if (navigator.clipboard?.writeText) {
                await navigator.clipboard.writeText(value);
            } else {
                const area = document.createElement('textarea');
                area.value = value;
                area.setAttribute('readonly', '');
                area.style.position = 'fixed';
                area.style.opacity = '0';
                document.body.appendChild(area);
                area.select();
                document.execCommand('copy');
                area.remove();
            }

            if (button instanceof HTMLElement) {
                button.classList.add('ai-copied');

                if (button.hasAttribute('data-ai-copy-target')) {
                    const label = button.querySelector('span:not(.ai-icon)');
                    const original = button.dataset.aiOriginalLabel ?? label?.textContent;

                    if (label && original) {
                        button.dataset.aiOriginalLabel = original;
                        label.textContent = 'Copied';
                    }

                    window.setTimeout(() => {
                        button.classList.remove('ai-copied');

                        if (label && button.dataset.aiOriginalLabel) {
                            label.textContent = button.dataset.aiOriginalLabel;
                        }
                    }, 1600);

                    return;
                }

                window.setTimeout(() => button.classList.remove('ai-copied'), 1600);
            }
        } catch {
            // Clipboard access can be denied; fail silently.
        }
    };

    const tidyZeroBadges = (root = document) => {
        root.querySelectorAll?.('.fi-ta-filters-dropdown .fi-badge').forEach((badge) => {
            if ((badge.textContent ?? '').trim() === '0') {
                badge.remove();
            }
        });
    };

    const bind = () => {
        highlightWithin();
        tidyZeroBadges();

        if (document.documentElement.dataset.aiCopyBound === 'true') {
            return;
        }

        document.documentElement.dataset.aiCopyBound = 'true';

        document.addEventListener('click', (event) => {
            const trigger = event.target.closest?.('[data-ai-copy], [data-ai-copy-target]');

            if (!trigger) {
                return;
            }

            event.preventDefault();

            let value = trigger.dataset.aiCopy ?? '';

            if (!value && trigger.dataset.aiCopyTarget) {
                value = document.getElementById(trigger.dataset.aiCopyTarget)?.textContent ?? '';
            }

            if (value) {
                copy(value, trigger);
            }
        });

        document.addEventListener('keydown', (event) => {
            if (!(event.metaKey || event.ctrlKey) || event.key !== 'Enter') {
                return;
            }

            const console = document.getElementById('replay-console');

            if (!console || !console.contains(document.activeElement)) {
                return;
            }

            event.preventDefault();
            console.querySelector('.ai-run')?.click();
        });
    };

    const scrollToReplay = () => {
        const target = document.getElementById('replay-console');

        if (!target) {
            return;
        }

        const params = new URLSearchParams(window.location.search);

        if (params.get('replay') === '1') {
            window.setTimeout(() => target.scrollIntoView({ behavior: 'smooth', block: 'center' }), 180);
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            bind();
            scrollToReplay();
        });
    } else {
        bind();
        scrollToReplay();
    }

    const registerLivewireHooks = () => {
        if (typeof window.Livewire?.hook !== 'function' || document.documentElement.dataset.aiLivewireHooks === 'true') {
            return;
        }

        document.documentElement.dataset.aiLivewireHooks = 'true';

        const refresh = ({ el } = {}) => {
            const root = el instanceof HTMLElement ? el : document;

            root.querySelectorAll?.('[data-ai-json]').forEach((node) => {
                delete node.dataset.aiHighlighted;
            });

            highlightWithin(root);
            tidyZeroBadges(root);
        };

        try {
            window.Livewire.hook('morph.updated', refresh);
            window.Livewire.hook('morphed', refresh);
        } catch {
            // Livewire versions expose different hook names; navigate events cover us.
        }
    };

    if (typeof window.Livewire?.hook === 'function') {
        registerLivewireHooks();
    } else {
        document.addEventListener('livewire:init', registerLivewireHooks);
    }

    document.addEventListener('livewire:navigated', () => {
        highlightWithin();
        scrollToReplay();
    });
})();
