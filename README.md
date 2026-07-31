# Peppermint Document Builder

Print-ready business documents for Laravel — offers, order confirmations, invoices and
delivery notes — from templates your users can edit themselves.

Unlike a generic HTML-to-PDF helper, this package owns the **document skeleton**: page
geometry, address field, header, footer and the mandatory legal details. A template fills
the free zones; it cannot move the tax number off the page. On an invoice, that constraint
is the feature.

> **Status: v0.1.** The Laravel side, the DIN 5008 preset and the renderer driver are
> complete and verified. The drag & drop editor is the next milestone — see
> [Roadmap](#roadmap).

## Why not just Blade?

Blade is excellent for developer-maintained reports. It stops being excellent the moment a
non-developer needs to change the wording of an offer, add a logo, or reorder the columns
of the item table. This package is for that second case: **user-editable document templates
with a layout nobody can break.**

## Installation

```bash
composer require peppermint/document-builder
php artisan document-builder:install
php artisan migrate
```

The install command publishes the config and writes a migration that adds three columns to
*your* template table. The package ships no migration of its own — a package that silently
alters a production table is a footgun.

For the DomPDF driver:

```bash
composer require dompdf/dompdf
```

## Usage

Map whatever your application has onto `DocumentData` once:

```php
use Peppermint\DocumentBuilder\Data\DocumentData;
use Peppermint\DocumentBuilder\DocumentBuilder;

$data = DocumentData::fromArray([
    'type' => 'offer',
    'subject' => 'Angebot über Druckerzeugnisse',
    'sender' => [
        'name' => 'Peppermint Digital GmbH',
        'lines' => ['Musterstraße 1', '26123 Oldenburg'],
    ],
    'recipient' => [
        'name' => 'Mustermann & Sohn GmbH',
        'lines' => ['Frau Dr. Erika Mustermann', 'Beispielweg 42', '26123 Oldenburg'],
        'note' => 'Einschreiben · Rückschein',
    ],
    'meta' => [
        'Angebotsnummer' => 'AN-2026-0815',
        'Datum' => '31.07.2026',
    ],
    'line_items' => $order->positions->map(fn ($p) => [
        'position' => $p->number,
        'description' => $p->title,
        'note' => $p->specification,
        'quantity' => $p->quantity,
        'unit' => $p->unit,
        'unit_price' => $p->unit_price,
        'total' => $p->total,
    ])->all(),
    'totals' => [
        'net' => $order->net,
        'gross' => $order->gross,
        'taxes' => [['label' => 'zzgl. 19 % USt.', 'amount' => $order->tax]],
    ],
]);

$pdf = app(DocumentBuilder::class)->pdf($data, $template->template_html);
```

`$template->template_html` is the body for the free zones. It knows only two things about
the item table:

```html
<p>{{ intro }}</p>
{{ line_items }}
{{ totals }}
<p>This offer is valid for 30 days.</p>
```

`{{ line_items }}` and `{{ totals }}` expand to markup; every other `{{ token }}` is a
value and is HTML-escaped. A table of unknown length is not a placeholder, which is why
those two are handled separately.

### Catching gaps before you print

```php
$missing = app(DocumentBuilder::class)->missingPlaceholders($data, $template->template_html);
```

Shipping an invoice with a blank tax number is worse than failing loudly.

### Preview

`html()` returns the same document `pdf()` renders, so an on-screen preview cannot drift
away from the printed result:

```php
return response(app(DocumentBuilder::class)->html($data, $body));
```

## The DIN 5008 preset

`Din5008Preset` implements German business letter form B. All offsets are specified from
the paper edge and converted to content-box offsets internally — absolutely positioned
elements sit outside the flow, and applying an offset against the wrong origin prints the
subject line straight through the address field.

| Element | Position from paper edge |
| --- | --- |
| Address field | 45 mm top, 20 mm left, 85 × 45 mm |
| Supplementary zone | 17.7 mm of the address field |
| Information block | 125 mm left, 75 mm wide |
| Subject line | 98.4 mm top |
| Fold marks | 87 mm and 192 mm |
| Hole mark | 148.5 mm |

Options are passed per document: `logo`, `footer_columns`, `watermark`, `fold_marks`,
`page_number_format`, `font_family`, `accent_color` and more.

## Renderers

The engine is a driver, so it stays swappable:

```php
'renderer' => \App\Printing\HeadlessChromeRenderer::class,
```

The bundled `DomPdfRenderer` is the default and carries the full skeleton — verified
against DomPDF 3:

- millimetre geometry for the address field, fold marks and hole mark
- `<thead>` repeats after every page break
- `page-break-inside: avoid` keeps the totals block together
- a fixed footer on every page
- page numbers with a total count, drawn from a `page_script`
- CSS `transform: rotate()` for draft watermarks

A five-page offer with 42 line items renders in about 250 ms.

**Constraints any template must respect**, because this is what DomPDF actually does:

1. Flat CSS only. `@media print` never applies — DomPDF's default media type is `screen`.
2. No flexbox, no grid. Layout with tables and floats.
3. Explicit column widths. Automatic table layout wraps amounts onto two lines.
4. `enable_php` must stay on, or there are no page numbers.

## Editor integration

Add the trait to your template model:

```php
use Peppermint\DocumentBuilder\Concerns\HasDocumentBuilderDesign;

class DocumentTemplate extends Model
{
    use HasDocumentBuilderDesign;
}
```

`editor_mode` defaults to `legacy`, so existing templates keep whatever editor they had.
Switching a template to the builder is a per-template decision, not a migration everybody
has to survive at once.

The typed contract for the frontend ships as an npm package:

```bash
npm install @peppermint-digital/document-builder
```

```ts
import { DEFAULT_COLUMNS, DIN_5008, type LineItemColumn } from '@peppermint-digital/document-builder/core';
```

## Roadmap

- [x] Data contract, placeholder and line-item rendering
- [x] DIN 5008 preset, verified against DomPDF
- [x] Renderer driver interface
- [ ] GrapesJS editor with bound zones and domain blocks
- [ ] Conditional sections
- [ ] A neutral (non-DIN) preset for international documents

## License

MIT
