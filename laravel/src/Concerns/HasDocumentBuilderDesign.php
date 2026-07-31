<?php

namespace Peppermint\DocumentBuilder\Concerns;

use Peppermint\DocumentBuilder\Support\DocumentBuilderSchema;

/**
 * Adds document-builder behaviour to a template model.
 *
 * Expects three columns (see {@see DocumentBuilderSchema::addColumns()}):
 * - `builder_design`  the editable design as JSON
 * - `builder_preset`  which skeleton it was designed against
 * - `editor_mode`     `legacy` or `builder`
 *
 * The compiled HTML is *not* owned by this trait — it lives in whatever body
 * column the host model already uses, so every existing print path keeps
 * working untouched. Override {@see self::documentBuilderBodyColumn()} if yours
 * is not called `template_html`.
 */
trait HasDocumentBuilderDesign
{
    public function documentBuilderBodyColumn(): string
    {
        return 'template_html';
    }

    /** True when this template is edited with the drag & drop builder. */
    public function usesDocumentBuilder(): bool
    {
        return $this->getAttribute('editor_mode') === DocumentBuilderSchema::MODE_BUILDER;
    }

    /**
     * Stores a design coming from the editor: the design itself plus the HTML
     * it compiled to. Both are written together — a design without its compiled
     * body would be unprintable, and a body without its design uneditable.
     *
     * @param  array<string, mixed>|string  $design
     */
    public function storeDocumentBuilderDesign(array|string $design, string $html, string $preset = 'din5008'): void
    {
        $this->setAttribute(
            'builder_design',
            is_string($design) ? $design : json_encode($design, JSON_THROW_ON_ERROR),
        );
        $this->setAttribute('builder_preset', $preset);
        $this->setAttribute($this->documentBuilderBodyColumn(), $html);
        $this->setAttribute('editor_mode', DocumentBuilderSchema::MODE_BUILDER);
    }

    /**
     * The stored design, ready to hand back to the editor.
     *
     * @return array<string, mixed>|null
     */
    public function documentBuilderDesign(): ?array
    {
        $design = $this->getAttribute('builder_design');

        if (! is_string($design) || $design === '') {
            return null;
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($design, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Switches the template back to the host's legacy editor without losing the
     * design — a user who tried the builder and changed their mind should not
     * have to rebuild from scratch if they switch back again.
     */
    public function useLegacyEditor(): void
    {
        $this->setAttribute('editor_mode', DocumentBuilderSchema::MODE_LEGACY);
    }
}
