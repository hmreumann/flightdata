# GitHub Actions Configuration

This directory contains GitHub Actions workflows and configuration for the Flight Data application.

## Workflows

### 🔄 CI/CD Pipeline (`ci.yml`)
- **Trigger**: Push/PR to `main` or `develop` branches
- **Features**:
  - Multi-PHP version testing (8.2, 8.3)
  - MySQL service for database testing
  - Automated testing with Pest
  - Static analysis with Larastan
  - Code formatting checks with Laravel Pint
  - Automated deployment on main branch

### 🔒 Security Scanning (`security.yml`)
- **Trigger**: Weekly schedule + manual dispatch
- **Features**:
  - Composer security audit
  - Symfony Security Checker
  - Dependency review for PRs

### 📏 Code Quality (`code-quality.yml`)
- **Trigger**: Push/PR to `main` or `develop` branches
- **Features**:
  - Laravel Pint code formatting
  - Larastan static analysis
  - Automatic code styling fixes

### 🚀 Release Management (`release.yml`)
- **Trigger**: Git tags starting with `v*`
- **Features**:
  - Automated testing before release
  - Asset building
  - GitHub release creation

### 🔧 Dependabot (`dependabot.yml`)
- **Schedule**: Weekly updates on Mondays
- **Coverage**:
  - Composer dependencies
  - NPM dependencies
  - GitHub Actions updates

## Templates

### Pull Request Template
- Ensures consistent PR descriptions
- Includes checklists for quality assurance
- Links to related issues

### Issue Templates
- **Bug Report**: Structured bug reporting with environment details
- **Feature Request**: Standardized feature suggestions

## Setup Requirements

### Repository Secrets
The workflows may require the following secrets:
- `GITHUB_TOKEN` (automatically provided)

### Branch Protection
Recommended branch protection rules for `main`:
- Require pull request reviews
- Require status checks to pass
- Require branches to be up to date
- Include administrators

### Environment Variables
For deployment workflows, you may need:
- `APP_ENV=production`
- `APP_KEY` (Laravel application key)
- Database connection details
- Other environment-specific variables

## Usage

### Running Workflows Locally
To test workflows locally, you can use [act](https://github.com/nektos/act):

```bash
# Install act
brew install act

# Run the CI workflow
act -j test

# Run with specific environment
act -j test --env-file .env.testing
```

### Manual Triggers
Some workflows can be triggered manually:
- Security scanning: Go to Actions → Security → Run workflow
- Code quality checks: Included in push/PR triggers

### Monitoring
- Check the Actions tab in your GitHub repository
- Set up notifications for failed workflows
- Review Dependabot PRs regularly

## Customization

### Adding New Workflows
1. Create a new `.yml` file in `.github/workflows/`
2. Define triggers, jobs, and steps
3. Test with a pull request

### Modifying Existing Workflows
1. Update the relevant `.yml` file
2. Test changes in a feature branch
3. Monitor the first run after merging

### Environment-Specific Deployments
Consider adding environment-specific workflows:
- `deploy-staging.yml` for staging deployments
- `deploy-production.yml` for production deployments

## Best Practices

1. **Keep workflows fast**: Cache dependencies when possible
2. **Fail fast**: Put quick checks (linting, formatting) before slow ones (tests)
3. **Use secrets properly**: Never expose sensitive data in logs
4. **Monitor resource usage**: Be mindful of GitHub Actions usage limits
5. **Keep workflows maintainable**: Use reusable actions and clear naming