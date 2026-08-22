# OneWeb Database System & QueryBuilder Specification

## 1. Database Configuration (`config.one`)

OneWeb reads database configuration from `config.one` or environment variables:

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

---

## 2. Query Directives

### Fetching Multiple Records
```html
@query products from products where status = "active" order by id desc limit 20
@endquery

@foreach products as product
    <h2>{{ product.name }}</h2>
    <p>Price: ৳{{ product.price }}</p>
@endforeach
```

### Fetching a Single Record
```html
@query user from users where id = {{ route.id }} first
@endquery

<h1>{{ user.name }}</h1>
<p>{{ user.email }}</p>
```

---

## 3. Declarative Form Database Operations

### Insert Data
```html
<form @insert="products" fields="name,category,price,description" redirect="/products.one">
    <input name="name" required>
    <input name="price" required>
    <button type="submit">Save</button>
</form>
```

### Update Data
```html
<form @update="products" where="id = {{ product.id }}" fields="name,price" redirect="/products.one">
    <input name="name" value="{{ product.name }}">
    <input name="price" value="{{ product.price }}">
    <button type="submit">Update</button>
</form>
```

### Delete Data
```html
<form @delete="products" where="id = {{ product.id }}" redirect="/products.one">
    <button type="submit">Delete</button>
</form>
```
