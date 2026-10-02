# Eloquent cast internals

Line numbers are for `laravel/framework` 12.x,
`src/Illuminate/Database/Eloquent/Concerns/HasAttributes.php`.

## A caster is rebuilt on every read and every write

`resolveCasterClass()` (:1861) ends with `return new $castType(...$arguments);`,
and `AsMultilingual::castUsing()` returns `new class($arguments)`. So a
`CastsAttributes` instance lives for one call.

Consequence: per-model state cannot live on the caster. Which object was handed
out for which model and attribute has to be kept somewhere else — see
[hydration.md](hydration.md).

## classCastCache

`getClassCastableAttributeValue()` (:904):

    if (isset($this->classCastCache[$key]) && ! $objectCachingDisabled) {
        return $this->classCastCache[$key];
    }
    $value = $caster->get($this, $key, $value, $this->attributes);
    if (... || $objectCachingDisabled) {
        unset($this->classCastCache[$key]);   // :920
    } else {
        $this->classCastCache[$key] = $value;
    }

`CastsMultilingual` sets `withoutObjectCaching = true`, so the cache is dropped
on every read and the object is rebuilt from JSON every time. That is the
reason this package needs its own registry.

`setClassCastableAttribute()` (:1244) re-casts on every assignment and unsets
the cache key the same way.

## mergeAttributesFromClassCasts

`mergeAttributesFromClassCasts()` (:1925) walks `classCastCache` and feeds each
entry back through `$caster->set()`. It is reached from `syncOriginal()`
(:2142), which does `$this->original = $this->getAttributes();`.

Relevant because it means a cached object would be written back by Laravel
itself — one more reason the registry is ours.

## discardChanges

`HasAttributes::discardChanges()` (:2227) resets `classCastCache` and
`attributeCastCache`. `HasMultilingual::discardChanges()` mirrors that for our
registry via `MultilingualCollection::drop($this)`.

## Set semantics

`AsMultilingual::set()` treats its input as one of two things:

    $model->name = ['en' => 'Michael'];   // a map → full replace
    $model->name = 'Michael';             // a scalar → the current locale only

So handing a whole `Multilingual` to `set()` replaces the map, which is what
makes the write-back safe to route through `setAttribute()`.

An empty map serializes to `null`, so an emptied attribute is stored as SQL
`NULL` while an untouched empty attribute stays `'[]'` in the database.

## json_encode and Cyrillic

`AsMultilingual::set()` calls plain `json_encode()` with no flags, so
non-ASCII is stored escaped: `{"ru":"\u0418\u0432\u0430\u043d"}`. Test
expectations must use the escaped form. Adding `JSON_UNESCAPED_UNICODE` would
be nicer on disk but is a separate decision.

## Test database

`phpunit.xml` sets `DB_CONNECTION=testing` and `DB_DATABASE=:memory:`, so
persistence tests create their own table with `Schema::create()` in `setUp()`
— there are no migrations under `workbench/database/migrations`.

Use a `text` column, not `json`: sqlite will happily store a JSON string into
either, but a `json` column adds type affinity that only confuses the
assertions.