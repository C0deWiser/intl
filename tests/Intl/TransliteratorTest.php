<?php

namespace Codewiser\Intl\Tests\Intl;

use Codewiser\Intl\Intl\Transliterator;
use Codewiser\Intl\IntlManager;
use Codewiser\Intl\Tests\Translator;
use Orchestra\Testbench\PHPUnit\TestCase;

class TransliteratorTest extends TestCase
{
    protected function text(string $locale = 'ru', string $rule = Transliterator::ANY_LATIN): Transliterator
    {
        return new Transliterator($rule);
    }

    // ------------------------------------------------------------ transliterate

    public function test_it_transliterates(): void
    {
        foreach ([
                     Transliterator::RUSSIAN_LATIN,
                     Transliterator::CYRILLIC_LATIN,
                     Transliterator::ANY_LATIN,
                 ] as $rule) {
            $this->assertSame(
                \Transliterator::create($rule)?->transliterate('Шёлковый пух'),
                $this->text()->convert('Шёлковый пух', $rule),
                "Rule {$rule} does not match the reference transliterator"
            );
        }
    }

    public function test_it_keeps_what_a_rule_cannot_spell(): void
    {
        // Any-Latin covers a lot, but not everything: an emoji has no Latin
        // spelling, and it is passed through rather than dropped. A name
        // transliterated for a search box must stay intact.
        $converted = (string) $this->text()->convert('Привет, Мир! 😀', Transliterator::ANY_LATIN);

        $this->assertStringContainsString('Privet', $converted, 'Cyrillic must become Latin');
        $this->assertStringContainsString('😀', $converted, 'A character with no Latin form is kept');
        $this->assertStringContainsString('!', $converted, 'Punctuation is kept');
    }

    public function test_a_rule_applies_only_to_its_own_script(): void
    {
        // Greek-Latin converts the Greek and leaves everything else as it found
        // it: Cyrillic, Han and emoji all come back untouched.
        $converted = (string) $this->text()->convert('Привет Ω 😀 日本', Transliterator::GREEK_LATIN);

        $this->assertStringContainsString('Привет', $converted, 'Cyrillic survives a Greek rule');
        $this->assertStringContainsString('😀', $converted, 'An emoji survives any script rule');
        $this->assertStringContainsString('日本', $converted, 'Han survives a Greek rule');
        $this->assertStringNotContainsString('Ω', $converted, 'Greek does not survive a Greek rule');
    }

    public function test_any_latin_is_not_just_cyrillic_latin(): void
    {
        // The two agree on Cyrillic here, since ICU romanizes it the same way
        // either route, but Any-Latin also reaches scripts Cyrillic-Latin will
        // not touch at all.
        $this->assertSame(
            $this->text()->convert('Привет', Transliterator::CYRILLIC_LATIN),
            $this->text()->convert('Привет', Transliterator::ANY_LATIN),
            'On Cyrillic the two rules agree'
        );

        $this->assertNotSame(
            $this->text()->convert('日本', Transliterator::ANY_LATIN),
            $this->text()->convert('日本', Transliterator::CYRILLIC_LATIN),
            'Any-Latin reaches Han, which Cyrillic-Latin cannot'
        );
    }

    public function test_han_latin(): void
    {
        $this->assertSame(
            \Transliterator::create(Transliterator::HAN_LATIN)?->transliterate('北京'),
            $this->text()->convert('北京', Transliterator::HAN_LATIN),
            'Han must become a Latin reading'
        );
    }

    public function test_an_unknown_rule_yields_null(): void
    {
        // create() answers null here, which a ?-> covers.
        $this->assertNull(
            $this->text()->convert('anything', 'No-Such-Rule'),
            'A rule ICU does not have converts nothing'
        );
    }

    public function test_it_defaults_to_any_latin(): void
    {
        $this->assertSame(
            $this->text()->convert('Привет', Transliterator::ANY_LATIN),
            $this->text()->convert('Привет'),
            'The default rule is Any-Latin'
        );
    }

    public function test_empty_text_stays_empty(): void
    {
        $this->assertSame('', $this->text()->convert(''));
        $this->assertSame('', $this->text()->convert('', Transliterator::RUSSIAN_LATIN));
    }

    // ------------------------------------------------------------- normalize

    public function test_it_normalizes(): void
    {
        // "é" as one code point, and "e" plus a combining accent, are the same
        // text written two ways.
        $decomposed = "e\u{0301}";
        $composed = "\u{00E9}";

        $this->assertSame(
            \Transliterator::create(Transliterator::NFC)?->transliterate($decomposed),
            $this->text()->normalize($decomposed),
            'NFC must match the reference'
        );

        $this->assertSame(
            $composed,
            $this->text()->normalize($decomposed),
            'NFC composes a base letter and its accent'
        );

        $this->assertSame(
            $decomposed,
            $this->text()->normalize($composed, Transliterator::NFD),
            'NFD decomposes back'
        );
    }

    public function test_normalization_makes_equal_text_compare_equal(): void
    {
        $decomposed = "e\u{0301}";
        $composed = "\u{00E9}";

        $this->assertNotSame(
            $decomposed,
            $composed,
            'PHP considers the two forms different strings'
        );

        $this->assertSame(
            $this->text()->normalize($decomposed),
            $this->text()->normalize($composed),
            'After NFC they are the same string, so === holds'
        );
    }

    public function test_it_reports_whether_text_is_normalized(): void
    {
        $composed = "\u{00E9}";
        $decomposed = "e\u{0301}";

        $this->assertTrue($this->text()->isNormalized($composed), 'Composed text is already in NFC');
        $this->assertFalse($this->text()->isNormalized($decomposed), 'Decomposed text is not in NFC');

        $this->assertTrue(
            $this->text()->isNormalized($decomposed, Transliterator::NFD),
            'The same text is already in NFD'
        );
    }

    public function test_it_normalizes_to_compatibility_form(): void
    {
        // NFKC also folds characters that merely look alike, which is what a
        // search index wants and a display does not.
        $this->assertSame(
            \Transliterator::create(Transliterator::NFKC)?->transliterate("①①"),
            $this->text()->normalize("①①", Transliterator::NFKC),
            'NFKC must match the reference'
        );

        $this->assertSame(
            "11",
            $this->text()->normalize("①①", Transliterator::NFKC),
            'NFKC folds a circled digit to a plain one'
        );
    }

    public function test_an_unknown_normal_form_yields_null(): void
    {
        $this->assertNull(
            $this->text()->normalize('text', 'NFX'),
            'A form ICU does not have normalizes nothing'
        );
    }

    // ------------------------------------------------------------------ rules

    public function test_it_lists_the_rules(): void
    {
        $rules = $this->text()->listRules();

        $this->assertNotEmpty($rules);
        $this->assertSame(array_values($rules), $rules, 'The rules come back as a list, not a map');
        $this->assertSame($rules, array_values(array_unique($rules)), 'No rule is listed twice');

        $sorted = $rules;
        sort($sorted);

        $this->assertSame($sorted, $rules, 'The rules are sorted');

        foreach ([Transliterator::ANY_LATIN, Transliterator::LATIN_ASCII, Transliterator::RUSSIAN_LATIN] as $rule) {
            $this->assertContains($rule, $rules, "The shipped rule {$rule} must exist in this ICU");
        }

        // The normal forms work with create() but are not transliteration rules,
        // so they are absent from this list. They are not an oversight.
        $this->assertNotContains(Transliterator::NFC, $rules, 'A normal form is not a transliteration rule');

        $this->assertNotNull(
            \Transliterator::create(Transliterator::NFC),
            'And it is still usable all the same'
        );
    }

    // -------------------------------------------------------------- the facade

    public function test_the_manager_gives_a_transliterator(): void
    {
        // The rule is given to the manager, so it has to reach the formatter —
        // compared against a hand-built one, which is the only way to tell a
        // pass-through from both sides happening to default to the same rule.
        $intl = new IntlManager(new Translator('ru'), transliterator: Transliterator::RUSSIAN_LATIN);

        $this->assertSame(
            $this->text('ru', Transliterator::RUSSIAN_LATIN)->convert('Шёлковый пух'),
            $intl->text()->convert('Шёлковый пух'),
            'The manager rule must be the one the formatter uses'
        );

        $this->assertNotEmpty($intl->text()->listRules());
    }

    public function test_the_manager_rule_can_be_reconfigured(): void
    {
        $intl = new IntlManager(new Translator('ru'), transliterator: Transliterator::ANY_LATIN);

        $this->assertSame(
            $intl,
            $intl->useTransliterator(Transliterator::RUSSIAN_LATIN),
            'useTransliterator() returns the manager itself'
        );

        $this->assertSame(
            $this->text('ru', Transliterator::RUSSIAN_LATIN)->convert('Шёлковый пух'),
            $intl->text()->convert('Шёлковый пух'),
            'A reconfigured manager must reach the transliterator'
        );
    }

    public function test_it_is_macroable(): void
    {
        Transliterator::macro('shout', fn() => 'SHOUT');

        try {
            $this->assertSame('SHOUT', $this->text()->shout());
        } finally {
            Transliterator::flushMacros();
        }

        $this->assertFalse(Transliterator::hasMacro('shout'));
    }
}