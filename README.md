# Migrator: Content Blocks content type builder

This TYPO3 extension extends the `migrator` extension by providing a content type builder for content blocks, helping you to migrate your existing content elements
to content blocks elements.

## Installation

```bash
composer require webcoast/migrator-to-content-blocks
```

The extension has a dependency to the `migrator` extension, which will be installed automatically through composer. It also has a dependency to the `friendsoftypo3/content-blocks` extension,
which also will be installed automatically through composer.

**Important:** Remember to include `friendsoftypo3/content-blocks` in your `composer.json` file, to keep it installed after you have migrated your content elements and remove the migration
related extensions.

If you want to migrate flux elements to content blocks elements you need the following packages:
* `friendsoftypo3/content-blocks`
* `webcoast/migrator-from-flux` (content type provider for flux content elements)
* `webcoast/migrator-to-content-blocks` (this extension)

If you want to migrate DCE elements to container elements, you need the following packages:
* `friendsoftypo3/content-blocks`
* `webcoast/migrator-from-dce` (content type provider for DCE content elements)
* `webcoast/migrator-to-content-blocks` (this extension)

## Compatibility

| Extension ↓ / TYPO3 → | 13.4 |
|-----------------------|:----:|
| 1.0.0                 |  ✅   |

## Content Type Builders

This extension provides a content type builder for content blocks. It takes the normalized configuration provided by the content type provider and generates a content block configuration,
which is than handed to the content block API to store the configuration and create the necessary files like language files, templates and the icon.

The builder also tries to copy the frontend template, backend preview template and icon from the original content element, if provided by the content type provider. If not, the default
content block templates and icon are used.

## Migration wizard
The interactive migration wizard guides you through the process of migrating your content elements to content blocks. The process contains the following questions, you need to answer:
1. **In which extension, should we place the content block?** This is auto-complete question with all installed extensions as options.
2. **What is the vendor name of the content block?** The default value is derived form the chosen extension key. The vendor name must not contain dots or slashes.
3. **What is the name of the content block?** You need to provide a valid content block name, e.g. `3-images-with-text`. Only lowercase characters, numbers and dashes are allowed.
4. **What is the type name (CType) of the content block?** The default value is derived from the vendor name and the content block name, e.g. `sitepackage_3imageswithtext`. Only characters,
   numbers and underscores are allowed.
5. **In which wizard category should the content block be placed?** This is auto-complete question with all existing wizard categories as options, but you can also provide a new category.
   However, the category will not be created/configured automatically, so you need to make sure, that the category you choose exists and is configured properly in the backend. The default
   value is the group value from the normalized content type configuration with `default` as fallback.
6. **Do you want to prefix the fields with the content block name?** Simple yes or no, if.
7. **What is the prefix type?** (Optional, if the previous answer is yes). Choose the prefix type, either `full` or `vendor` as supported by content blocks.

After answering these questions, the wizard walks through all fields in the normalized configuration and asks the following questions for each field:
1. **Do you want to process this field?** If not, skips this field. If yes, continues with the next question.
2. **What is the identifier of the field?** The field name, e.g. `image` or `faq_elements`. Only characters, numbers and underscores are allowed. The default value is derived from the
   field name in the normalized configuration.
3. **What is the label of the field?** The label of the field. The default value is label from the normalized configuration. This may be a language label starting with `LLL:`
4. **Do you want to use an existing field?** Yes or no. The default value depends on, if the chosen field name already exists in the TCA schema of the `tt_content` table.

Tabs and sections (anonymous inline records without a database table) are supported. For sections, the wizards asks if you want to migrate the section to inline records. If so, it asks
the questions 1 - 4 for each field within the section. If not, it asks you to choose the field type for the field, that should replace the section field. This is helpful when you want to
replace a section field, that mainly consists of an image with title and teaser, with file references with custom fields. Those custom fields however, must be created and configured
separately within the `Configuration/TCA/Overrides/sys_file_reference.php` file of your extension, as this is out-of-scope for the content type builder.

After processing all fields, the builder create a content block definition, which is then handed to the content block API.

## Sponsors

The development of this extension has been sponsored by
* [Aemka](https://aemka.de/)
* [apart](https://apart.lu/)
* [HZ Internet Services](https://www.hziegenhain.de/)
* [Siteway](https://www.siteway.de/)

Thanks to all sponsors for their support and contributions to the development of this extension!

If you are interested in sponsoring the development of this extension, please contact me via email to [thorben@webcoast.dk](mailto:thorben@webcoast.dk) or in the TYPO3 Slack channel
(#ext-migrator).

## Contributing
Contributions to this extension are always welcome, both in form of pull requests, bug reports and feature requests and ideas.

If you have questions, reach out to me via email to [thorben@webcoast.dk](mailto:thorben@webcoast.dk), the discussion section of this repository or the TYPO3 Slack channel (#ext-migrator).

## License
This extension is licensed under the GPL-3.0 License. See the [LICENSE](LICENSE) file for more details.
