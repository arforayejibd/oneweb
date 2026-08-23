<?php

namespace OneScript\Engine;

class ComponentRegistry
{
    public static function has(string $name): bool
    {
        $clean = strtolower(trim(preg_replace('/^one-/i', '', $name)));
        $known = ['container', 'grid', 'card', 'button', 'badge', 'heading', 'alert', 'input', 'form'];
        return in_array($clean, $known, true);
    }

    /**
     * Render a custom <one-component> node to standard HTML with Modern Dark Glassmorphic Design.
     */
    public static function render(string $name, array $attributes, string $childrenHtml): string
    {
        $name = strtolower(trim(preg_replace('/^one-/i', '', $name)));

        switch ($name) {
            case 'container':
                $maxWidth = $attributes['max-width'] ?? '7xl';
                return "<div class=\"max-w-{$maxWidth} mx-auto px-4 sm:px-6 lg:px-8 py-8 relative z-10\">\n{$childrenHtml}\n</div>";

            case 'grid':
                $cols = $attributes['cols'] ?? '3';
                $gap = $attributes['gap'] ?? '6';

                $gridColsClass = match ($cols) {
                    '1' => 'grid-cols-1',
                    '2' => 'grid-cols-1 md:grid-cols-2',
                    '3' => 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3',
                    '4' => 'grid-cols-1 md:grid-cols-2 lg:grid-cols-4',
                    default => "grid-cols-1 md:grid-cols-{$cols}"
                };

                return "<div class=\"grid {$gridColsClass} gap-{$gap}\">\n{$childrenHtml}\n</div>";

            case 'card':
                $title = isset($attributes['title']) ? htmlspecialchars($attributes['title'], ENT_QUOTES, 'UTF-8') : null;
                $price = isset($attributes['price']) ? htmlspecialchars($attributes['price'], ENT_QUOTES, 'UTF-8') : null;
                $badge = isset($attributes['badge']) ? htmlspecialchars($attributes['badge'], ENT_QUOTES, 'UTF-8') : null;
                $badgeVariant = $attributes['badge-variant'] ?? 'info';

                $html = "<div class=\"glass-card rounded-3xl p-6 sm:p-7 transition-all duration-300 flex flex-col justify-between group font-sans\">\n";
                $html .= "  <div>\n";

                if ($badge !== null && $badge !== '') {
                    $html .= "    <div class=\"mb-3\">" . self::render('badge', ['variant' => $badgeVariant], $badge) . "</div>\n";
                }

                if ($title !== null && $title !== '') {
                    $html .= "    <h3 class=\"text-xl font-bold text-white tracking-tight group-hover:text-indigo-400 transition-colors duration-200\">{$title}</h3>\n";
                }

                if (!empty(trim($childrenHtml))) {
                    $html .= "    <div class=\"text-slate-400 text-sm leading-relaxed mt-2.5\">\n{$childrenHtml}\n    </div>\n";
                }

                $html .= "  </div>\n";

                if ($price !== null && $price !== '') {
                    $html .= "  <div class=\"mt-6 pt-4 border-t border-slate-800/80 flex items-center justify-between\">\n";
                    $html .= "    <span class=\"text-xs font-semibold text-slate-500 uppercase tracking-widest\">Price</span>\n";
                    $html .= "    <span class=\"text-2xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 via-purple-400 to-pink-400\">{$price}</span>\n";
                    $html .= "  </div>\n";
                }

                $html .= "</div>";
                return $html;

            case 'button':
                $href = $attributes['href'] ?? null;
                $variant = $attributes['variant'] ?? 'primary';
                $type = $attributes['type'] ?? 'button';

                $baseClasses = "inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-sm transition-all duration-200 shadow-lg cursor-pointer border active:scale-95 select-none";

                $variantClasses = match ($variant) {
                    'secondary' => 'bg-slate-800/80 text-slate-200 border-slate-700 hover:bg-slate-700 hover:text-white shadow-slate-900/50',
                    'outline' => 'bg-transparent text-indigo-400 border-indigo-500/40 hover:bg-indigo-500/10 hover:border-indigo-500 shadow-indigo-950/20',
                    'danger' => 'bg-gradient-to-r from-rose-600 to-red-600 text-white border-rose-500/50 hover:from-rose-500 hover:to-red-500 shadow-rose-900/40',
                    'success' => 'bg-gradient-to-r from-emerald-600 to-teal-600 text-white border-emerald-500/50 hover:from-emerald-500 hover:to-teal-500 shadow-emerald-900/40',
                    default => 'bg-gradient-to-r from-indigo-600 via-purple-600 to-indigo-600 text-white border-indigo-500/40 hover:from-indigo-500 hover:to-purple-500 shadow-indigo-600/30'
                };

                $classes = "{$baseClasses} {$variantClasses}";

                if ($href !== null) {
                    $escapedHref = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');
                    return "<a href=\"{$escapedHref}\" class=\"{$classes}\">{$childrenHtml}</a>";
                } else {
                    return "<button type=\"{$type}\" class=\"{$classes}\">{$childrenHtml}</button>";
                }

            case 'badge':
                $variant = $attributes['variant'] ?? 'info';

                $variantClasses = match ($variant) {
                    'success' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                    'warning' => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                    'danger' => 'bg-rose-500/10 text-rose-400 border-rose-500/20',
                    'purple' => 'bg-purple-500/10 text-purple-400 border-purple-500/20',
                    default => 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20'
                };

                return "<span class=\"inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold border backdrop-blur-sm {$variantClasses}\"><span class=\"w-1.5 h-1.5 rounded-full bg-current animate-pulse\"></span>{$childrenHtml}</span>";

            case 'heading':
                $level = $attributes['level'] ?? '2';
                $level = in_array($level, ['1', '2', '3', '4', '5', '6']) ? $level : '2';

                $sizeClass = match ($level) {
                    '1' => 'text-4xl sm:text-6xl font-extrabold tracking-tight text-white mb-4 leading-tight',
                    '2' => 'text-2xl sm:text-4xl font-extrabold tracking-tight text-white mb-3',
                    '3' => 'text-xl sm:text-2xl font-bold tracking-tight text-white mb-2',
                    default => 'text-lg font-semibold text-white mb-2'
                };

                return "<h{$level} class=\"{$sizeClass}\">{$childrenHtml}</h{$level}>";

            case 'alert':
                $type = $attributes['type'] ?? 'info';

                $alertClasses = match ($type) {
                    'success' => 'bg-emerald-950/40 text-emerald-300 border-emerald-500/30',
                    'warning' => 'bg-amber-950/40 text-amber-300 border-amber-500/30',
                    'danger' => 'bg-rose-950/40 text-rose-300 border-rose-500/30',
                    default => 'bg-indigo-950/40 text-indigo-300 border-indigo-500/30'
                };

                return "<div class=\"p-4 rounded-2xl border backdrop-blur-md {$alertClasses} text-sm font-medium flex items-center gap-3 shadow-lg\">\n{$childrenHtml}\n</div>";

            case 'input':
                $nameAttr = htmlspecialchars($attributes['name'] ?? '', ENT_QUOTES, 'UTF-8');
                $label = isset($attributes['label']) ? htmlspecialchars($attributes['label'], ENT_QUOTES, 'UTF-8') : null;
                $inputType = htmlspecialchars($attributes['type'] ?? 'text', ENT_QUOTES, 'UTF-8');
                $placeholder = isset($attributes['placeholder']) ? htmlspecialchars($attributes['placeholder'], ENT_QUOTES, 'UTF-8') : '';
                $value = isset($attributes['value']) ? htmlspecialchars($attributes['value'], ENT_QUOTES, 'UTF-8') : '';
                $required = isset($attributes['required']) ? 'required' : '';

                $html = "<div class=\"flex flex-col gap-1.5 mb-4\">\n";
                if ($label !== null) {
                    $html .= "  <label class=\"text-xs font-semibold text-slate-400 uppercase tracking-wider\">{$label}</label>\n";
                }
                $html .= "  <input type=\"{$inputType}\" name=\"{$nameAttr}\" value=\"{$value}\" placeholder=\"{$placeholder}\" {$required} class=\"w-full px-4 py-3 bg-slate-900/90 border border-slate-800 rounded-xl text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all duration-200\">\n";
                $html .= "</div>";
                return $html;

            case 'form':
                $attrString = '';
                foreach ($attributes as $k => $v) {
                    $attrString .= ' ' . htmlspecialchars($k, ENT_QUOTES, 'UTF-8') . '="' . htmlspecialchars($v, ENT_QUOTES, 'UTF-8') . '"';
                }
                return "<form class=\"glass-card rounded-3xl p-8 border border-slate-800 shadow-xl space-y-4\"{$attrString}>\n{$childrenHtml}\n</form>";

            default:
                $attrString = '';
                foreach ($attributes as $k => $v) {
                    $attrString .= ' ' . htmlspecialchars($k, ENT_QUOTES, 'UTF-8') . '="' . htmlspecialchars($v, ENT_QUOTES, 'UTF-8') . '"';
                }
                if ($childrenHtml === '') {
                    return "<one-{$name}{$attrString} />";
                }
                return "<one-{$name}{$attrString}>{$childrenHtml}</one-{$name}>";
        }
    }
}
