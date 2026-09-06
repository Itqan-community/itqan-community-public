# Itqan Composer Tools

Flarum composer improvements maintained by the Itqan community.

## Live Markdown preview

The new-discussion composer includes an accessible preview toggle. On tablet
and desktop widths it opens a side-by-side preview; on phones it switches
between the editor and preview. The rendered output uses Flarum's own
TextFormatter configuration, so enabled Markdown extensions are represented in
the preview exactly as they are in published posts.

## Development

```sh
cd js
npm install
npm run build
```

With the local forum running and the extension enabled, run the browser checks:

```sh
npm test
```
