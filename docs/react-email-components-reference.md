# React Email components reference

Summary of [react.email/components](https://react.email/components) for building email-safe HTML (e.g. in Laravel Blade).

## Layout

- **Container** – Centers content, constrains max width (e.g. 600px / 37.5em). Use a wrapper `<table>` with `align="center"`, `width="100%"`, and an inner table with `max-width` + fixed `width` for the content column.
- **Section** – Block of content; can contain rows/columns. Implement as `<table role="presentation">` rows.
- **Grid / Row / Column** – Section with rows and columns. Use nested tables: outer table = rows, each row `<td>` contains a table for columns.

## Typography & content

- **Heading** – Renders as `h1`–`h6`. Props: `as`, `m`, `mx`, `my`, `mt`, `mr`, `mb`, `ml`. Use semantic tags with inline styles.
- **Text** – Paragraph/label. Use `<p>` with inline `font-size`, `line-height`, `margin`, `color`.
- **Link** – `<a href="" target="_blank">` with inline styles. Typical link color e.g. `#067df7`.
- **Hr** – Horizontal rule / divider.

## Actions & media

- **Button** – Styled **link** (`<a>`), not `<button>`. Inline styles: background, padding, color, border-radius. Use `display:inline-block`, `padding:12px 18px`, `text-decoration:none`, `color:#fff`. MSO-friendly: wrap label in `<span>` and use `mso-padding-alt:0` where needed.
- **Image** – `<img>` with `display:block`, `border:none`, `outline:none`, `max-width:100%`, explicit `width`/`height` where possible.

## Structure patterns (from render output)

- Root: `<html>` → `<body>` → outer `<table width="100%" cellpadding="0" cellspacing="0" role="presentation" align="center">`.
- Body cell: one `<td>` wrapping the whole email (background, font-family, font-size, line-height).
- Logo: centered image in its own row (e.g. `margin: 20px auto` or table cell `text-align:center`).
- Content card: inner `<table align="center" width="600" style="max-width:600px" …>` with `background-color`, `padding` (e.g. 45px).
- Content cell: single `<td>` for main copy; use `<table>` for multi-column or stacked blocks.
- Buttons: wrapper table with `text-align:center`; inner `<a>` with button styles.
- Footer: separate table below the card, same max-width; optional columns (e.g. Unsubscribe | Manage preferences; address line below in smaller, muted text).

## Styling conventions

- **Inline styles** – Required for email; avoid relying on `<style>` for critical layout.
- **Font** – `font-family: ui-sans-serif, system-ui, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji"` or Arial/Helvetica.
- **Body** – `background-color: rgb(250,251,251)` or `#fafbfb`; `font-size: 16px`; `line-height: 24px`.
- **Links** – `color: #067df7`; `text-decoration: none` (or underline for body links).
- **Primary button** – e.g. `background-color: rgb(34,80,244)`; `color: rgb(255,255,255)`; `padding: 12px 18px`; `border-radius: 0.5rem`.
- **Muted text** – `color: rgb(153,161,175)` or `#99a1af`; `font-size: 14px` for footer/legal.

## Preview / preload

- **Preview text** – Hidden div at top of body with zero-width characters so inbox shows a short preview (e.g. “Welcome to …”) instead of raw HTML. Example: `<div style="display:none; overflow:hidden; line-height:1px; opacity:0; max-height:0; max-width:0">Preview text here</div>`.
- **Preload** – Optional `<link rel="preload" as="image" href="…">` for logo in `<head>`.

## Component list (by category)

| Category   | Components |
|-----------|------------|
| Layout    | Container, Section, Row, Column |
| Structure | Headers (centered/side/social), Footers (1-col/2-col) |
| Typography| Heading, Text, Link, Hr |
| Actions   | Buttons (single, two, download style) |
| Media     | Image, Avatars, Gallery |
| Content   | List, Code inline/block, Markdown |
| Blocks    | Divider, Articles, Features, Stats, Testimonials, Feedback, Pricing, Ecommerce, Marketing |

## Applying to Laravel mail

- Use one Blade layout that mirrors this structure: outer table → body `<td>` → logo row → content card table (max-width 600px, padding 45px) → `{!! Illuminate\Mail\Markdown::parse($slot) !!}` → footer table (same width) → legal line.
- Keep buttons as `<a>` with inline styles; ensure Laravel’s markdown theme outputs the same (e.g. `.button` as link).
- Add a preview div with `config('app.name')` and zero-width spaces.
- Optional: add `mail.footer` config for address and links (Unsubscribe / Manage preferences) to match React Email footer examples.
