<?php

namespace Codewiser\Intl\Intl;

use Codewiser\Intl\Intl\Traits\ConfigureTransliterator;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\Traits\Macroable;
use Psr\Log\LoggerAwareTrait;

/**
 * Converts text between writing systems, and between Unicode normal forms.
 *
 * Two rather different jobs that ICU happens to solve with the same machinery:
 *
 * Transliteration turns a name into something a URL or a search box can carry.
 * "Шёлковый пух" has no ASCII spelling of its own, and transliterating it gives
 * "Shëlkovyy pukh" — a name a person can search for, which is not the same as a
 * transliteration of the words.
 *
 * Normalization matters because Unicode has several ways to write the same
 * text. "é" can be one code point or "e" plus a combining accent, and those two
 * strings are not equal to PHP even though they look identical. Composing with
 * NFC before comparing or storing makes canonically equal text compare equal.
 *
 * @see https://unicode.org/reports/tr15/ Canonical equivalence.
 */
class Transliterator
{
    use Macroable, LoggerAwareTrait, ConfigureTransliterator;

    /**
     * Any script to Latin, keeping the letters accented.
     */
    public const ANY_LATIN = 'Any-Latin';

    /**
     * Any script to unaccented ASCII, dropping what cannot be spelled.
     */
    public const LATIN_ASCII = 'Latin-ASCII';

    /**
     * Cyrillic to Latin per the BGN/PCGN romanization.
     */
    public const RUSSIAN_LATIN = 'Russian-Latin/BGN';

    /**
     * Cyrillic to Latin, the ICU default transliteration.
     */
    public const CYRILLIC_LATIN = 'Cyrillic-Latin';

    /**
     * Greek to Latin.
     */
    public const GREEK_LATIN = 'Greek-Latin';

    /**
     * Han to Latin with pinyin, when ICU has readings for the characters.
     */
    public const HAN_LATIN = 'Han-Latin';

    /**
     * Han to Latin with Wade-Giles, the older romanization.
     */
    public const HAN_LATIN_WADE = 'Han-Latin/Wade';

    /**
     * Composed form: "é" as one code point.
     */
    public const NFC = 'NFC';

    /**
     * Decomposed form: "é" as "e" plus a combining accent.
     */
    public const NFD = 'NFD';

    /**
     * Compatibility form, folding characters that mean the same but look different.
     */
    public const NFKC = 'NFKC';

    public function __construct(
        protected Translator $translator,
        protected string $transliterator,
    ) {
        //
    }

    /**
     * Convert the text with the given rule, e.g. self::ANY_LATIN.
     *
     * Returns null for a rule ICU does not have. Call listRules() for the list;
     * it holds several hundred entries.
     */
    public function convert(string $text, ?string $form = null): ?string
    {
        return \Transliterator::create($form ?? $this->transliterator)?->transliterate($text);
    }

    /**
     * Put the text into a Unicode normal form, e.g. self::NFC.
     */
    public function normalize(string $text, string $form = self::NFC): ?string
    {
        return \Transliterator::create($form)?->transliterate($text);
    }

    /**
     * Whether the text is already in the given normal form.
     */
    public function isNormalized(string $text, string $form = self::NFC): bool
    {
        return $this->normalize($text, $form) === $text;
    }

    /**
     * Every transliteration rule this ICU build has, sorted.
     *
     * The set depends on the ICU version, so do not branch on the presence of a
     * particular rule: a rule added in a later version would take the other path.
     *
     * The normal forms are absent from it even though `normalize()` accepts
     * them — ICU lists one kind and not the other.
     *
     * @return array<int, string>
     */
    public function listRules(): array
    {
        $rules = \Transliterator::listIDs();

        sort($rules);

        return $rules;
    }
}