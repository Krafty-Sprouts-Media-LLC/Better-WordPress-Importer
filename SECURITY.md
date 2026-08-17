<!--
Better WordPress Importer — security policy
Copyright (c) 2026 Krafty Sprouts Media, LLC
-->

# Security

This plugin parses user-uploaded XML. Treat WXR files as untrusted input.

## Reporting a vulnerability

Do **not** open a public GitHub issue for security reports.

Email [Krafty Sprouts Media, LLC](https://kraftysprouts.com) or contact [Kingsley Felix](https://github.com/iamkingsleyf) privately. Include:

- WordPress and PHP versions
- Plugin version
- Steps to reproduce
- Impact (XXE, privilege, data loss, etc.)

## What we already harden

- `XMLReader::open()` uses `LIBXML_NONET`
- DTD loading and entity substitution are disabled
- Chunked uploads require a `.xml` extension and the `import` / `upload_files` capability path used by the dashboard importer
- Assembled WXR files are stored as private attachments

We will acknowledge valid reports and ship a fix in a patch release when we can.
