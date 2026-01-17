# Contributing to LaraForge

Thank you for considering contributing to LaraForge! This document outlines the guidelines and workflow for contributing.

## Code of Conduct

By participating in this project, you agree to maintain a respectful and inclusive environment for everyone.

## How to Contribute

### Reporting Bugs

Before submitting a bug report:
1. Check the [issue tracker](https://github.com/oilmonegov/laraforge/issues) for existing reports
2. Ensure you're using the latest version
3. Collect relevant information (PHP version, OS, error messages)

Use the bug report template when creating a new issue.

### Suggesting Features

Feature requests are welcome! Please use the feature request template and provide:
- A clear description of the feature
- The problem it solves
- Example usage if applicable

### Pull Requests

1. Fork the repository
2. Create a feature branch from `main`:
   ```bash
   git checkout -b feature/your-feature-name
   ```
3. Make your changes following our coding standards
4. Write or update tests as needed
5. Ensure all checks pass
6. Submit a pull request

## Development Setup

### Requirements

- PHP 8.4+
- Composer

### Installation

```bash
git clone https://github.com/oilmonegov/laraforge.git
cd laraforge
composer install
```

### Running Tests

```bash
# Run all tests
composer test

# Run tests with coverage
./vendor/bin/pest --coverage
```

### Code Style

We use Laravel Pint for code formatting:

```bash
# Check code style
composer lint

# Fix code style issues
./vendor/bin/pint
```

### Static Analysis

We use PHPStan for static analysis:

```bash
composer analyse
```

## Commit Message Convention

We follow [Conventional Commits](https://www.conventionalcommits.org/):

```
<type>(<scope>): <description>

[optional body]

[optional footer(s)]
```

### Types

- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `style`: Code style changes (formatting, no logic change)
- `refactor`: Code refactoring
- `perf`: Performance improvements
- `test`: Adding or updating tests
- `build`: Build system or dependency changes
- `ci`: CI/CD configuration changes
- `chore`: Other changes that don't modify src or test files

### Examples

```
feat(generator): add support for custom templates

fix(config): resolve YAML parsing error for nested arrays

docs: update installation instructions

test(template): add tests for variable substitution
```

## Branch Naming

Use descriptive branch names:

- `feature/description` - New features
- `fix/description` - Bug fixes
- `docs/description` - Documentation updates
- `refactor/description` - Code refactoring

## Code Review Process

1. All pull requests require at least one approval
2. CI checks must pass before merging
3. Keep pull requests focused and reasonably sized
4. Respond to feedback constructively

## Testing Guidelines

- Write tests for new features and bug fixes
- Maintain or improve code coverage
- Use descriptive test names
- Follow the existing test structure

```php
it('generates a model with fillable attributes', function () {
    // Arrange
    // Act
    // Assert
});
```

## Documentation

- Update README.md for user-facing changes
- Add PHPDoc comments for public methods
- Include examples for new features

## Questions?

Feel free to open a discussion or reach out if you have questions about contributing.

Thank you for helping improve LaraForge!
