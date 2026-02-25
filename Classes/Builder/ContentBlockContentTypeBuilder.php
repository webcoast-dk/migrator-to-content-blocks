<?php

declare(strict_types=1);

namespace WEBcoast\MigratorToContentBlocks\Builder;

use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use TYPO3\CMS\ContentBlocks\Builder\ContentBlockBuilder;
use TYPO3\CMS\ContentBlocks\Definition\ContentType\ContentType;
use TYPO3\CMS\ContentBlocks\Definition\ContentType\ContentTypeIcon;
use TYPO3\CMS\ContentBlocks\Definition\Factory\UniqueIdentifierCreator;
use TYPO3\CMS\ContentBlocks\Loader\LoadedContentBlock;
use TYPO3\CMS\ContentBlocks\Registry\ContentBlockRegistry;
use TYPO3\CMS\ContentBlocks\Service\PackageResolver;
use TYPO3\CMS\ContentBlocks\Utility\ContentBlockPathUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use WEBcoast\Migrator\Builder\AbstractInteractiveContentTypeBuilder;
use WEBcoast\Migrator\Configuration\ContentTypeProviderInterface;
use WEBcoast\Migrator\Migration\FieldType;

class ContentBlockContentTypeBuilder extends AbstractInteractiveContentTypeBuilder
{
    public function __construct(protected ContentBlockRegistry $contentBlockRegistry, protected PackageResolver $packageResolver, protected ContentBlockBuilder $contentBlockBuilder)
    {
    }

    public function getTitle(): string
    {
        return 'Content Block';
    }

    public function buildContentTypeConfiguration(string $contentTypeName, array $contentTypeConfiguration, ContentTypeProviderInterface $contentTypeProvider): void
    {
        $this->io->section('Creating content block configuration for  "' . $contentTypeName . '"');

        $availableExtensions = $this->getPossibleExtensions();
        $extensionQuestion = new Question('In which extension, should we place the content block?');
        $extensionQuestion->setAutocompleterValues($availableExtensions);
        $extensionQuestion->setValidator(function ($extension) use ($availableExtensions) {
            if (empty($extension)) {
                throw new \RuntimeException('The extension key must not be empty.');
            }
            if (!in_array($extension, $availableExtensions, true)) {
                throw new \RuntimeException('The extension key "' . $extension . '" is not available. Please choose one of the following: ' . implode(', ', $availableExtensions));
            }

            return $extension;
        });
        $targetExtensionKey = $this->io->askQuestion($extensionQuestion);

        $targetVendorName = $this->io->ask('What is the vendor name of the content block?', preg_replace('/_/', '', $targetExtensionKey), function ($value) {
            if (empty($value) || str_contains($value, '.') || str_contains($value, '/')) {
                throw new \RuntimeException('The vendor name of the content block must not be empty and must not contain a dot or a slash.');
            }

            return $value;
        });

        $targetContentBlockName = self::buildContentBlockName($contentTypeConfiguration['title']);
        $targetContentBlockName = $this->io->ask('What is the name of the content block?', $targetContentBlockName, function ($value) {
            if (empty($value) || str_contains($value, '.') || str_contains($value, '/')) {
                throw new \RuntimeException('The name of the content block must not be empty and must not contain a dot or a slash.');
            }

            return $value;
        });

        if ($this->contentBlockRegistry->hasContentBlock($targetVendorName . '/' . $targetContentBlockName)) {
            throw new \RuntimeException('A content block "' . $targetVendorName . '/' . $targetContentBlockName . '" already exists.');
        }

        $prefixFields = $this->io->askQuestion(new ConfirmationQuestion('Do you want to prefix the fields with the content block name?', true));
        $prefixType = null;
        if ($prefixFields) {
            $prefixType = $this->io->askQuestion(new ChoiceQuestion('What is the prefix type?', ['full', 'vendor'], 'full'));
        }

        $fullName = $targetVendorName . '/' . $targetContentBlockName;
        $description = 'Description for ' . ContentType::CONTENT_ELEMENT->getHumanReadable() . ' ' . $fullName;
        $configuration = [
            'table' => 'tt_content',
            'typeField' => 'CType',
            'name' => $fullName,
            'typeName' => UniqueIdentifierCreator::createContentTypeIdentifier($fullName),
            'title' => $contentTypeConfiguration['title'],
            'description' => $contentTypeConfiguration['wizard_description'] ?: $description,
            'group' => $contentTypeConfiguration['wizard_category'] ?: 'default',
            'prefixFields' => $prefixFields,
            'fields' => $this->buildFieldsConfiguration($contentTypeConfiguration['fields'], $targetExtensionKey)
        ];

        if ($prefixType) {
            $configuration['prefixType'] = $prefixType;
        }

//        if (count(array_intersect(['space_before_class', 'space_after_class', 'layout', 'frame_class'], GeneralUtility::trimExplode(',', $contentTypeConfiguration['palette_fields'])))) {
//            $configuration['basics'][] = 'TYPO3/Appearance';
//        }
//
//        if ($contentTypeConfiguration['show_category_tab'] ?? false) {
//            $configuration['fields'][] = 'TYPO3/Categories';
//        }

        $contentBlock = new LoadedContentBlock(
            $targetVendorName . '/' . $targetContentBlockName,
            $configuration,
            new ContentTypeIcon(),
            $targetExtensionKey,
            'EXT:' . $targetExtensionKey . '/' . ContentBlockPathUtility::getRelativeContentElementsPath(),
            ContentType::CONTENT_ELEMENT
        );

        $this->io->block('Configuration finished, saving content block "' . $contentBlock->getName() . '"', style: 'bg=green;fg=black', padding: true);

        $this->contentBlockBuilder->create($contentBlock);
        $this->copyTemplate($contentTypeProvider, $contentTypeName, $contentBlock);
        $this->copyIcon($contentTypeProvider, $contentTypeName, $contentBlock);
    }

    private function buildFieldsConfiguration(array $fields, string $targetExtensionKey): array
    {
        $contentBlockFields = [];
        foreach ($fields as $field) {
            if ($field['type'] === FieldType::TAB) {
                $this->io->writeln('<b>Tab:</b> ' . $field['title'] . ' (' . $field['identifier'] . ')');
                if ($this->io->askQuestion(new ConfirmationQuestion('Do you want to process this tab?', true))) {
                    $contentBlockFields[] = $this->buildFieldConfiguration($field, $targetExtensionKey);
                }
            } else {
                $this->io->writeln('<b>Field:</b> ' . $field['label'] . ' (' . $field['identifier'] . ')');
                if ($this->io->askQuestion(new ConfirmationQuestion('Do you want to process this field?', true))) {
                    $contentBlockFields[] = $this->buildFieldConfiguration($field, $targetExtensionKey);
                }
            }
        }

        return $contentBlockFields;
    }

    protected function buildFieldConfiguration(array $field, string $targetExtensionKey): array
    {
        if ($field['type'] === FieldType::TAB) {
            return [
                'identifier' => $this->io->askQuestion(
                    (new Question('What is the identifier of the tab?', $field['identifier']))
                        ->setValidator(function ($value) {
                            if (empty($value)) {
                                throw new \RuntimeException('The identifier of the tab must not be empty.');
                            }

                            return $value;
                        })
                ),
                'type' => 'Tab',
                'label' => $this->io->askQuestion(
                    (new Question('What is the label of the field?', $field['title']))
                        ->setValidator(function ($value) {
                            if (empty($value)) {
                                throw new \RuntimeException('The label of the field must not be empty.');
                            }

                            return $value;
                        })
                )
            ];
        }

        $fieldConfiguration = [
            'identifier' => $this->io->askQuestion(
                (new Question('What is the identifier of the field?', GeneralUtility::camelCaseToLowerCaseUnderscored($field['identifier'])))
                    ->setValidator(function ($value) {
                        if (empty($value)) {
                            throw new \RuntimeException('The identifier of the field must not be empty.');
                        }

                        return $value;
                    })
            ),
            'label' => $this->io->askQuestion(
                (new Question('What is the label of the field?', $field['label']))
                    ->setValidator(function ($value) {
                        if (empty($value)) {
                            throw new \RuntimeException('The label of the field must not be empty.');
                        }

                        return $value;
                    })
            )
        ];

        if ($this->io->askQuestion(new ConfirmationQuestion('Do you want to use an existing field?', false))) {
            $fieldConfiguration['useExistingField'] = true;
        }

        if ($field['type'] !== FieldType::SECTION) {
            $fieldConfiguration['type'] = match ($field['type']) {
                FieldType::CATEGORY => 'Category',
                FieldType::CHECKBOX => 'Checkbox',
                FieldType::COLOR => 'Color',
                FieldType::DATETIME => 'DateTime',
                FieldType::EMAIL => 'Email',
                FieldType::FILE, FieldType::LEGACY_FILE => 'File',
                FieldType::FLEXFORM => 'FlexForm',
                FieldType::FOLDER => 'Folder',
                FieldType::GROUP => 'Relation',
                FieldType::IMAGE_MANIPULATION => 'ImageManipulation',
                FieldType::INLINE => 'Collection',
                FieldType::TEXT => 'Text',
                FieldType::JSON => 'Json',
                FieldType::LANGUAGE => 'Language',
                FieldType::LINK => 'Link',
                FieldType::NUMBER => 'Number',
                FieldType::PASSWORD => 'Password',
                FieldType::RADIO => 'Radio',
                FieldType::SELECT => 'Select',
                FieldType::SLUG => 'Slug',
                FieldType::TEXTAREA => 'Textarea',
                FieldType::UUID => 'Uuid',
            };

            $fieldConfiguration = array_replace_recursive($fieldConfiguration, $field['config'] ?? []);
        } else {
            $this->io->block('The field "' . $field['label'] . '" is a section type. Sections are converted to collections/inline records. Do want to build the collection configuration or convert the section to another field?', style: 'bg=yellow;fg=black', padding: true);

            if ($this->io->askQuestion(new ConfirmationQuestion('Do you want to build the collection configuration (yes) or convert to another field (no)?', true))) {
                $table = 'tx_' . str_replace('_', '', $targetExtensionKey) . '_domain_model_' . $fieldConfiguration['identifier'];
                $table = preg_replace('/([^s])s$/', '$1', $table);
                $fieldConfiguration = array_replace_recursive(
                    $fieldConfiguration,
                    [
                        'type' => 'Collection',
                        'table' => $this->io->askQuestion(
                            (new Question('What is the table name? Must start with "tx_' . str_replace('_', '', $targetExtensionKey) . '_"', $table))
                                ->setValidator(function ($value) use ($table, $targetExtensionKey) {
                                    if (empty($value)) {
                                        return $table;
                                    }
                                    if (!str_starts_with($value, 'tx_' . str_replace('_', '', $targetExtensionKey) . '_')) {
                                        throw new \RuntimeException('The table name must start with "tx_' . str_replace('_', '', $targetExtensionKey) . '_".');
                                    }

                                    return $value;
                                })
                        ),
                    ]
                );

                $this->io->info('Let\'s continue with the section fields.');

                $fieldConfiguration['fields'] = $this->buildFieldsConfiguration($field['fields'] ?? [], $targetExtensionKey);
            } else {
                $this->io->info('Converting section "' . $field['title'] . '" to another field type.');
                $fieldConfiguration['type'] = $this->io->askQuestion(new ChoiceQuestion('What is the field type?', ['Category', 'Checkbox', 'Color', 'DateTime', 'Email', 'File', 'FlexForm', 'Folder', 'Relation', 'ImageManipulation', 'Collection', 'Text', 'Json', 'Language', 'Link', 'None', 'Number', 'Pass', 'Password', 'Radio', 'Select', 'Slug', 'Textarea', 'Uuid'], null));
                $fieldConfiguration['useExistingField'] = $this->io->askQuestion(new ConfirmationQuestion('Do you want to use an existing field?', true));
            }
        }

        if ($fieldConfiguration['useExistingField'] ?? false) {
            unset($fieldConfiguration['type']);
        }

        return $fieldConfiguration;
    }

    protected function copyTemplate(ContentTypeProviderInterface $provider, string $contentTypeIdentifier, LoadedContentBlock $contentBlock): void
    {
        $templateContent = $provider->getTemplate($contentTypeIdentifier);

        if ($templateContent) {
            GeneralUtility::writeFile(GeneralUtility::getFileAbsFileName($contentBlock->getExtPath() . '/' . $contentBlock->getPackage() . '/' . ContentBlockPathUtility::getFrontendTemplatePath()), $templateContent);
        }
    }

    protected function copyIcon(ContentTypeProviderInterface $provider, string $contentTypeIdentifier, LoadedContentBlock $contentBlock): void
    {
        $absoluteIconPath = $provider->getIcon($contentTypeIdentifier);
        $iconContent = file_get_contents($absoluteIconPath);
        $iconFileExt = pathinfo($absoluteIconPath, PATHINFO_EXTENSION);

        if ($iconContent && $iconFileExt) {
            GeneralUtility::writeFile(GeneralUtility::getFileAbsFileName($contentBlock->getExtPath() . '/' . $contentBlock->getPackage() . '/' . ContentBlockPathUtility::getIconPathWithoutFileExtension() . '.' . $iconFileExt), $iconContent);
        }
    }

    protected function getPossibleExtensions(): array
    {
        $extensions = [];

        foreach ($this->packageResolver->getAvailablePackages() as $package) {
            $extensions[] = $package->getValueFromComposerManifest('extra')?->{'typo3/cms'}->{'extension-key'};
        }

        return $extensions;
    }

    private static function buildContentBlockName(string $title): string
    {
        $title = preg_replace('/[^\w]/', '-', $title);
        $title = preg_replace('/-+/', '-', $title);
        $title = trim($title, '-');

        return strtolower(trim($title));
    }

    public function supports(array $normalizedConfiguration): bool
    {
        // Supports all content types
        return true;
    }
}
