<?php

use Carbon\Carbon;

if (! function_exists('format_fcfa')) {
    /** 150000 -> "150 000 FCFA" */
    function format_fcfa(float|int|string|null $montant): string
    {
        $montant = (float) $montant;
        $decimales = floor($montant) == $montant ? 0 : 2;

        return number_format($montant, $decimales, ',', ' ').' FCFA';
    }
}

if (! function_exists('format_date')) {
    /** Date -> "JJ/MM/AAAA" */
    function format_date(Carbon|string|null $date): string
    {
        return $date ? Carbon::parse($date)->format('d/m/Y') : '';
    }
}

if (! function_exists('surligner')) {
    /**
     * Texte échappé dans lequel chaque occurrence du terme recherché est entourée de <mark> (insensible à la casse et aux accents
     * majuscules/minuscules). On découpe le texte BRUT puis on échappe chaque morceau : aucune injection possible, et un terme comme
     * « 039 » ne casse jamais une entité HTML.
     */
    function surligner(string $texte, string $terme): Illuminate\Support\HtmlString
    {
        $terme = trim($terme);
        if ($terme === '') {
            return new Illuminate\Support\HtmlString(e($texte));
        }

        $morceaux = preg_split('/('.preg_quote($terme, '/').')/iu', $texte, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$texte];
        $html = '';
        foreach ($morceaux as $i => $morceau) {
            $html .= $i % 2 === 1 ? '<mark>'.e($morceau).'</mark>' : e($morceau);
        }

        return new Illuminate\Support\HtmlString($html);
    }
}
