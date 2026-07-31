<?php

namespace Peppermint\DocumentBuilder\Services;

/**
 * Substitutes `{{ placeholder }}` tokens in a rendered template.
 *
 * The builder never touches placeholders — it only inserts the tokens. All
 * substitution happens here, so the preview and the printed PDF cannot drift
 * apart.
 */
class PlaceholderRenderer
{
    /** Matches `{{ key }}`, `{{key}}` and `{{  key  }}` alike. */
    private const TOKEN_PATTERN = '/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/';

    /**
     * Replaces tokens in an HTML document. Values are HTML-escaped — a company
     * called "Müller & Sohn" must not be able to break the markup.
     *
     * Line breaks in a value become `<br>`, because address blocks are handed
     * over as multi-line strings.
     *
     * @param  array<string, string|null>  $replacements
     */
    public function renderHtml(string $template, array $replacements, bool $stripUnknown = true): string
    {
        return $this->replace(
            $template,
            $replacements,
            $stripUnknown,
            static fn (string $value): string => nl2br(
                htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                false,
            ),
        );
    }

    /**
     * Replaces tokens in plain text. No escaping.
     *
     * @param  array<string, string|null>  $replacements
     */
    public function renderText(string $template, array $replacements, bool $stripUnknown = true): string
    {
        return $this->replace($template, $replacements, $stripUnknown, static fn (string $value): string => $value);
    }

    /**
     * Tokens present in the template that the given replacements do not cover.
     *
     * Use it to warn before printing rather than shipping an invoice with a
     * blank tax number.
     *
     * @param  array<string, string|null>  $replacements
     * @return list<string>
     */
    public function missingPlaceholders(string $template, array $replacements): array
    {
        return array_values(array_unique(array_filter(
            $this->placeholdersIn($template),
            static fn (string $key): bool => ! array_key_exists($key, $replacements),
        )));
    }

    /**
     * Every distinct token used in the template, in order of appearance.
     *
     * @return list<string>
     */
    public function placeholdersIn(string $template): array
    {
        preg_match_all(self::TOKEN_PATTERN, $template, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    /**
     * @param  array<string, string|null>  $replacements
     * @param  callable(string): string  $escape
     */
    private function replace(string $template, array $replacements, bool $stripUnknown, callable $escape): string
    {
        return (string) preg_replace_callback(
            self::TOKEN_PATTERN,
            static function (array $match) use ($replacements, $stripUnknown, $escape): string {
                $key = $match[1];

                if (array_key_exists($key, $replacements)) {
                    return $escape((string) ($replacements[$key] ?? ''));
                }

                // Leaving the raw token in a customer-facing document looks
                // broken, but so does silently swallowing it.
                // `missingPlaceholders()` exists so callers can catch this
                // before printing.
                return $stripUnknown ? '' : $match[0];
            },
            $template,
        );
    }
}
