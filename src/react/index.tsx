import { forwardRef, useEffect, useImperativeHandle, useRef } from 'react';

import { createDocumentBuilder } from '../core/editor';
import type {
    DocumentBuilderInstance,
    DocumentDesign,
    LineItemColumn,
    PageSetup,
    PlaceholderDefinition,
    SkeletonPreview,
    ZoneName,
} from '../core/types';

export interface DocumentBuilderProps {
    /**
     * The stored design. Treated as the *initial* value — the editor is far too
     * expensive to re-initialise on every keystroke, so it is not a fully
     * controlled input. Changing this to a design the editor did not itself
     * produce reloads it.
     */
    value?: DocumentDesign | null;
    onChange?: (design: DocumentDesign) => void;
    page?: Partial<PageSetup>;
    availableColumns?: LineItemColumn[];
    placeholders?: PlaceholderDefinition[] | Record<string, string>;
    /** Context drawn behind the free zone: address window, subject, footer. */
    skeletonPreview?: SkeletonPreview;
    /** Vorbelegung leerer Zonen beim ersten Öffnen — vor allem die Fußzeile. */
    defaults?: Partial<Record<ZoneName, string>>;
    onUploadImage?: (file: File) => Promise<string>;
    onReady?: (instance: DocumentBuilderInstance) => void;
    theme?: 'light' | 'dark';
    locale?: 'de' | 'en';
    height?: string | number;
    className?: string;
}

export interface DocumentBuilderHandle {
    getHtml(): string;
    getDesign(): DocumentDesign | null;
    getColumns(): LineItemColumn[];
    loadDesign(design: DocumentDesign | null): void;
    insertPlaceholder(key: string): void;
    isEmpty(): boolean;
    getInstance(): DocumentBuilderInstance | null;
}

export const DocumentBuilder = forwardRef<DocumentBuilderHandle, DocumentBuilderProps>(
    function DocumentBuilder(
        {
            value,
            onChange,
            page,
            availableColumns,
            placeholders,
            skeletonPreview,
            defaults,
            onUploadImage,
            onReady,
            theme = 'light',
            locale = 'de',
            height = '75vh',
            className,
        },
        ref,
    ) {
        const containerRef = useRef<HTMLDivElement | null>(null);
        const instanceRef = useRef<DocumentBuilderInstance | null>(null);
        /** Zuletzt vom Editor erzeugter Rumpf — sichert den Abgleich unten ab. */
        const lastEmittedRef = useRef<string | undefined>(value?.body);

        // Callbacks live in refs so a re-render never tears down the editor.
        const onChangeRef = useRef(onChange);
        const onReadyRef = useRef(onReady);
        const onUploadImageRef = useRef(onUploadImage);
        onChangeRef.current = onChange;
        onReadyRef.current = onReady;
        onUploadImageRef.current = onUploadImage;

        useEffect(() => {
            if (!containerRef.current) {
                return;
            }

            const instance = createDocumentBuilder({
                container: containerRef.current,
                design: value ?? null,
                page,
                availableColumns,
                placeholders,
                skeletonPreview,
                defaults,
                theme,
                locale,
                onUploadImage: (file) => {
                    const handler = onUploadImageRef.current;

                    return handler
                        ? handler(file)
                        : Promise.reject(new Error('Kein Upload-Handler konfiguriert.'));
                },
                onChange: (design) => {
                    lastEmittedRef.current = design.body;
                    onChangeRef.current?.(design);
                },
                onReady: (ready) => onReadyRef.current?.(ready),
            });

            instanceRef.current = instance;

            return () => {
                instance.destroy();
                instanceRef.current = null;
            };
            // Mount once. Theme, locale and column changes need a remount, which
            // the host triggers with a `key` — cheaper than diffing GrapesJS.
            // eslint-disable-next-line react-hooks/exhaustive-deps
        }, []);

        // Reload only when the design changed outside of this editor.
        useEffect(() => {
            if (value === undefined || value?.body === lastEmittedRef.current) {
                return;
            }

            lastEmittedRef.current = value?.body;
            instanceRef.current?.loadDesign(value);
        }, [value]);

        useImperativeHandle(
            ref,
            () => ({
                getHtml: () => instanceRef.current?.getHtml() ?? '',
                getDesign: () => instanceRef.current?.getDesign() ?? null,
                getColumns: () => instanceRef.current?.getColumns() ?? [],
                loadDesign: (design: DocumentDesign | null) => instanceRef.current?.loadDesign(design),
                insertPlaceholder: (key: string) => instanceRef.current?.insertPlaceholder(key),
                isEmpty: () => instanceRef.current?.isEmpty() ?? true,
                getInstance: () => instanceRef.current,
            }),
            [],
        );

        // Two elements on purpose: GrapesJS overwrites the inline style of the
        // element it mounts into with `height: 100%`. Sizing that same element
        // would collapse it to zero, so the height lives on an outer wrapper the
        // editor never touches.
        return (
            <div
                className={className}
                style={{ height: typeof height === 'number' ? `${height}px` : height }}
            >
                <div ref={containerRef} style={{ height: '100%' }} />
            </div>
        );
    },
);

export type {
    DocumentBuilderInstance,
    DocumentDesign,
    LineItemColumn,
    PageSetup,
    PlaceholderDefinition,
    SkeletonPreview,
    ZoneName,
} from '../core/types';
export { DEFAULT_COLUMNS, DIN_5008 } from '../core/defaults';
export { tokenFor } from '../core/variables';
