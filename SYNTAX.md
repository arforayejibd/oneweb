# OneWeb Language Syntax Reference Guide

## 1. Variable Interpolation
```html
<!-- HTML Escaped Output (Safe against XSS) -->
<h1>{{ user.name }}</h1>
<p>{{ product.price }}</p>

<!-- Raw HTML Output (Unescaped) -->
<div>{!! post.content !!}</div>
```

---

## 2. Conditionals (`@if`, `@elseif`, `@else`, `@endif`)
```html
@if user.balance > 0
    <p>Available Balance: ৳{{ user.balance }}</p>
@elseif user.credit > 0
    <p>Credit Available</p>
@else
    <p>No balance</p>
@endif
```

---

## 3. Iteration (`@foreach`, `@endforeach`)
```html
@foreach products as product
    <div class="card">
        <h3>{{ loop.index }}. {{ product.name }}</h3>
        <p>Price: ৳{{ product.price }}</p>
    </div>
@endforeach
```

---

## 4. Database Queries (`@query`)
```html
@query products from products where status = "active" order by id desc limit 10
@endquery
```

---

## 5. Declarative Forms (`@insert`, `@update`, `@delete`)
```html
<!-- Insert Record -->
<form @insert="products" fields="name,price" redirect="/index.one">
    <input name="name">
    <input name="price">
    <button type="submit">Add Product</button>
</form>

<!-- Update Record -->
<form @update="products" where="id = {{ product.id }}" fields="name,price">
    <input name="name" value="{{ product.name }}">
    <button type="submit">Update</button>
</form>

<!-- Delete Record -->
<form @delete="products" where="id = {{ product.id }}">
    <button type="submit">Delete</button>
</form>
```

---

## 6. Layouts & Includes (`@layout`, `@section`, `@yield`, `@include`)

### Layout File (`layouts/main.one`):
```html
<!DOCTYPE html>
<html>
<head><title>OneWeb</title></head>
<body>
    @yield "content"
</body>
</html>
```

### Page File (`dashboard.one`):
```html
@layout "main"

@section "content"
    <h1>Dashboard Page</h1>
@endsection
```
