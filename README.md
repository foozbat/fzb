[![CI](https://github.com/foozbat/fzb/actions/workflows/ci.yml/badge.svg?branch=master)](https://github.com/foozbat/fzb/actions/workflows/ci.yml)

# FZB Framework

A lightweight PHP 8.1+ web framework offering a modular approach to building modern web applications.

---

## 🚀 Features

- **Router** – Define clean and intuitive routes for your application.
- **Input Validator** – Easily validate and sanitize user inputs.
- **Template Renderer** – Render views with a simple and flexible templating engine.
- **Database Abstraction Layer** – Interact with your database using a consistent and straightforward API.
- **Lightweight ORM** – Map database records to PHP objects effortlessly.
- **htmx Integration** – Static methods for htmx use cases.
- And many more!

---

## ⚙️ Requirements

- PHP 8.1 or higher
- Composer

---

## 📦 Installation

Add the following to your `composer.json` file:

```json
{
    "require": {
        "fzb/fzb": "dev-master"
    }
}
```

Then run:
```bash
composer update
```

## 🛠️ Usage

You can use individual components of the FZB Framework as needed.
For example, to use the router:

```php
use Fzb\Router;

$router = new Router();
$router->get('/home', function() {
    echo 'Welcome Home!';
});
```

## 🧪 Testing

Tests are located in the tests directory.
To run the tests:
```bash
composer test
```
Make sure PHPUnit is installed and configured

## ⚠️ License & Usage

This project is licensed under the **FZB Source-Available License**.

- You may use the code as-is for personal or internal projects.
- Contributions are welcome via pull requests.
- You **may not fork**, redistribute, or rebrand this codebase without explicit written permission from the author.

See [LICENSE](LICENSE) for full terms.