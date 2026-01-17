<?php

declare(strict_types=1);

namespace LaraForge\DesignSystem;

/**
 * Component Library
 *
 * Defines modular UI components with variants for different use cases.
 * Supports Blade, Livewire, Vue, and React component generation.
 */
final class ComponentLibrary
{
    /**
     * @param  array<string, array<string, mixed>>  $customComponents
     */
    public function __construct(
        private readonly BrandGuidelines $brand,
        private readonly string $stack = 'blade',
        private readonly array $customComponents = [],
    ) {}

    /**
     * Get all available component definitions.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getComponents(): array
    {
        return array_merge(
            $this->getTableComponents(),
            $this->getFormComponents(),
            $this->getNavigationComponents(),
            $this->getFeedbackComponents(),
            $this->getLayoutComponents(),
            $this->getDataDisplayComponents(),
            $this->customComponents,
        );
    }

    /**
     * Get a specific component definition.
     *
     * @return array<string, mixed>|null
     */
    public function getComponent(string $name): ?array
    {
        return $this->getComponents()[$name] ?? null;
    }

    /**
     * Get table component variants.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getTableComponents(): array
    {
        return [
            'table-simple' => [
                'name' => 'Simple Table',
                'description' => 'Basic table with headers and rows',
                'category' => 'tables',
                'variants' => ['default', 'striped', 'bordered', 'compact'],
                'props' => [
                    'columns' => ['type' => 'array', 'required' => true],
                    'data' => ['type' => 'array', 'required' => true],
                    'variant' => ['type' => 'string', 'default' => 'default'],
                ],
                'slots' => ['header', 'row', 'empty'],
                'documentation' => $this->getTableSimpleDoc(),
            ],
            'table-sortable' => [
                'name' => 'Sortable Table',
                'description' => 'Table with column sorting',
                'category' => 'tables',
                'extends' => 'table-simple',
                'variants' => ['default', 'striped'],
                'props' => [
                    'columns' => ['type' => 'array', 'required' => true],
                    'data' => ['type' => 'array', 'required' => true],
                    'sortable' => ['type' => 'array', 'default' => []],
                    'defaultSort' => ['type' => 'string', 'default' => null],
                    'defaultDirection' => ['type' => 'string', 'default' => 'asc'],
                ],
                'events' => ['sort'],
            ],
            'table-searchable' => [
                'name' => 'Searchable Table',
                'description' => 'Table with search functionality',
                'category' => 'tables',
                'extends' => 'table-sortable',
                'props' => [
                    'searchable' => ['type' => 'bool', 'default' => true],
                    'searchPlaceholder' => ['type' => 'string', 'default' => 'Search...'],
                    'searchColumns' => ['type' => 'array', 'default' => []],
                ],
                'events' => ['search', 'sort'],
            ],
            'table-paginated' => [
                'name' => 'Paginated Table',
                'description' => 'Table with pagination controls',
                'category' => 'tables',
                'extends' => 'table-searchable',
                'props' => [
                    'perPage' => ['type' => 'int', 'default' => 15],
                    'perPageOptions' => ['type' => 'array', 'default' => [10, 15, 25, 50, 100]],
                    'currentPage' => ['type' => 'int', 'default' => 1],
                    'total' => ['type' => 'int', 'required' => true],
                ],
                'events' => ['page-change', 'per-page-change', 'search', 'sort'],
            ],
            'table-selectable' => [
                'name' => 'Selectable Table',
                'description' => 'Table with row selection',
                'category' => 'tables',
                'extends' => 'table-paginated',
                'props' => [
                    'selectable' => ['type' => 'bool', 'default' => true],
                    'selected' => ['type' => 'array', 'default' => []],
                    'selectAll' => ['type' => 'bool', 'default' => false],
                ],
                'events' => ['selection-change', 'select-all'],
            ],
            'table-advanced' => [
                'name' => 'Advanced Data Table',
                'description' => 'Full-featured data table with filters, bulk actions, and export',
                'category' => 'tables',
                'extends' => 'table-selectable',
                'props' => [
                    'filters' => ['type' => 'array', 'default' => []],
                    'bulkActions' => ['type' => 'array', 'default' => []],
                    'exportFormats' => ['type' => 'array', 'default' => ['csv', 'xlsx', 'pdf']],
                    'columnVisibility' => ['type' => 'bool', 'default' => true],
                    'density' => ['type' => 'string', 'default' => 'normal'],
                ],
                'events' => ['filter', 'bulk-action', 'export', 'column-visibility-change'],
            ],
        ];
    }

    /**
     * Get form component variants.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getFormComponents(): array
    {
        return [
            'input' => [
                'name' => 'Text Input',
                'description' => 'Standard text input field',
                'category' => 'forms',
                'variants' => ['default', 'filled', 'outlined', 'underlined'],
                'sizes' => ['sm', 'md', 'lg'],
                'props' => [
                    'type' => ['type' => 'string', 'default' => 'text'],
                    'label' => ['type' => 'string', 'default' => null],
                    'placeholder' => ['type' => 'string', 'default' => null],
                    'error' => ['type' => 'string', 'default' => null],
                    'hint' => ['type' => 'string', 'default' => null],
                    'disabled' => ['type' => 'bool', 'default' => false],
                    'required' => ['type' => 'bool', 'default' => false],
                    'prefix' => ['type' => 'string', 'default' => null],
                    'suffix' => ['type' => 'string', 'default' => null],
                ],
            ],
            'textarea' => [
                'name' => 'Textarea',
                'description' => 'Multi-line text input',
                'category' => 'forms',
                'extends' => 'input',
                'props' => [
                    'rows' => ['type' => 'int', 'default' => 3],
                    'autoResize' => ['type' => 'bool', 'default' => false],
                    'maxLength' => ['type' => 'int', 'default' => null],
                    'showCount' => ['type' => 'bool', 'default' => false],
                ],
            ],
            'select' => [
                'name' => 'Select',
                'description' => 'Dropdown select input',
                'category' => 'forms',
                'variants' => ['default', 'searchable', 'multi', 'tags'],
                'props' => [
                    'options' => ['type' => 'array', 'required' => true],
                    'multiple' => ['type' => 'bool', 'default' => false],
                    'searchable' => ['type' => 'bool', 'default' => false],
                    'clearable' => ['type' => 'bool', 'default' => false],
                    'placeholder' => ['type' => 'string', 'default' => 'Select...'],
                ],
            ],
            'checkbox' => [
                'name' => 'Checkbox',
                'description' => 'Checkbox input',
                'category' => 'forms',
                'variants' => ['default', 'card', 'toggle'],
                'props' => [
                    'label' => ['type' => 'string', 'required' => true],
                    'checked' => ['type' => 'bool', 'default' => false],
                    'indeterminate' => ['type' => 'bool', 'default' => false],
                ],
            ],
            'radio' => [
                'name' => 'Radio',
                'description' => 'Radio button input',
                'category' => 'forms',
                'variants' => ['default', 'card', 'button'],
                'props' => [
                    'options' => ['type' => 'array', 'required' => true],
                    'inline' => ['type' => 'bool', 'default' => false],
                ],
            ],
            'date-picker' => [
                'name' => 'Date Picker',
                'description' => 'Date selection input',
                'category' => 'forms',
                'variants' => ['single', 'range', 'month', 'year'],
                'props' => [
                    'format' => ['type' => 'string', 'default' => 'Y-m-d'],
                    'minDate' => ['type' => 'string', 'default' => null],
                    'maxDate' => ['type' => 'string', 'default' => null],
                    'disabledDates' => ['type' => 'array', 'default' => []],
                ],
            ],
            'file-upload' => [
                'name' => 'File Upload',
                'description' => 'File upload with S3-compatible storage support',
                'category' => 'forms',
                'variants' => ['dropzone', 'button', 'avatar', 'gallery'],
                'props' => [
                    'accept' => ['type' => 'string', 'default' => '*'],
                    'multiple' => ['type' => 'bool', 'default' => false],
                    'maxSize' => ['type' => 'int', 'default' => 10485760],
                    'maxFiles' => ['type' => 'int', 'default' => 10],
                    'storage' => ['type' => 'string', 'default' => 's3'],
                    'cdnUrl' => ['type' => 'string', 'default' => null],
                    'preview' => ['type' => 'bool', 'default' => true],
                    'chunked' => ['type' => 'bool', 'default' => false],
                ],
                'events' => ['upload-start', 'upload-progress', 'upload-complete', 'upload-error'],
            ],
            'signature' => [
                'name' => 'Signature Pad',
                'description' => 'Digital signature capture with S3 storage',
                'category' => 'forms',
                'props' => [
                    'width' => ['type' => 'int', 'default' => 400],
                    'height' => ['type' => 'int', 'default' => 200],
                    'penColor' => ['type' => 'string', 'default' => '#000000'],
                    'backgroundColor' => ['type' => 'string', 'default' => '#ffffff'],
                    'storage' => ['type' => 'string', 'default' => 's3'],
                ],
                'events' => ['change', 'clear', 'save'],
            ],
            'rich-editor' => [
                'name' => 'Rich Text Editor',
                'description' => 'WYSIWYG editor with image uploads',
                'category' => 'forms',
                'variants' => ['basic', 'full', 'markdown'],
                'props' => [
                    'toolbar' => ['type' => 'array', 'default' => ['bold', 'italic', 'link', 'image']],
                    'imageUploadStorage' => ['type' => 'string', 'default' => 's3'],
                    'maxLength' => ['type' => 'int', 'default' => null],
                ],
            ],
        ];
    }

    /**
     * Get navigation component variants.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getNavigationComponents(): array
    {
        return [
            'navbar' => [
                'name' => 'Navigation Bar',
                'description' => 'Top navigation bar',
                'category' => 'navigation',
                'variants' => ['default', 'transparent', 'sticky', 'centered'],
                'props' => [
                    'brand' => ['type' => 'string', 'required' => true],
                    'items' => ['type' => 'array', 'default' => []],
                    'sticky' => ['type' => 'bool', 'default' => false],
                ],
                'slots' => ['brand', 'items', 'actions'],
            ],
            'sidebar' => [
                'name' => 'Sidebar',
                'description' => 'Side navigation panel',
                'category' => 'navigation',
                'variants' => ['default', 'compact', 'floating', 'mini'],
                'props' => [
                    'items' => ['type' => 'array', 'required' => true],
                    'collapsed' => ['type' => 'bool', 'default' => false],
                    'collapsible' => ['type' => 'bool', 'default' => true],
                ],
            ],
            'breadcrumb' => [
                'name' => 'Breadcrumb',
                'description' => 'Navigation breadcrumbs',
                'category' => 'navigation',
                'variants' => ['default', 'arrows', 'slashes'],
                'props' => [
                    'items' => ['type' => 'array', 'required' => true],
                    'separator' => ['type' => 'string', 'default' => '/'],
                ],
            ],
            'tabs' => [
                'name' => 'Tabs',
                'description' => 'Tab navigation',
                'category' => 'navigation',
                'variants' => ['default', 'pills', 'underlined', 'bordered'],
                'props' => [
                    'tabs' => ['type' => 'array', 'required' => true],
                    'active' => ['type' => 'string', 'default' => null],
                    'vertical' => ['type' => 'bool', 'default' => false],
                ],
            ],
            'pagination' => [
                'name' => 'Pagination',
                'description' => 'Page navigation',
                'category' => 'navigation',
                'variants' => ['default', 'simple', 'mini'],
                'props' => [
                    'currentPage' => ['type' => 'int', 'required' => true],
                    'totalPages' => ['type' => 'int', 'required' => true],
                    'siblingsCount' => ['type' => 'int', 'default' => 1],
                    'showFirstLast' => ['type' => 'bool', 'default' => true],
                ],
            ],
        ];
    }

    /**
     * Get feedback component variants.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getFeedbackComponents(): array
    {
        return [
            'alert' => [
                'name' => 'Alert',
                'description' => 'Alert messages',
                'category' => 'feedback',
                'variants' => ['info', 'success', 'warning', 'danger'],
                'props' => [
                    'title' => ['type' => 'string', 'default' => null],
                    'message' => ['type' => 'string', 'required' => true],
                    'dismissible' => ['type' => 'bool', 'default' => false],
                    'icon' => ['type' => 'string', 'default' => null],
                ],
            ],
            'toast' => [
                'name' => 'Toast',
                'description' => 'Toast notifications',
                'category' => 'feedback',
                'variants' => ['info', 'success', 'warning', 'danger'],
                'props' => [
                    'message' => ['type' => 'string', 'required' => true],
                    'duration' => ['type' => 'int', 'default' => 5000],
                    'position' => ['type' => 'string', 'default' => 'top-right'],
                ],
            ],
            'modal' => [
                'name' => 'Modal',
                'description' => 'Modal dialog',
                'category' => 'feedback',
                'variants' => ['default', 'fullscreen', 'drawer', 'sheet'],
                'sizes' => ['sm', 'md', 'lg', 'xl', 'full'],
                'props' => [
                    'title' => ['type' => 'string', 'default' => null],
                    'closable' => ['type' => 'bool', 'default' => true],
                    'closeOnEscape' => ['type' => 'bool', 'default' => true],
                    'closeOnClickOutside' => ['type' => 'bool', 'default' => true],
                ],
                'slots' => ['header', 'body', 'footer'],
            ],
            'progress' => [
                'name' => 'Progress',
                'description' => 'Progress indicators',
                'category' => 'feedback',
                'variants' => ['bar', 'circular', 'steps'],
                'props' => [
                    'value' => ['type' => 'int', 'required' => true],
                    'max' => ['type' => 'int', 'default' => 100],
                    'showLabel' => ['type' => 'bool', 'default' => false],
                    'indeterminate' => ['type' => 'bool', 'default' => false],
                ],
            ],
            'skeleton' => [
                'name' => 'Skeleton',
                'description' => 'Loading placeholder',
                'category' => 'feedback',
                'variants' => ['text', 'circle', 'rectangle', 'card'],
                'props' => [
                    'width' => ['type' => 'string', 'default' => '100%'],
                    'height' => ['type' => 'string', 'default' => '1rem'],
                    'animate' => ['type' => 'bool', 'default' => true],
                ],
            ],
        ];
    }

    /**
     * Get layout component variants.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getLayoutComponents(): array
    {
        return [
            'card' => [
                'name' => 'Card',
                'description' => 'Content card',
                'category' => 'layout',
                'variants' => ['default', 'elevated', 'outlined', 'filled'],
                'props' => [
                    'title' => ['type' => 'string', 'default' => null],
                    'subtitle' => ['type' => 'string', 'default' => null],
                    'image' => ['type' => 'string', 'default' => null],
                    'footer' => ['type' => 'string', 'default' => null],
                ],
                'slots' => ['header', 'body', 'footer', 'media'],
            ],
            'container' => [
                'name' => 'Container',
                'description' => 'Content container',
                'category' => 'layout',
                'variants' => ['default', 'fluid', 'narrow'],
                'props' => [
                    'maxWidth' => ['type' => 'string', 'default' => '7xl'],
                    'padding' => ['type' => 'bool', 'default' => true],
                ],
            ],
            'grid' => [
                'name' => 'Grid',
                'description' => 'Grid layout',
                'category' => 'layout',
                'props' => [
                    'cols' => ['type' => 'int', 'default' => 12],
                    'gap' => ['type' => 'string', 'default' => '4'],
                    'responsive' => ['type' => 'bool', 'default' => true],
                ],
            ],
            'divider' => [
                'name' => 'Divider',
                'description' => 'Content divider',
                'category' => 'layout',
                'variants' => ['solid', 'dashed', 'dotted'],
                'props' => [
                    'orientation' => ['type' => 'string', 'default' => 'horizontal'],
                    'label' => ['type' => 'string', 'default' => null],
                ],
            ],
        ];
    }

    /**
     * Get data display component variants.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getDataDisplayComponents(): array
    {
        return [
            'avatar' => [
                'name' => 'Avatar',
                'description' => 'User avatar',
                'category' => 'data-display',
                'variants' => ['circle', 'square', 'rounded'],
                'sizes' => ['xs', 'sm', 'md', 'lg', 'xl'],
                'props' => [
                    'src' => ['type' => 'string', 'default' => null],
                    'name' => ['type' => 'string', 'default' => null],
                    'fallback' => ['type' => 'string', 'default' => null],
                ],
            ],
            'badge' => [
                'name' => 'Badge',
                'description' => 'Status badge',
                'category' => 'data-display',
                'variants' => ['default', 'outline', 'dot'],
                'props' => [
                    'label' => ['type' => 'string', 'required' => true],
                    'color' => ['type' => 'string', 'default' => 'primary'],
                ],
            ],
            'stat' => [
                'name' => 'Stat',
                'description' => 'Statistics display',
                'category' => 'data-display',
                'variants' => ['default', 'card', 'inline'],
                'props' => [
                    'label' => ['type' => 'string', 'required' => true],
                    'value' => ['type' => 'string', 'required' => true],
                    'change' => ['type' => 'string', 'default' => null],
                    'changeType' => ['type' => 'string', 'default' => 'neutral'],
                    'icon' => ['type' => 'string', 'default' => null],
                ],
            ],
            'list' => [
                'name' => 'List',
                'description' => 'Data list',
                'category' => 'data-display',
                'variants' => ['default', 'divided', 'card'],
                'props' => [
                    'items' => ['type' => 'array', 'required' => true],
                    'hoverable' => ['type' => 'bool', 'default' => false],
                ],
            ],
            'timeline' => [
                'name' => 'Timeline',
                'description' => 'Event timeline',
                'category' => 'data-display',
                'variants' => ['default', 'alternate', 'compact'],
                'props' => [
                    'items' => ['type' => 'array', 'required' => true],
                    'orientation' => ['type' => 'string', 'default' => 'vertical'],
                ],
            ],
        ];
    }

    /**
     * Get component template for the current stack.
     */
    public function getTemplate(string $component): string
    {
        return match ($this->stack) {
            'blade' => $this->getBladeTemplate($component),
            'livewire' => $this->getLivewireTemplate($component),
            'vue' => $this->getVueTemplate($component),
            'react' => $this->getReactTemplate($component),
            default => $this->getBladeTemplate($component),
        };
    }

    /**
     * Get component documentation.
     *
     * @return array<string, mixed>
     */
    public function getDocumentation(string $component): array
    {
        $definition = $this->getComponent($component);

        if ($definition === null) {
            return [];
        }

        return [
            'name' => $definition['name'],
            'description' => $definition['description'],
            'category' => $definition['category'],
            'props' => $definition['props'] ?? [],
            'variants' => $definition['variants'] ?? [],
            'events' => $definition['events'] ?? [],
            'slots' => $definition['slots'] ?? [],
            'example' => $this->getExample($component),
        ];
    }

    private function getTableSimpleDoc(): string
    {
        return <<<'DOC'
## Simple Table

A basic table component for displaying tabular data.

### Usage

```blade
<x-table-simple
    :columns="['Name', 'Email', 'Role']"
    :data="$users"
/>
```

### Variants

- `default`: Standard table styling
- `striped`: Alternating row colors
- `bordered`: Visible cell borders
- `compact`: Reduced padding
DOC;
    }

    private function getBladeTemplate(string $component): string
    {
        return "@component('components.{$component}')";
    }

    private function getLivewireTemplate(string $component): string
    {
        return "<livewire:{$component} />";
    }

    private function getVueTemplate(string $component): string
    {
        $pascalCase = str_replace('-', '', ucwords($component, '-'));

        return "<{$pascalCase} />";
    }

    private function getReactTemplate(string $component): string
    {
        $pascalCase = str_replace('-', '', ucwords($component, '-'));

        return "<{$pascalCase} />";
    }

    private function getExample(string $component): string
    {
        return match ($this->stack) {
            'blade' => $this->getBladeExample($component),
            'livewire' => $this->getLivewireExample($component),
            'vue' => $this->getVueExample($component),
            'react' => $this->getReactExample($component),
            default => '',
        };
    }

    private function getBladeExample(string $component): string
    {
        return "<x-{$component} />";
    }

    private function getLivewireExample(string $component): string
    {
        return "<livewire:{$component} />";
    }

    private function getVueExample(string $component): string
    {
        $pascalCase = str_replace('-', '', ucwords($component, '-'));

        return "<template>\n  <{$pascalCase} />\n</template>";
    }

    private function getReactExample(string $component): string
    {
        $pascalCase = str_replace('-', '', ucwords($component, '-'));

        return "import { {$pascalCase} } from '@/components';\n\nexport default function Example() {\n  return <{$pascalCase} />;\n}";
    }

    /**
     * Get the brand guidelines.
     */
    public function getBrand(): BrandGuidelines
    {
        return $this->brand;
    }

    /**
     * Create with defaults.
     */
    public static function create(BrandGuidelines $brand, string $stack = 'blade'): self
    {
        return new self($brand, $stack);
    }
}
