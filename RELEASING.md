# Releasing

Maintainer notes for publishing this package and shipping updates to the sites
that consume it.

## One-time: list the package on Packagist

1. Sign in at <https://packagist.org> with the GitHub account that owns the repo.
2. Go to <https://packagist.org/packages/submit>, paste
   `https://github.com/batustun/filament-media-library`, press **Check**, then
   **Submit**.
   Packagist reads `composer.json` and registers the name `batustun/filament-media-library`.
3. Open the package page → **Settings** → enable the **GitHub Hook** (or install
   the Packagist GitHub App when prompted). This is what makes a new tag appear
   on Packagist within seconds instead of up to a day.
4. Verify: the package page should list `v1.0.0` under *Versions*, and the
   "Last update" badge should stop saying *auto-update not enabled*.

### After Packagist is live

Consumers no longer need a VCS repository entry. In each site's `composer.json`,
delete this block:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/batustun/filament-media-library.git" }
]
```

then run `composer update batustun/filament-media-library`. Resolution gets
faster, because Composer stops cloning the repo to enumerate every tag.

## Every release

```bash
cd ~/web/filament-media-library

git pull                      # the changelog workflow commits to main after each release
# ... make the changes ...
vendor/bin/pint               # code style
vendor/bin/phpstan analyse    # static analysis
vendor/bin/pest               # tests
git add -A && git commit -m "feat: ..."

git tag -a v1.1.0 -m "v1.1.0"
git push origin main --follow-tags
gh release create v1.1.0 --title "v1.1.0" --notes "..."
```

What happens on its own after that:

| Trigger | Result |
|---|---|
| tag push | `tests` (16 jobs), `static analysis`, `code style` run |
| release published | `update changelog` writes the release notes into `CHANGELOG.md` and commits to `main` |
| release published | Packagist indexes the new version (needs the hook from step 3) |

> Because the changelog workflow commits to `main`, always `git pull` before
> starting the next piece of work.

## Choosing the version number

The consuming sites pin `^1.0`, which accepts every `1.x` and never `2.0`.

| Change | Bump | Example |
|---|---|---|
| Bug fix, no API change | patch | `1.1.0` → `1.1.1` |
| New feature, existing code keeps working | minor | `1.1.1` → `1.2.0` |
| Renamed class/method/config key, changed DB schema, raised PHP/Laravel/Filament floor | major | `1.2.0` → `2.0.0` |

A major release needs every consumer to change its constraint to `^2.0`
deliberately, so it is the right home for anything that would otherwise break a
site during a routine `composer update`.

## Updating a consuming site

```bash
composer update batustun/filament-media-library
php artisan migrate           # only when the release adds migrations
php artisan filament:assets   # only when the release changes the stylesheet
```

Check what you are about to get first:

```bash
composer outdated batustun/filament-media-library
```

## Rolling back

Releases are immutable, so roll forward where you can. When you cannot:

```bash
composer require batustun/filament-media-library:1.1.0 --update-with-dependencies
```

Never delete or re-point a published tag: anything that already resolved it
would silently get different code under the same version number.
