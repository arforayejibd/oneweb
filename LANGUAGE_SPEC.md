# OneWeb Language Specification & Grammar (v1.0)

## 1. Overview

**OneWeb** is an HTML-first web programming language. HTML serves as the root document structure, while OneWeb constructs provide dynamic state, database access, conditionals, iteration, template inherits, and layout composition.

---

## 2. EBNF Grammar

```ebnf
Document        ::= ( TextNode | VariableNode | RawVariableNode | DirectiveNode | FormDirectiveNode )*

VariableNode    ::= "{{" Expression "}}"
RawVariableNode ::= "{!!" Expression "!!}"

DirectiveNode   ::= QueryDirective
                  | ForeachDirective
                  | IfDirective
                  | IncludeDirective
                  | LayoutDirective
                  | SectionDirective
                  | YieldDirective
                  | ComponentDirective

QueryDirective   ::= "@query" Identifier [ "from" Identifier ] [ "where" Expression ] [ "order by" Identifier [ "asc" | "desc" ] ] [ "limit" Integer ] [ "first" ] Document "@endquery"

ForeachDirective ::= "@foreach" Expression "as" Identifier [ "=>" Identifier ] Document "@endforeach"

IfDirective      ::= "@if" Expression Document ( "@elseif" Expression Document )* [ "@else" Document ] "@endif"

IncludeDirective ::= "@include" StringLiteral [ ParameterList ]

LayoutDirective  ::= "@layout" StringLiteral
SectionDirective ::= "@section" StringLiteral Document "@endsection"
YieldDirective   ::= "@yield" StringLiteral

FormDirectiveNode ::= "<form" ( "@insert" | "@update" | "@delete" ) "=" StringLiteral [ "fields=" StringLiteral ] [ "where=" StringLiteral ] [ "validate=" StringLiteral ] [ "redirect=" StringLiteral ] AttributeList ">"
```

---

## 3. Token Definitions (Lexer Specification)

| Token Type | Pattern / Description |
| :--- | :--- |
| `T_TEXT` | Raw HTML content outside template directives |
| `T_VAR` | `{{ expression }}` (Auto HTML Escaped) |
| `T_RAW_VAR` | `{!! expression !!}` (Raw Unescaped HTML) |
| `T_QUERY_START` | `@query <name> [from <table>] [where ...] [order by ...] [limit ...] [first]` |
| `T_QUERY_END` | `@endquery` |
| `T_FOREACH_START` | `@foreach <collection> as <item>` |
| `T_FOREACH_END` | `@endforeach` |
| `T_IF_START` | `@if <condition>` |
| `T_ELSEIF` | `@elseif <condition>` |
| `T_ELSE` | `@else` |
| `T_IF_END` | `@endif` |
| `T_LAYOUT` | `@layout <template>` |
| `T_SECTION_START` | `@section <name>` |
| `T_SECTION_END` | `@endsection` |
| `T_YIELD` | `@yield <name>` |
| `T_INCLUDE` | `@include <template>` |
| `T_FORM_INSERT` | `<form @insert="table" fields="..." validate="...">` |
| `T_FORM_UPDATE` | `<form @update="table" where="..." fields="..." validate="...">` |
| `T_FORM_DELETE` | `<form @delete="table" where="...">` or `<button @delete="table" where="...">` |
| `T_ONE_COMPONENT_START` | `<one-<component> [attr="val"...]>` (UI Element Start) |
| `T_ONE_COMPONENT_END` | `</one-<component>>` (UI Element End) |
| `T_ONE_COMPONENT_SELF` | `<one-<component> [attr="val"...] />` (Self-Closing UI Element) |

---

## 4. AST Node Hierarchy

```text
DocumentNode
 ├── HtmlNode (value)
 ├── VariableNode (expression, escape: true)
 ├── RawVariableNode (expression, escape: false)
 ├── QueryNode (name, table, where, orderBy, limit, isFirst, children)
 ├── ForeachNode (collection, item, key, children)
 ├── IfNode (condition, ifChildren, elseifBranches, elseChildren)
 ├── LayoutNode (template)
 ├── SectionNode (name, children)
 ├── YieldNode (name)
 ├── FormNode (actionType, table, where, fields, validate, redirect)
 └── ComponentNode (name, attributes, children)
```
