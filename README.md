# OneWeb - HTML-First PHP Template Engine & Micro-Framework (v1.0.3)

[![Version](https://img.shields.io/badge/version-v1.0.3-blue.svg)](https://github.com/arforayejibd/oneweb)
[![Latest Stable Version](https://img.shields.io/packagist/v/arforayejibd/oneweb.svg)](https://packagist.org/packages/arforayejibd/oneweb)
[![Total Downloads](https://img.shields.io/packagist/dt/arforayejibd/oneweb.svg)](https://packagist.org/packages/arforayejibd/oneweb)
[![License](https://img.shields.io/packagist/l/arforayejibd/oneweb.svg)](https://packagist.org/packages/arforayejibd/oneweb)

**OneWeb** is a lightweight, HTML-first template engine and micro-framework for PHP. It enables building dynamic web applications with declarative HTML, auto-escaping, direct database query blocks, zero-boilerplate forms, nested layouts, and a built-in modern UI component system.

---

## Features

- **HTML-First Syntax**: Keep templates clean and declarative. No more messy PHP tag soup.
- **Auto-XSS Protection**: Automatic HTML escaping on all variable interpolations (`{{ var }}`).
- **Declarative Database Queries**: Fetch data directly inside templates with `@query`.
- **Zero-Boilerplate Forms**: Execute database inserts, updates, and deletes directly from `<form>` attributes with automatic validation and CSRF checks.
- **File-Based Routing**: Routing resolved automatically based on the directory hierarchy in your `public/` folder.
- **Built-in UI Component System**: Ready-to-use `<one-*>` component tags for grids, badges, cards, alerts, and inputs styled with modern Tailwind CSS layouts.
- **Nested Layouts & Sections**: Build clean page structures using `@layout`, `@section`, and `@yield`.

---

## Installation

### 1. Create a New Project
Run the following command to download the framework skeleton and set up your project in the current directory:

```bash
composer create-project arforayejibd/oneweb ./
```

### 2. Start the Local Server
Start the local development server using one of the following commands:

**Cross-platform (recommended):**
```bash
composer run one
```
*(Or `composer start`)*

**Or using shortcuts:**
- **Windows:** `start one` or `run one`
- **macOS/Linux:** `./start one` or `./run one` *(Make sure to run `chmod +x start run` first)*



This command will automatically:
- Start the server at **`http://localhost:8000`**
- Create a `public/` directory with a default `index.one` homepage if it doesn't exist.
- Configure `.vscode/settings.json` to enable HTML syntax highlighting for `*.one` templates in VS Code.
- Generate your database configuration `config.one` and the SQLite database `onescript.sqlite` in the project root.

---

## Directory Structure

A typical OneWeb application structure:

```text
├── public/                 # Your public web root (routing resolves here)
│   ├── index.one           # Home page (resolves to /)
│   ├── test.one            # Test page (resolves to /test)
│   └── header.one          # Shared partials
├── config.one              # Database and application configuration
├── onescript.sqlite        # Database file (if using SQLite)
└── vendor/                 # Composer dependencies
```

---

## Quick Start

### 1. Database Configuration (`config.one`)
Define your database configuration in a `config.one` file in your root folder:

```html
@db
    driver = "sqlite"
    database = "onescript.sqlite"
@enddb
```

Or for MySQL:

```html
@db
    driver = "mysql"
    host = "127.0.0.1"
    port = 3306
    dbname = "mywebsite"
    username = "root"
    password = "password"
    charset = "utf8mb4"
@enddb
```

### 2. Variable Interpolation
```html
<!-- HTML Escaped Output (Safe against XSS) -->
<h1>Hello, {{ user.name }}!</h1>

<!-- Raw HTML Output (Unescaped) -->
<div>{!! post.content !!}</div>
```

### 3. Conditionals & Loops
```html
@if user.balance > 0
    <p>Available Balance: ৳{{ user.balance }}</p>
@else
    <p>No balance</p>
@endif

@foreach products as product
    <div class="card">
        <h3>{{ loop.index }}. {{ product.name }}</h3>
    </div>
@endforeach
```

### 4. Database Queries (`@query`)
Fetch data directly inside your templates:

```html
@query products from products where status = "active" order by id desc limit 10
@endquery

@foreach products as product
    <p>{{ product.name }} - ৳{{ product.price }}</p>
@endforeach
```

To fetch a single record:

```html
@query user from users where id = {{ route.id }} first
@endquery

<h1>{{ user.name }}</h1>
```

### 5. Declarative Forms (`@insert`, `@update`, `@delete`)
Perform safe database CRUD operations with zero server-side handler code:

```html
<!-- Insert Record -->
<form @insert="products" fields="name,price" redirect="/index.one">
    <input name="name" required placeholder="Product Name">
    <input name="price" required placeholder="Price">
    <button type="submit">Add Product</button>
</form>

<!-- Update Record -->
<form @update="products" where="id = {{ product.id }}" fields="name,price" redirect="/index.one">
    <input name="name" value="{{ product.name }}">
    <button type="submit">Update</button>
</form>

<!-- Delete Record -->
<form @delete="products" where="id = {{ product.id }}" redirect="/index.one">
    <button type="submit">Delete</button>
</form>
```

### 6. Layouts & Partials (`@layout`, `@section`, `@yield`, `@include`)

#### Layout file (`layouts/main.one`):
```html
<!DOCTYPE html>
<html>
<head>
    <title>OneWeb Application</title>
</head>
<body>
    @include "header"
    
    <main>
        @yield "content"
    </main>
</body>
</html>
```

#### Page file (`dashboard.one`):
```html
@layout "main"

@section "content"
    <h1>Welcome to the Dashboard</h1>
@endsection
```

---

## Built-in UI Components (`<one-*>`)

OneWeb ships with a modern, modular UI component registry that generates standard CSS-styled layout elements:

- **`<one-container max-width="7xl">`**: Wraps content in a responsive, centered container.
- **`<one-grid cols="3" gap="6">`**: Sets up a responsive flex/grid structure.
- **`<one-card title="..." price="..." badge="..." badge-variant="...">`**: Modern cards with content slots.
- **`<one-button type="..." href="...">`**: Styled buttons (`primary`, `secondary`, `success`, `danger`, `outline`).
- **`<one-badge variant="...">`**: Custom badges (`purple`, `success`, `warning`, `danger`, `info`). Includes a pulse animation dot.
- **`<one-alert type="...">`**: Standard alerts.
- **`<one-input name="..." label="..." type="..." placeholder="...">`**: Form inputs with label styling.

Example component composition:

```html
<one-container max-width="7xl">
    <one-badge variant="purple">Page Overview</one-badge>
    <one-heading level="1">Dashboard</one-heading>
    
    <one-grid cols="2" gap="4">
        <one-card title="Analytics" price="Active">
            <p>Your storefront traffic statistics.</p>
            <one-button href="/analytics" type="primary">View Details</one-button>
        </one-card>
    </one-grid>
</one-container>
```

---

## License

This package is open-sourced software licensed under the [MIT License](LICENSE).
