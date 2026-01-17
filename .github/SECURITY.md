# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | :white_check_mark: |

## Reporting a Vulnerability

We take security seriously. If you discover a security vulnerability within LaraForge, please report it responsibly.

### How to Report

**Please do NOT report security vulnerabilities through public GitHub issues.**

Instead, please send an email to: **chuks@oilmonegov.com**

Include the following information:
- Type of vulnerability
- Full path to the affected file(s)
- Step-by-step instructions to reproduce the issue
- Proof-of-concept or exploit code (if possible)
- Impact assessment

### Response Timeline

- **Initial Response**: Within 48 hours
- **Status Update**: Within 7 days
- **Resolution Target**: Within 30 days (depending on complexity)

### What to Expect

1. **Acknowledgment**: We'll confirm receipt of your report
2. **Investigation**: We'll investigate and validate the issue
3. **Resolution**: We'll work on a fix and coordinate disclosure
4. **Credit**: We'll credit you in the release notes (unless you prefer anonymity)

### Safe Harbor

We support responsible disclosure. If you follow these guidelines, we will:
- Not pursue legal action against you
- Work with you to understand and resolve the issue
- Credit your contribution (with your permission)

## Security Best Practices for Users

When using LaraForge:

1. **Keep Updated**: Always use the latest version
2. **Review Generated Code**: Inspect generated files before use
3. **Secure Configurations**: Never commit sensitive data in configuration files
4. **Validate Input**: Always validate user input in generated code

## Known Security Considerations

- LaraForge generates code based on templates. Always review generated code before deploying to production.
- Configuration files may contain sensitive paths. Ensure `.laraforge.yaml` is properly secured.

## Security Updates

Security updates will be released as patch versions and announced through:
- GitHub Security Advisories
- Release notes
