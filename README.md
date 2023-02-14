# Folio Community Plugin

## Description

Folio Community Management. Multisite indexing and latest publications.

### Languages Available

- English
- Catalan
- Spanish (Supported by Author)

## Developing With npm, esbuild

### Requirements

- [Node.js](https://nodejs.org/)

### Installing Dependencies

- Make sure you have installed Node.js and NPM, on your computer globally.
- For optimize node_modules disk space optionally you can install pnpm.
- Then open your terminal and browse to the location of your theme copy.
- Run: `$ npm install` or `$ pnpm install`

### Running

To work with and compile scripts files, and generate development assets on the fly run:

```
$ npm run watch
```

If using pnpm:

```
$ pnpm watch
```

To compile asssets for development run:

```
$ npm run dev
```

If using pnpm:

```
$ pnpm dev
```

To compile assets for production run:

```
$ npm run prod
```

If using pnpm:

```
$ pnpm prod
```

### Changelog

#### v1.0.0
- Initial release


#### v1.0.1
- Added pnpm compatibility
- Fix: Shortcodes in static home page, remove shortcodes query_vars


#### v1.0.2
- Update recent activity refresh icon + update time
- Added dayjs dependency
- Recent activity post human friendly time ago
- Fix: Recent activity post timezone conversion
