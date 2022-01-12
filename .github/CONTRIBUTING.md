# CONTRIBUTING

We are using [GitHub Actions](https://github.com/features/actions) as a continuous integration system.

For details, see [`workflows/continuous-integration.yml`](workflows/continuous-integration.yml).

## Code style

We format the code automatically with [php-cs-fixer](https://github.com/friendsofphp/php-cs-fixer)

    make fix

Prefer explicit naming and short, focused functions over excessive comments.

## Static Code Analysis

We are using [`phpstan/phpstan`](https://github.com/phpstan/phpstan) to statically analyze the code.

Run

```bash
make stan
```

to run a static code analysis.

## Tests

We are using [`phpunit/phpunit`](https://github.com/sebastianbergmann/phpunit) to drive the development.

Run

```bash
make test
```

to run all the tests.

## Extra lazy?

Run

```bash
make
```

to enforce coding standards, perform a static code analysis, and run tests!

:bulb: Run

```bash
make help
```

to display a list of available targets with corresponding descriptions.
