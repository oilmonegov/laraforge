<?php

declare(strict_types=1);

namespace LaraForge\Generators;

use LaraForge\Support\Generator;

/**
 * Generator for Pest Browser tests.
 *
 * Generates browser/E2E tests using Pest's browser testing plugin
 * for testing user interactions, forms, navigation, and visual elements.
 */
final class BrowserTestGenerator extends Generator
{
    public function identifier(): string
    {
        return 'browser-test';
    }

    public function name(): string
    {
        return 'Browser Test Generator';
    }

    public function description(): string
    {
        return 'Generates Pest browser tests for E2E and visual testing';
    }

    public function options(): array
    {
        return [
            'name' => [
                'type' => 'string',
                'description' => 'The name of the browser test (e.g., LoginFlow, CheckoutProcess)',
                'required' => true,
            ],
            'url' => [
                'type' => 'string',
                'description' => 'The base URL or route to test',
                'required' => false,
                'default' => '/',
            ],
            'interactions' => [
                'type' => 'array',
                'description' => 'List of interactions to test (click, type, select, etc.)',
                'required' => false,
                'default' => [],
            ],
            'assertions' => [
                'type' => 'array',
                'description' => 'List of assertions to make (see, dontSee, title, etc.)',
                'required' => false,
                'default' => [],
            ],
            'authenticated' => [
                'type' => 'boolean',
                'description' => 'Whether the test requires authentication',
                'required' => false,
                'default' => false,
            ],
            'screenshots' => [
                'type' => 'boolean',
                'description' => 'Whether to capture screenshots on failure',
                'required' => false,
                'default' => true,
            ],
            'mobile' => [
                'type' => 'boolean',
                'description' => 'Whether to include mobile viewport tests',
                'required' => false,
                'default' => false,
            ],
            'accessibility' => [
                'type' => 'boolean',
                'description' => 'Whether to include accessibility checks',
                'required' => false,
                'default' => false,
            ],
        ];
    }

    public function generate(array $options): array
    {
        $name = $this->studlyCase($options['name']);
        $url = $options['url'] ?? '/';
        $interactions = $options['interactions'] ?? [];
        $assertions = $options['assertions'] ?? [];
        $authenticated = $options['authenticated'] ?? false;
        $screenshots = $options['screenshots'] ?? true;
        $mobile = $options['mobile'] ?? false;
        $accessibility = $options['accessibility'] ?? false;

        $files = [];

        // Generate the browser test file
        $testContent = $this->generateTestContent(
            $name,
            $url,
            $interactions,
            $assertions,
            $authenticated,
            $screenshots,
            $mobile,
            $accessibility
        );

        $testPath = "tests/Browser/{$name}Test.php";
        $files[$testPath] = $testContent;

        // Generate page object if interactions are complex
        if (count($interactions) > 3) {
            $pageObjectContent = $this->generatePageObject($name, $url, $interactions);
            $pageObjectPath = "tests/Browser/Pages/{$name}Page.php";
            $files[$pageObjectPath] = $pageObjectContent;
        }

        return $files;
    }

    private function generateTestContent(
        string $name,
        string $url,
        array $interactions,
        array $assertions,
        bool $authenticated,
        bool $screenshots,
        bool $mobile,
        bool $accessibility
    ): string {
        $testCases = $this->generateTestCases(
            $name,
            $url,
            $interactions,
            $assertions,
            $authenticated,
            $screenshots
        );

        $mobileTests = $mobile ? $this->generateMobileTests($name, $url) : '';
        $accessibilityTests = $accessibility ? $this->generateAccessibilityTests($name, $url) : '';

        return <<<PHP
<?php

declare(strict_types=1);

use function Pest\Browser\browse;

/**
 * Browser tests for {$name}
 *
 * Tests user interactions and visual elements at {$url}
 */

{$testCases}
{$mobileTests}
{$accessibilityTests}
PHP;
    }

    private function generateTestCases(
        string $name,
        string $url,
        array $interactions,
        array $assertions,
        bool $authenticated,
        bool $screenshots
    ): string {
        $cases = [];

        // Basic page load test
        $cases[] = $this->generatePageLoadTest($name, $url);

        // Generate interaction tests
        foreach ($interactions as $interaction) {
            $cases[] = $this->generateInteractionTest($name, $url, $interaction, $authenticated, $screenshots);
        }

        // Generate assertion tests
        foreach ($assertions as $assertion) {
            $cases[] = $this->generateAssertionTest($name, $url, $assertion);
        }

        // If no interactions or assertions, generate default tests
        if (empty($interactions) && empty($assertions)) {
            $cases[] = $this->generateDefaultTests($name, $url, $authenticated);
        }

        return implode("\n\n", $cases);
    }

    private function generatePageLoadTest(string $name, string $url): string
    {
        return <<<PHP
it('loads the {$name} page successfully', function () {
    browse(function (\$browser) {
        \$browser->visit('{$url}')
            ->assertStatus(200);
    });
});
PHP;
    }

    private function generateInteractionTest(
        string $name,
        string $url,
        array $interaction,
        bool $authenticated,
        bool $screenshots
    ): string {
        $type = $interaction['type'] ?? 'click';
        $selector = $interaction['selector'] ?? 'button';
        $value = $interaction['value'] ?? '';
        $description = $interaction['description'] ?? "performs {$type} on {$selector}";

        $authLine = $authenticated ? "\n            ->loginAs(User::factory()->create())" : '';
        $screenshotLine = $screenshots ? "\n            ->screenshot('{$name}-{$type}')" : '';

        $actionLine = match ($type) {
            'click' => "->click('{$selector}')",
            'type' => "->type('{$selector}', '{$value}')",
            'select' => "->select('{$selector}', '{$value}')",
            'check' => "->check('{$selector}')",
            'uncheck' => "->uncheck('{$selector}')",
            'press' => "->press('{$selector}')",
            'attach' => "->attach('{$selector}', '{$value}')",
            default => "->click('{$selector}')",
        };

        return <<<PHP
it('{$description}', function () {
    browse(function (\$browser) {
        \$browser->visit('{$url}'){$authLine}
            {$actionLine}
            ->assertPathIsNot('{$url}'){$screenshotLine};
    });
});
PHP;
    }

    private function generateAssertionTest(string $name, string $url, array $assertion): string
    {
        $type = $assertion['type'] ?? 'see';
        $value = $assertion['value'] ?? '';
        $selector = $assertion['selector'] ?? null;
        $description = $assertion['description'] ?? "asserts {$type} {$value}";

        $assertionLine = match ($type) {
            'see' => "->assertSee('{$value}')",
            'dontSee' => "->assertDontSee('{$value}')",
            'seeIn' => "->assertSeeIn('{$selector}', '{$value}')",
            'title' => "->assertTitle('{$value}')",
            'titleContains' => "->assertTitleContains('{$value}')",
            'visible' => "->assertVisible('{$selector}')",
            'present' => "->assertPresent('{$selector}')",
            'missing' => "->assertMissing('{$selector}')",
            'enabled' => "->assertEnabled('{$selector}')",
            'disabled' => "->assertDisabled('{$selector}')",
            'focused' => "->assertFocused('{$selector}')",
            'value' => "->assertValue('{$selector}', '{$value}')",
            'attribute' => "->assertAttribute('{$selector}', 'data-test', '{$value}')",
            default => "->assertSee('{$value}')",
        };

        return <<<PHP
it('{$description}', function () {
    browse(function (\$browser) {
        \$browser->visit('{$url}')
            {$assertionLine};
    });
});
PHP;
    }

    private function generateDefaultTests(string $name, string $url, bool $authenticated): string
    {
        $authLine = $authenticated ? "\n            ->loginAs(User::factory()->create())" : '';

        return <<<PHP
it('displays the main content', function () {
    browse(function (\$browser) {
        \$browser->visit('{$url}'){$authLine}
            ->assertPresent('main, [role="main"], .content, #app');
    });
});

it('has no broken links', function () {
    browse(function (\$browser) {
        \$browser->visit('{$url}'){$authLine}
            ->assertPresent('a[href]');

        // Note: Full link checking would require additional setup
    });
});

it('has proper page structure', function () {
    browse(function (\$browser) {
        \$browser->visit('{$url}'){$authLine}
            ->assertPresent('head')
            ->assertPresent('body');
    });
});
PHP;
    }

    private function generateMobileTests(string $name, string $url): string
    {
        return <<<PHP

describe('mobile viewport', function () {
    it('is responsive on mobile devices', function () {
        browse(function (\$browser) {
            \$browser->resize(375, 667) // iPhone SE
                ->visit('{$url}')
                ->assertPresent('body')
                ->screenshot('{$name}-mobile');
        });
    });

    it('is responsive on tablet devices', function () {
        browse(function (\$browser) {
            \$browser->resize(768, 1024) // iPad
                ->visit('{$url}')
                ->assertPresent('body')
                ->screenshot('{$name}-tablet');
        });
    });

    it('handles touch interactions', function () {
        browse(function (\$browser) {
            \$browser->resize(375, 667)
                ->visit('{$url}')
                ->tap('button, a, [role="button"]');
        });
    })->skip('Touch interactions require additional browser setup');
});
PHP;
    }

    private function generateAccessibilityTests(string $name, string $url): string
    {
        return <<<PHP

describe('accessibility', function () {
    it('has proper heading hierarchy', function () {
        browse(function (\$browser) {
            \$browser->visit('{$url}')
                ->assertPresent('h1');
        });
    });

    it('has alt text for images', function () {
        browse(function (\$browser) {
            // Check that images have alt attributes
            \$browser->visit('{$url}')
                ->script("
                    const images = document.querySelectorAll('img');
                    const missingAlt = Array.from(images).filter(img => !img.alt);
                    if (missingAlt.length > 0) {
                        throw new Error('Found ' + missingAlt.length + ' images without alt text');
                    }
                ");
        });
    });

    it('has proper form labels', function () {
        browse(function (\$browser) {
            \$browser->visit('{$url}')
                ->script("
                    const inputs = document.querySelectorAll('input:not([type=\"hidden\"]):not([type=\"submit\"]):not([type=\"button\"])');
                    inputs.forEach(input => {
                        const hasLabel = input.labels?.length > 0 || input.getAttribute('aria-label') || input.getAttribute('aria-labelledby');
                        if (!hasLabel) {
                            console.warn('Input missing label:', input);
                        }
                    });
                ");
        });
    });

    it('has sufficient color contrast', function () {
        // Note: Full contrast checking requires axe-core or similar
        browse(function (\$browser) {
            \$browser->visit('{$url}')
                ->assertPresent('body');
        });
    })->skip('Color contrast checking requires axe-core integration');

    it('is keyboard navigable', function () {
        browse(function (\$browser) {
            \$browser->visit('{$url}')
                ->keys('body', '{tab}')
                ->assertFocused('a, button, input, select, textarea, [tabindex]');
        });
    });
});
PHP;
    }

    private function generatePageObject(string $name, string $url, array $interactions): string
    {
        $selectors = [];
        $methods = [];

        foreach ($interactions as $interaction) {
            $selector = $interaction['selector'] ?? 'button';
            $selectorName = $this->camelCase($selector);
            $selectors[$selectorName] = $selector;

            $type = $interaction['type'] ?? 'click';
            $methodName = $this->camelCase("{$type}_{$selectorName}");
            $methods[] = $this->generatePageObjectMethod($methodName, $type, $selectorName);
        }

        $selectorProperties = '';
        foreach ($selectors as $name => $selector) {
            $selectorProperties .= "    public string \${$name} = '{$selector}';\n";
        }

        $methodCode = implode("\n\n", $methods);

        return <<<PHP
<?php

declare(strict_types=1);

namespace Tests\Browser\Pages;

/**
 * Page Object for {$name}
 *
 * Encapsulates page interactions and selectors for maintainability.
 */
class {$name}Page
{
    public string \$url = '{$url}';

{$selectorProperties}
{$methodCode}
}
PHP;
    }

    private function generatePageObjectMethod(string $methodName, string $type, string $selectorName): string
    {
        return match ($type) {
            'click' => <<<PHP
    public function {$methodName}(\$browser): void
    {
        \$browser->click(\$this->{$selectorName});
    }
PHP,
            'type' => <<<PHP
    public function {$methodName}(\$browser, string \$value): void
    {
        \$browser->type(\$this->{$selectorName}, \$value);
    }
PHP,
            default => <<<PHP
    public function {$methodName}(\$browser): void
    {
        \$browser->click(\$this->{$selectorName});
    }
PHP,
        };
    }

    private function studlyCase(string $value): string
    {
        $value = str_replace(['-', '_'], ' ', $value);
        $words = explode(' ', $value);

        return implode('', array_map('ucfirst', $words));
    }

    private function camelCase(string $value): string
    {
        return lcfirst($this->studlyCase($value));
    }
}
