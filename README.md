# Better WordPress Importer

A maintained fork of [humanmade/WordPress-Importer](https://github.com/humanmade/WordPress-Importer) (WordPress Importer v2) for large WXR imports: chunked dashboard upload, author matching, find-or-create categories/tags, file logging, and concurrent attachment prefetch.

Requires PHP 8.1+.

## How do I use it?

### Via the Dashboard

1. Install the plugin from GitHub. ([Download as a ZIP.](https://github.com/Krafty-Sprouts-Media-LLC/Better-WordPress-Importer/archive/master.zip))
2. Activate it (deactivate the original WordPress Importer if it is also installed).
3. Go to Tools → Import
4. Select **Better WordPress Importer**
5. Follow the on-screen instructions.

### Via the CLI

```sh
wp wxr-importer import import-file.xml
```

Run `wp help wxr-importer import` for options.

## License

GPLv2 or later.

## Credits

Original WordPress Importer by Ryan Boren, [Jon Cave](https://profiles.wordpress.org/duck_), [Andrew Nacin](https://profiles.wordpress.org/nacin), and [Peter Westwood](https://profiles.wordpress.org/westi). Importer v2 / Redux by [Ryan McCue](https://github.com/rmccue) and [humanmade contributors](https://github.com/humanmade/WordPress-Importer/graphs/contributors). This fork is maintained by [Krafty Sprouts Media, LLC](https://kraftysprouts.com).
