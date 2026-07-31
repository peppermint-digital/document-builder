<?php

namespace Peppermint\DocumentBuilder\Support;

use Illuminate\Database\Schema\Blueprint;

/**
 * Blueprint helpers for the columns the document builder needs.
 *
 * Deliberately *not* shipped as a package migration: the host application owns
 * its template table, and a package that silently alters a production table is
 * a footgun. Call these from your own migration instead.
 *
 * Both helpers are additive and database-agnostic (SQLite, MySQL, PostgreSQL).
 */
class DocumentBuilderSchema
{
    /** The legacy editor a host already had — raw HTML, a rich text field, whatever. */
    public const MODE_LEGACY = 'legacy';

    /** The drag & drop builder. */
    public const MODE_BUILDER = 'builder';

    public const MODES = [self::MODE_LEGACY, self::MODE_BUILDER];

    /**
     * Adds the builder columns to a template table.
     *
     * `editor_mode` defaults to `legacy`, so every pre-existing row keeps its
     * current editor and nothing needs backfilling. That is what turns the
     * rollout into a per-template decision instead of a migration everybody has
     * to survive at once.
     *
     * Pass `null` for `$after` when creating a *new* table: MySQL only accepts
     * `AFTER` inside `ALTER TABLE`, while Laravel's grammar appends the
     * modifier unconditionally. SQLite ignores it entirely, so a suite running
     * on SQLite stays green and the deploy against MySQL is where it breaks.
     */
    public static function addColumns(Blueprint $table, ?string $after = 'template_html'): void
    {
        $design = $table->longText('builder_design')->nullable();
        $preset = $table->string('builder_preset', 32)->nullable();
        $mode = $table->string('editor_mode', 16)->default(self::MODE_LEGACY);

        if ($after !== null) {
            $design->after($after);
            $preset->after('builder_design');
            $mode->after('builder_preset');
        }
    }

    /**
     * Reverses {@see self::addColumns()}.
     *
     * Dropping `builder_design` destroys the editable design. The compiled HTML
     * in the host's own body column survives, but it can no longer be opened in
     * the builder.
     */
    public static function dropColumns(Blueprint $table): void
    {
        $table->dropColumn(['builder_design', 'builder_preset', 'editor_mode']);
    }
}
