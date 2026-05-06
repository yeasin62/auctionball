# PDF fonts

Drop these TrueType font files here for AuctionBall PDF exports to render Bengali correctly.

## Required font

**Solaiman Lipi** — the standard open Bangla typeface. Download `SolaimanLipi.ttf` and place it in this directory.

Official source: https://www.omicronlab.com/bangla-fonts.html

The font is distributed by Omicron Lab under terms permitting redistribution; review the bundled licence file before deploying. Once `SolaimanLipi.ttf` is present here, Bengali column headers and labels in players/teams/season-summary PDFs will render correctly. Without it, the PDFs still generate but Bengali characters appear as empty boxes (Latin/English content is unaffected).

## Optional

- `SolaimanLipi-Bold.ttf` — for bold Bengali text in headings
- Any DejaVu Sans variant — already bundled with dompdf, no action needed

## Verifying installation

After dropping the file:

```powershell
php artisan tinker --execute="echo file_exists(storage_path('fonts/SolaimanLipi.ttf')) ? 'OK' : 'MISSING';"
```

Then export a PDF from `/dashboard/players/export.pdf` while logged in as a Bengali-locale user — the column headers should be in Bangla script.

## Why isn't the font bundled?

Fonts are licensed creative works. Even when freely redistributable, AuctionBall ships without bundled font binaries so each deployment can verify and version the licence appropriately. Place the file once per environment (`storage/fonts/` is gitignored by default — add it to your deploy artefact or run `php artisan auctionball:fonts:install` if you scripted a downloader).
