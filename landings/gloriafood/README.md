# GloriaFood compatibility module

This manifest exposes the existing production landing to the module registry
without creating a second renderer or duplicating its stylesheet.

- `template.php` delegates to the current outer template.
- `style` and `script` in the manifest point to the existing single public
  stylesheet and script.
- translations remain in the readable shared JSON seed.
- `legacy_mode` tells integration code not to assume that the V7 `tN` content
  model has already been migrated.

