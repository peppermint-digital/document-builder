import type { Editor } from 'grapesjs';

import { escapeHtml } from './theme';
import type { PlaceholderDefinition } from './types';

/** Accepts both the rich array form and a plain `{ key: label }` map. */
export function normalizePlaceholders(
    input: PlaceholderDefinition[] | Record<string, string> | undefined,
): PlaceholderDefinition[] {
    if (!input) {
        return [];
    }

    if (Array.isArray(input)) {
        return input.filter((placeholder) => Boolean(placeholder?.key));
    }

    return Object.entries(input).map(([key, label]) => ({ key, label }));
}

export function tokenFor(key: string): string {
    return `{{ ${key} }}`;
}

/**
 * Inserts a placeholder token at the caret when a text block is being edited,
 * and falls back to appending it to the selected text block otherwise. Silently
 * does nothing when neither applies — there is no sensible target then.
 */
export function insertPlaceholder(editor: Editor, key: string): void {
    const token = tokenFor(key);
    const rte = editor.RichTextEditor as unknown as {
        insertHTML?: (content: string, opts?: Record<string, unknown>) => void;
    };

    if (typeof editor.getEditing === 'function' && editor.getEditing() && rte?.insertHTML) {
        rte.insertHTML(token, { select: false });

        return;
    }

    const selected = editor.getSelected();

    if (selected?.is?.('text')) {
        const current = String(selected.get('content') ?? '');
        selected.set('content', current ? `${current} ${token}` : token);
    }
}

/**
 * Adds a placeholder dropdown to the rich-text toolbar. Rendering the action as
 * a `<select>` is GrapesJS's own pattern for multi-choice RTE actions — a
 * button would need a second popover layer we do not want here.
 */
export function registerPlaceholderRteAction(
    editor: Editor,
    placeholders: PlaceholderDefinition[],
    label = 'Platzhalter',
): void {
    if (placeholders.length === 0) {
        return;
    }

    const grouped = new Map<string, PlaceholderDefinition[]>();

    placeholders.forEach((placeholder) => {
        const group = placeholder.group ?? '';
        grouped.set(group, [...(grouped.get(group) ?? []), placeholder]);
    });

    const options = [...grouped.entries()]
        .map(([group, entries]) => {
            const items = entries
                .map(
                    (entry) =>
                        `<option value="${escapeHtml(entry.key)}">${escapeHtml(entry.label)}</option>`,
                )
                .join('');

            return group === '' ? items : `<optgroup label="${escapeHtml(group)}">${items}</optgroup>`;
        })
        .join('');

    editor.RichTextEditor.add('document-placeholders', {
        icon: `<select class="gjs-field db-placeholder-select" style="min-width:9rem">
                 <option value="">${escapeHtml(label)} …</option>
                 ${options}
               </select>`,
        event: 'change',
        attributes: { title: `${label} einfügen` },
        result: (rte: unknown, action: unknown) => {
            const select = (action as { btn?: HTMLElement })?.btn?.firstElementChild as HTMLSelectElement | null;

            if (!select?.value) {
                return;
            }

            (rte as { insertHTML?: (c: string, o?: Record<string, unknown>) => void }).insertHTML?.(
                tokenFor(select.value),
                { select: false },
            );

            select.value = '';
        },
    } as never);
}
