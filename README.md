# Gotenberg PDF engine for EspoCRM

PDF Engine for EspoCRM using Gotenberg, a Docker-powered stateless API for PDF files.

> [!IMPORTANT]
> Gotenberg must be installed and accessible to use this extension. It can be hosted separately from EspoCRM.
>
> Refer to the [Gotenberg documentation] for installation details.

## Getting Started

Follow these instructions to install and configure the extension.

### Installing the Extension

This extension is installed like any other EspoCRM extension. For detailed steps, refer to the [EspoCRM documentation].

### Configuring the Gotenberg API URL

After installing the extension, configure the Gotenberg API URL as follows:

#### From the UI

1. Navigate to **Administration > Integrations > Gotenberg**.
2. Check the `Enabled` option.
3. Enter the Gotenberg API URL in the `API URL` field, e.g., `http://gotenberg:3000`.
4. Click **Save**.

> [!NOTE]
> These settings will be stored in the `data/config.php` file.

#### From the Configuration File

To configure the extension directly, update the `data/config.php` file with the following:

```php
<?php
return [
    // ... existing configuration
    'pdfEngine' => 'Gotenberg',  // Change from default Dompdf to Gotenberg
    'gotenbergApiUrl' => 'http://gotenberg:3000',
];
```

> [!TIP]
> Use only one method to configure the extension. If you configure it via the UI, do not edit the `data/config.php` file, and vice versa.

After updating the configuration, clear the cache:

- **From the UI**: Go to **Administration > Clear Cache**.
- **From the Command Line**: Run the following command:

  ```sh
  php clear_cache.php
  ```

## Development

This project is based on the official [ext-template]. To simplify development, key commands from the ext-template have been wrapped in a `Makefile` and are executed within a Docker container.

To see all available commands run:

```sh
make help
```

> [!TIP]
> If you prefer to run the original commands directly, first access the Docker container using:
>
> `docker compose exec --user devilbox php bash`

### Setup

To build a full instance, run:

```sh
make all
```

### Building extension package

To build extension package run the following command:

```sh
make extension
```

## License

Please see [License File] for more information.

[EspoCRM documentation]: https://docs.espocrm.com/administration/extensions/#installing
[Gotenberg documentation]: https://gotenberg.dev/docs/getting-started/installation
[License File]: LICENSE
[ext-template]: https://github.com/espocrm/ext-template
