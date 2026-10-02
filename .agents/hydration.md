# Hydration

`$model->name` returns a plain value in the current locale. Wrapping the read
returns the whole map instead:

    $model->name;                            // 'Michael'
    $model->multilingual('name');             // Multilingual<string>
    $model->multilingual('name')->get();     // 'Michael'
    $model->multilingual('name')['es'];      // 'Miguel'

`AsCollection` behaves the same way for a list of maps: hydrated it returns
`Collection` of `Multilingual`, unhydrated a `Collection` of
plain values.

## The problem

A mapped object is mutable, and mutating it tells the model nothing:

    $model->name->first_name = 'Johnny';
    $model->name->first_name;   // 'Johnny', from the object
    $model->getDirty();         // [] — the model never heard of it
    $model->save();             // nothing written

Laravel does not help here. With `withoutObjectCaching = true` (set in
`CastsMultilingual`) it unsets `classCastCache[$key]` on every read, so the
object is rebuilt from the raw JSON on every single attribute access — see
[casts.md](casts.md).

## The registry

`CastsMultilingual`, used by `Multilingual`, keeps one entry per model and
attribute: the raw value it was built from, and the object built out of it.

    static::hydrate($model, $key, $raw, $build)  // get-or-build
    static::store($model, $key, $raw, $object)   // remember it
    static::entries($model)                      // list
    static::drop($model, $key = null)            // forget one / all

The cast passes the raw value in, so the registry never has to re-read it.

## The invariant

> An entry is good only while `attributes[$key]` still strictly equals the raw
> value the object was built from.

One check covers every way the attribute can move away, because they all end up
writing to `$model->attributes` or replacing it:

| Action | What happens to `attributes[$key]` |
| --- | --- |
| `setAttribute()`, `offsetSet()`, `offsetUnset()` | new string via the cast |
| `unset()` | key gone |
| `setRawAttributes()`, `refresh()`, `replicate()` | whole array replaced |
| `discardChanges()` | whole array replaced by the original |

A mismatch means the object is stale, so it is dropped instead of written back.
That is what makes assigning an attribute win over an object handed out earlier.

## The write-back

`flushMultilingualAttributes()` loops the registry and re-encodes each live
object through the cast. It is called from exactly one place: the
`getAttributes()` override in `HasMultilingual`.

That single hook is enough because everything funnels through it —
`getDirty()`, `isDirty()`, `toArray()`, `save()`, `insert`, `syncOriginal()`,
`replicate()`. And `setAttribute()` does *not* call `getAttributes()`, so there
is no recursion.

Writing back through `setAttribute()` rather than by hand keeps the encoding in
one place. The raw value then moved, so the entry is re-stored against the new
raw value.

## Empty values

`Multilingual::jsonSerialize()` returns `null` for an empty map, so writing an
emptied object back stores SQL `NULL`. An untouched empty attribute is `'[]'`
in the database, so writing it back would dirty a column nobody changed. Hence
the guard: skip when the object is empty *and* the raw value is empty too.

## WeakMap

The registry is a static `WeakMap` keyed by model instance. Weak, so a
discarded model takes its objects with it and nothing is left behind after the
request. It lives in `CastsMultilingual`, so the
caster works on any model.

## Dead ends

- **`remember()`, `forget()`, `put()` as the registry API.** `Illuminate\Support\Collection`
  already has those as non-static methods, and redeclaring them static is a
  fatal error. Hence `hydrate()` / `drop()` / `store()`.
- **`classCastCache` instead of our own registry.** Caching is per model
  instance and cleared in `setRawAttributes()` (HasAttributes:2047) and
  `discardChanges()` (:2225), and it stores no raw snapshot to compare
  against, so it cannot express the invariant above. Enabling caching instead
  also pushes our object back through `set()` from `mergeAttributesFromClassCasts()`
  (:1925), called by `syncOriginal()`, which duplicates the write-back path.

## Known limitation

A model that defines its own `getAttributes()` overrides the trait's one and
silently loses the write-back. `flushMultilingualAttributes()` has to be called
from there.