<?php

namespace Codewiser\Intl\Tests\Intl;

use Codewiser\Intl\IntlManager;
use Codewiser\Intl\IntlServiceProvider;
use Codewiser\Intl\Tests\Translator;
use DateTimeImmutable;
use DateTimeZone;
use IntlDateFormatter;
use Orchestra\Testbench\TestCase;

/**
 * The wiring rather than the formatting.
 *
 * Every other test builds its formatters by hand, which is what lets it assert
 * against ICU directly. That leaves one thing uncovered: whether the container
 * hands out a manager configured the way the service provider says it should,
 * and whether every factory on it is reachable at all.
 */
class IntlManagerTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [IntlServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app->setLocale('ru');
    }

    /**
     * The same manager the container builds, assembled here so the two can be
     * compared instead of asserted separately.
     */
    protected function reference(): IntlManager
    {
        return new IntlManager(
            new Translator('ru'),
            'UTC',
            IntlDateFormatter::GREGORIAN,
            'EUR',
            ''
        );
    }

    public function test_the_helper_returns_the_bound_manager(): void
    {
        $this->assertInstanceOf(IntlManager::class, intl());
        $this->assertSame(intl(), intl(), 'It is a singleton, so one manager per request');
    }

    public function test_the_helper_is_the_same_as_resolving_the_binding(): void
    {
        $this->assertSame(
            $this->app->make(IntlManager::class),
            intl(),
            'intl() must be a synonym of the container binding'
        );
    }

    public function test_it_takes_the_locale_from_the_app(): void
    {
        $this->assertSame(
            $this->reference()->locale('en')->display(),
            intl()->locale('en')->display(),
            'A locale left unnamed is the app one, so the app locale must reach it'
        );

        $this->assertNotSame(
            $this->reference()->locale('en')->display('en'),
            intl()->locale('en')->display(),
            'Naming the app locale in another language must differ from naming it in itself'
        );
    }

    public function test_every_factory_is_reachable(): void
    {
        $datetime = new DateTimeImmutable('2026-10-03 15:04:05', new DateTimeZone('UTC'));
        $reference = $this->reference();

        // Compared against hand-built formatters rather than literals: the point
        // is that the factory passes its locale, timezone and currency through,
        // not what this ICU version says a date looks like.
        $this->assertSame(
            $reference->date($datetime)->format(IntlDateFormatter::MEDIUM),
            intl()->date($datetime)->format(IntlDateFormatter::MEDIUM),
            'date() must reach the manager timezone and calendar'
        );

        $this->assertSame(
            $reference->number(1234.56)->currency(),
            intl()->number(1234.56)->currency(),
            'number() must reach the manager currency'
        );

        $this->assertSame($reference->currency('RUB')->name(), intl()->currency('RUB')->name());
    }

    public function test_the_shorthand_methods_chain(): void
    {
        $intl = intl()->useTimezone('Europe/Moscow')->useCalendar(IntlDateFormatter::GREGORIAN)->useCurrency('RUB');

        $this->assertSame($intl, $intl->useCurrency('USD'), 'Every use* returns the manager itself');

        $this->assertSame(
            $this->reference()->useTimezone('Europe/Moscow')->useCurrency('USD')->currency()->name(),
            $intl->currency()->name(),
            'A reconfigured manager must reach the currency formatter'
        );
    }

    public function test_every_formatter_takes_a_logger(): void
    {
        $datetime = new DateTimeImmutable('2026-10-03 15:04:05', new DateTimeZone('UTC'));

        $formatters = [
            'date' => intl()->date($datetime),
            'number' => intl()->number(1),
            'locale' => intl()->locale('ru'),
            'currency' => intl()->currency(),
            'text' => intl()->text(),
        ];

        foreach ($formatters as $name => $formatter) {
            $this->assertTrue(
                method_exists($formatter, 'setLogger'),
                "The {$name} formatter must take a logger"
            );

            // The manager hands its logger to each one, so a formatter must not
            // refuse one when it is given.
            $formatter->setLogger(new \Illuminate\Log\Logger(new \Monolog\Logger('test')));
        }
    }
}