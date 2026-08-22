# OneWeb Security Architecture & Trust Model

## 1. Security Principles

OneWeb enforces security **by architecture**, not developer discipline.

### Threat Immunity Matrix

| Security Threat | Mitigation Architecture |
| :--- | :--- |
| **SQL Injection** | Parameterized SQL Query Execution (Zero SQL String Concatenation) |
| **Cross-Site Scripting (XSS)** | Default Auto HTML Escaping for `{{ var }}` tags |
| **CSRF Attacks** | Automatic Session CSRF Token Injection & Form Verification |
| **Mass Assignment** | Explicit `fields="col1,col2"` Filtering on Forms |
| **Path Traversal** | Sanitize include/layout file resolution paths |
| **Unsafe Code Execution** | Strictly NO `eval()` or dynamic script execution |

---

## 2. SQL Injection Architectural Prevention

All user inputs in queries or form submissions are bound to PDO placeholders (`:p0`, `:p1`, etc.).

```html
<!-- OneWeb Template -->
@query users where id = {{ request.id }} @endquery
```

Internally Compiled Engine SQL:
```sql
SELECT * FROM `users` WHERE `id` = :p0
```

Raw user input is NEVER concatenated into SQL strings.

---

## 3. Automatic XSS Escaping

```html
<!-- Input in Database: <script>alert('xss')</script> -->

<!-- Standard variable output (ESCAPED) -->
{{ user.name }}
<!-- Rendered HTML: &lt;script&gt;alert(&#039;xss&#039;)&lt;/script&gt; -->

<!-- Explicit Raw Output (UNESCAPED - Use with Caution) -->
{!! user.bio !!}
```

---

## 4. Mass Assignment Protection

Forms must explicitly state allowed columns using the `fields` attribute:

```html
<form @insert="users" fields="name,email,phone">
```

If an attacker injects `<input name="is_admin" value="1">`, the OneWeb Form Handler filters out `is_admin` automatically because it is omitted from the `fields` attribute.
